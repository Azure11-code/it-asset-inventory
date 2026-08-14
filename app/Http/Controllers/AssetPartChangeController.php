<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetPartChange;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;

class AssetPartChangeController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('perm:assets,edit',   only: ['store', 'update']),
            new Middleware('perm:assets,delete', only: ['destroy']),
        ];
    }

    public function store(Request $request, Asset $asset)
    {
        $data = $this->validateData($request);

        $asset->partChanges()->create([
            ...$data,
            'performed_by_user_id' => $data['performed_by_user_id'] ?? Auth::id(),
        ]);

        return back()->with('success', 'Part change recorded.');
    }

    public function update(Request $request, Asset $asset, AssetPartChange $partChange)
    {
        abort_if($partChange->asset_id !== $asset->id, 404);

        $data = $this->validateData($request);
        $partChange->update($data);

        return back()->with('success', 'Part change updated.');
    }

    public function destroy(Asset $asset, AssetPartChange $partChange)
    {
        abort_if($partChange->asset_id !== $asset->id, 404);

        $partChange->delete();

        return back()->with('success', 'Part change deleted.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'part_name'            => ['required', 'string', 'max:100'],
            'old_value'            => ['nullable', 'string', 'max:255'],
            'new_value'            => ['required', 'string', 'max:255'],
            'reason'               => ['nullable', 'string', 'max:100'],
            'changed_at'           => ['required', 'date'],
            'performed_by_user_id' => ['nullable', 'exists:users,id'],
            'notes'                => ['nullable', 'string'],
            'incident_report_id'   => ['nullable', 'exists:incident_reports,id'],
            'recommendation_id'    => ['nullable', 'exists:recommendations,id'],
        ]);
    }
}
