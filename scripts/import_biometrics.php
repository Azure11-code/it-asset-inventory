<?php

/**
 * Loads the BIOMETRIC ASSET MONITORING sheet (2024, 2025, 2026) into assets.
 *
 *     docker compose exec app php scripts/import_biometrics.php            # dry run
 *     docker compose exec app php scripts/import_biometrics.php --apply    # write
 *
 * INSERT only. It never runs UPDATE, DELETE, DROP, ALTER or TRUNCATE against
 * existing data, never changes the schema, and skips any asset whose tag or
 * serial number is already present.
 *
 * It may create two kinds of supporting row, both pure inserts:
 *   - the ZKTECO brand, if it does not exist yet;
 *   - a location named in the sheet that does not exist yet.
 *
 * WHERE THE DATA CAME FROM
 * The rows below were read from a screenshot of the sheet, not pasted as text.
 * Serial numbers are the field that cannot be eyeballed later, and two of them
 * disagree about character 3 ("PA5..." vs "PAS..."), so at least one reading is
 * suspect. Every imported asset carries a note saying it came from this sheet,
 * so the whole batch can be found and checked:
 *
 *     SELECT asset_tag, serial_number FROM assets WHERE notes LIKE '%BIOMETRIC ASSET MONITORING%';
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Asset;
use App\Models\Brand;
use App\Models\Location;
use Illuminate\Support\Facades\DB;

const CATEGORY_BIOMETRIC  = 53;
const CONDITION_DEFECTIVE = 5;
const SOURCE_NOTE         = 'Imported from the BIOMETRIC ASSET MONITORING sheet.';

/**
 * Warehouse names in the sheet that do not match a location exactly.
 *
 * "(NEW)" qualifies the device, not the place — a replacement terminal at the
 * same warehouse — so those rows resolve to the same location as their older
 * counterpart rather than to a second site.
 */
const LOCATION_ALIASES = [
    'ZAMBO WAREHOUSE'          => 'ZAMBOANGA WAREHOUSE',  // abbreviation
    'CEBU WAREHOUSE'           => 'CEBU 1 WAREHOUSE',     // sheet predates the 1/2 split
    'CAGAYAN WAREHOUSE'        => 'CAGAYAN 1 WAREHOUSE',  // sheet predates the 1/2/3 split
    'TABACO WAREHOUSE'         => 'TABACO 1 WAREHOUSE',   // sheet predates the 1/2 split
    'TABACO WAREHOUSE (NEW)'   => 'TABACO 1 WAREHOUSE',   // replacement unit, same site
    'HARBOUR WAREHOUSE (NEW)'  => 'HARBOUR WAREHOUSE',    // replacement unit, same site
    'BUFFER HO'                => 'HEAD OFFICE',          // spare kept at the head office
    'PULLOUT / DEFECTIVE UNIT' => 'HEAD OFFICE',          // not a place; pullouts sit with IT
];

/** Names that are genuinely new sites and get created rather than guessed at. */
const LOCATIONS_TO_CREATE = [
    'CAGAYAN RICE WAREHOUSE',
    'NEW BATAAN WAREHOUSE',
    'JLL/LAUTAN - Y-TOWER ADMIN BLDG',
];

/** Sheet rows: tag, warehouse, serial, item, purchase, deployed, remark. */
$K40    = 'ZKTECO TIME ATTENDANCE TERMINAL K-40';
$MB10   = 'MB10-VL ZKTECO BIOMETRIC DEVICE';

