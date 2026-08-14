<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class BrandController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('perm:brands,view',   only: ['index']),
            new Middleware('perm:brands,create', only: ['store']),
            new Middleware('perm:brands,edit',   only: ['update']),
            new Middleware('perm:brands,delete', only: ['destroy']),
        ];
    }

    private const SORTABLE = ['name', 'description', 'is_active'];

    public function index(Request $request)
    {
        $sort      = in_array($request->sort, self::SORTABLE, true) ? $request->sort : 'name';
        $direction = $request->direction === 'desc' ? 'desc' : 'asc';

        $brands = Brand::query()
            ->when($request->search, fn ($q, $search) =>
                $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")
                                       ->orWhere('slug', 'like', "%{$search}%"))
            )
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Brands/Index', [
            'brands'  => $brands,
            'filters' => $request->only('search', 'sort', 'direction'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255', 'unique:brands,name'],
            'slug'        => ['nullable', 'string', 'max:255', 'unique:brands,slug'],
            'description' => ['nullable', 'string'],
            'is_active'   => ['boolean'],
        ]);

        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);

        Brand::create($data);

        return back()->with('success', 'Brand created.');
    }

    public function update(Request $request, Brand $brand)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255', Rule::unique('brands', 'name')->ignore($brand->id)],
            'slug'        => ['nullable', 'string', 'max:255', Rule::unique('brands', 'slug')->ignore($brand->id)],
            'description' => ['nullable', 'string'],
            'is_active'   => ['boolean'],
        ]);

        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);

        $brand->update($data);

        return back()->with('success', 'Brand updated.');
    }

    public function destroy(Brand $brand)
    {
        $brand->delete();

        return back()->with('success', 'Brand deleted.');
    }
}
