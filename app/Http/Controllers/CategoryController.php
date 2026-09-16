<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CategoryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('perm:categories,view',   only: ['index']),
            new Middleware('perm:categories,create', only: ['store']),
            new Middleware('perm:categories,edit',   only: ['update']),
            new Middleware('perm:categories,delete', only: ['destroy']),
        ];
    }

    private const SORTABLE = ['name', 'prefix', 'description', 'is_active'];

    public function index(Request $request)
    {
        $sort      = in_array($request->sort, self::SORTABLE, true) ? $request->sort : 'name';
        $direction = $request->direction === 'desc' ? 'desc' : 'asc';

        $categories = Category::query()
            ->when($request->search, fn ($q, $s) => $q->search($s))

            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
            'filters'    => $request->only('search', 'sort', 'direction'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255', 'unique:categories,name'],
            'slug'        => ['nullable', 'string', 'max:255', 'unique:categories,slug'],
            'prefix'      => ['nullable', 'string', 'max:10'],
            'description' => ['nullable', 'string'],
            'is_active'   => ['boolean'],
        ]);

        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);

        Category::create($data);

        return back()->with('success', 'Category created.');
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($category->id)],
            'slug'        => ['nullable', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($category->id)],
            'prefix'      => ['nullable', 'string', 'max:10'],
            'description' => ['nullable', 'string'],
            'is_active'   => ['boolean'],
        ]);

        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);

        $category->update($data);

        return back()->with('success', 'Category updated.');
    }

    public function destroy(Category $category)
    {
        $category->delete();

        return back()->with('success', 'Category deleted.');
    }
}