$rows = [
    // YEAR 2024
    ['2024AIM0700', 'HARBOUR WAREHOUSE',               'A8N5233561561', $K40,  '2024-01-19', '2024-02-20', null],
    ['2024AIM0701', 'MALABON WAREHOUSE',               'A8N5233561554', $K40,  '2024-01-19', '2024-02-20', null],
    ['2024AIM0702', 'HEAD OFFICE',                     'A8N5233561557', $K40,  '2024-01-19', '2024-02-20', 'Transferred date: June 26, 2025.'],
    ['2024AIM0703', 'CALACA WAREHOUSE',                'A8N5233561549', $K40,  '2024-01-19', '2024-02-20', null],
    ['2024AIM0704', 'PNOC WAREHOUSE',                  'A8N5233561411', $K40,  '2024-01-19', '2024-02-20', null],
    ['2024AIM0705', 'ZAMBO WAREHOUSE',                 'A8N5233561448', $K40,  '2024-01-19', '2024-02-20', null],
    ['2024AIM0706', 'GENSAN WAREHOUSE',                'A8N5233561379', $K40,  '2024-01-19', '2024-02-20', null],
    ['2024AIM0707', 'ILOILO WAREHOUSE',                'A8N5233561396', $K40,  '2024-01-19', '2024-02-20', null],
    ['2024AIM0708', 'DAVAO WAREHOUSE',                 'A8N5233561404', $K40,  '2024-01-19', '2024-02-20', null],
    ['2024AIM0709', 'SURIGAO WAREHOUSE',               'A8N5233561509', $K40,  '2024-01-19', '2024-02-20', null],
    ['2024AIM0710', 'CEBU WAREHOUSE',                  'A8N5233561551', $K40,  '2024-01-19', '2024-02-20', null],
    ['2024AIM0711', 'BACOLOD WAREHOUSE',               'A8N5233561410', $K40,  '2024-01-19', '2024-02-20', null],
    ['2024AIM0712', 'CAGAYAN WAREHOUSE',               'A8N5233561408', $K40,  '2024-01-19', '2024-01-19', null],
    ['2024AIM0713', 'BOLO WAREHOUSE',                  'A8N5233560301', $K40,  '2024-01-19', '2024-03-26', 'Formerly issued to BATAAN WAREHOUSE / ORION.'],
    ['2024AIM0714', 'PULLOUT / DEFECTIVE UNIT',        'A8N5233561553', $K40,  '2024-01-19', '2024-10-23', 'Pullout / defective unit. Formerly issued to BOLO WAREHOUSE / ORION.'],
    ['2024AIM0715', 'JLL/LAUTAN - Y-TOWER ADMIN BLDG', 'A8N5233561438', $K40,  '2024-01-19', '2024-11-15', 'Formerly issued to BULACAN WAREHOUSE / BEATUS (02/02/26).'],
    ['2024AIM0716', 'TABACO WAREHOUSE',                'A8N5233560666', $K40,  '2024-11-28', '2024-11-28', null],

    // YEAR 2025
    ['2025AIM0700', 'CAGAYAN RICE WAREHOUSE',          'A8N5233560035', $K40,  '2025-02-15', '2025-02-19', null],
    ['2025AIM0701', 'NEW BATAAN WAREHOUSE',            'PA54251700096', $K40,  '2025-07-10', null,         null],

    // YEAR 2026
    ['2026AIM0700', 'TABACO WAREHOUSE (NEW)',          'UDP3254800718', $MB10, '2026-05-26', '2026-06-08', null],
    ['2026AIM0701', 'BOLO WAREHOUSE',                  'PAS4251700119', null,  null,         null,         'Sheet lists a serial number only: no item name, purchase date or deployed date.'],
    ['2026AIM0702', 'HARBOUR WAREHOUSE (NEW)',         'UDP3254800826', $MB10, '2026-09-14', '2026-09-15', null],
    ['2026AIM0703', 'BUFFER HO',                       'UDP3254800836', $MB10, '2026-09-14', null,         'Buffer unit held at the head office.'],
];

$apply = in_array('--apply', $argv, true);
echo $apply ? "=== APPLYING ===\n\n" : "=== DRY RUN (nothing is written) ===\n\n";

// ── Resolve every location before writing a single asset ──────────────────
$locations = Location::pluck('id', 'name');
$needed    = collect($rows)->pluck(1)->unique()
    ->map(fn ($n) => LOCATION_ALIASES[$n] ?? $n)->unique()->values();

$missing = $needed->reject(fn ($n) => $locations->has($n))->values();
$unexpected = $missing->reject(fn ($n) => in_array($n, LOCATIONS_TO_CREATE, true));

if ($unexpected->isNotEmpty()) {
    fwrite(STDERR, "ABORT: these warehouses are neither known nor listed for creation:\n  "
        . $unexpected->implode("\n  ") . "\n");
    exit(1);
}

echo "Locations to create (" . $missing->count() . "):\n";
foreach ($missing as $name) echo "  + {$name}\n";
if ($missing->isEmpty()) echo "  none\n";

echo "\nWarehouse names resolved by assumption:\n";
foreach (LOCATION_ALIASES as $sheet => $real) printf("  %-26s -> %s\n", $sheet, $real);

// ── Brand ──────────────────────────────────────────────────────────────────
$brand = Brand::where('name', 'ZKTECO')->first();
echo "\nBrand ZKTECO: " . ($brand ? "exists (id={$brand->id})" : 'will be created') . "\n";

// ── Decide each row ────────────────────────────────────────────────────────
$existingTags    = Asset::withTrashed()->pluck('asset_tag')->flip();
$existingSerials = Asset::withTrashed()->whereNotNull('serial_number')->pluck('serial_number')->flip();

