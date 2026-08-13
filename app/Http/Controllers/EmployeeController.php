<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Services\AccountabilityDocxGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller
{
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
            'status'        => ['required', Rule::in(['active', 'inactive', 'resigned'])],
        ]);
    }
}
