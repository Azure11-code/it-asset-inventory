<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Condition;
use App\Models\Employee;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AssetController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('perm:assets,view',   only: ['index', 'show']),
            new Middleware('perm:assets,create', only: ['create', 'store', 'bulkReceiveForm', 'bulkStore']),
            new Middleware('perm:assets,edit',   only: ['edit', 'update']),
            new Middleware('perm:assets,delete', only: ['destroy']),
            new Middleware('perm:assets,export', only: ['export']),
            new Middleware('perm:assets,import', only: ['import', 'importTemplate']),
        ];
    }

    private const SORT_MAP = [
        'asset_tag'      => 'assets.asset_tag',
        'category'       => 'categories.name',
        'serial_number'  => 'assets.serial_number',
        'current_status' => 'assets.current_status',
        'holder'         => 'employees.last_name',
        'purchase_date'  => 'assets.purchase_date',
        'warranty_until' => 'assets.warranty_until',
    ];

    public function index(Request $request)
    {
        $sortKey   = array_key_exists($request->sort, self::SORT_MAP) ? $request->sort : null;
        $direction = $request->direction === 'desc' ? 'desc' : 'asc';

        $query = Asset::query()
            ->select('assets.*')
            ->with([
                'brand:id,name',
                'category:id,name,prefix',
                'currentHolder:id,first_name,middle_name,last_name',
                'currentLocation:id,name',
            ])
            ->when($request->search, fn ($q, $s) => $q->search($s))

            ->when($request->status, fn ($q, $s) => $q->where('current_status', $s))
            ->when($request->category_id, fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->brand_id, fn ($q, $id) => $q->where('brand_id', $id))
            ->when($request->warranty, function ($q, $w) {
                if ($w === 'active')        $q->whereDate('warranty_until', '>', now()->addDays(90));
                if ($w === 'expiring_soon') $q->whereBetween('warranty_until', [now(), now()->addDays(90)]);
                if ($w === 'expired')       $q->whereDate('warranty_until', '<', now());
            });

        if ($sortKey === 'category') {
            $query->leftJoin('categories', 'assets.category_id', '=', 'categories.id');
        }
        if ($sortKey === 'holder') {
            $query->leftJoin('employees', 'assets.current_holder_id', '=', 'employees.id');
        }

        if ($sortKey) {
            $query->orderBy(self::SORT_MAP[$sortKey], $direction);
        } else {
            $query->latest('assets.id');
        }

        $assets = $query
            ->paginate(10)
            ->withQueryString()
            ->through(fn ($a) => [
                'id'              => $a->id,
                'asset_tag'       => $a->asset_tag,
                'serial_number'   => $a->serial_number,
                'model'           => $a->model,
                'brand'           => $a->brand ? ['name' => $a->brand->name] : null,
                'category'        => $a->category ? ['name' => $a->category->name] : null,
                'current_status'  => $a->current_status,
                'current_holder'  => $a->currentHolder ? ['full_name' => $a->currentHolder->full_name] : null,
                'current_location'=> $a->currentLocation ? ['name' => $a->currentLocation->name] : null,
                'purchase_date'   => $a->purchase_date?->format('Y-m-d'),
                'deployment_date' => $a->deployment_date?->format('Y-m-d'),
                'warranty_until'  => $a->warranty_until?->format('Y-m-d'),
                'warranty_status' => $a->warranty_status,
                'age_years'       => $a->age_years,
                'age_formatted'              => $a->age_formatted,
                'service_duration_formatted' => $a->service_duration_formatted,
                'is_eligible_for_replacement' => $a->is_eligible_for_replacement,
            ]);

        return Inertia::render('Assets/Index', [
            'assets'  => $assets,
            'lookups' => [
                'categories' => Category::select('id', 'name')->orderBy('name')->get(),
                'brands'     => Brand::select('id', 'name')->orderBy('name')->get(),
            ],
            'filters' => $request->only('search', 'status', 'category_id', 'brand_id', 'warranty', 'sort', 'direction'),
        ]);
    }

    public function importTemplate(\App\Services\AssetImportTemplate $tpl)
    {
        return $tpl->stream();
    }

    public function import(Request $request, \App\Services\AssetXlsxImporter $importer)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'], // 5 MB
        ]);

        $result = $importer->import($request->file('file'));

        $msg = sprintf(
            '%d asset%s created%s',
            $result['created'],
            $result['created'] === 1 ? '' : 's',
            $result['skipped'] > 0 ? ", {$result['skipped']} row(s) skipped" : ''
        );

        // Only surface the first 15 skip reasons so the flash message doesn't overflow
        $skipReasons = collect($result['rows'])
            ->where('status', 'skipped')
            ->take(15)
            ->map(fn ($r) => "Row {$r['row']}" . ($r['tag'] ? " ({$r['tag']})" : '') . ": {$r['reason']}")
            ->all();

        return redirect()->route('assets.index')
            ->with('success', $msg)
            ->with('import_skips', $skipReasons);
    }

    public function export(Request $request, \App\Services\AssetXlsxExporter $exporter)
    {
        $sortKey   = array_key_exists($request->sort, self::SORT_MAP) ? $request->sort : null;
        $direction = $request->direction === 'desc' ? 'desc' : 'asc';

        $query = Asset::query()
            ->select('assets.*')
            ->when($request->search, fn ($q, $s) => $q->search($s))

            ->when($request->status, fn ($q, $s) => $q->where('current_status', $s))
            ->when($request->category_id, fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->brand_id, fn ($q, $id) => $q->where('brand_id', $id))
            ->when($request->warranty, function ($q, $w) {
                if ($w === 'active')        $q->whereDate('warranty_until', '>', now()->addDays(90));
                if ($w === 'expiring_soon') $q->whereBetween('warranty_until', [now(), now()->addDays(90)]);
                if ($w === 'expired')       $q->whereDate('warranty_until', '<', now());
            });

        if ($sortKey === 'category') {
            $query->leftJoin('categories', 'assets.category_id', '=', 'categories.id');
        }
        if ($sortKey === 'holder') {
            $query->leftJoin('employees', 'assets.current_holder_id', '=', 'employees.id');
        }
        if ($sortKey) {
            $query->orderBy(self::SORT_MAP[$sortKey], $direction);
        } else {
            $query->latest('assets.id');
        }

        $filename = 'assets-' . now()->format('Ymd-His') . '.xlsx';
        return $exporter->stream($query, $filename);
    }

    public function create()
    {
        return Inertia::render('Assets/Create', [
            'lookups' => $this->lookups(),
            'defaults' => [
                'expected_lifespan_years' => 5,
                'condition'      => 'new',
                'current_status' => 'in_stock',
                'purchase_date'  => now()->format('Y-m-d'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data = $this->applyWarrantyDefault($data);

        Asset::create($data);

        return redirect()->route('assets.index')->with('success', 'Asset created.');
    }

    public function show(Asset $asset)
    {
        $asset->load([
            'brand:id,name', 'category:id,name,prefix',
            'condition:id,name,tone',
            'currentHolder:id,first_name,middle_name,last_name,department_id',
            'currentHolder.department:id,name',
            'currentLocation:id,name',
            'movements.fromEmployee:id,first_name,last_name',
            'movements.toEmployee:id,first_name,last_name',
            'movements.fromLocation:id,name',
            'movements.toLocation:id,name',
            'movements.performer:id,name',
            'partChanges.performer:id,name',
            'partChanges.incidentReport:id,ir_no',
            'partChanges.recommendation:id,doc_no',
        ]);

        // Latest assignment movement (issuance or transfer) that brought the
        // asset to its current holder — used for "assigned since" display.
        $latestAssignment = $asset->current_holder_id
            ? $asset->movements
                ->whereIn('type', ['issuance', 'transfer'])
                ->where('to_employee_id', $asset->current_holder_id)
                ->sortByDesc('movement_date')
                ->first()
            : null;

        return Inertia::render('Assets/Show', [
            'lookups' => [
                'employees' => Employee::where('status', 'active')
                                ->orderBy('last_name')
                                ->get(['id', 'first_name', 'middle_name', 'last_name'])
                                ->map(fn ($e) => ['id' => $e->id, 'name' => $e->full_name])
                                ->values(),
                'locations'  => Location::select('id', 'name')->orderBy('name')->get(),
                'conditions' => Condition::where('is_active', true)
                                ->select('id', 'name', 'tone')
                                ->orderBy('sort_order')
                                ->get(),
                'users'      => \App\Models\User::select('id', 'name')->orderBy('name')->get(),
                'incident_reports' => \App\Models\IncidentReport::where('asset_id', $asset->id)
                                ->whereIn('status', ['approved', 'submitted', 'draft'])
                                ->orderByDesc('report_date')
                                ->get(['id', 'ir_no', 'reported_problem'])
                                ->map(fn ($r) => ['id' => $r->id, 'name' => "{$r->ir_no} — {$r->reported_problem}"])
                                ->values(),
                'recommendations'  => \App\Models\Recommendation::where('asset_id', $asset->id)
                                ->whereIn('status', ['approved', 'submitted', 'draft'])
                                ->orderByDesc('report_date')
                                ->get(['id', 'doc_no', 'subject'])
                                ->map(fn ($r) => ['id' => $r->id, 'name' => "{$r->doc_no} — {$r->subject}"])
                                ->values(),
            ],
            'asset' => [
                'id'              => $asset->id,
                'asset_tag'       => $asset->asset_tag,
                'serial_number'   => $asset->serial_number,
                'model'           => $asset->model,
                'description'     => $asset->description,
                'specifications'  => $asset->specifications,
                'brand'           => $asset->brand,
                'category'        => $asset->category,
                'purchase_date'   => $asset->purchase_date?->format('Y-m-d'),
                'deployment_date' => $asset->deployment_date?->format('Y-m-d'),
                'purchase_cost'   => $asset->purchase_cost,
                'vendor'          => $asset->vendor,
                'expected_lifespan_years' => $asset->expected_lifespan_years,
                'warranty_until'  => $asset->warranty_until?->format('Y-m-d'),
                'condition'       => $asset->condition ? ['id' => $asset->condition->id, 'name' => $asset->condition->name, 'tone' => $asset->condition->tone] : null,
                'current_status'  => $asset->current_status,
                'current_holder'  => $asset->currentHolder ? [
                    'id'         => $asset->currentHolder->id,
                    'full_name'  => $asset->currentHolder->full_name,
                    'department' => $asset->currentHolder->department?->name,
                ] : null,
                'current_location'=> $asset->currentLocation,
                'assigned_since'  => $latestAssignment?->movement_date?->format('Y-m-d'),
                'is_first_issuance' => $latestAssignment
                    ? ($asset->deployment_date && $latestAssignment->movement_date?->equalTo($asset->deployment_date))
                    : false,
                'notes'           => $asset->notes,
                'age_years'       => $asset->age_years,
                'age_formatted'              => $asset->age_formatted,
                'service_duration_formatted' => $asset->service_duration_formatted,
                'warranty_status' => $asset->warranty_status,
                'is_eligible_for_replacement' => $asset->is_eligible_for_replacement,
                'part_changes'    => $asset->partChanges->map(fn ($pc) => [
                    'id'                 => $pc->id,
                    'part_name'          => $pc->part_name,
                    'old_value'          => $pc->old_value,
                    'new_value'          => $pc->new_value,
                    'reason'             => $pc->reason,
                    'changed_at'         => $pc->changed_at?->format('Y-m-d'),
                    'performer'          => $pc->performer ? ['id' => $pc->performer->id, 'name' => $pc->performer->name] : null,
                    'notes'              => $pc->notes,
                    'incident_report_id' => $pc->incident_report_id,
                    'incident_report'    => $pc->incidentReport ? ['id' => $pc->incidentReport->id, 'ir_no' => $pc->incidentReport->ir_no] : null,
                    'recommendation_id'  => $pc->recommendation_id,
                    'recommendation'     => $pc->recommendation ? ['id' => $pc->recommendation->id, 'doc_no' => $pc->recommendation->doc_no] : null,
                ]),
                'movements'       => $asset->movements->map(fn ($m) => [
                    'id'             => $m->id,
                    'type'           => $m->type,
                    'movement_date'  => $m->movement_date?->format('Y-m-d'),
                    'from_employee'  => $m->fromEmployee ? ['id' => $m->fromEmployee->id, 'full_name' => $m->fromEmployee->full_name] : null,
                    'to_employee'    => $m->toEmployee   ? ['id' => $m->toEmployee->id,   'full_name' => $m->toEmployee->full_name]   : null,
                    'from_location'  => $m->fromLocation,
                    'to_location'    => $m->toLocation,
                    'performer'      => $m->performer ? ['name' => $m->performer->name] : null,
                    'reference'      => $m->reference,
                    'remarks'        => $m->remarks,
                    'created_at'     => $m->created_at?->format('Y-m-d H:i'),
                    'updated_at'     => $m->updated_at?->format('Y-m-d H:i'),
                ]),
            ],
        ]);
    }

    public function edit(Asset $asset)
    {
        return Inertia::render('Assets/Edit', [
            'asset' => [
                'id'                       => $asset->id,
                'asset_tag'                => $asset->asset_tag,
                'serial_number'            => $asset->serial_number,
                'model'                    => $asset->model,
                'description'              => $asset->description,
                'specifications'           => $asset->specifications,
                'brand_id'                 => $asset->brand_id,
                'category_id'              => $asset->category_id,
                'purchase_date'            => $asset->purchase_date?->format('Y-m-d'),
                'deployment_date'          => $asset->deployment_date?->format('Y-m-d'),
                'purchase_cost'            => $asset->purchase_cost,
                'vendor'                   => $asset->vendor,
                'expected_lifespan_years'  => $asset->expected_lifespan_years,
                'warranty_until'           => $asset->warranty_until?->format('Y-m-d'),
                'condition_id'             => $asset->condition_id,
                'current_status'           => $asset->current_status,
                'current_holder_id'        => $asset->current_holder_id,
                'current_location_id'      => $asset->current_location_id,
                'notes'                    => $asset->notes,
            ],
            'lookups' => $this->lookups(),
        ]);
    }

    public function update(Request $request, Asset $asset)
    {
        $data = $this->validateData($request, $asset);

        $asset->update($data);

        return redirect()->route('assets.show', $asset)->with('success', 'Asset updated.');
    }

    public function destroy(Asset $asset)
    {
        $asset->delete();

        return redirect()->route('assets.index')->with('success', 'Asset removed.');
    }

    public function bulkReceiveForm()
    {
        return Inertia::render('Assets/BulkReceive', [
            'lookups' => $this->lookups(),
            'defaults' => [
                'purchase_date'           => now()->format('Y-m-d'),
                'expected_lifespan_years' => 5,
                'condition'               => 'new',
                'quantity'                => 1,
                'start_number'            => 1,
            ],
        ]);
    }

    public function bulkStore(Request $request)
    {
        $data = $request->validate([
            'category_id'             => ['required', 'exists:categories,id'],
            'brand_id'                => ['nullable', 'exists:brands,id'],
            'model'                   => ['nullable', 'string', 'max:100'],
            'description'             => ['nullable', 'string', 'max:255'],
            'purchase_date'           => ['nullable', 'date'],
            'purchase_cost'           => ['nullable', 'numeric', 'min:0'],
            'expected_lifespan_years' => ['required', 'integer', 'min:1', 'max:30'],
            'condition_id'            => ['nullable', 'exists:conditions,id'],
            'current_location_id'     => ['nullable', 'exists:locations,id'],
            'tag_prefix'              => ['required', 'string', 'max:20'],
            'start_number'            => ['required', 'integer', 'min:0'],
            'quantity'                => ['required', 'integer', 'min:1', 'max:500'],
            'notes'                   => ['nullable', 'string'],
        ]);

        $warrantyUntil = $data['purchase_date']
            ? Carbon::parse($data['purchase_date'])->addYears($data['expected_lifespan_years'])->format('Y-m-d')
            : null;

        $created = 0;
        $duplicates = [];

        DB::transaction(function () use ($data, $warrantyUntil, &$created, &$duplicates) {
            for ($i = 0; $i < $data['quantity']; $i++) {
                $num = $data['start_number'] + $i;
                $tag = $data['tag_prefix'] . str_pad($num, 3, '0', STR_PAD_LEFT);

                if (Asset::where('asset_tag', $tag)->exists()) {
                    $duplicates[] = $tag;
                    continue;
                }

                Asset::create([
                    'asset_tag'               => $tag,
                    'category_id'             => $data['category_id'],
                    'brand_id'                => $data['brand_id'] ?? null,
                    'model'                   => $data['model'] ?? null,
                    'description'             => $data['description'] ?? null,
                    'purchase_date'           => $data['purchase_date'] ?? null,
                    'purchase_cost'           => $data['purchase_cost'] ?? null,
                    'expected_lifespan_years' => $data['expected_lifespan_years'],
                    'warranty_until'          => $warrantyUntil,
                    'condition_id'            => $data['condition_id'] ?? null,
                    'current_status'          => 'in_stock',
                    'current_location_id'     => $data['current_location_id'] ?? null,
                    'notes'                   => $data['notes'] ?? null,
                ]);
                $created++;
            }
        });

        $msg = "{$created} asset" . ($created === 1 ? '' : 's') . ' created.';
        if (!empty($duplicates)) {
            $msg .= ' Skipped duplicate tag' . (count($duplicates) === 1 ? '' : 's') . ': ' . implode(', ', $duplicates);
        }

        return redirect()->route('assets.index')->with('success', $msg);
    }

    private function validateData(Request $request, ?Asset $asset = null): array
    {
        return $request->validate([
            'asset_tag'              => ['required', 'string', 'max:100', Rule::unique('assets', 'asset_tag')->ignore($asset?->id)],
            'serial_number'          => ['nullable', 'string', 'max:100'],
            'model'                  => ['nullable', 'string', 'max:100'],
            'description'            => ['nullable', 'string', 'max:255'],
            'specifications'         => ['nullable', 'array'],
            'specifications.*.key'   => ['nullable', 'string', 'max:100'],
            'specifications.*.value' => ['nullable', 'string', 'max:500'],
            'brand_id'               => ['nullable', 'exists:brands,id'],
            'category_id'            => ['nullable', 'exists:categories,id'],
            'purchase_date'          => ['nullable', 'date'],
            'deployment_date'        => ['nullable', 'date'],
            'purchase_cost'          => ['nullable', 'numeric', 'min:0'],
            'vendor'                 => ['nullable', 'string', 'max:255'],
            'expected_lifespan_years'=> ['required', 'integer', 'min:1', 'max:30'],
            'warranty_until'         => ['nullable', 'date'],
            'condition_id'           => ['nullable', 'exists:conditions,id'],
            'current_status'         => ['required', Rule::in(['in_stock', 'assigned', 'for_repair', 'defective', 'retired', 'replaced'])],
            'current_holder_id'      => ['nullable', 'exists:employees,id'],
            'current_location_id'    => ['nullable', 'exists:locations,id'],
            'notes'                  => ['nullable', 'string'],
        ]);
    }

    private function applyWarrantyDefault(array $data): array
    {
        if (empty($data['warranty_until']) && !empty($data['purchase_date'])) {
            $data['warranty_until'] = Carbon::parse($data['purchase_date'])
                ->addYears($data['expected_lifespan_years'] ?? 5)
                ->format('Y-m-d');
        }
        return $data;
    }

    private function lookups(): array
    {
        return [
            'brands'     => Brand::select('id', 'name')->orderBy('name')->get(),
            'categories' => Category::select('id', 'name', 'prefix')->orderBy('name')->get(),
            'conditions' => Condition::where('is_active', true)
                                ->select('id', 'name', 'tone')
                                ->orderBy('sort_order')
                                ->get(),
            'employees'  => Employee::where('status', 'active')
                                ->orderBy('last_name')
                                ->get(['id', 'first_name', 'middle_name', 'last_name'])
                                ->map(fn ($e) => ['id' => $e->id, 'name' => $e->full_name])
                                ->values(),
            'locations'  => Location::select('id', 'name')->orderBy('name')->get(),
            'code_rules' => \App\Models\AssetCodeRule::where('is_active', true)
                                ->orderBy('sort_order')->orderBy('prefix_start')
                                ->get(['id', 'label', 'prefix_start', 'prefix_end', 'category_id'])
                                ->map(fn ($r) => [
                                    'id'           => $r->id,
                                    'name'         => "{$r->label}  ({$r->prefix_start}–{$r->prefix_end})",
                                    'category_id'  => $r->category_id,
                                ])->values(),
        ];
    }
}
