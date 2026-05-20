<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AssetMovementController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ConditionController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\UserController;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ─────────── Auth ───────────
Route::middleware('guest')->group(function () {
    Route::get('/login',  [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// ─────────── Protected app routes ───────────
Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        $today = Carbon::today();
        $startDate = $today->copy()->subDays(13);

        $movementsByDay = AssetMovement::query()
            ->whereBetween('movement_date', [$startDate, $today])
            ->select('movement_date', 'type', DB::raw('COUNT(*) as count'))
            ->groupBy('movement_date', 'type')
            ->get()
            ->groupBy(fn ($r) => Carbon::parse($r->movement_date)->format('Y-m-d'));

        $days = [];
        for ($d = $startDate->copy(); $d->lte($today); $d->addDay()) {
            $key = $d->format('Y-m-d');
            $rows = $movementsByDay->get($key, collect());
            $days[] = [
                'date'     => $key,
                'label'    => $d->format('M j'),
                'issuance' => (int) ($rows->firstWhere('type', 'issuance')->count ?? 0),
                'return'   => (int) ($rows->firstWhere('type', 'return')->count   ?? 0),
                'transfer' => (int) ($rows->firstWhere('type', 'transfer')->count ?? 0),
            ];
        }

        $byCategory = Asset::query()
            ->join('categories', 'assets.category_id', '=', 'categories.id')
            ->select('categories.name', DB::raw('COUNT(*) as count'))
            ->groupBy('categories.name')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'count' => (int) $r->count])
            ->values();

        $byStatus = Asset::query()
            ->select('current_status', DB::raw('COUNT(*) as count'))
            ->groupBy('current_status')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->current_status => (int) $r->count]);

        $now = Carbon::now();
        $warrantyActive       = Asset::whereDate('warranty_until', '>', $now->copy()->addDays(90))->count();
        $warrantyExpiringSoon = Asset::whereBetween('warranty_until', [$now, $now->copy()->addDays(90)])->count();
        $warrantyExpired      = Asset::whereDate('warranty_until', '<', $now)->count();

        return Inertia::render('Dashboard', [
            'stats' => [
                'total_assets' => Asset::count(),
                'assigned'     => (int) ($byStatus['assigned'] ?? 0),
                'in_stock'     => (int) ($byStatus['in_stock'] ?? 0),
                'employees'    => Employee::where('status', 'active')->count(),
            ],
            'recent_movements' => AssetMovement::with([
                    'asset:id,asset_tag',
                    'toEmployee:id,first_name,middle_name,last_name',
                    'fromEmployee:id,first_name,middle_name,last_name',
                ])
                ->latest('movement_date')
                ->latest('id')
                ->limit(6)
                ->get()
                ->map(fn ($m) => [
                    'id'             => $m->id,
                    'type'           => $m->type,
                    'movement_date'  => $m->movement_date?->format('Y-m-d'),
                    'asset_tag'      => $m->asset?->asset_tag,
                    'asset_id'       => $m->asset_id,
                    'to_employee'    => $m->toEmployee ? ['full_name' => $m->toEmployee->full_name] : null,
                    'from_employee'  => $m->fromEmployee ? ['full_name' => $m->fromEmployee->full_name] : null,
                ]),
            'charts' => [
                'movements'   => $days,
                'by_category' => $byCategory,
                'by_status'   => [
                    'in_stock'   => (int) ($byStatus['in_stock'] ?? 0),
                    'assigned'   => (int) ($byStatus['assigned'] ?? 0),
                    'for_repair' => (int) ($byStatus['for_repair'] ?? 0),
                    'defective'  => (int) ($byStatus['defective'] ?? 0),
                    'retired'    => (int) ($byStatus['retired'] ?? 0),
                    'replaced'   => (int) ($byStatus['replaced'] ?? 0),
                ],
                'warranty' => [
                    'active'        => $warrantyActive,
                    'expiring_soon' => $warrantyExpiringSoon,
                    'expired'       => $warrantyExpired,
                ],
            ],
        ]);
    })->name('dashboard');

    Route::get('assets/bulk-receive',     [AssetController::class, 'bulkReceiveForm'])->name('assets.bulkReceive');
    Route::post('assets/bulk-receive',    [AssetController::class, 'bulkStore'])->name('assets.bulkStore');
    Route::resource('assets',             AssetController::class);
    Route::post('assets/{asset}/issue',    [AssetMovementController::class, 'issue'])->name('assets.issue');
    Route::post('assets/{asset}/return',   [AssetMovementController::class, 'returnFromHolder'])->name('assets.return');
    Route::post('assets/{asset}/transfer', [AssetMovementController::class, 'transfer'])->name('assets.transfer');
    Route::patch('assets/{asset}/movements/{movement}',  [AssetMovementController::class, 'updateMovement'])->name('assets.movements.update');
    Route::delete('assets/{asset}/movements/{movement}', [AssetMovementController::class, 'destroyMovement'])->name('assets.movements.destroy');

    Route::resource('departments', DepartmentController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('brands',      BrandController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('locations',   LocationController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('categories',  CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('conditions',  ConditionController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('employees',   EmployeeController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('users',       UserController::class)->only(['index', 'store', 'update', 'destroy']);
});
