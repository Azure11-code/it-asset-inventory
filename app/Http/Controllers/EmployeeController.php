<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Services\AccountabilityDocxGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('perm:employees,view',       only: ['index', 'assets', 'show']),
            new Middleware('perm:employees,create',     only: ['store']),
            new Middleware('perm:employees,edit',       only: ['update']),
            new Middleware('perm:employees,delete',     only: ['destroy']),
            new Middleware('perm:accountability,print', only: ['accountability']),
        ];
    }

    private const SORT_MAP = [
        'employee'   => 'employees.last_name',
        'position'   => 'employees.position',
        'department' => 'departments.name',
        'location'   => 'locations.name',
        'status'     => 'employees.status',
    ];

    public function index(Request $request)
    {
        $sortKey   = array_key_exists($request->sort, self::SORT_MAP) ? $request->sort : 'employee';
        $sortCol   = self::SORT_MAP[$sortKey];
        $direction = $request->direction === 'desc' ? 'desc' : 'asc';

        $employees = Employee::query()
            ->select('employees.*')
            ->leftJoin('departments', 'employees.department_id', '=', 'departments.id')
            ->leftJoin('locations', 'employees.location_id', '=', 'locations.id')
            ->with(['department:id,name', 'location:id,name'])
            ->withCount('heldAssets')
            ->when($request->search, fn ($q, $search) =>
                $q->where(fn ($w) => $w->where('first_name', 'like', "%{$search}%")
                                       ->orWhere('last_name', 'like', "%{$search}%")
                                       ->orWhere('employee_no', 'like', "%{$search}%")
                                       ->orWhere('email', 'like', "%{$search}%"))
            )
            ->when($request->department_id, fn ($q, $id) => $q->where('department_id', $id))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderBy($sortCol, $direction)
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Employees/Index', [
            'employees'   => $employees,
            'departments' => Department::select('id', 'name')->orderBy('name')->get(),
            'locations'   => Location::select('id', 'name')->orderBy('name')->get(),
            'filters'     => $request->only('search', 'department_id', 'status', 'sort', 'direction'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        Employee::create($data);

        return back()->with('success', 'Employee created.');
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $this->validateData($request, $employee);

        $employee->update($data);

        return back()->with('success', 'Employee updated.');
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();

        return back()->with('success', 'Employee deleted.');
    }

    public function accountability(Employee $employee, AccountabilityDocxGenerator $generator): StreamedResponse
    {
        return $generator->generate($employee);
    }

    /**
     * Rich employee profile page — everything linked to this person
     * (held assets, movement history, permits, incidents, recommendations,
     * accountability signed files).
     */
    public function show(Employee $employee)
    {
        $employee->load([
            'department:id,name',
            'location:id,name',
            'attachments.uploader:id,name',
        ]);

        $heldAssets = $employee->heldAssets()
            ->with(['brand:id,name', 'category:id,name', 'currentLocation:id,name'])
            ->orderBy('asset_tag')
            ->get()
            ->map(fn ($a) => [
                'id'               => $a->id,
                'asset_tag'        => $a->asset_tag,
                'category'         => $a->category?->name,
                'brand'            => $a->brand?->name,
                'model'            => $a->model,
                'serial_number'    => $a->serial_number,
                'current_status'   => $a->current_status,
                'current_location' => $a->currentLocation?->name,
                'deployment_date'  => $a->deployment_date?->format('Y-m-d'),
            ]);

        $movements = \App\Models\AssetMovement::query()
            ->where(fn ($q) => $q->where('to_employee_id', $employee->id)
                                 ->orWhere('from_employee_id', $employee->id))
            ->with([
                'asset:id,asset_tag',
                'fromEmployee:id,first_name,middle_name,last_name',
                'toEmployee:id,first_name,middle_name,last_name',
            ])
            ->latest('movement_date')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn ($m) => [
                'id'            => $m->id,
                'type'          => $m->type,
                'movement_date' => $m->movement_date?->format('Y-m-d'),
                'asset_id'      => $m->asset_id,
                'asset_tag'     => $m->asset?->asset_tag,
                'from_employee' => $m->fromEmployee?->full_name,
                'to_employee'   => $m->toEmployee?->full_name,
                'is_incoming'   => $m->to_employee_id === $employee->id,
            ]);

        $permits = \App\Models\AssetPermit::query()
            ->where(fn ($q) => $q->where('employee_id', $employee->id)
                                 ->orWhere('requested_by_employee_id', $employee->id))
            ->withCount('items')
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn ($p) => [
                'id'          => $p->id,
                'permit_no'   => $p->permit_no,
                'destination' => $p->destination,
                'purpose'     => $p->purpose,
                'date_borrow' => $p->date_borrow?->format('Y-m-d'),
                'date_return' => $p->date_return?->format('Y-m-d'),
                'status'      => $p->status,
                'item_count'  => $p->items_count,
                'role'        => $p->employee_id === $employee->id ? 'borrower' : 'requestor',
            ]);

        $incidents = \App\Models\IncidentReport::query()
            ->where('end_user_employee_id', $employee->id)
            ->with(['asset:id,asset_tag'])
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn ($i) => [
                'id'               => $i->id,
                'ir_no'            => $i->ir_no,
                'reported_problem' => $i->reported_problem,
                'report_date'      => $i->report_date?->format('Y-m-d'),
                'status'           => $i->status,
                'asset_tag'        => $i->asset?->asset_tag,
                'asset_id'         => $i->asset_id,
            ]);

        $recommendations = \App\Models\Recommendation::query()
            ->where('requestor_employee_id', $employee->id)
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn ($r) => [
                'id'          => $r->id,
                'doc_no'      => $r->doc_no,
                'subject'     => $r->subject,
                'report_date' => $r->report_date?->format('Y-m-d'),
                'status'      => $r->status,
            ]);

        return Inertia::render('Employees/Show', [
            'employee' => [
                'id'            => $employee->id,
                'employee_no'   => $employee->employee_no,
                'first_name'    => $employee->first_name,
                'middle_name'   => $employee->middle_name,
                'last_name'     => $employee->last_name,
                'full_name'     => $employee->full_name,
                'email'         => $employee->email,
                'contact_no'    => $employee->contact_no,
                'position'      => $employee->position,
                'department'    => $employee->department?->name,
                'location'      => $employee->location?->name,
                'date_hired'    => $employee->date_hired?->format('Y-m-d'),
                'date_resigned' => $employee->date_resigned?->format('Y-m-d'),
                'status'        => $employee->status,
                'notes'         => $employee->notes,
            ],
            'held_assets'     => $heldAssets,
            'movements'       => $movements,
            'permits'         => $permits,
            'incidents'       => $incidents,
            'recommendations' => $recommendations,
            'attachments'     => $employee->attachments->map(fn ($a) => [
                'id'            => $a->id,
                'original_name' => $a->original_name,
                'mime_type'     => $a->mime_type,
                'size_bytes'    => $a->size_bytes,
                'label'         => $a->label,
                'uploaded_by'   => $a->uploader?->name,
                'created_at'    => $a->created_at?->format('Y-m-d H:i'),
            ])->all(),
            'stats' => [
                'held_assets_count' => $heldAssets->count(),
                'movements_count'   => $movements->count(),
                'permits_count'     => $permits->count(),
                'incidents_count'   => $incidents->count(),
                'recommendations_count' => $recommendations->count(),
                'attachments_count' => $employee->attachments->count(),
            ],
        ]);
    }

    public function assets(Employee $employee): JsonResponse
    {
        $assets = $employee->heldAssets()
            ->with(['brand:id,name', 'category:id,name', 'currentLocation:id,name'])
            ->orderBy('asset_tag')
            ->get()
            ->map(fn ($a) => [
                'id'              => $a->id,
                'asset_tag'       => $a->asset_tag,
                'category'        => $a->category?->name,
                'brand'           => $a->brand?->name,
                'model'           => $a->model,
                'serial_number'   => $a->serial_number,
                'current_status'  => $a->current_status,
                'current_location'=> $a->currentLocation?->name,
                'deployment_date' => $a->deployment_date?->format('Y-m-d'),
            ]);

        return response()->json([
            'employee' => [
                'id'        => $employee->id,
                'full_name' => $employee->full_name,
                'employee_no' => $employee->employee_no,
            ],
            'assets' => $assets,
        ]);
    }

    private function validateData(Request $request, ?Employee $employee = null): array
    {
        return $request->validate([
            'employee_no'   => ['required', 'string', 'max:50', Rule::unique('employees', 'employee_no')->ignore($employee?->id)],
            'first_name'    => ['required', 'string', 'max:255'],
            'middle_name'   => ['nullable', 'string', 'max:255'],
            'last_name'     => ['required', 'string', 'max:255'],
            'email'         => ['nullable', 'email', 'max:255', Rule::unique('employees', 'email')->ignore($employee?->id)],
            'contact_no'    => ['nullable', 'string', 'max:50'],
            'position'      => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'location_id'   => ['nullable', 'exists:locations,id'],
            'date_hired'    => ['nullable', 'date'],
            'date_resigned' => ['nullable', 'date', 'after_or_equal:date_hired'],
            'status'        => ['required', Rule::in(['active', 'inactive', 'resigned'])],
            'notes'         => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