$plan = [];
$skipped = [];

foreach ($rows as [$tag, $sheetLocation, $serial, $item, $purchase, $deployed, $remark]) {
    if ($existingTags->has($tag)) {
        $skipped[] = "{$tag}: asset tag already exists";
        continue;
    }
    if ($serial && $existingSerials->has($serial)) {
        $skipped[] = "{$tag}: serial {$serial} already exists";
        continue;
    }

    $isDefective = str_contains($sheetLocation, 'DEFECTIVE');
    $resolved    = LOCATION_ALIASES[$sheetLocation] ?? $sheetLocation;

    $notes = array_filter([
        $remark,
        $resolved !== $sheetLocation ? "Sheet records the warehouse as \"{$sheetLocation}\"." : null,
        SOURCE_NOTE,
    ]);

    $plan[] = [
        'asset_tag'       => $tag,
        'serial_number'   => $serial,
        'sheet_location'  => $sheetLocation,
        'location'        => $resolved,
        'description'     => $item,
        'model'           => match (true) {
            $item === $GLOBALS['K40']  => 'TIME ATTENDANCE TERMINAL K-40',
            $item === $GLOBALS['MB10'] => 'MB10-VL',
            default                    => null,
        },
        'purchase_date'   => $purchase,
        'deployment_date' => $deployed,
        'current_status'  => $isDefective ? 'defective' : ($deployed ? 'assigned' : 'in_stock'),
        'condition_id'    => $isDefective ? CONDITION_DEFECTIVE : null,
        'notes'           => implode(' ', $notes),
    ];
}

echo "\n" . str_repeat('-', 108) . "\n";
printf("%-14s %-16s %-26s %-10s %-12s %s\n", 'TAG', 'SERIAL', 'LOCATION', 'STATUS', 'DEPLOYED', 'MODEL');
echo str_repeat('-', 108) . "\n";
foreach ($plan as $p) {
    printf("%-14s %-16s %-26s %-10s %-12s %s\n",
        $p['asset_tag'], $p['serial_number'] ?? '-', $p['location'],
        $p['current_status'], $p['deployment_date'] ?? '-', $p['model'] ?? '-');
}
echo str_repeat('-', 108) . "\n";
printf("to insert: %d    skipped: %d\n", count($plan), count($skipped));
foreach ($skipped as $s) echo "  skip {$s}\n";

if (!$apply) {
    echo "\nDry run only. Re-run with --apply to write.\n";
    exit(0);
}

// ── Write ──────────────────────────────────────────────────────────────────
$assetsBefore = Asset::withTrashed()->count();

DB::transaction(function () use ($plan, $missing, &$brand) {
    foreach ($missing as $name) {
        Location::create([
            'name'            => $name,
            'has_departments' => false,
            'is_active'       => true,
        ]);
    }
    $locations = Location::pluck('id', 'name');

    // brands.slug is NOT NULL without a default, so it has to be supplied.
    $brand ??= Brand::create([
        'name'      => 'ZKTECO',
        'slug'      => 'zkteco',
        'is_active' => true,
    ]);

    foreach ($plan as $p) {
        Asset::create([
            'asset_tag'           => $p['asset_tag'],
            'serial_number'       => $p['serial_number'],
            'model'               => $p['model'],
            'description'         => $p['description'],
            'brand_id'            => $brand->id,
            'category_id'         => CATEGORY_BIOMETRIC,
            'current_location_id' => $locations[$p['location']],
            'current_status'      => $p['current_status'],
            'condition_id'        => $p['condition_id'],
            'purchase_date'       => $p['purchase_date'],
            'deployment_date'     => $p['deployment_date'],
            'notes'               => $p['notes'],
        ]);
    }
});

$assetsAfter = Asset::withTrashed()->count();
$imported    = Asset::where('notes', 'like', '%BIOMETRIC ASSET MONITORING%')->count();

echo "\n=== RESULT ===\n";
printf("  assets total          : %d -> %d (+%d, expected +%d)\n",
    $assetsBefore, $assetsAfter, $assetsAfter - $assetsBefore, count($plan));
printf("  rows carrying the note: %d\n", $imported);
printf("  biometric assets      : %d\n", Asset::where('category_id', CATEGORY_BIOMETRIC)->count());

$ok = ($assetsAfter - $assetsBefore) === count($plan);
echo $ok ? "\nDone.\n" : "\nCOUNT MISMATCH - restore the backup.\n";
exit($ok ? 0 : 1);
