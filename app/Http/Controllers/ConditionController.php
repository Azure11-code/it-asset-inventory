<?php

namespace App\Http\Controllers;

use App\Models\Condition;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ConditionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('perm:conditions,view',   only: ['index']),
            new Middleware('perm:conditions,create', only: ['store']),
            new Middleware('perm:conditions,edit',   only: ['update']),
            new Middleware('perm:conditions,delete', only: ['destroy']),
        ];
    }

    private const SORTABLE = ['sort_order', 'name', 'description', 'assets_count', 'is_active'];

    public function index(Request $request)
    {
        $sort      = in_array($request->sort, self::SORTABLE, true) ? $request->sort : 'sort_order';
        $direction = $request->direction === 'desc' ? 'desc' : 'asc';

        $query = Condition::query()
            ->when($request->search, fn ($q, $s) => $q->search($s))

            ->withCount('assets')
            ->orderBy($sort, $direction);

        if ($sort === 'sort_order') {
            $query->orderBy('name');
        }

        $conditions = $query->paginate(15)->withQueryString();

        return Inertia::render('Conditions/Index', [
            'conditions' => $conditions,
            'filters'    => $request->only('search', 'sort', 'direction'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100', 'unique:conditions,name'],
            'description' => ['nullable', 'string', 'max:255'],
            'tone'        => ['required', Rule::in(Condition::TONES)],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['boolean'],
        ]);
        $data['slug'] = Str::slug($data['name']);

        Condition::create($data);

        return back()->with('success', 'Condition created.');
    }

    public function update(Request $request, Condition $condition)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100', Rule::unique('conditions', 'name')->ignore($condition->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'tone'        => ['required', Rule::in(Condition::TONES)],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['boolean'],
        ]);
        $data['slug'] = Str::slug($data['name']);

        $condition->update($data);

        return back()->with('success', 'Condition updated.');
    }

    public function destroy(Condition $condition)
    {
        if ($condition->assets()->exists()) {
            return back()->with('error', "Can't delete '{$condition->name}' — {$condition->assets()->count()} asset(s) reference it.");
        }
        $condition->delete();

        return back()->with('success', 'Condition deleted.');
    }
}
