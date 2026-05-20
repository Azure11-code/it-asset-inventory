<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DepartmentController extends Controller
{
    private const SORTABLE = ['code', 'name', 'is_active'];

    public function index(Request $request)
    {
        $sort      = in_array($request->sort, self::SORTABLE, true) ? $request->sort : 'name';
        $direction = $request->direction === 'desc' ? 'desc' : 'asc';

        $departments = Department::query()
            ->when($request->search, fn ($q, $search) =>
                $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")
                                       ->orWhere('code', 'like', "%{$search}%"))
            )
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Departments/Index', [
            'departments' => $departments,
            'filters'     => $request->only('search', 'sort', 'direction'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'      => ['required', 'string', 'max:50', 'unique:departments,code'],
            'name'      => ['required', 'string', 'max:255', 'unique:departments,name'],
            'is_active' => ['boolean'],
        ]);

        Department::create($data);

        return back()->with('success', 'Department created.');
    }

    public function update(Request $request, Department $department)
    {
        $data = $request->validate([
            'code'      => ['required', 'string', 'max:50', Rule::unique('departments', 'code')->ignore($department->id)],
            'name'      => ['required', 'string', 'max:255', Rule::unique('departments', 'name')->ignore($department->id)],
            'is_active' => ['boolean'],
        ]);

        $department->update($data);

        return back()->with('success', 'Department updated.');
    }

    public function destroy(Department $department)
    {
        $department->delete();

        return back()->with('success', 'Department deleted.');
    }
}
