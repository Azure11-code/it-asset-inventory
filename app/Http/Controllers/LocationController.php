<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LocationController extends Controller
{
    private const SORTABLE = ['name', 'description', 'is_active'];

    public function index(Request $request)
    {
        $sort      = in_array($request->sort, self::SORTABLE, true) ? $request->sort : 'name';
        $direction = $request->direction === 'desc' ? 'desc' : 'asc';

        $locations = Location::query()
            ->when($request->search, fn ($q, $search) =>
                $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")
                                       ->orWhere('description', 'like', "%{$search}%"))
            )
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Locations/Index', [
            'locations' => $locations,
            'filters'   => $request->only('search', 'sort', 'direction'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255', 'unique:locations,name'],
            'description' => ['nullable', 'string'],
            'is_active'   => ['boolean'],
        ]);

        Location::create($data);

        return back()->with('success', 'Location created.');
    }

    public function update(Request $request, Location $location)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255', Rule::unique('locations', 'name')->ignore($location->id)],
            'description' => ['nullable', 'string'],
            'is_active'   => ['boolean'],
        ]);

        $location->update($data);

        return back()->with('success', 'Location updated.');
    }

    public function destroy(Location $location)
    {
        $location->delete();

        return back()->with('success', 'Location deleted.');
    }
}
