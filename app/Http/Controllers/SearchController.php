<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SearchController extends Controller
{
    /** Quick autocomplete suggestions — returns a small grouped set. */
    public function suggest(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '' || mb_strlen($q) < 2) {
            return response()->json(['groups' => []]);
        }

        $groups = [];
        foreach ($this->collectAll($q, perGroupLimit: 5) as $type => $items) {
            if ($items->isEmpty()) continue;
            $groups[] = [
                'type'  => $type,
                'label' => self::TYPE_LABELS[$type],
                'items' => $items->values()->all(),
            ];
        }
        return response()->json(['groups' => $groups]);
    }

    /** Full results page. */
    public function page(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $groups = [];
        $total = 0;
        if ($q !== '' && mb_strlen($q) >= 2) {
            foreach ($this->collectAll($q, perGroupLimit: 25) as $type => $items) {
                $count = $items->count();
                $total += $count;
                $groups[] = [
                    'type'  => $type,
                    'label' => self::TYPE_LABELS[$type],
                    'items' => $items->values()->all(),
                    'count' => $count,
                ];
            }
        }

        return Inertia::render('Search/Index', [
            'query'  => $q,
            'groups' => $groups,
            'total'  => $total,
        ]);
    }

    private const TYPE_LABELS = [
        'asset'      => 'Assets',
        'employee'   => 'Employees',
        'location'   => 'Locations',
        'department' => 'Departments',
        'category'   => 'Categories',
        'brand'      => 'Brands',
    ];

    /** Returns a keyed collection: type => Collection<result-array>. */
    private function collectAll(string $q, int $perGroupLimit): array
    {
        $like = "%{$q}%";

        return [
            'asset' => Asset::query()
                ->with(['brand:id,name', 'category:id,name', 'currentHolder:id,first_name,middle_name,last_name'])
                ->where(fn ($w) => $w->where('asset_tag', 'like', $like)
                                     ->orWhere('serial_number', 'like', $like)
                                     ->orWhere('model', 'like', $like)
                                     ->orWhere('description', 'like', $like))
                ->limit($perGroupLimit)
                ->get()
                ->map(fn ($a) => [
                    'id'       => $a->id,
                    'title'    => $a->asset_tag,
                    'subtitle' => trim(($a->category?->name ?? '') . ' · ' . ($a->brand?->name ?? '') . ' ' . ($a->model ?? '') . ($a->currentHolder ? ' · Held by ' . $a->currentHolder->full_name : ''), ' ·'),
                    'url'      => "/assets/{$a->id}",
                ]),

            'employee' => Employee::query()
                ->with(['department:id,name'])
                ->where(fn ($w) => $w->where('first_name', 'like', $like)
                                     ->orWhere('middle_name', 'like', $like)
                                     ->orWhere('last_name', 'like', $like)
                                     ->orWhere('employee_no', 'like', $like)
                                     ->orWhere('email', 'like', $like)
                                     ->orWhereRaw("CONCAT_WS(' ', first_name, last_name) LIKE ?", [$like]))
                ->limit($perGroupLimit)
                ->get()
                ->map(fn ($e) => [
                    'id'       => $e->id,
                    'title'    => $e->full_name,
                    'subtitle' => trim(($e->employee_no ?? '') . ($e->department ? ' · ' . $e->department->name : '') . ($e->position ? ' · ' . $e->position : ''), ' ·'),
                    'url'      => '/employees?search=' . urlencode($e->employee_no ?? $e->last_name),
                ]),

            'location' => Location::query()
                ->where('name', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->limit($perGroupLimit)
                ->get()
                ->map(fn ($l) => [
                    'id'       => $l->id,
                    'title'    => $l->name,
                    'subtitle' => $l->description ?: 'Location',
                    'url'      => '/locations?search=' . urlencode($l->name),
                ]),

            'department' => Department::query()
                ->where('name', 'like', $like)
                ->limit($perGroupLimit)
                ->get()
                ->map(fn ($d) => [
                    'id'       => $d->id,
                    'title'    => $d->name,
                    'subtitle' => 'Department',
                    'url'      => '/departments?search=' . urlencode($d->name),
                ]),

            'category' => Category::query()
                ->where('name', 'like', $like)
                ->limit($perGroupLimit)
                ->get()
                ->map(fn ($c) => [
                    'id'       => $c->id,
                    'title'    => $c->name,
                    'subtitle' => 'Category',
                    'url'      => '/categories?search=' . urlencode($c->name),
                ]),

            'brand' => Brand::query()
                ->where('name', 'like', $like)
                ->limit($perGroupLimit)
                ->get()
                ->map(fn ($b) => [
                    'id'       => $b->id,
                    'title'    => $b->name,
                    'subtitle' => 'Brand',
                    'url'      => '/brands?search=' . urlencode($b->name),
                ]),
        ];
    }
}
