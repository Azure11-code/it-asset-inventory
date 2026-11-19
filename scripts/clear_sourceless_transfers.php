<?php

/**
 * Clears the movement rows that record a transfer from nobody.
 *
 * A transfer needs a source. These rows have none, so they describe a handover
 * that never happened ("From — → Someone"). They are being removed so the
 * assets can be issued again properly, which writes correct history.
 *
 * Run a dry run first — it reports exactly what it would do and changes
 * nothing:
 *
 *     docker compose exec app php scripts/clear_sourceless_transfers.php
 *
 * Then, to apply:
 *
 *     docker compose exec app php scripts/clear_sourceless_transfers.php --apply
 *
 * What it touches, and nothing else:
 *   - DELETEs asset_movements rows where type = 'transfer' AND
 *     from_employee_id IS NULL.
 *   - Releases the assets named in those rows (current_holder_id = NULL,
 *     current_status = 'in_stock') plus any further asset listed in
 *     EXTRA_ASSET_TAGS, so they can be assigned fresh.
 *
 * It does NOT touch deployment_date (the date a device first went out is a
 * historical fact), current_location_id (the device is still physically
 * somewhere), the department (the department rule keeps it through a return),
 * employees, or any genuine transfer that has a real source.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;

/**
 * Assets to release even though no deleted row names them — an asset that was
 * assigned without ever recording a movement.
 */
const EXTRA_ASSET_TAGS = ['2022AIM0086-01'];

$apply = in_array('--apply', $argv, true);

$movements = AssetMovement::where('type', 'transfer')
    ->whereNull('from_employee_id')
    ->orderBy('id')
    ->get();

if ($movements->isEmpty()) {
    echo "Nothing to do: no sourceless transfers found.\n";
    exit(0);
}

$assetIds = $movements->pluck('asset_id')->filter()->unique();

$extra = Asset::whereIn('asset_tag', EXTRA_ASSET_TAGS)->pluck('id', 'asset_tag');
foreach (EXTRA_ASSET_TAGS as $tag) {
    if (!$extra->has($tag)) {
        fwrite(STDERR, "ABORT: EXTRA_ASSET_TAGS names {$tag}, which does not exist.\n");
        exit(1);
    }
}

$releaseIds = $assetIds->merge($extra->values())->unique()->values();

echo $apply ? "=== APPLYING ===\n\n" : "=== DRY RUN (nothing is written) ===\n\n";

echo "Movement rows to delete (" . $movements->count() . "):\n";
foreach ($movements as $m) {
    printf("  id=%-5d %-16s -> %-28s %s\n",
        $m->id,
        Asset::withTrashed()->find($m->asset_id)?->asset_tag ?? '?',
        Employee::find($m->to_employee_id)?->full_name ?? 'NULL',
        substr((string) $m->movement_date, 0, 10)
    );
}

echo "\nAssets to release (" . $releaseIds->count() . "):\n";
foreach (Asset::whereIn('id', $releaseIds)->orderBy('asset_tag')->get() as $a) {
    printf("  %-16s %-10s held by %-28s -> in_stock, no holder\n",
        $a->asset_tag,
        $a->current_status,
        Employee::find($a->current_holder_id)?->full_name ?? 'nobody'
    );
}

// Any genuine transfer must survive untouched; prove it before and after.
$genuineBefore = AssetMovement::where('type', 'transfer')->whereNotNull('from_employee_id')->count();
$otherBefore   = AssetMovement::where('type', '!=', 'transfer')->count();

if (!$apply) {
    echo "\nDry run only. Re-run with --apply to write.\n";
    exit(0);
}

DB::transaction(function () use ($movements, $releaseIds) {
    AssetMovement::whereIn('id', $movements->pluck('id'))->delete();

    // Updated one at a time so the model's own rules (the department rule
    // among them) run, rather than being bypassed by a mass update.
    foreach (Asset::whereIn('id', $releaseIds)->get() as $asset) {
        $asset->update([
            'current_holder_id' => null,
            'current_status'    => 'in_stock',
        ]);
    }
});

$genuineAfter = AssetMovement::where('type', 'transfer')->whereNotNull('from_employee_id')->count();
$otherAfter   = AssetMovement::where('type', '!=', 'transfer')->count();
$leftover     = AssetMovement::where('type', 'transfer')->whereNull('from_employee_id')->count();
$stillHeld    = Asset::whereIn('id', $releaseIds)->whereNotNull('current_holder_id')->count();

echo "\n=== RESULT ===\n";
printf("  sourceless transfers remaining : %d (expected 0)\n", $leftover);
printf("  genuine transfers              : %d -> %d (must match)\n", $genuineBefore, $genuineAfter);
printf("  issuances/returns              : %d -> %d (must match)\n", $otherBefore, $otherAfter);
printf("  released assets still held     : %d (expected 0)\n", $stillHeld);

$ok = $leftover === 0
    && $genuineBefore === $genuineAfter
    && $otherBefore === $otherAfter
    && $stillHeld === 0;

echo $ok ? "\nDone.\n" : "\nCHECKS FAILED — restore the backup.\n";
exit($ok ? 0 : 1);
