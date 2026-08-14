<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetMovement;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AssetMovementController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('perm:assets,edit',   only: ['issue', 'returnFromHolder', 'transfer', 'updateMovement']),
            new Middleware('perm:assets,delete', only: ['destroyMovement']),
        ];
    }

    public function issue(Request $request, Asset $asset)
    {
        if ($asset->current_status === 'assigned') {
            return back()->with('error', "Asset {$asset->asset_tag} is already assigned. Return it first.");
        }
        if (in_array($asset->current_status, ['retired', 'replaced'])) {
            return back()->with('error', "Asset {$asset->asset_tag} is {$asset->current_status} and cannot be issued.");
        }

        $data = $request->validate([
            'to_employee_id' => ['required', 'exists:employees,id'],
            'to_location_id' => ['nullable', 'exists:locations,id'],
            'movement_date'  => ['required', 'date'],
            'reference'      => ['nullable', 'string', 'max:100'],
            'remarks'        => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($asset, $data, $request) {
            AssetMovement::create([
                'asset_id'         => $asset->id,
                'type'             => 'issuance',
                'from_employee_id' => $asset->current_holder_id,
                'to_employee_id'   => $data['to_employee_id'],
                'from_location_id' => $asset->current_location_id,
                'to_location_id'   => $data['to_location_id'] ?? $asset->current_location_id,
                'movement_date'    => $data['movement_date'],
                'performed_by'     => $request->user()?->id,
                'reference'        => $data['reference'] ?? null,
                'remarks'          => $data['remarks'] ?? null,
            ]);

            $asset->update([
                'current_holder_id'   => $data['to_employee_id'],
                'current_location_id' => $data['to_location_id'] ?? $asset->current_location_id,
                'current_status'      => 'assigned',
                // First-time deployment date — set once, never overwritten
                'deployment_date'     => $asset->deployment_date ?? $data['movement_date'],
            ]);
        });

        return back()->with('success', "Asset {$asset->asset_tag} issued.");
    }

    public function returnFromHolder(Request $request, Asset $asset)
    {
        if ($asset->current_status !== 'assigned') {
            return back()->with('error', "Asset {$asset->asset_tag} is not currently assigned to anyone.");
        }

        $data = $request->validate([
            'to_location_id'    => ['nullable', 'exists:locations,id'],
            'movement_date'     => ['required', 'date'],
            'new_status'        => ['required', Rule::in(['in_stock', 'for_repair', 'defective'])],
            'new_condition_id'  => ['nullable', 'exists:conditions,id'],
            'reference'         => ['nullable', 'string', 'max:100'],
            'remarks'           => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($asset, $data, $request) {
            AssetMovement::create([
                'asset_id'         => $asset->id,
                'type'             => 'return',
                'from_employee_id' => $asset->current_holder_id,
                'to_employee_id'   => null,
                'from_location_id' => $asset->current_location_id,
                'to_location_id'   => $data['to_location_id'] ?? $asset->current_location_id,
                'movement_date'    => $data['movement_date'],
                'performed_by'     => $request->user()?->id,
                'reference'        => $data['reference'] ?? null,
                'remarks'          => $data['remarks'] ?? null,
            ]);

            $update = [
                'current_holder_id'   => null,
                'current_location_id' => $data['to_location_id'] ?? $asset->current_location_id,
                'current_status'      => $data['new_status'],
            ];
            if (!empty($data['new_condition_id'])) {
                $update['condition_id'] = $data['new_condition_id'];
            }
            $asset->update($update);
        });

        return back()->with('success', "Asset {$asset->asset_tag} returned.");
    }

    public function updateMovement(Request $request, Asset $asset, AssetMovement $movement)
    {
        if ($movement->asset_id !== $asset->id) {
            throw new NotFoundHttpException();
        }

        $data = $request->validate([
            'movement_date'    => ['required', 'date'],
            'from_employee_id' => ['nullable', 'exists:employees,id'],
            'to_employee_id'   => ['nullable', 'exists:employees,id'],
            'from_location_id' => ['nullable', 'exists:locations,id'],
            'to_location_id'   => ['nullable', 'exists:locations,id'],
            'reference'        => ['nullable', 'string', 'max:100'],
            'remarks'          => ['nullable', 'string'],
        ]);

        $movement->update($data);

        // If this is the most recent movement of its type, sync the asset state
        $latest = $asset->movements()->orderByDesc('movement_date')->orderByDesc('id')->first();
        if ($latest && $latest->id === $movement->id) {
            $update = [];
            if ($movement->type === 'issuance' || $movement->type === 'transfer') {
                if ($data['to_employee_id']) $update['current_holder_id'] = $data['to_employee_id'];
                if ($data['to_location_id']) $update['current_location_id'] = $data['to_location_id'];
            }
            if ($update) $asset->update($update);
        }

        return back()->with('success', 'Movement updated.');
    }

    public function destroyMovement(Asset $asset, AssetMovement $movement)
    {
        if ($movement->asset_id !== $asset->id) {
            throw new NotFoundHttpException();
        }
        $movement->delete();

        return back()->with('success', 'Movement removed.');
    }

    public function transfer(Request $request, Asset $asset)
    {
        $data = $request->validate([
            'to_employee_id' => ['nullable', 'exists:employees,id'],
            'to_location_id' => ['nullable', 'exists:locations,id'],
            'movement_date'  => ['required', 'date'],
            'reference'      => ['nullable', 'string', 'max:100'],
            'remarks'        => ['nullable', 'string'],
        ]);

        if (empty($data['to_employee_id']) && empty($data['to_location_id'])) {
            return back()->withErrors(['to_employee_id' => 'Pick at least a new employee or location.']);
        }

        DB::transaction(function () use ($asset, $data, $request) {
            AssetMovement::create([
                'asset_id'         => $asset->id,
                'type'             => 'transfer',
                'from_employee_id' => $asset->current_holder_id,
                'to_employee_id'   => $data['to_employee_id'] ?? $asset->current_holder_id,
                'from_location_id' => $asset->current_location_id,
                'to_location_id'   => $data['to_location_id'] ?? $asset->current_location_id,
                'movement_date'    => $data['movement_date'],
                'performed_by'     => $request->user()?->id,
                'reference'        => $data['reference'] ?? null,
                'remarks'          => $data['remarks'] ?? null,
            ]);

            $update = [];
            if (!empty($data['to_employee_id'])) {
                $update['current_holder_id'] = $data['to_employee_id'];
                $update['current_status'] = 'assigned';
            }
            if (!empty($data['to_location_id'])) {
                $update['current_location_id'] = $data['to_location_id'];
            }
            $asset->update($update);
        });

        return back()->with('success', "Asset {$asset->asset_tag} transferred.");
    }
}
