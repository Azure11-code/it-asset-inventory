<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Services\AccountabilityDocxGenerator;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountabilityController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('perm:accountability,view',  only: ['index', 'attachments']),
            new Middleware('perm:accountability,print', only: ['download']),
        ];
    }

    /**
     * Return attachments for one employee — used by the signed-forms modal on the
     * Accountability index page. Kept as JSON to avoid a full page reload.
     */
    public function attachments(Employee $employee)
    {
        $employee->load('attachments.uploader:id,name');

        return response()->json([
            'employee' => [
                'id'          => $employee->id,
                'name'        => $employee->full_name,
                'employee_no' => $employee->employee_no,
            ],
            'attachments' => $employee->attachments->map(fn ($a) => [
                'id'            => $a->id,
                'original_name' => $a->original_name,
                'mime_type'     => $a->mime_type,
                'size_bytes'    => $a->size_bytes,
                'label'         => $a->label,
                'uploaded_by'   => $a->uploader?->name,
                'created_at'    => $a->created_at?->format('Y-m-d H:i'),
            ])->all(),
        ]);
    }

    private const SORT_MAP = [
        'employee'   => 'employees.last_name',
        'department' => 'departments.name',
        'assets'     => 'held_assets_count',
    ];

    public function index(Request $request)
    {
        $sortKey   = array_key_exists($request->sort, self::SORT_MAP) ? $request->sort : 'employee';
        $sortCol   = self::SORT_MAP[$sortKey];
        $direction = $request->direction === 'desc' ? 'desc' : 'asc';

        $employees = Employee::query()
            ->select('employees.*')
            ->leftJoin('departments', 'employees.department_id', '=', 'departments.id')
            ->with(['department:id,name'])
            ->withCount(['heldAssets', 'attachments'])
            ->having('held_assets_count', '>', 0)
            ->when($request->search, fn ($q, $s) =>
                $q->where(fn ($w) => $w->where('first_name', 'like', "%{$s}%")
                                       ->orWhere('last_name', 'like', "%{$s}%")
                                       ->orWhere('employee_no', 'like', "%{$s}%")
                                       ->orWhereRaw("CONCAT_WS(' ', first_name, last_name) LIKE ?", ["%{$s}%"]))
            )
            ->when($request->department_id, fn ($q, $id) => $q->where('department_id', $id))
            ->orderBy($sortCol, $direction)
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Accountability/Index', [
            'employees'   => $employees,
            'departments' => Department::select('id', 'name')->orderBy('name')->get(),
            'filters'     => $request->only('search', 'department_id', 'sort', 'direction'),
        ]);
    }

    public function download(Request $request, Employee $employee, AccountabilityDocxGenerator $generator): StreamedResponse
    {
        $assetIds = $request->has('asset_id')
            ? [(int) $request->query('asset_id')]
            : null;

        return $generator->generate($employee, $assetIds);
    }
}
