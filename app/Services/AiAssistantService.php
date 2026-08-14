<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * AI assistant powered by Google Gemini.
 *
 * How it works:
 *   1. User asks a question in natural language.
 *   2. We send the question to Gemini together with the list of tool schemas
 *      (search_assets, count_assets, etc.) so the model knows what it can query.
 *   3. Gemini may respond with either plain text (answer) OR a function call
 *      request. If it's a function call, we execute the corresponding method,
 *      send the tool result back, and loop until Gemini produces final text.
 *
 * Tools are the ONLY way Gemini can touch the database — it cannot generate
 * or run raw SQL. Adding new capabilities means adding a new tool method.
 */
class RateLimitedException extends RuntimeException {
    public function __construct(public int $retryAfterSeconds, string $message = 'Rate limit exceeded') {
        parent::__construct($message);
    }
}

class AiAssistantService
{
    private const MAX_TOOL_LOOPS = 12;
    private const MAX_RETRIES = 2;
    private const ENDPOINT_TEMPLATE = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    public function chat(string $userMessage, array $history = []): array
    {
        $apiKey = config('services.gemini.api_key');
        if (empty($apiKey)) {
            throw new RuntimeException('Gemini API key is not configured. Set GEMINI_API_KEY in .env.');
        }

        // Build the conversation for Gemini.
        // history items look like [{role: 'user'|'model', text: '...'}]
        $contents = [];
        foreach ($history as $h) {
            $role = ($h['role'] ?? 'user') === 'model' ? 'model' : 'user';
            $contents[] = [
                'role'  => $role,
                'parts' => [['text' => (string) ($h['text'] ?? '')]],
            ];
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $userMessage]]];

        $toolsUsed = [];

        for ($i = 0; $i < self::MAX_TOOL_LOOPS; $i++) {
            $response = $this->callGemini($apiKey, $contents);

            $candidate = $response['candidates'][0] ?? null;
            if (!$candidate) {
                return ['text' => 'Sorry, walang response galing sa AI service.', 'tools_used' => $toolsUsed];
            }

            $parts = $candidate['content']['parts'] ?? [];
            $functionCall = null;
            $textParts = [];
            foreach ($parts as $part) {
                if (isset($part['functionCall'])) $functionCall = $part['functionCall'];
                if (isset($part['text']))         $textParts[]  = $part['text'];
            }

            if ($functionCall) {
                $name = $functionCall['name'] ?? '';
                $args = $functionCall['args'] ?? [];
                $toolsUsed[] = ['name' => $name, 'args' => $args];

                $result = $this->executeTool($name, is_array($args) ? $args : []);

                // Echo the model's original parts VERBATIM — Gemini 2.5+ requires the
                // thoughtSignature that came with the functionCall to be preserved.
                // We also normalize any empty args (PHP [] → JSON {}) inline.
                $modelParts = array_map(function ($p) {
                    if (isset($p['functionCall']) && empty($p['functionCall']['args'])) {
                        $p['functionCall']['args'] = new \stdClass();
                    }
                    return $p;
                }, $parts);
                $contents[] = ['role' => 'model', 'parts' => $modelParts];

                // Our function-response turn.
                $contents[] = [
                    'role'  => 'user',
                    'parts' => [[
                        'functionResponse' => [
                            'name'     => $name,
                            'response' => ['content' => empty($result) ? new \stdClass() : $result],
                        ],
                    ]],
                ];
                continue;
            }

            $text = trim(implode("\n", $textParts));
            return ['text' => $text !== '' ? $text : '(walang laman ang sagot)', 'tools_used' => $toolsUsed];
        }

        return ['text' => 'Sorry, sobrang dami na ng tool calls. Try to rephrase your question.', 'tools_used' => $toolsUsed];
    }

    private function callGemini(string $apiKey, array $contents): array
    {
        $model = config('services.gemini.model', 'gemini-3.5-flash-lite');
        $url   = sprintf(self::ENDPOINT_TEMPLATE, $model);

        $body = [
            'systemInstruction' => [
                'parts' => [[
                    'text' => $this->systemPrompt(),
                ]],
            ],
            'contents' => $contents,
            'tools'    => [[
                'functionDeclarations' => $this->toolSchemas(),
            ]],
            'generationConfig' => [
                'temperature'     => 0.3,
                'maxOutputTokens' => 800,
            ],
        ];

        // Retry loop for transient errors (429 rate limit, 503 model overload).
        // We respect Gemini's suggested retryDelay when provided.
        $lastError = null;
        for ($attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++) {
            $resp = Http::timeout(60)
                ->withQueryParameters(['key' => $apiKey])
                ->post($url, $body);

            if ($resp->successful()) return $resp->json();

            $status = $resp->status();
            $lastError = $resp->body();

            // 429 = rate limit, 503 = model overloaded — worth retrying
            if (in_array($status, [429, 503]) && $attempt < self::MAX_RETRIES) {
                $delay = $this->extractRetryDelay($resp->json()) ?? (2 * ($attempt + 1));
                sleep(min($delay, 20));
                continue;
            }

            Log::warning('Gemini API error', ['status' => $status, 'body' => $lastError]);

            if ($status === 429) {
                $delay = $this->extractRetryDelay($resp->json()) ?? 30;
                throw new RateLimitedException($delay, "Rate limit — please wait {$delay} seconds and try again.");
            }
            throw new RuntimeException('AI service error: HTTP ' . $status . ' — ' . $lastError);
        }

        throw new RuntimeException('AI service error after retries: ' . $lastError);
    }

    /**
     * Extract retryDelay from a Gemini error response, e.g. "14s" → 14.
     */
    private function extractRetryDelay(?array $errorJson): ?int
    {
        if (!$errorJson) return null;
        foreach ($errorJson['error']['details'] ?? [] as $detail) {
            if (($detail['@type'] ?? '') === 'type.googleapis.com/google.rpc.RetryInfo') {
                if (preg_match('/(\d+)s/', $detail['retryDelay'] ?? '', $m)) {
                    return (int) $m[1];
                }
            }
        }
        return null;
    }

    private function systemPrompt(): string
    {
        return <<<PROMPT
You are the IT Asset Inventory assistant for Arvin International Marketing Inc. Answer questions about physical IT assets (laptops, desktops, monitors, printers, peripherals), the employees who hold them, warranty status, movements, and inventory statistics.

Guidelines:
- ALWAYS call a tool to get current data before answering — never invent numbers or asset tags.
- For complex or analytical questions, chain MULTIPLE tool calls to build a deeper answer. Don't stop at the first result if the question calls for correlation, comparison, or root-cause analysis.

DISAMBIGUATION — this is critical:
- When the user uses a term that could map to multiple entities, do NOT guess and do NOT silently broaden the query. Examples:
    - "PC" → could mean Desktop, Laptop, Workstation, or all computers combined
    - "computer" → same
    - "printer" → could be a specific brand of printer, or all
    - "Zamboanga" → could be one location or many (Zamboanga City vs. Zamboanga Warehouse)
    - "IT" → could be the department "IT", or a person named IT, or a category
- Before answering: if you are less than 90% sure what the user means for a term, ALWAYS call `list_reference_data` (categories, departments, or locations as appropriate) to see the actual names in the system.
- After checking, if:
    - EXACTLY ONE candidate obviously matches the user's term → proceed and mention which one you used
    - MULTIPLE plausible candidates → STOP and ask the user which one. Show them the options. Example: "May **Zamboanga City** at **Zamboanga Warehouse** kami — alin sa dalawa?"
    - NO clear match → ask what they mean, listing what actually exists. Example: "Wala kaming category na 'PC'. Meron kaming Desktop, Laptop, at Workstation — alin dito?"
- It is BETTER to ask one clarifying question than to give a wrong or overly-broad answer.
- Do NOT silently substitute a broader term (e.g. "asset" for "PC") — that hides the mismatch from the user.

Tool routing hints:
    - `list_reference_data` — call this FIRST for disambiguation when unsure about categories/departments/locations/brands
    - `advanced_asset_query` for compound filters (status + category + warranty + spec, etc.)
    - `compliance_report` for audits, health checks, risk, "what's wrong with our inventory?"
    - `query_by_specification` for questions about spec fields — ALWAYS pass ALL likely synonym variants (e.g. ["antivirus","endpoint","av"], ["os","operating system"])
    - `asset_history` for questions about ONE asset's lifecycle
    - `employee_asset_summary` for top/bottom employee holder questions
    - `list_specification_keys` first if you don't know what spec key names exist

- Reply in the language the user used (English or Filipino/Taglish). Match their tone.
- If a tool returns no results, say so plainly. Don't fabricate.
- When surfacing findings, ADD BRIEF ANALYSIS or a "so what?" — don't just dump data. E.g. after listing assets past lifespan, note "these are candidates for replacement budgeting."
- Today's date is {$this->today()}.

WHAT YOU CAN DO (share when relevant):
✅ Search / count / list assets by any combination of: tag, serial, model, category, brand, status, holder, department, location, warranty state, past-lifespan, purchase date range, spec fields (antivirus, OS, storage, etc.)
✅ Full asset history — every issuance, return, transfer, and part change of a single asset
✅ Search / filter employees, permits, incident reports, recommendations, part changes
✅ Compliance audit — missing antivirus, past-warranty in-use, past-lifespan not retired, orphaned assigned assets, overloaded employees
✅ Reference data — list departments, locations, categories, brands, conditions with counts
✅ Cross-entity analysis — combine multiple queries to answer "why?" and "what's the pattern?" questions

WHAT YOU CANNOT DO — say this clearly when asked, do NOT fake an answer:
❌ Modify anything (create/edit/delete assets, employees, movements) — read-only ako. Say: "Hindi ko kaya i-modify. Gawin mo sa app UI."
❌ Send emails, notifications, reminders
❌ Historical trends over months/years (walang snapshot data — kasalukuyang state lang meron ako)
❌ Financial forecasting or budget prediction — no forecasting model
❌ Predict future failures or which assets will break next — no ML model
❌ Compare vendors by failure rate or reliability — hindi na-track ang failure metadata para dito
❌ Generate charts/graphs — text tables lang ang kayang i-render
❌ Access data outside this system — HR records, financial data, external systems (walang tools para dito)
❌ Answer general knowledge questions unrelated to IT inventory (weather, news, coding help, etc.)
❌ Read/analyze uploaded images or attachments — walang vision tool

When a user asks something you CAN'T do, respond clearly:
    - Say what specifically cannot be answered
    - Suggest what CAN be done nearby, if anything (e.g., "Hindi ko kaya i-predict, pero pwede kong ipakita kung anong assets ang lampas na sa lifespan — mga potential na sunod na palitan")
    - Never make up data or pretend a tool exists

Formatting rules (IMPORTANT — output rendered as Markdown in a chat bubble):
- For any list of 2+ items with 2+ attributes (assets, employees, movements, warranty results, dept/category breakdowns), USE A MARKDOWN TABLE. Never dump long bullet lists when a table is clearer.
- Table example:
  | Asset Tag | Category | Holder | Warranty Until |
  |-----------|----------|--------|----------------|
  | LPT-001   | Laptop   | Juan   | 2027-03-15     |
- For a SINGLE record answer (e.g. get_asset with 1 result), use bold labels and short paragraphs, not a table.
- For simple counts / single-number answers, one plain sentence.
- Use **bold** for key numbers or names. Use short paragraphs. Avoid preambles like "Here is the information you requested".
PROMPT;
    }

    private function today(): string
    {
        return Carbon::today()->format('Y-m-d');
    }

    // ── Tool schemas (what Gemini sees) ────────────────────────────────────

    private function toolSchemas(): array
    {
        return [
            [
                'name'        => 'search_assets',
                'description' => 'Free-text search across asset tag, serial number, model, and holder name. Returns up to 10 matches with basic info.',
                'parameters'  => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Search text (tag, serial, model, or person name).'],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name'        => 'get_asset',
                'description' => 'Get full details of one asset by its tag or serial number.',
                'parameters'  => [
                    'type' => 'object',
                    'properties' => [
                        'tag_or_serial' => ['type' => 'string', 'description' => 'Asset tag (e.g. LPT-001) or serial number.'],
                    ],
                    'required' => ['tag_or_serial'],
                ],
            ],
            [
                'name'        => 'count_assets',
                'description' => 'Count assets with optional filters. Use for questions like "how many laptops?" or "how many defective assets?".',
                'parameters'  => [
                    'type' => 'object',
                    'properties' => [
                        'status'          => ['type' => 'string', 'description' => 'One of: in_stock, assigned, for_repair, defective, retired, replaced. Optional.'],
                        'category_name'   => ['type' => 'string', 'description' => 'Category name (e.g. Laptop, Monitor). Optional.'],
                        'department_name' => ['type' => 'string', 'description' => 'Department name. Optional.'],
                    ],
                ],
            ],
            [
                'name'        => 'assets_by_employee',
                'description' => 'List assets currently held by an employee. Match by first/last name.',
                'parameters'  => [
                    'type' => 'object',
                    'properties' => [
                        'employee_name' => ['type' => 'string', 'description' => 'Full or partial employee name.'],
                    ],
                    'required' => ['employee_name'],
                ],
            ],
            [
                'name'        => 'find_expiring_warranties',
                'description' => 'List assets whose warranty expires within a given number of days. Use for "which assets are expiring soon?"',
                'parameters'  => [
                    'type' => 'object',
                    'properties' => [
                        'days_ahead' => ['type' => 'integer', 'description' => 'Look this many days into the future. Default 90.'],
                    ],
                ],
            ],
            [
                'name'        => 'find_replacement_eligible',
                'description' => 'List assets past their expected lifespan (eligible for replacement). Use for "which assets should we replace?"',
                'parameters'  => [
                    'type' => 'object',
                    'properties' => new \stdClass(), // no params, but Gemini requires an object
                ],
            ],
            [
                'name'        => 'stats_by_category',
                'description' => 'Return total asset count per category. Use for breakdowns of the inventory mix.',
                'parameters'  => ['type' => 'object', 'properties' => new \stdClass()],
            ],
            [
                'name'        => 'stats_by_department',
                'description' => 'Return total asset count per department. Use for questions about how assets are distributed across teams.',
                'parameters'  => ['type' => 'object', 'properties' => new \stdClass()],
            ],
            [
                'name'        => 'recent_movements',
                'description' => 'List recent asset movements (issuances, returns, transfers) within the last N days.',
                'parameters'  => [
                    'type' => 'object',
                    'properties' => [
                        'days' => ['type' => 'integer', 'description' => 'Look back this many days. Default 14.'],
                    ],
                ],
            ],
            [
                'name'        => 'list_specification_keys',
                'description' => 'List all distinct specification field names in use across assets (e.g. Antivirus, OS Name, Storage, Processor, Hostname, MS Office). Use this first when the user asks about a spec you are unsure exists.',
                'parameters'  => ['type' => 'object', 'properties' => new \stdClass()],
            ],
            [
                'name'        => 'advanced_asset_query',
                'description' => 'Powerful multi-criteria asset search. Combine ANY of: status, category, department, brand, holder name, warranty state, past-lifespan, purchase date range, spec must-have, spec must-be-missing. Use for compound questions like "laptops in Marketing assigned to someone whose warranty expired and are past their 5-year lifespan". Returns total match count plus sample rows.',
                'parameters'  => [
                    'type' => 'object',
                    'properties' => [
                        'status'            => ['type' => 'string', 'description' => 'in_stock | assigned | for_repair | defective | retired | replaced'],
                        'category_name'     => ['type' => 'string', 'description' => 'Category name (partial match).'],
                        'department_name'   => ['type' => 'string', 'description' => 'Department name (partial match).'],
                        'brand_name'        => ['type' => 'string', 'description' => 'Brand name (partial match).'],
                        'holder_name'       => ['type' => 'string', 'description' => 'Employee name (partial match, first or last).'],
                        'warranty_status'   => ['type' => 'string', 'description' => 'expired | expiring_soon | active'],
                        'past_lifespan'     => ['type' => 'boolean', 'description' => 'True to only include assets past their expected lifespan.'],
                        'purchased_after'   => ['type' => 'string', 'description' => 'ISO date, e.g. "2020-01-01". Include only assets purchased on/after this date.'],
                        'purchased_before'  => ['type' => 'string', 'description' => 'ISO date. Include only assets purchased on/before this date.'],
                        'spec_missing'      => ['type' => 'string', 'description' => 'A spec key name (case-insensitive substring). Include only assets where this spec is empty or not present. Use for "assets without antivirus" combined with other filters.'],
                        'spec_value_match'  => [
                            'type' => 'object',
                            'description' => 'Filter to assets where a spec key contains this value. Format: {"spec_key": "OS Name", "value": "Windows 11"}. Substring match on both.',
                            'properties' => [
                                'spec_key' => ['type' => 'string'],
                                'value'    => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name'        => 'compliance_report',
                'description' => 'Run an inventory health/compliance audit. Detects: assets marked "assigned" but with no holder, past-warranty assets still in service, past-lifespan assets not retired, employees with no assets, employees with unusually many assets (>5), assets missing critical specs (antivirus/OS). Use when user asks about inventory health, audit, compliance, risks, or issues.',
                'parameters'  => ['type' => 'object', 'properties' => new \stdClass()],
            ],
            [
                'name'        => 'search_permits',
                'description' => 'Search Permits to Bring Asset (PBA) — documents allowing an employee to take a device off-premises. Filter by status, employee, date range, currently-valid.',
                'parameters'  => [
                    'type' => 'object',
                    'properties' => [
                        'status'        => ['type' => 'string', 'description' => 'draft | approved | returned | cancelled'],
                        'employee_name' => ['type' => 'string', 'description' => 'Partial employee name.'],
                        'valid_now'     => ['type' => 'boolean', 'description' => 'True to only include permits currently valid (today between valid_from and valid_to).'],
                        'from_date'     => ['type' => 'string', 'description' => 'ISO date. Only permits with date_borrow on/after this date.'],
                        'to_date'       => ['type' => 'string', 'description' => 'ISO date. Only permits with date_borrow on/before this date.'],
                    ],
                ],
            ],
            [
                'name'        => 'search_incidents',
                'description' => 'Search Incident Reports (IR) — logged asset problems (defective, damaged, malfunction). Filter by status, asset tag, end user, date range.',
                'parameters'  => [
                    'type' => 'object',
                    'properties' => [
                        'status'         => ['type' => 'string', 'description' => 'draft | submitted | approved | closed'],
                        'asset_tag'      => ['type' => 'string', 'description' => 'Exact or partial asset tag.'],
                        'end_user_name'  => ['type' => 'string', 'description' => 'Partial name of the end user who reported.'],
                        'from_date'      => ['type' => 'string', 'description' => 'ISO date. Reports on/after this report_date.'],
                        'to_date'        => ['type' => 'string', 'description' => 'ISO date. Reports on/before this report_date.'],
                        'search_text'    => ['type' => 'string', 'description' => 'Free-text substring in reported problem, findings, or action taken.'],
                    ],
                ],
            ],
            [
                'name'        => 'search_recommendations',
                'description' => 'Search Recommendations (REC) — proposals for upgrades, replacements, purchases. Filter by status, asset, requestor, date range, subject text.',
                'parameters'  => [
                    'type' => 'object',
                    'properties' => [
                        'status'          => ['type' => 'string', 'description' => 'draft | submitted | approved | closed'],
                        'asset_tag'       => ['type' => 'string', 'description' => 'Exact or partial asset tag.'],
                        'requestor_name'  => ['type' => 'string', 'description' => 'Partial requestor name.'],
                        'department'      => ['type' => 'string', 'description' => 'Requestor department (partial match).'],
                        'subject_text'    => ['type' => 'string', 'description' => 'Substring in subject or body.'],
                        'from_date'       => ['type' => 'string', 'description' => 'ISO date. On/after this report_date.'],
                        'to_date'         => ['type' => 'string', 'description' => 'ISO date. On/before this report_date.'],
                    ],
                ],
            ],
            [
                'name'        => 'search_part_changes',
                'description' => 'Search asset part-change history — RAM upgrades, SSD swaps, GPU replacements, etc. Filter by part name, asset, reason, date range.',
                'parameters'  => [
                    'type' => 'object',
                    'properties' => [
                        'part_name'  => ['type' => 'string', 'description' => 'Partial part name (RAM, SSD, GPU, etc.).'],
                        'asset_tag'  => ['type' => 'string', 'description' => 'Filter to one asset.'],
                        'reason'     => ['type' => 'string', 'description' => 'Partial reason (Upgrade, Failure, Defective, etc.).'],
                        'from_date'  => ['type' => 'string', 'description' => 'ISO date. Changes on/after this date.'],
                        'to_date'    => ['type' => 'string', 'description' => 'ISO date. Changes on/before this date.'],
                    ],
                ],
            ],
            [
                'name'        => 'list_reference_data',
                'description' => 'List master/reference data — departments, locations, categories, brands, conditions — with asset counts. Use for questions like "list all departments", "how many brands do we have?", "what categories exist?".',
                'parameters'  => [
                    'type' => 'object',
                    'properties' => [
                        'kind' => ['type' => 'string', 'description' => 'departments | locations | categories | brands | conditions | all. Default: all'],
                    ],
                ],
            ],
            [
                'name'        => 'search_employees',
                'description' => 'Search / filter EMPLOYEES (people), not assets. Use for questions about employees themselves — "employees hired in 2025", "employees whose employee_no starts with 2026", "sino sa HR department", etc. Note the difference: employee_no is the HR-issued ID (may look similar to asset tags but they are separate identifiers).',
                'parameters'  => [
                    'type' => 'object',
                    'properties' => [
                        'name'                 => ['type' => 'string', 'description' => 'Partial name match (first, middle, or last).'],
                        'employee_no'          => ['type' => 'string', 'description' => 'Exact or partial employee number (case-insensitive substring). E.g. "2026" matches employees whose employee_no contains "2026".'],
                        'employee_no_starts'   => ['type' => 'string', 'description' => 'Employee number prefix — matches employee_no starting with this. E.g. "2026" for employees hired/numbered in 2026.'],
                        'department_name'      => ['type' => 'string', 'description' => 'Partial department name.'],
                        'location_name'        => ['type' => 'string', 'description' => 'Partial location name.'],
                        'position'             => ['type' => 'string', 'description' => 'Partial position/title.'],
                        'status'               => ['type' => 'string', 'description' => 'active | inactive | resigned'],
                        'hired_after'          => ['type' => 'string', 'description' => 'ISO date (YYYY-MM-DD). Only employees hired on/after this date.'],
                        'hired_before'         => ['type' => 'string', 'description' => 'ISO date. Only employees hired on/before this date.'],
                    ],
                ],
            ],
            [
                'name'        => 'employee_asset_summary',
                'description' => 'Top holders + employees without any assets. Use for "sino ang may pinakamaraming asset?" or "sino wala pang device?".',
                'parameters'  => [
                    'type' => 'object',
                    'properties' => [
                        'top_n' => ['type' => 'integer', 'description' => 'How many top holders to return. Default 10.'],
                    ],
                ],
            ],
            [
                'name'        => 'asset_history',
                'description' => 'Full timeline of one asset — every movement (issuance/return/transfer) and every part change (RAM upgrade, SSD swap, etc.). Use for questions like "history of asset X", "kailan pa ni-issue si X sa employee?", "anong mga na-upgrade sa X?".',
                'parameters'  => [
                    'type' => 'object',
                    'properties' => [
                        'tag_or_serial' => ['type' => 'string'],
                    ],
                    'required' => ['tag_or_serial'],
                ],
            ],
            [
                'name'        => 'query_by_specification',
                'description' => 'Query assets by a specification field stored in the specifications JSON (case-insensitive substring match on the key). Use this for questions about Antivirus, OS Name, Storage, Processor, Hostname, MS Office, OS License, and similar per-asset spec attributes. Returns counts grouped by value AND a sample of asset tags/holders per group, plus assets that have no value set for the spec. Because organizations rename spec fields over time, ALWAYS pass ALL likely synonym variants — e.g. for antivirus/endpoint protection, pass ["antivirus", "endpoint", "av"]; for OS, pass ["os", "operating system"].',
                'parameters'  => [
                    'type' => 'object',
                    'properties' => [
                        'spec_keys' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'One or more spec field names or synonyms. Match is case-insensitive substring — "antivirus" matches "Antivirus Software", "endpoint" matches "Endpoint Protection". Pass every reasonable variant.',
                        ],
                        'value' => ['type' => 'string', 'description' => 'Optional. Filter to only assets whose value matches this (case-insensitive). E.g. value="No" for "who has no antivirus?"'],
                    ],
                    'required' => ['spec_keys'],
                ],
            ],
        ];
    }

    // ── Tool executor (dispatch to real DB queries) ────────────────────────

    private function executeTool(string $name, array $args): array
    {
        try {
            return match ($name) {
                'search_assets'             => $this->toolSearchAssets($args),
                'get_asset'                 => $this->toolGetAsset($args),
                'count_assets'              => $this->toolCountAssets($args),
                'assets_by_employee'        => $this->toolAssetsByEmployee($args),
                'find_expiring_warranties'  => $this->toolExpiringWarranties($args),
                'find_replacement_eligible' => $this->toolReplacementEligible(),
                'stats_by_category'         => $this->toolStatsByCategory(),
                'stats_by_department'       => $this->toolStatsByDepartment(),
                'recent_movements'          => $this->toolRecentMovements($args),
                'list_specification_keys'   => $this->toolListSpecKeys(),
                'query_by_specification'    => $this->toolQueryBySpec($args),
                'advanced_asset_query'      => $this->toolAdvancedQuery($args),
                'compliance_report'         => $this->toolComplianceReport(),
                'employee_asset_summary'    => $this->toolEmployeeAssetSummary($args),
                'asset_history'             => $this->toolAssetHistory($args),
                'search_employees'          => $this->toolSearchEmployees($args),
                'search_permits'            => $this->toolSearchPermits($args),
                'search_incidents'          => $this->toolSearchIncidents($args),
                'search_recommendations'    => $this->toolSearchRecommendations($args),
                'search_part_changes'       => $this->toolSearchPartChanges($args),
                'list_reference_data'       => $this->toolListReferenceData($args),
                default                     => ['error' => "Unknown tool: {$name}"],
            };
        } catch (\Throwable $e) {
            Log::warning('AI tool error', ['tool' => $name, 'args' => $args, 'error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    private function toolSearchAssets(array $args): array
    {
        $q = trim((string) ($args['query'] ?? ''));
        if ($q === '') return ['results' => [], 'note' => 'empty query'];

        $rows = Asset::query()
            ->with(['currentHolder:id,first_name,last_name', 'category:id,name', 'brand:id,name'])
            ->where(fn ($w) => $w->where('asset_tag', 'like', "%{$q}%")
                                 ->orWhere('serial_number', 'like', "%{$q}%")
                                 ->orWhere('model', 'like', "%{$q}%")
                                 ->orWhereHas('currentHolder', fn ($h) =>
                                     $h->whereRaw("CONCAT_WS(' ', first_name, last_name) LIKE ?", ["%{$q}%"])
                                 ))
            ->limit(10)
            ->get();

        return [
            'count'   => $rows->count(),
            'results' => $rows->map(fn ($a) => [
                'tag'      => $a->asset_tag,
                'serial'   => $a->serial_number,
                'model'    => $a->model,
                'category' => $a->category?->name,
                'brand'    => $a->brand?->name,
                'status'   => $a->current_status,
                'holder'   => $a->currentHolder?->full_name,
            ])->all(),
        ];
    }

    private function toolGetAsset(array $args): array
    {
        $key = trim((string) ($args['tag_or_serial'] ?? ''));
        if ($key === '') return ['error' => 'tag_or_serial required'];

        $a = Asset::with(['currentHolder:id,first_name,last_name', 'currentLocation:id,name', 'category:id,name', 'brand:id,name', 'condition:id,name'])
            ->where('asset_tag', $key)->orWhere('serial_number', $key)
            ->first();
        if (!$a) return ['found' => false, 'message' => "No asset found with tag or serial '{$key}'"];

        return [
            'found' => true,
            'asset' => [
                'tag'             => $a->asset_tag,
                'serial'          => $a->serial_number,
                'model'           => $a->model,
                'description'     => $a->description,
                'category'        => $a->category?->name,
                'brand'           => $a->brand?->name,
                'condition'       => $a->condition?->name,
                'status'          => $a->current_status,
                'holder'          => $a->currentHolder?->full_name,
                'location'        => $a->currentLocation?->name,
                'purchase_date'   => $a->purchase_date?->format('Y-m-d'),
                'purchase_cost'   => $a->purchase_cost,
                'vendor'          => $a->vendor,
                'warranty_until'  => $a->warranty_until?->format('Y-m-d'),
                'warranty_status' => $a->warranty_status,
                'age_formatted'   => $a->age_formatted,
                'is_eligible_for_replacement' => $a->is_eligible_for_replacement,
                'notes'           => $a->notes,
            ],
        ];
    }

    private function toolCountAssets(array $args): array
    {
        $query = Asset::query();
        $applied = [];

        if (!empty($args['status'])) {
            $query->where('current_status', $args['status']);
            $applied['status'] = $args['status'];
        }
        if (!empty($args['category_name'])) {
            $catId = Category::where('name', 'like', "%{$args['category_name']}%")->value('id');
            if ($catId) {
                $query->where('category_id', $catId);
                $applied['category'] = $args['category_name'];
            } else {
                return ['count' => 0, 'note' => "Category '{$args['category_name']}' not found"];
            }
        }
        if (!empty($args['department_name'])) {
            $deptId = Department::where('name', 'like', "%{$args['department_name']}%")->value('id');
            if ($deptId) {
                $query->where('department_id', $deptId);
                $applied['department'] = $args['department_name'];
            } else {
                return ['count' => 0, 'note' => "Department '{$args['department_name']}' not found"];
            }
        }

        return ['count' => $query->count(), 'filters' => $applied];
    }

    private function toolAssetsByEmployee(array $args): array
    {
        $name = trim((string) ($args['employee_name'] ?? ''));
        if ($name === '') return ['error' => 'employee_name required'];

        $employees = Employee::whereRaw("CONCAT_WS(' ', first_name, last_name) LIKE ?", ["%{$name}%"])
            ->limit(3)->get();
        if ($employees->isEmpty()) return ['found' => false, 'message' => "No employee matches '{$name}'"];
        if ($employees->count() > 1) {
            return [
                'found' => 'multiple',
                'matches' => $employees->map(fn ($e) => ['id' => $e->id, 'name' => $e->full_name])->all(),
                'message' => 'Multiple employees matched. Ask user to pick.',
            ];
        }

        $emp = $employees->first();
        $assets = Asset::where('current_holder_id', $emp->id)
            ->with('category:id,name', 'brand:id,name')->get();

        return [
            'employee' => $emp->full_name,
            'count'    => $assets->count(),
            'assets'   => $assets->map(fn ($a) => [
                'tag'      => $a->asset_tag,
                'model'    => $a->model,
                'category' => $a->category?->name,
                'brand'    => $a->brand?->name,
                'status'   => $a->current_status,
            ])->all(),
        ];
    }

    private function toolExpiringWarranties(array $args): array
    {
        $days = (int) ($args['days_ahead'] ?? 90);
        $today = Carbon::today();
        $limit = $today->copy()->addDays($days);

        $rows = Asset::whereBetween('warranty_until', [$today, $limit])
            ->with('category:id,name', 'currentHolder:id,first_name,last_name')
            ->orderBy('warranty_until')
            ->limit(30)->get();

        return [
            'days_ahead' => $days,
            'count'      => $rows->count(),
            'results'    => $rows->map(fn ($a) => [
                'tag'            => $a->asset_tag,
                'category'       => $a->category?->name,
                'holder'         => $a->currentHolder?->full_name,
                'warranty_until' => $a->warranty_until?->format('Y-m-d'),
                'days_left'      => (int) $today->diffInDays($a->warranty_until),
            ])->all(),
        ];
    }

    private function toolReplacementEligible(): array
    {
        $rows = Asset::whereNotNull('purchase_date')
            ->whereRaw('DATE_ADD(purchase_date, INTERVAL expected_lifespan_years YEAR) < CURDATE()')
            ->whereNotIn('current_status', ['retired', 'replaced'])
            ->with('category:id,name', 'currentHolder:id,first_name,last_name')
            ->orderBy('purchase_date')
            ->limit(30)->get();

        return [
            'count'   => $rows->count(),
            'results' => $rows->map(fn ($a) => [
                'tag'           => $a->asset_tag,
                'category'      => $a->category?->name,
                'holder'        => $a->currentHolder?->full_name,
                'age'           => $a->age_formatted,
                'purchase_date' => $a->purchase_date?->format('Y-m-d'),
                'lifespan'      => $a->expected_lifespan_years,
                'status'        => $a->current_status,
            ])->all(),
        ];
    }

    private function toolStatsByCategory(): array
    {
        $rows = Asset::join('categories', 'assets.category_id', '=', 'categories.id')
            ->select('categories.name', DB::raw('COUNT(*) as count'))
            ->groupBy('categories.name')
            ->orderByDesc('count')
            ->get();

        return [
            'total_assets' => Asset::count(),
            'by_category'  => $rows->map(fn ($r) => ['category' => $r->name, 'count' => (int) $r->count])->all(),
        ];
    }

    private function toolStatsByDepartment(): array
    {
        $rows = Asset::leftJoin('departments', 'assets.department_id', '=', 'departments.id')
            ->select('departments.name', DB::raw('COUNT(*) as count'))
            ->groupBy('departments.name')
            ->orderByDesc('count')
            ->get();

        return [
            'by_department' => $rows->map(fn ($r) => [
                'department' => $r->name ?? '(unassigned)',
                'count'      => (int) $r->count,
            ])->all(),
        ];
    }

    private function toolSearchPermits(array $args): array
    {
        $q = \App\Models\AssetPermit::with(['employee:id,first_name,last_name', 'items']);
        $applied = [];
        if (!empty($args['status']))        { $q->where('status', $args['status']); $applied['status'] = $args['status']; }
        if (!empty($args['employee_name'])) {
            $n = $args['employee_name'];
            $q->where(fn ($w) => $w->where('employee_name', 'like', "%{$n}%")
                                    ->orWhereHas('employee', fn ($e) => $e->whereRaw("CONCAT_WS(' ', first_name, last_name) LIKE ?", ["%{$n}%"])));
            $applied['employee'] = $n;
        }
        if (!empty($args['valid_now'])) {
            $today = Carbon::today();
            $q->whereDate('valid_from', '<=', $today)->whereDate('valid_to', '>=', $today);
            $applied['valid_now'] = true;
        }
        if (!empty($args['from_date'])) { $q->where('date_borrow', '>=', $args['from_date']); $applied['from_date'] = $args['from_date']; }
        if (!empty($args['to_date']))   { $q->where('date_borrow', '<=', $args['to_date']);   $applied['to_date']   = $args['to_date']; }

        $total = (clone $q)->count();
        $rows = $q->orderByDesc('date_borrow')->limit(30)->get();
        return [
            'filters_applied' => $applied,
            'total_matched'   => $total,
            'sample'          => $rows->map(fn ($p) => [
                'permit_no'   => $p->permit_no,
                'employee'    => $p->employee_name ?: $p->employee?->full_name,
                'destination' => $p->destination,
                'purpose'     => $p->purpose,
                'date_borrow' => $p->date_borrow?->format('Y-m-d'),
                'date_return' => $p->date_return?->format('Y-m-d'),
                'valid_from'  => $p->valid_from?->format('Y-m-d'),
                'valid_to'    => $p->valid_to?->format('Y-m-d'),
                'status'      => $p->status,
                'item_count'  => $p->items->count(),
            ])->all(),
        ];
    }

    private function toolSearchIncidents(array $args): array
    {
        $q = IncidentReport::with(['asset:id,asset_tag', 'endUser:id,first_name,last_name']);
        $applied = [];
        if (!empty($args['status']))       { $q->where('status', $args['status']); $applied['status'] = $args['status']; }
        if (!empty($args['asset_tag'])) {
            $ids = Asset::where('asset_tag', 'like', "%{$args['asset_tag']}%")->pluck('id');
            $q->whereIn('asset_id', $ids); $applied['asset_tag'] = $args['asset_tag'];
        }
        if (!empty($args['end_user_name'])) {
            $n = $args['end_user_name'];
            $q->where(fn ($w) => $w->where('end_user_name', 'like', "%{$n}%")
                                    ->orWhereHas('endUser', fn ($e) => $e->whereRaw("CONCAT_WS(' ', first_name, last_name) LIKE ?", ["%{$n}%"])));
            $applied['end_user'] = $n;
        }
        if (!empty($args['from_date']))    { $q->where('report_date', '>=', $args['from_date']); $applied['from_date'] = $args['from_date']; }
        if (!empty($args['to_date']))      { $q->where('report_date', '<=', $args['to_date']);   $applied['to_date']   = $args['to_date']; }
        if (!empty($args['search_text'])) {
            $s = $args['search_text'];
            $q->where(fn ($w) => $w->where('reported_problem', 'like', "%{$s}%")
                                    ->orWhere('findings', 'like', "%{$s}%")
                                    ->orWhere('action_taken', 'like', "%{$s}%"));
            $applied['search_text'] = $s;
        }
        $total = (clone $q)->count();
        $rows = $q->orderByDesc('report_date')->limit(30)->get();
        return [
            'filters_applied' => $applied,
            'total_matched'   => $total,
            'sample'          => $rows->map(fn ($r) => [
                'ir_no'            => $r->ir_no,
                'asset_tag'        => $r->asset?->asset_tag,
                'end_user'         => $r->end_user_name ?: $r->endUser?->full_name,
                'report_date'      => $r->report_date?->format('Y-m-d'),
                'reported_problem' => \Illuminate\Support\Str::limit((string)$r->reported_problem, 120),
                'status'           => $r->status,
            ])->all(),
        ];
    }

    private function toolSearchRecommendations(array $args): array
    {
        $q = \App\Models\Recommendation::with(['asset:id,asset_tag', 'requestor:id,first_name,last_name']);
        $applied = [];
        if (!empty($args['status']))         { $q->where('status', $args['status']); $applied['status'] = $args['status']; }
        if (!empty($args['asset_tag'])) {
            $ids = Asset::where('asset_tag', 'like', "%{$args['asset_tag']}%")->pluck('id');
            $q->whereIn('asset_id', $ids); $applied['asset_tag'] = $args['asset_tag'];
        }
        if (!empty($args['requestor_name'])) {
            $n = $args['requestor_name'];
            $q->where(fn ($w) => $w->where('requestor_name', 'like', "%{$n}%")
                                    ->orWhereHas('requestor', fn ($e) => $e->whereRaw("CONCAT_WS(' ', first_name, last_name) LIKE ?", ["%{$n}%"])));
            $applied['requestor'] = $n;
        }
        if (!empty($args['department'])) {
            $q->where('requestor_department', 'like', "%{$args['department']}%");
            $applied['department'] = $args['department'];
        }
        if (!empty($args['subject_text'])) {
            $s = $args['subject_text'];
            $q->where(fn ($w) => $w->where('subject', 'like', "%{$s}%")->orWhere('body', 'like', "%{$s}%"));
            $applied['subject_text'] = $s;
        }
        if (!empty($args['from_date'])) { $q->where('report_date', '>=', $args['from_date']); $applied['from_date'] = $args['from_date']; }
        if (!empty($args['to_date']))   { $q->where('report_date', '<=', $args['to_date']);   $applied['to_date']   = $args['to_date']; }

        $total = (clone $q)->count();
        $rows = $q->orderByDesc('report_date')->limit(30)->get();
        return [
            'filters_applied' => $applied,
            'total_matched'   => $total,
            'sample'          => $rows->map(fn ($r) => [
                'doc_no'      => $r->doc_no,
                'subject'     => $r->subject,
                'asset_tag'   => $r->asset?->asset_tag,
                'requestor'   => $r->requestor_name ?: $r->requestor?->full_name,
                'department'  => $r->requestor_department,
                'report_date' => $r->report_date?->format('Y-m-d'),
                'status'      => $r->status,
            ])->all(),
        ];
    }

    private function toolSearchPartChanges(array $args): array
    {
        $q = \App\Models\AssetPartChange::with(['asset:id,asset_tag', 'performer:id,name']);
        $applied = [];
        if (!empty($args['part_name'])) { $q->where('part_name', 'like', "%{$args['part_name']}%"); $applied['part'] = $args['part_name']; }
        if (!empty($args['asset_tag'])) {
            $ids = Asset::where('asset_tag', 'like', "%{$args['asset_tag']}%")->pluck('id');
            $q->whereIn('asset_id', $ids); $applied['asset_tag'] = $args['asset_tag'];
        }
        if (!empty($args['reason']))    { $q->where('reason', 'like', "%{$args['reason']}%"); $applied['reason'] = $args['reason']; }
        if (!empty($args['from_date'])) { $q->where('changed_at', '>=', $args['from_date']); $applied['from_date'] = $args['from_date']; }
        if (!empty($args['to_date']))   { $q->where('changed_at', '<=', $args['to_date']);   $applied['to_date']   = $args['to_date']; }

        $total = (clone $q)->count();
        $rows = $q->orderByDesc('changed_at')->limit(30)->get();
        return [
            'filters_applied' => $applied,
            'total_matched'   => $total,
            'sample'          => $rows->map(fn ($pc) => [
                'asset_tag'  => $pc->asset?->asset_tag,
                'part'       => $pc->part_name,
                'from'       => $pc->old_value,
                'to'         => $pc->new_value,
                'reason'     => $pc->reason,
                'changed_at' => $pc->changed_at?->format('Y-m-d'),
                'by'         => $pc->performer?->name,
            ])->all(),
        ];
    }

    private function toolListReferenceData(array $args): array
    {
        $kind = strtolower((string) ($args['kind'] ?? 'all'));
        $out = [];

        if (in_array($kind, ['all', 'departments'])) {
            $out['departments'] = Department::withCount('employees')->orderBy('name')
                ->get(['id','name','code','is_active'])
                ->map(fn ($d) => ['name' => $d->name, 'code' => $d->code, 'employees' => $d->employees_count, 'active' => (bool)$d->is_active])->all();
        }
        if (in_array($kind, ['all', 'locations'])) {
            $out['locations'] = \App\Models\Location::orderBy('name')->get(['id','name','is_active'])
                ->map(fn ($l) => ['name' => $l->name, 'active' => (bool)$l->is_active])->all();
        }
        if (in_array($kind, ['all', 'categories'])) {
            $out['categories'] = Category::withCount('assets')->orderBy('name')->get(['id','name','prefix','is_active'])
                ->map(fn ($c) => ['name' => $c->name, 'prefix' => $c->prefix, 'assets' => $c->assets_count, 'active' => (bool)$c->is_active])->all();
        }
        if (in_array($kind, ['all', 'brands'])) {
            $out['brands'] = \App\Models\Brand::withCount('assets')->orderBy('name')->get(['id','name','is_active'])
                ->map(fn ($b) => ['name' => $b->name, 'assets' => $b->assets_count, 'active' => (bool)$b->is_active])->all();
        }
        if (in_array($kind, ['all', 'conditions'])) {
            $out['conditions'] = \App\Models\Condition::orderBy('sort_order')->orderBy('name')->get(['id','name','is_active'])
                ->map(fn ($c) => ['name' => $c->name, 'active' => (bool)$c->is_active])->all();
        }
        return $out;
    }

    private function toolSearchEmployees(array $args): array
    {
        $q = Employee::query()
            ->with(['department:id,name', 'location:id,name'])
            ->withCount('heldAssets');

        $applied = [];

        if (!empty($args['name'])) {
            $n = $args['name'];
            $q->whereRaw("CONCAT_WS(' ', first_name, middle_name, last_name) LIKE ?", ["%{$n}%"]);
            $applied['name'] = $n;
        }
        if (!empty($args['employee_no'])) {
            $q->where('employee_no', 'like', "%{$args['employee_no']}%");
            $applied['employee_no'] = $args['employee_no'];
        }
        if (!empty($args['employee_no_starts'])) {
            $q->where('employee_no', 'like', "{$args['employee_no_starts']}%");
            $applied['employee_no_starts'] = $args['employee_no_starts'];
        }
        if (!empty($args['department_name'])) {
            $ids = Department::where('name', 'like', "%{$args['department_name']}%")->pluck('id');
            $q->whereIn('department_id', $ids);
            $applied['department'] = $args['department_name'];
        }
        if (!empty($args['location_name'])) {
            $ids = \App\Models\Location::where('name', 'like', "%{$args['location_name']}%")->pluck('id');
            $q->whereIn('location_id', $ids);
            $applied['location'] = $args['location_name'];
        }
        if (!empty($args['position'])) {
            $q->where('position', 'like', "%{$args['position']}%");
            $applied['position'] = $args['position'];
        }
        if (!empty($args['status'])) {
            $q->where('status', $args['status']);
            $applied['status'] = $args['status'];
        }
        if (!empty($args['hired_after'])) {
            $q->where('date_hired', '>=', $args['hired_after']);
            $applied['hired_after'] = $args['hired_after'];
        }
        if (!empty($args['hired_before'])) {
            $q->where('date_hired', '<=', $args['hired_before']);
            $applied['hired_before'] = $args['hired_before'];
        }

        $total = (clone $q)->count();
        $rows  = $q->limit(50)->get();

        return [
            'filters_applied' => $applied,
            'total_matched'   => $total,
            'sample'          => $rows->map(fn ($e) => [
                'employee_no' => $e->employee_no,
                'name'        => $e->full_name,
                'position'    => $e->position,
                'department'  => $e->department?->name,
                'location'    => $e->location?->name,
                'status'      => $e->status,
                'date_hired'  => $e->date_hired?->format('Y-m-d'),
                'held_assets' => $e->held_assets_count,
            ])->all(),
            'truncated'       => $total > 50,
        ];
    }

    private function toolAdvancedQuery(array $args): array
    {
        $q = Asset::query()->select('assets.*')
            ->with(['category:id,name', 'brand:id,name', 'currentHolder:id,first_name,last_name', 'currentLocation:id,name']);

        $applied = [];

        if (!empty($args['status'])) {
            $q->where('current_status', $args['status']);
            $applied['status'] = $args['status'];
        }
        if (!empty($args['category_name'])) {
            $ids = Category::where('name', 'like', "%{$args['category_name']}%")->pluck('id');
            $q->whereIn('category_id', $ids);
            $applied['category'] = $args['category_name'];
        }
        if (!empty($args['department_name'])) {
            $ids = Department::where('name', 'like', "%{$args['department_name']}%")->pluck('id');
            $q->whereIn('department_id', $ids);
            $applied['department'] = $args['department_name'];
        }
        if (!empty($args['brand_name'])) {
            $ids = \App\Models\Brand::where('name', 'like', "%{$args['brand_name']}%")->pluck('id');
            $q->whereIn('brand_id', $ids);
            $applied['brand'] = $args['brand_name'];
        }
        if (!empty($args['holder_name'])) {
            $hn = $args['holder_name'];
            $q->whereHas('currentHolder', fn ($w) =>
                $w->whereRaw("CONCAT_WS(' ', first_name, last_name) LIKE ?", ["%{$hn}%"]));
            $applied['holder'] = $hn;
        }
        if (!empty($args['warranty_status'])) {
            $ws = $args['warranty_status'];
            if ($ws === 'active')        $q->whereDate('warranty_until', '>', now()->addDays(90));
            if ($ws === 'expiring_soon') $q->whereBetween('warranty_until', [now(), now()->addDays(90)]);
            if ($ws === 'expired')       $q->whereDate('warranty_until', '<', now());
            $applied['warranty'] = $ws;
        }
        if (!empty($args['past_lifespan'])) {
            $q->whereNotNull('purchase_date')
              ->whereRaw('DATE_ADD(purchase_date, INTERVAL expected_lifespan_years YEAR) < CURDATE()');
            $applied['past_lifespan'] = true;
        }
        if (!empty($args['purchased_after']))  { $q->where('purchase_date', '>=', $args['purchased_after']);  $applied['purchased_after']  = $args['purchased_after']; }
        if (!empty($args['purchased_before'])) { $q->where('purchase_date', '<=', $args['purchased_before']); $applied['purchased_before'] = $args['purchased_before']; }

        // Spec filters run in-memory after DB filter — get all matching first, cap at 500 for perf.
        $needsSpecFilter = !empty($args['spec_missing']) || !empty($args['spec_value_match']['spec_key']);

        // Total count BEFORE spec filter (cheap SQL count is more useful when no spec filter)
        $totalSql = (clone $q)->count();

        if ($needsSpecFilter) {
            $rows = $q->limit(1000)->get();

            $missNeedle = !empty($args['spec_missing']) ? strtolower(trim($args['spec_missing'])) : null;
            $matchKey   = !empty($args['spec_value_match']['spec_key']) ? strtolower(trim($args['spec_value_match']['spec_key'])) : null;
            $matchVal   = !empty($args['spec_value_match']['value']) ? strtolower(trim($args['spec_value_match']['value'])) : null;

            $filtered = $rows->filter(function ($a) use ($missNeedle, $matchKey, $matchVal) {
                $specs = collect($a->specifications ?? [])->filter(fn ($s) => is_array($s));

                if ($missNeedle !== null) {
                    $found = $specs->contains(fn ($s) =>
                        str_contains(strtolower($s['key'] ?? ''), $missNeedle)
                        && trim((string) ($s['value'] ?? '')) !== ''
                    );
                    if ($found) return false; // spec IS present — exclude
                }
                if ($matchKey !== null) {
                    $ok = $specs->contains(fn ($s) =>
                        str_contains(strtolower($s['key'] ?? ''), $matchKey)
                        && ($matchVal === null || str_contains(strtolower((string) ($s['value'] ?? '')), $matchVal))
                    );
                    if (!$ok) return false;
                }
                return true;
            });
            $total  = $filtered->count();
            $sample = $filtered->take(30);
            $applied['spec_missing'] = $args['spec_missing'] ?? null;
            $applied['spec_value_match'] = $args['spec_value_match'] ?? null;
            $note = $totalSql > 1000 ? "Spec filter was applied on first 1000 SQL matches (of {$totalSql}). Narrow other filters to see all." : null;
        } else {
            $total  = $totalSql;
            $sample = $q->limit(30)->get();
            $note   = null;
        }

        return [
            'filters_applied' => $applied,
            'total_matched'   => $total,
            'sample'          => $sample->map(fn ($a) => [
                'tag'      => $a->asset_tag,
                'category' => $a->category?->name,
                'brand'    => $a->brand?->name,
                'model'    => $a->model,
                'status'   => $a->current_status,
                'holder'   => $a->currentHolder?->full_name,
                'location' => $a->currentLocation?->name,
                'age'      => $a->age_formatted,
                'warranty' => $a->warranty_status,
            ])->values()->all(),
            'note' => $note,
        ];
    }

    private function toolComplianceReport(): array
    {
        $issues = [];

        // 1. Assigned status but no holder
        $c = Asset::where('current_status', 'assigned')->whereNull('current_holder_id')->count();
        if ($c > 0) $issues[] = [
            'issue'    => 'assigned_status_but_no_holder',
            'severity' => 'high',
            'count'    => $c,
            'note'     => "These assets are marked as 'assigned' but no employee is set. Data quality issue.",
        ];

        // 2. Past warranty but still deployed
        $c = Asset::whereDate('warranty_until', '<', now())
            ->whereIn('current_status', ['assigned', 'in_stock'])->count();
        if ($c > 0) $issues[] = [
            'issue'    => 'past_warranty_still_deployed',
            'severity' => 'medium',
            'count'    => $c,
            'note'     => "Warranty expired but asset still in service. Consider renewal or replacement plan.",
        ];

        // 3. Past lifespan but not retired
        $c = Asset::whereNotNull('purchase_date')
            ->whereRaw('DATE_ADD(purchase_date, INTERVAL expected_lifespan_years YEAR) < CURDATE()')
            ->whereNotIn('current_status', ['retired', 'replaced'])->count();
        if ($c > 0) $issues[] = [
            'issue'    => 'past_lifespan_not_retired',
            'severity' => 'medium',
            'count'    => $c,
            'note'     => "Asset is beyond expected service life. Eligible for automatic replacement if it breaks.",
        ];

        // 4. Assets missing antivirus spec (case-insensitive match on any spec key containing antivirus/endpoint)
        $missingAv = 0; $noAv = 0;
        Asset::select('id', 'specifications', 'category_id')
            ->whereHas('category', fn ($q) => $q->whereIn('name', ['Laptop', 'Desktop', 'PC', 'Workstation'])) // only computers
            ->chunk(500, function ($chunk) use (&$missingAv, &$noAv) {
                foreach ($chunk as $a) {
                    $entry = collect($a->specifications ?? [])
                        ->first(fn ($s) => is_array($s) &&
                            (stripos($s['key'] ?? '', 'antivirus') !== false
                             || stripos($s['key'] ?? '', 'endpoint') !== false));
                    $val = is_array($entry) ? strtolower(trim((string) ($entry['value'] ?? ''))) : '';
                    if ($val === '')          $missingAv++;
                    elseif (in_array($val, ['no', 'n', 'none', '-'])) $noAv++;
                }
            });
        if ($missingAv > 0) $issues[] = [
            'issue' => 'computers_missing_antivirus_spec',
            'severity' => 'low',
            'count' => $missingAv,
            'note'  => "Computer assets with no 'Antivirus'/'Endpoint' spec set. Data completeness issue.",
        ];
        if ($noAv > 0) $issues[] = [
            'issue' => 'computers_without_antivirus',
            'severity' => 'high',
            'count' => $noAv,
            'note'  => "Computer assets explicitly marked as having NO antivirus. Security risk.",
        ];

        // 5. Employees with unusually many assets (>5)
        $overloaded = Employee::withCount('heldAssets')
            ->having('held_assets_count', '>', 5)
            ->orderByDesc('held_assets_count')
            ->limit(10)->get();
        if ($overloaded->isNotEmpty()) $issues[] = [
            'issue' => 'employees_with_many_assets',
            'severity' => 'info',
            'count' => $overloaded->count(),
            'sample'=> $overloaded->map(fn ($e) => ['name' => $e->full_name, 'count' => $e->held_assets_count])->all(),
            'note'  => "Employees holding more than 5 assets. Might be normal (IT/warehouse) or worth reviewing.",
        ];

        return [
            'total_issues' => count($issues),
            'issues'       => $issues,
        ];
    }

    private function toolEmployeeAssetSummary(array $args): array
    {
        $topN = max(1, min(30, (int) ($args['top_n'] ?? 10)));

        $top = Employee::withCount('heldAssets')
            ->having('held_assets_count', '>', 0)
            ->orderByDesc('held_assets_count')
            ->limit($topN)->get();

        $totalActive = Employee::where('status', 'active')->count();
        $withAssets  = Employee::whereHas('heldAssets')->where('status', 'active')->count();
        $withoutSample = Employee::with('department:id,name')
            ->where('status', 'active')
            ->whereDoesntHave('heldAssets')
            ->limit(15)->get();

        return [
            'total_active_employees'   => $totalActive,
            'active_with_assets'       => $withAssets,
            'active_without_assets'    => $totalActive - $withAssets,
            'top_holders'              => $top->map(fn ($e) => [
                'name'       => $e->full_name,
                'count'      => $e->held_assets_count,
                'department' => $e->department?->name,
            ])->all(),
            'without_assets_sample'    => $withoutSample->map(fn ($e) => [
                'name'       => $e->full_name,
                'department' => $e->department?->name,
                'position'   => $e->position,
            ])->all(),
        ];
    }

    private function toolAssetHistory(array $args): array
    {
        $key = trim((string) ($args['tag_or_serial'] ?? ''));
        if ($key === '') return ['error' => 'tag_or_serial required'];

        $a = Asset::with([
            'category:id,name', 'brand:id,name',
            'currentHolder:id,first_name,last_name', 'currentLocation:id,name',
            'movements' => fn ($q) => $q->with(['fromEmployee:id,first_name,last_name', 'toEmployee:id,first_name,last_name', 'fromLocation:id,name', 'toLocation:id,name'])->orderByDesc('movement_date')->limit(30),
            'partChanges' => fn ($q) => $q->orderByDesc('changed_at')->limit(30),
        ])->where('asset_tag', $key)->orWhere('serial_number', $key)->first();

        if (!$a) return ['found' => false, 'message' => "No asset with tag or serial '{$key}'"];

        return [
            'found' => true,
            'asset' => [
                'tag'      => $a->asset_tag,
                'serial'   => $a->serial_number,
                'category' => $a->category?->name,
                'brand'    => $a->brand?->name,
                'model'    => $a->model,
            ],
            'current' => [
                'status'        => $a->current_status,
                'holder'        => $a->currentHolder?->full_name,
                'location'      => $a->currentLocation?->name,
                'age'           => $a->age_formatted,
                'in_service'    => $a->service_duration_formatted,
                'warranty'      => $a->warranty_status,
                'past_lifespan' => $a->is_eligible_for_replacement,
            ],
            'movements' => $a->movements->map(fn ($m) => [
                'date'     => $m->movement_date?->format('Y-m-d'),
                'type'     => $m->type,
                'from'     => $m->fromEmployee?->full_name ?? $m->fromLocation?->name,
                'to'       => $m->toEmployee?->full_name ?? $m->toLocation?->name,
                'reference'=> $m->reference,
                'remarks'  => $m->remarks,
            ])->all(),
            'part_changes' => $a->partChanges->map(fn ($p) => [
                'date'      => $p->changed_at?->format('Y-m-d'),
                'part'      => $p->part_name,
                'from'      => $p->old_value,
                'to'        => $p->new_value,
                'reason'    => $p->reason,
                'notes'     => $p->notes,
            ])->all(),
        ];
    }

    private function toolListSpecKeys(): array
    {
        $keys = [];
        Asset::select('id', 'specifications')->chunk(500, function ($chunk) use (&$keys) {
            foreach ($chunk as $a) {
                foreach ($a->specifications ?? [] as $s) {
                    $k = is_array($s) ? trim((string) ($s['key'] ?? '')) : '';
                    if ($k !== '') $keys[$k] = ($keys[$k] ?? 0) + 1;
                }
            }
        });
        arsort($keys);
        return [
            'total_keys' => count($keys),
            'keys' => array_map(fn ($k, $c) => ['key' => $k, 'used_in_assets' => $c], array_keys($keys), array_values($keys)),
        ];
    }

    private function toolQueryBySpec(array $args): array
    {
        // Accept either spec_keys (array) or legacy spec_key (string)
        $rawKeys = $args['spec_keys'] ?? $args['spec_key'] ?? null;
        if (is_string($rawKeys)) $rawKeys = [$rawKeys];
        if (!is_array($rawKeys) || empty($rawKeys)) return ['error' => 'spec_keys required (one or more strings)'];

        $needles = array_values(array_filter(array_map(
            fn ($k) => strtolower(trim((string) $k)),
            $rawKeys
        )));
        if (empty($needles)) return ['error' => 'spec_keys must contain at least one non-empty string'];

        $filterValue = isset($args['value']) ? trim((string) $args['value']) : null;

        $groups   = []; // valueLabel => [{tag, category, holder, matched_key}]
        $noValue  = [];
        $matchedKeys = []; // Track which spec keys actually matched

        Asset::with(['currentHolder:id,first_name,last_name', 'category:id,name'])
            ->select('id', 'asset_tag', 'category_id', 'current_holder_id', 'specifications')
            ->chunk(500, function ($chunk) use (&$groups, &$noValue, &$matchedKeys, $needles, $filterValue) {
                foreach ($chunk as $a) {
                    $item = [
                        'tag'      => $a->asset_tag,
                        'category' => $a->category?->name,
                        'holder'   => $a->currentHolder?->full_name,
                    ];

                    // Find the FIRST spec entry whose key contains any of the needles.
                    $entry = null;
                    $matchedKey = null;
                    foreach ($a->specifications ?? [] as $s) {
                        if (!is_array($s)) continue;
                        $k = strtolower(trim((string) ($s['key'] ?? '')));
                        if ($k === '') continue;
                        foreach ($needles as $needle) {
                            if (str_contains($k, $needle)) {
                                $entry = $s;
                                $matchedKey = $s['key'];
                                break 2;
                            }
                        }
                    }
                    if ($matchedKey !== null) {
                        $matchedKeys[$matchedKey] = ($matchedKeys[$matchedKey] ?? 0) + 1;
                    }
                    $raw = is_array($entry) ? trim((string) ($entry['value'] ?? '')) : '';

                    if ($raw === '') {
                        $noValue[] = $item;
                        continue;
                    }
                    if ($filterValue !== null && strcasecmp($raw, $filterValue) !== 0) continue;

                    $bucket = ucfirst(strtolower($raw));
                    $groups[$bucket] ??= [];
                    $groups[$bucket][] = $item;
                }
            });

        uksort($groups, fn ($a, $b) => count($groups[$b]) <=> count($groups[$a]));
        $byValue = [];
        foreach ($groups as $val => $items) {
            $byValue[] = [
                'value'     => $val,
                'count'     => count($items),
                'sample'    => array_slice($items, 0, 25),
                'truncated' => count($items) > 25,
            ];
        }

        return [
            'searched_for'            => $needles,
            'matched_spec_keys'       => $matchedKeys, // shows which real keys were found (helps user know synonyms in use)
            'filter_value'            => $filterValue,
            'total_with_value'        => array_sum(array_map(fn ($g) => count($g), $groups)),
            'total_without_value'     => count($noValue),
            'by_value'                => $byValue,
            'without_value_sample'    => array_slice($noValue, 0, 25),
            'without_value_truncated' => count($noValue) > 25,
        ];
    }

    private function toolRecentMovements(array $args): array
    {
        $days  = (int) ($args['days'] ?? 14);
        $since = Carbon::today()->subDays($days);

        $rows = \App\Models\AssetMovement::with([
                'asset:id,asset_tag',
                'fromEmployee:id,first_name,last_name',
                'toEmployee:id,first_name,last_name',
            ])
            ->where('movement_date', '>=', $since)
            ->orderByDesc('movement_date')
            ->limit(30)->get();

        return [
            'days'      => $days,
            'count'     => $rows->count(),
            'movements' => $rows->map(fn ($m) => [
                'date'  => $m->movement_date?->format('Y-m-d'),
                'type'  => $m->type,
                'asset' => $m->asset?->asset_tag,
                'from'  => $m->fromEmployee?->full_name,
                'to'    => $m->toEmployee?->full_name,
            ])->all(),
        ];
    }
}
