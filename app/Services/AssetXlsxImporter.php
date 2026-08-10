<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Condition;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class AssetXlsxImporter
{
    /** Canonical column keys in the order they appear in the built-in template. */
    public const COLUMNS = [
        'asset_tag'      => 'Asset Tag',
        'category'       => 'Category',
        'brand'          => 'Brand',
        'model'          => 'Model',
        'serial_number'  => 'Serial No.',
        'description'    => 'Description',
        'specifications' => 'Specifications',
        'purchase_date'  => 'Purchase Date',
        'deployment_date'=> 'Deployment Date',
        'purchase_cost'  => 'Purchase Cost',
        'lifespan_years' => 'Lifespan (Years)',
        'warranty_until' => 'Warranty Until',
        'condition'      => 'Condition',
        'status'         => 'Status',
        'location'       => 'Location',
        'notes'          => 'Notes',
    ];

    /** canonical key → accepted header labels (case-insensitive). */
    private const ALIASES = [
        'asset_tag'      => ['Asset Tag', 'Asset Number', 'Asset No', 'Asset No.', 'Tag', 'Tag Number'],
        'category'       => ['Category', 'Type'],
        'brand'          => ['Brand', 'Make', 'Manufacturer'],
        'model'          => ['Model', 'Model Number', 'Model No'],
        'serial_number'  => ['Serial No.', 'Serial No', 'Serial', 'Serial Number', 'S/N', 'SN'],
        'description'    => ['Description', 'Desc'],
        'specifications' => ['Specifications', 'Specs'],
        'purchase_date'  => ['Purchase Date', 'Date Purchased', 'Date Bought', 'Bought'],
        'deployment_date'=> ['Deployment Date', 'Deployed Date', 'Date Deployed'],
        'purchase_cost'  => ['Purchase Cost', 'Cost', 'Price'],
        'lifespan_years' => ['Lifespan (Years)', 'Lifespan', 'Years', 'Life Span', 'Life Span (Years)'],
        'warranty_until' => ['Warranty Until', 'Warranty End', 'Warranty Expiry'],
        'condition'      => ['Condition'],
        'status'         => ['Status'],
        'location'       => ['Location', 'Site', 'Branch'],
        'notes'          => ['Notes', 'Remarks', 'Comments'],
        'holder'         => ['Assignee', 'Holder', 'Assigned To', 'User', 'Assigned User'],
        'department'     => ['Department', 'Dept', 'Departments'],
    ];

    /**
     * Excel header → key stored under Specifications.
     * Rule: any header not matched to a canonical field gets checked here.
     * Values that come from a "(GB)" header get " GB" appended when purely numeric.
     */
    private const SPEC_ALIASES = [
        'hostname'                => 'Hostname',
        'computer name'           => 'Hostname',
        'pc name'                 => 'Hostname',
        'processor'               => 'Processor',
        'cpu'                     => 'Processor',
        'memory'                  => 'RAM',
        'memory (gb)'             => 'RAM',
        'ram'                     => 'RAM',
        'ram (gb)'                => 'RAM',
        'harddisk'                => 'Storage',
        'hard disk'               => 'Storage',
        'hard drive'              => 'Storage',
        'hdd'                     => 'Storage',
        'ssd'                     => 'Storage',
        'storage'                 => 'Storage',
        'bitdefender'             => 'Antivirus',
        'antivirus'               => 'Antivirus',
        'os / firmware version'   => 'OS Name',
        'os / firmware'           => 'OS Name',
        'os'                      => 'OS Name',
        'operating system'        => 'OS Name',
        'firmware version'        => 'Firmware Version',
        'os license'              => 'OS License',
        'ms office'               => 'MS Office',
        'ms license'              => 'MS Office License',
        'office license'          => 'MS Office License',
        'monitor size'            => 'Monitor Size',
    ];

    private const VALID_STATUSES = ['in_stock', 'assigned', 'for_repair', 'defective', 'retired', 'replaced'];

    /**
     * @return array{created:int, skipped:int, rows:array<array{row:int, tag:?string, status:'created'|'skipped', reason?:string}>}
     */
    public function import(UploadedFile $file): array
    {
        $book  = IOFactory::createReader('Xlsx')->load($file->getRealPath());
        $sheet = $book->getSheetByName('Assets') ?? $book->getActiveSheet();

        $headerRow = $this->findHeaderRow($sheet);
        if ($headerRow === null) {
            return ['created' => 0, 'skipped' => 0, 'rows' => [[
                'row' => 0, 'tag' => null, 'status' => 'skipped',
                'reason' => 'Could not find a recognizable header row. Expected columns like "Asset Tag/Number", "Category", "Brand", "Serial", etc.',
            ]]];
        }

        // Two maps we care about:
        //   - $fieldCols[canonical]      = column index (for canonical fields)
        //   - $specCols[spec_key]        = column index (for spec-mapped columns)
        // Also track "(GB)" hints so we can append units later.
        [$fieldCols, $specCols, $unitHints] = $this->buildColumnMaps($sheet, $headerRow);

        // Preload lookups
        $categories = $this->indexByLowerName(Category::query());
        $brands     = $this->indexByLowerName(Brand::query());
        $conditions  = $this->indexByLowerName(Condition::query());
        $locations   = $this->indexByLowerName(Location::query());
        $departments = $this->indexByLowerName(Department::query());
        $employees  = Employee::get(['id', 'first_name', 'middle_name', 'last_name'])
            ->mapWithKeys(fn ($e) => [strtolower($e->full_name) => $e->id]);
        $existingTags = Asset::pluck('asset_tag')->mapWithKeys(fn ($t) => [strtolower($t) => true]);

        $created = 0;
        $skipped = 0;
        $rows    = [];

        $get = function (int $col, int $r) use ($sheet) {
            $v = $sheet->getCell([$col, $r])->getValue();
            return $v;
        };
        $getField = function (string $canonical, int $r) use ($fieldCols, $get) {
            $col = $fieldCols[$canonical] ?? null;
            return $col === null ? null : $get($col, $r);
        };

        $lastRow = $sheet->getHighestDataRow();
        for ($r = $headerRow + 1; $r <= $lastRow; $r++) {
            $tag = trim((string) $getField('asset_tag', $r));
            if ($tag === '') continue;
            if (str_starts_with(strtoupper($tag), 'EX-')) continue;

            if (isset($existingTags[strtolower($tag)])) {
                $rows[] = ['row' => $r, 'tag' => $tag, 'status' => 'skipped', 'reason' => 'Asset Tag already exists.'];
                $skipped++;
                continue;
            }

            // Category is required
            $categoryName = trim((string) $getField('category', $r));
            if ($categoryName === '') {
                $rows[] = ['row' => $r, 'tag' => $tag, 'status' => 'skipped', 'reason' => 'Category is required.'];
                $skipped++;
                continue;
            }
            $categoryId = $categories[strtolower($categoryName)] ?? null;
            if ($categoryId === null) {
                $rows[] = ['row' => $r, 'tag' => $tag, 'status' => 'skipped',
                    'reason' => "Unknown Category: \"{$categoryName}\". Create it in Master Data first."];
                $skipped++;
                continue;
            }

            $brandId     = $this->resolveLookup($getField('brand',     $r), $brands,     'Brand',     $r, $tag, $rows, $skipped);
            if ($brandId === false)     continue;
            $conditionId = $this->resolveLookup($getField('condition', $r), $conditions, 'Condition', $r, $tag, $rows, $skipped);
            if ($conditionId === false) continue;
            $locationId   = $this->resolveLookup($getField('location',   $r), $locations,   'Location',   $r, $tag, $rows, $skipped);
            if ($locationId === false)   continue;
            $departmentId = $this->resolveLookup($getField('department', $r), $departments, 'Department', $r, $tag, $rows, $skipped);
            if ($departmentId === false) continue;

            // Holder (Assignee) — new
            $holderRaw = trim((string) $getField('holder', $r));
            $holderId  = null;
            if ($holderRaw !== '') {
                $holderId = $employees[strtolower($holderRaw)] ?? null;
                if ($holderId === null) {
                    $rows[] = ['row' => $r, 'tag' => $tag, 'status' => 'skipped',
                        'reason' => "Unknown Assignee: \"{$holderRaw}\". Create the employee first, or check spelling."];
                    $skipped++;
                    continue;
                }
            }

            // Status — explicit column wins, otherwise auto-derive from holder
            $statusRaw = trim((string) $getField('status', $r));
            if ($statusRaw === '') {
                $status = $holderId ? 'assigned' : 'in_stock';
            } else {
                $status = str_replace(' ', '_', strtolower($statusRaw));
                if (!in_array($status, self::VALID_STATUSES, true)) {
                    $rows[] = ['row' => $r, 'tag' => $tag, 'status' => 'skipped',
                        'reason' => "Invalid Status \"{$statusRaw}\". Allowed: " . implode(', ', self::VALID_STATUSES)];
                    $skipped++;
                    continue;
                }
            }

            // Dates
            try {
                $purchaseDate   = $this->parseDate($getField('purchase_date',   $r));
                $deploymentDate = $this->parseDate($getField('deployment_date', $r));
                $warrantyUntil  = $this->parseDate($getField('warranty_until',  $r));
            } catch (\Throwable $e) {
                $rows[] = ['row' => $r, 'tag' => $tag, 'status' => 'skipped', 'reason' => 'Invalid date value: ' . $e->getMessage()];
                $skipped++;
                continue;
            }

            $lifespan = (int) ($getField('lifespan_years', $r) ?: 5);
            if ($lifespan < 1 || $lifespan > 30) $lifespan = 5;

            if (!$warrantyUntil && $purchaseDate) {
                $warrantyUntil = Carbon::parse($purchaseDate)->addYears($lifespan)->format('Y-m-d');
            }

            $purchaseCostRaw = $getField('purchase_cost', $r);
            $purchaseCost    = is_numeric($purchaseCostRaw) ? (float) $purchaseCostRaw : null;

            // Specifications — either a single "Specifications" column (existing behavior)
            // or auto-composed from spec-mapped columns (Hostname, Processor, RAM, ...).
            $specs = $this->parseSpecs($getField('specifications', $r));
            if (empty($specs)) {
                $specs = $this->composeSpecsFromColumns($r, $sheet, $specCols, $unitHints);
            }

            Asset::create([
                'asset_tag'               => $tag,
                'serial_number'           => $this->str($getField('serial_number', $r)),
                'model'                   => $this->str($getField('model',         $r)),
                'description'             => $this->str($getField('description',   $r)),
                'specifications'          => $specs,
                'brand_id'                => $brandId,
                'category_id'             => $categoryId,
                'condition_id'            => $conditionId,
                'purchase_date'           => $purchaseDate,
                'deployment_date'         => $deploymentDate,
                'purchase_cost'           => $purchaseCost,
                'expected_lifespan_years' => $lifespan,
                'warranty_until'          => $warrantyUntil,
                'current_status'          => $status,
                'current_holder_id'       => $holderId,
                'current_location_id'     => $locationId,
                'department_id'           => $departmentId,
                'notes'                   => $this->str($getField('notes', $r)),
            ]);

            $existingTags[strtolower($tag)] = true;
            $rows[] = ['row' => $r, 'tag' => $tag, 'status' => 'created'];
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped, 'rows' => $rows];
    }

    /** Locate the header row: scan first 20 rows, pick the one with the most recognized header labels (min 3). */
    private function findHeaderRow($sheet): ?int
    {
        $highest = min($sheet->getHighestRow(), 20);
        $highestCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $best = ['row' => null, 'matches' => 0];

        $allAliases = collect(self::ALIASES)->flatten()->map(fn ($s) => strtolower($s))->all();
        $allSpecs   = array_keys(self::SPEC_ALIASES);
        $recognized = array_flip(array_unique(array_merge($allAliases, $allSpecs)));

        for ($r = 1; $r <= $highest; $r++) {
            $matches = 0;
            for ($c = 1; $c <= $highestCol; $c++) {
                $v = strtolower(trim((string) $sheet->getCell([$c, $r])->getValue()));
                if ($v !== '' && isset($recognized[$v])) $matches++;
            }
            if ($matches > $best['matches']) $best = ['row' => $r, 'matches' => $matches];
        }
        return ($best['matches'] >= 3) ? $best['row'] : null;
    }

    /**
     * Build the header → column maps.
     * @return array{0: array<string,int>, 1: array<string,int>, 2: array<string,bool>}
     *         [fieldCols, specCols, unitHints (spec_key => appendGb?)]
     */
    private function buildColumnMaps($sheet, int $headerRow): array
    {
        // reverse alias index: label(lower) → canonical
        $canonicalByLabel = [];
        foreach (self::ALIASES as $canonical => $labels) {
            foreach ($labels as $l) $canonicalByLabel[strtolower($l)] = $canonical;
        }

        $fieldCols = [];
        $specCols  = [];
        $unitHints = [];

        $highestCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        for ($c = 1; $c <= $highestCol; $c++) {
            $label = trim((string) $sheet->getCell([$c, $headerRow])->getValue());
            if ($label === '') continue;
            $low = strtolower($label);

            if (isset($canonicalByLabel[$low])) {
                $fieldCols[$canonicalByLabel[$low]] = $c;
                continue;
            }
            if (isset(self::SPEC_ALIASES[$low])) {
                $key = self::SPEC_ALIASES[$low];
                $specCols[$key] = $c;
                if (preg_match('/\(gb\)|gb\s*$/i', $label)) $unitHints[$key] = true;
                continue;
            }
            // Silently ignored: CONTEXT_HEADERS + anything else
        }
        return [$fieldCols, $specCols, $unitHints];
    }

    /** Auto-build a specifications array from the row's spec-mapped columns. */
    private function composeSpecsFromColumns(int $r, $sheet, array $specCols, array $unitHints): ?array
    {
        $out = [];
        foreach ($specCols as $key => $col) {
            $raw = $sheet->getCell([$col, $r])->getValue();
            $val = trim((string) ($raw ?? ''));
            if ($val === '') continue;
            if (!empty($unitHints[$key]) && is_numeric($val)) {
                $val = rtrim(rtrim($val, '0'), '.') . ' GB';
                // preserve integer form if user wrote "8"
                if (str_ends_with($val, '. GB')) $val = str_replace('. GB', ' GB', $val);
            }
            $out[] = ['key' => $key, 'value' => $val];
        }
        return $out ?: null;
    }

    private function indexByLowerName($query)
    {
        return $query->pluck('id', 'name')->mapWithKeys(fn ($id, $n) => [strtolower($n) => $id]);
    }

    /** Returns id (int), null when blank, or false when invalid (row is aborted). */
    private function resolveLookup($raw, $map, string $label, int $r, string $tag, array &$rows, int &$skipped)
    {
        $name = trim((string) $raw);
        if ($name === '') return null;
        $id = $map[strtolower($name)] ?? null;
        if ($id === null) {
            $rows[] = ['row' => $r, 'tag' => $tag, 'status' => 'skipped',
                'reason' => "Unknown {$label}: \"{$name}\". Create it in Master Data first."];
            $skipped++;
            return false;
        }
        return $id;
    }

    private function str($v): ?string
    {
        $s = trim((string) ($v ?? ''));
        return $s === '' ? null : $s;
    }

    private function parseDate($v): ?string
    {
        if ($v === null || $v === '') return null;
        if (is_numeric($v)) {
            return ExcelDate::excelToDateTimeObject((float) $v)->format('Y-m-d');
        }
        return Carbon::parse((string) $v)->format('Y-m-d');
    }

    /** Parses "key: value\nkey: value" back into the app's [{key,value}] shape. */
    private function parseSpecs($v): ?array
    {
        $s = trim((string) ($v ?? ''));
        if ($s === '') return null;
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $s) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (str_contains($line, ':')) {
                [$k, $val] = array_map('trim', explode(':', $line, 2));
                $out[] = ['key' => $k, 'value' => $val];
            } else {
                $out[] = ['key' => '', 'value' => $line];
            }
        }
        return $out ?: null;
    }
}
