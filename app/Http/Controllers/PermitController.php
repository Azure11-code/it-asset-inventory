<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetPermit;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PermitController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('perm:permits,view',   only: ['index', 'show']),
            new Middleware('perm:permits,create', only: ['create', 'store']),
            new Middleware('perm:permits,edit',   only: ['edit', 'update']),
            new Middleware('perm:permits,delete', only: ['destroy']),
            new Middleware('perm:permits,print',  only: ['docx']),
        ];
    }

    private const SORT_MAP = [
        'permit_no'    => 'asset_permits.permit_no',
        'employee'     => 'employees.last_name',
        'destination'  => 'asset_permits.destination',
        'date_borrow'  => 'asset_permits.date_borrow',
        'date_return'  => 'asset_permits.date_return',
        'status'       => 'asset_permits.status',
    ];

    public function index(Request $request)
    {
        $sortKey   = array_key_exists($request->sort, self::SORT_MAP) ? $request->sort : null;
        $direction = $request->direction === 'desc' ? 'desc' : 'asc';

        $query = AssetPermit::query()
            ->select('asset_permits.*')
            ->with(['employee:id,first_name,middle_name,last_name', 'items'])
            ->when($request->search, fn ($q, $s) =>
                $q->where(fn ($w) => $w->where('permit_no', 'like', "%{$s}%")
                                       ->orWhere('destination', 'like', "%{$s}%")
                                       ->orWhere('purpose', 'like', "%{$s}%"))
            )
            ->when($request->status, fn ($q, $s) => $q->where('asset_permits.status', $s));

        if ($sortKey === 'employee') {
            $query->leftJoin('employees', 'asset_permits.employee_id', '=', 'employees.id');
        }

        if ($sortKey) {
            $query->orderBy(self::SORT_MAP[$sortKey], $direction);
        } else {
            $query->latest('asset_permits.id');
        }

        $permits = $query
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($p) => [
                'id'          => $p->id,
                'permit_no'   => $p->permit_no,
                'employee'    => $p->employee ? ['id' => $p->employee->id, 'name' => $p->employee->full_name] : null,
                'destination' => $p->destination,
                'purpose'     => $p->purpose,
                'date_borrow' => $p->date_borrow?->format('Y-m-d'),
                'date_return' => $p->date_return?->format('Y-m-d'),
                'item_count'  => $p->items->count(),
                'status'      => $p->status,
            ]);

        return Inertia::render('Permits/Index', [
            'permits' => $permits,
            'filters' => $request->only('search', 'status', 'sort', 'direction'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Permits/Create', [
            'permit'   => null,
            'lookups'  => $this->lookups(),
            'defaults' => [
                'permit_no'   => AssetPermit::nextPermitNo(),
                'date_borrow' => now()->format('Y-m-d'),
                'date_return' => now()->addWeek()->format('Y-m-d'),
                'valid_from'  => now()->format('Y-m-d'),
                'valid_to'    => now()->addWeek()->format('Y-m-d'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $permit = DB::transaction(function () use ($data) {
            $permit = AssetPermit::create(collect($data)->except('items')->toArray());
            foreach ($data['items'] ?? [] as $i => $item) {
                $permit->items()->create([
                    'asset_id'    => $item['asset_id'] ?? null,
                    'qty'         => $item['qty'] ?? 1,
                    'unit'        => $item['unit'] ?? 'PC',
                    'description' => $item['description'],
                    'serial_no'   => $item['serial_no'] ?? null,
                    'remarks'     => $item['remarks'] ?? null,
                    'sort_order'  => $i,
                ]);
            }
            return $permit;
        });

        return redirect()->route('permits.show', $permit)->with('success', 'Permit created.');
    }

    public function show(AssetPermit $permit)
    {
        $permit->load([
            'employee.department',
            'employee.location',
            'requestedBy',
            'issuedBy', 'notedBy', 'notedBySecondary', 'approvedBy',
            'items.asset',
        ]);

        return Inertia::render('Permits/Show', [
            'permit' => $this->serialize($permit),
        ]);
    }

    public function edit(AssetPermit $permit)
    {
        $permit->load(['items.asset']);

        return Inertia::render('Permits/Create', [
            'permit'   => $this->serialize($permit, forForm: true),
            'lookups'  => $this->lookups(),
            'defaults' => null,
        ]);
    }

    public function update(Request $request, AssetPermit $permit)
    {
        $data = $this->validateData($request, $permit);

        DB::transaction(function () use ($data, $permit) {
            $permit->update(collect($data)->except('items')->toArray());
            $permit->items()->delete();
            foreach ($data['items'] ?? [] as $i => $item) {
                $permit->items()->create([
                    'asset_id'    => $item['asset_id'] ?? null,
                    'qty'         => $item['qty'] ?? 1,
                    'unit'        => $item['unit'] ?? 'PC',
                    'description' => $item['description'],
                    'serial_no'   => $item['serial_no'] ?? null,
                    'remarks'     => $item['remarks'] ?? null,
                    'sort_order'  => $i,
                ]);
            }
        });

        return redirect()->route('permits.show', $permit)->with('success', 'Permit updated.');
    }

    public function destroy(AssetPermit $permit)
    {
        $permit->delete();
        return redirect()->route('permits.index')->with('success', 'Permit removed.');
    }

    public function docx(AssetPermit $permit, \App\Services\DocxExporter $exporter)
    {
        return $exporter->permit($permit);
    }

    private function validateData(Request $request, ?AssetPermit $permit = null): array
    {
        return $request->validate([
            'permit_no'                  => ['required', 'string', 'max:50',
                                              \Illuminate\Validation\Rule::unique('asset_permits', 'permit_no')->ignore($permit?->id)],
            'employee_id'                => ['nullable', 'required_without:employee_name', 'exists:employees,id'],
            'employee_name'              => ['nullable', 'required_without:employee_id', 'string', 'max:255'],
            'position_text'              => ['nullable', 'string', 'max:255'],
            'department_text'            => ['nullable', 'string', 'max:255'],
            'destination'                => ['required', 'string', 'max:255'],
            'purpose'                    => ['required', 'string', 'max:255'],
            'date_borrow'                => ['required', 'date'],
            'date_return'                => ['required', 'date', 'after_or_equal:date_borrow'],
            'valid_from'                 => ['required', 'date'],
            'valid_to'                   => ['required', 'date', 'after_or_equal:valid_from'],
            'requested_by_employee_id'   => ['nullable', 'exists:employees,id'],
            'issued_by_user_id'          => ['nullable', 'exists:users,id'],
            'noted_by_user_id'           => ['nullable', 'exists:users,id'],
            'noted_by_secondary_user_id' => ['nullable', 'exists:users,id'],
            'approved_by_user_id'        => ['nullable', 'exists:users,id'],
            'approval_note'              => ['nullable', 'string', 'max:255'],
            'status'                     => ['required', \Illuminate\Validation\Rule::in(['draft', 'approved', 'returned', 'cancelled'])],
            'items'                      => ['required', 'array', 'min:1'],
            'items.*.asset_id'           => ['nullable', 'exists:assets,id'],
            'items.*.qty'                => ['required', 'integer', 'min:1'],
            'items.*.unit'               => ['required', 'string', 'max:20'],
            'items.*.description'        => ['required', 'string', 'max:255'],
            'items.*.serial_no'          => ['nullable', 'string', 'max:100'],
            'items.*.remarks'            => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function serialize(AssetPermit $permit, bool $forForm = false): array
    {
        $base = [
            'id'                         => $permit->id,
            'permit_no'                  => $permit->permit_no,
            'employee_id'                => $permit->employee_id,
            'employee_name'              => $permit->employee_name,
            'position_text'              => $permit->position_text,
            'department_text'            => $permit->department_text,
            'destination'                => $permit->destination,
            'purpose'                    => $permit->purpose,
            'date_borrow'                => $permit->date_borrow?->format('Y-m-d'),
            'date_return'                => $permit->date_return?->format('Y-m-d'),
            'valid_from'                 => $permit->valid_from?->format('Y-m-d'),
            'valid_to'                   => $permit->valid_to?->format('Y-m-d'),
            'requested_by_employee_id'   => $permit->requested_by_employee_id,
            'issued_by_user_id'          => $permit->issued_by_user_id,
            'noted_by_user_id'           => $permit->noted_by_user_id,
            'noted_by_secondary_user_id' => $permit->noted_by_secondary_user_id,
            'approved_by_user_id'        => $permit->approved_by_user_id,
            'approval_note'              => $permit->approval_note,
            'status'                     => $permit->status,
            'items'                      => $permit->items->map(fn ($it) => [
                'asset_id'    => $it->asset_id,
                'qty'         => $it->qty,
                'unit'        => $it->unit,
                'description' => $it->description,
                'serial_no'   => $it->serial_no,
                'remarks'     => $it->remarks,
            ])->values(),
        ];

        if ($forForm) {
            return $base;
        }

        return array_merge($base, [
            'employee'           => [
                'name'       => $permit->employee?->full_name ?? $permit->employee_name,
                'position'   => $permit->employee?->position ?? $permit->position_text,
                'department' => $permit->employee?->department?->name ?? $permit->department_text,
            ],
            'requested_by'       => $permit->requestedBy?->full_name,
            'issued_by'          => $permit->issuedBy?->name,
            'noted_by'           => $permit->notedBy?->name,
            'noted_by_secondary' => $permit->notedBySecondary?->name,
            'approved_by'        => $permit->approvedBy?->name,
            'items'              => $permit->items->map(fn ($it) => [
                'qty'         => $it->qty,
                'unit'        => $it->unit,
                'description' => $it->description,
                'serial_no'   => $it->serial_no,
                'remarks'     => $it->remarks,
            ])->values(),
        ]);
    }

    private function lookups(): array
    {
        return [
            'employees' => Employee::orderBy('last_name')
                ->get(['id', 'first_name', 'middle_name', 'last_name', 'position', 'department_id', 'location_id'])
                ->map(fn ($e) => [
                    'id'            => $e->id,
                    'name'          => $e->full_name,
                    'position'      => $e->position,
                    'department_id' => $e->department_id,
                    'location_id'   => $e->location_id,
                ])->values(),
            'users'  => User::orderBy('name')->get(['id', 'name'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values(),
            'assets' => Asset::select('id', 'asset_tag', 'serial_number', 'model', 'description')
                ->orderBy('asset_tag')
                ->get()
                ->map(fn ($a) => [
                    'id'   => $a->id,
                    'name' => $a->asset_tag . ' — ' . ($a->model ?: ($a->description ?: 'Asset')),
                    'description' => $a->description ?: $a->model,
                    'serial_no'   => $a->serial_number,
                ])->values(),
        ];
    }
}
