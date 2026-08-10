<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Condition;
use App\Models\Employee;
use App\Models\Location;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ScanController extends Controller
{
    /** Camera + manual-entry scan page. */
    public function index()
    {
        return Inertia::render('Scan/Index');
    }

    /** Lookup by tag, render the result page (details + inline action forms). */
    public function lookup(Request $request)
    {
        $tag = trim((string) $request->query('tag'));

        if ($tag === '') {
            return Inertia::render('Scan/Result', ['asset' => null, 'searched' => '']);
        }

        $asset = Asset::query()
            ->where('asset_tag', $tag)
            ->orWhere('asset_tag', strtoupper($tag))
            ->with([
                'brand:id,name', 'category:id,name',
                'condition:id,name,tone',
                'currentHolder:id,first_name,middle_name,last_name,department_id',
                'currentHolder.department:id,name',
                'currentLocation:id,name',
                'department:id,name',
            ])
            ->first();

        return Inertia::render('Scan/Result', [
            'searched' => $tag,
            'asset'    => $asset ? [
                'id'              => $asset->id,
                'asset_tag'       => $asset->asset_tag,
                'serial_number'   => $asset->serial_number,
                'model'           => $asset->model,
                'category'        => $asset->category ? ['id' => $asset->category->id, 'name' => $asset->category->name] : null,
                'brand'           => $asset->brand ? ['id' => $asset->brand->id, 'name' => $asset->brand->name] : null,
                'current_status'  => $asset->current_status,
                'current_holder'  => $asset->currentHolder ? [
                    'id'         => $asset->currentHolder->id,
                    'full_name'  => $asset->currentHolder->full_name,
                    'department' => $asset->currentHolder->department?->name,
                ] : null,
                'current_location'=> $asset->currentLocation ? ['id' => $asset->currentLocation->id, 'name' => $asset->currentLocation->name] : null,
                'department'      => $asset->department ? ['id' => $asset->department->id, 'name' => $asset->department->name] : null,
                'purchase_date'   => $asset->purchase_date?->format('Y-m-d'),
                'deployment_date' => $asset->deployment_date?->format('Y-m-d'),
                'warranty_until'  => $asset->warranty_until?->format('Y-m-d'),
                'notes'           => $asset->notes,
            ] : null,
            'lookups' => $asset ? [
                'employees'  => Employee::where('status', 'active')->orderBy('last_name')
                                    ->get(['id','first_name','middle_name','last_name'])
                                    ->map(fn ($e) => ['id' => $e->id, 'name' => $e->full_name])->values(),
                'locations'  => Location::select('id', 'name')->orderBy('name')->get(),
                'conditions' => Condition::where('is_active', true)->select('id', 'name', 'tone')->orderBy('sort_order')->get(),
            ] : null,
        ]);
    }
}
