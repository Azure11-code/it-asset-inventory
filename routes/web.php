<?php

use App\Http\Controllers\AiChatController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AccountabilityController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AssetCodeRuleController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SignatoryController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AssetMovementController;
use App\Http\Controllers\AssetPartChangeController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ConditionController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\IncidentReportController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\PermitController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\UserController;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Category;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
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

        // Ids ride along with every tally so the dashboard can link each figure
        // to the matching filtered asset list.
        $byCategory = Asset::query()
            ->join('categories', 'assets.category_id', '=', 'categories.id')
            ->select('categories.id', 'categories.name', DB::raw('COUNT(*) as count'))
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => ['id' => (int) $r->id, 'name' => $r->name, 'count' => (int) $r->count])
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

        // ── Maintained Asset: everything except retired/replaced ──
        // Same list the assets filter uses, so the tile and its link agree.
        $maintainedCount = Asset::whereNotIn('current_status', \App\Http\Controllers\AssetController::RETIRED_STATUSES)->count();

        // ── Endpoint protection status from JSON specifications (No / Yes / Excluded) ──
        // Any named AV product counts as installed. Kept generic — not tied to one vendor.
        $endpointProtection = ['No' => 0, 'Yes' => 0, 'Excluded' => 0, '—' => 0];
        Asset::select('id', 'specifications')->chunk(500, function ($chunk) use (&$endpointProtection) {
            foreach ($chunk as $a) {
                $val = collect($a->specifications ?? [])
                    ->first(fn ($s) => is_array($s) && strcasecmp($s['key'] ?? '', 'Antivirus') === 0);
                $raw = is_array($val) ? trim((string) ($val['value'] ?? '')) : '';
                if ($raw === '') { $endpointProtection['—']++; continue; }
                $lc = strtolower($raw);
                if ($lc === 'yes' || $lc === 'y') $endpointProtection['Yes']++;
                elseif ($lc === 'excluded') $endpointProtection['Excluded']++;
                elseif ($lc === 'no' || $lc === 'n' || $lc === 'none' || $lc === '-') $endpointProtection['No']++;
                else $endpointProtection['Yes']++; // any other named product counts as installed
            }
        });

        // ── Count per department (Category × Department matrix) ──
        // ── Assets per Department ──
        // Locations that are split into departments (the head office) report
        // one bar per department; every other location reports a single bar
        // under its own name. Each bar links to the list it counts.
        $byDeptRows = Asset::query()
            ->join('locations', 'assets.current_location_id', '=', 'locations.id')
            ->where('locations.has_departments', true)
            ->join('departments', 'assets.department_id', '=', 'departments.id')
            ->select('departments.id', 'departments.name', DB::raw('COUNT(*) as count'))
            ->groupBy('departments.id', 'departments.name')
            ->get()
            ->map(fn ($r) => [
                'name'   => $r->name,
                'count'  => (int) $r->count,
                'filter' => ['department_id' => (int) $r->id],
            ]);

        $byLocationRows = Asset::query()
            ->join('locations', 'assets.current_location_id', '=', 'locations.id')
            ->where('locations.has_departments', false)
            ->select('locations.id', 'locations.name', DB::raw('COUNT(*) as count'))
            ->groupBy('locations.id', 'locations.name')
            ->get()
            ->map(fn ($r) => [
                'name'   => $r->name,
                'count'  => (int) $r->count,
                'filter' => ['location_id' => (int) $r->id],
            ]);

        // Safety net: an asset with no location, or sitting at the head office
        // with no department, belongs to neither group above. Normally there
        // are none, and then no bar is drawn — but they must never go missing
        // from the chart, or the bars would quietly stop adding up.
        $unplacedCount = Asset::query()
            ->leftJoin('locations', 'assets.current_location_id', '=', 'locations.id')
            ->where(function ($q) {
                $q->whereNull('assets.current_location_id')
                  ->orWhere(fn ($w) => $w->where('locations.has_departments', true)
                                         ->whereNull('assets.department_id'));
            })
            ->count();

        $byDept = $byDeptRows->concat($byLocationRows);

        if ($unplacedCount > 0) {
            $byDept = $byDept->push([
                'name'   => 'Unassigned',
                'count'  => $unplacedCount,
                'filter' => ['placement' => 'unassigned'],
            ]);
        }

        $byDept = $byDept->sortByDesc('count')->values();

        // ── Category × Department pivot ──
        $catDeptRows = Asset::query()
            ->leftJoin('categories', 'assets.category_id', '=', 'categories.id')
            ->leftJoin('departments', 'assets.department_id', '=', 'departments.id')
            ->select('categories.name as category', 'departments.name as department', DB::raw('COUNT(*) as count'))
            ->groupBy('categories.name', 'departments.name')
            ->get();
        // Columns and rows carry their ids so each pivot cell can link to the
        // assets behind it; the cells themselves stay keyed by name.
        $categories     = Category::orderBy('name')->get(['id', 'name']);
        $departments    = Department::orderBy('name')->get(['id', 'name']);
        $categoryList   = $categories->pluck('name')->all();
        $departmentList = $departments->pluck('name')->all();

        $catDeptMatrix = [];
        foreach ($categories as $cat) {
            $row = [
                'category'    => $cat->name,
                'category_id' => $cat->id,
                'total'       => 0,
                'cells'       => array_fill_keys($departmentList, 0),
            ];
            foreach ($catDeptRows->where('category', $cat->name) as $cell) {
                if ($cell->department && in_array($cell->department, $departmentList, true)) {
                    $row['cells'][$cell->department] = (int) $cell->count;
                    $row['total'] += (int) $cell->count;
                }
            }
            if ($row['total'] > 0) $catDeptMatrix[] = $row;
        }

        // ── Location × Category pivot ──
        $locCatRows = Asset::query()
            ->leftJoin('categories', 'assets.category_id', '=', 'categories.id')
            ->leftJoin('locations',  'assets.current_location_id', '=', 'locations.id')
            ->select('locations.name as location', 'categories.name as category', DB::raw('COUNT(*) as count'))
            ->groupBy('locations.name', 'categories.name')
            ->get();
        $locations    = Location::orderBy('name')->get(['id', 'name']);
        $locCatMatrix = [];
        foreach ($locations as $loc) {
            $row = [
                'location'    => $loc->name,
                'location_id' => $loc->id,
                'total'       => 0,
                'cells'       => array_fill_keys($categoryList, 0),
            ];
            foreach ($locCatRows->where('location', $loc->name) as $cell) {
                if ($cell->category && in_array($cell->category, $categoryList, true)) {
                    $row['cells'][$cell->category] = (int) $cell->count;
                    $row['total'] += (int) $cell->count;
                }
            }
            if ($row['total'] > 0) $locCatMatrix[] = $row;
        }

        return Inertia::render('Dashboard', [
            'stats' => [
                'total_assets'   => Asset::count(),
                'maintained'     => $maintainedCount,
                'assigned'       => (int) ($byStatus['assigned'] ?? 0),
                'in_stock'       => (int) ($byStatus['in_stock'] ?? 0),
                'employees'      => Employee::where('status', 'active')->count(),
                'endpoint_protection' => $endpointProtection,
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
                'movements'    => $days,
                'by_category'  => $byCategory,
                'by_department'=> $byDept,
                'cat_dept'     => ['departments' => $departments, 'rows' => $catDeptMatrix],
                'loc_cat'      => ['categories'  => $categories,  'rows' => $locCatMatrix],
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

    // ── Dashboard pivot exports ──
    Route::get('dashboard/exports/cat-dept.xlsx', function (\App\Services\PivotXlsxExporter $exporter) {
        $rowsRaw = Asset::query()
            ->leftJoin('categories',  'assets.category_id',   '=', 'categories.id')
            ->leftJoin('departments', 'assets.department_id', '=', 'departments.id')
            ->select('categories.name as category', 'departments.name as department', DB::raw('COUNT(*) as count'))
            ->groupBy('categories.name', 'departments.name')->get();
        $categories  = Category::orderBy('name')->pluck('name')->all();
        $departments = Department::orderBy('name')->pluck('name')->all();
        $rows = [];
        foreach ($categories as $cat) {
            $cells = array_fill_keys($departments, 0); $total = 0;
            foreach ($rowsRaw->where('category', $cat) as $r) {
                if ($r->department && in_array($r->department, $departments, true)) {
                    $cells[$r->department] = (int) $r->count; $total += (int) $r->count;
                }
            }
            if ($total > 0) $rows[] = ['label' => $cat, 'cells' => $cells, 'total' => $total];
        }
        return $exporter->stream('Category × Department', 'Category', $departments, $rows,
            'category-x-department-' . now()->format('Ymd-His') . '.xlsx');
    })->name('dashboard.export.catDept')->middleware('perm:assets,export');

    Route::get('dashboard/exports/loc-cat.xlsx', function (\App\Services\PivotXlsxExporter $exporter) {
        $rowsRaw = Asset::query()
            ->leftJoin('categories', 'assets.category_id',          '=', 'categories.id')
            ->leftJoin('locations',  'assets.current_location_id',  '=', 'locations.id')
            ->select('locations.name as location', 'categories.name as category', DB::raw('COUNT(*) as count'))
            ->groupBy('locations.name', 'categories.name')->get();
        $categories = Category::orderBy('name')->pluck('name')->all();
        $locations  = Location::orderBy('name')->pluck('name')->all();
        $rows = [];
        foreach ($locations as $loc) {
            $cells = array_fill_keys($categories, 0); $total = 0;
            foreach ($rowsRaw->where('location', $loc) as $r) {
                if ($r->category && in_array($r->category, $categories, true)) {
                    $cells[$r->category] = (int) $r->count; $total += (int) $r->count;
                }
            }
            if ($total > 0) $rows[] = ['label' => $loc, 'cells' => $cells, 'total' => $total];
        }
        return $exporter->stream('Location × Category', 'Location', $categories, $rows,
            'location-x-category-' . now()->format('Ymd-His') . '.xlsx');
    })->name('dashboard.export.locCat')->middleware('perm:assets,export');

    Route::get('assets/bulk-receive',     [AssetController::class, 'bulkReceiveForm'])->name('assets.bulkReceive');
    Route::post('assets/bulk-receive',    [AssetController::class, 'bulkStore'])->name('assets.bulkStore');
    Route::get('assets/export',           [AssetController::class, 'export'])->name('assets.export');
    Route::get('assets/import-template',  [AssetController::class, 'importTemplate'])->name('assets.importTemplate');
    Route::post('assets/import',          [AssetController::class, 'import'])->name('assets.import');
    Route::resource('assets',             AssetController::class);
    Route::post('assets/{asset}/issue',    [AssetMovementController::class, 'issue'])->name('assets.issue');
    Route::post('assets/{asset}/return',   [AssetMovementController::class, 'returnFromHolder'])->name('assets.return');
    Route::post('assets/{asset}/transfer', [AssetMovementController::class, 'transfer'])->name('assets.transfer');
    Route::patch('assets/{asset}/movements/{movement}',  [AssetMovementController::class, 'updateMovement'])->name('assets.movements.update');
    Route::delete('assets/{asset}/movements/{movement}', [AssetMovementController::class, 'destroyMovement'])->name('assets.movements.destroy');

    Route::post('assets/{asset}/part-changes',                       [AssetPartChangeController::class, 'store'])->name('assets.part-changes.store');
    Route::patch('assets/{asset}/part-changes/{partChange}',         [AssetPartChangeController::class, 'update'])->name('assets.part-changes.update');
    Route::delete('assets/{asset}/part-changes/{partChange}',        [AssetPartChangeController::class, 'destroy'])->name('assets.part-changes.destroy');

    Route::resource('departments', DepartmentController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('brands',      BrandController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('locations',   LocationController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('categories',  CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('conditions',  ConditionController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('asset-code-rules', AssetCodeRuleController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['asset-code-rules' => 'rule']);
    Route::get('asset-code-rules/{rule}/next', [AssetCodeRuleController::class, 'next'])->name('asset-code-rules.next');
    Route::resource('employees',   EmployeeController::class)->only(['index', 'store', 'update', 'destroy', 'show']);
    Route::get('employees/{employee}/assets', [EmployeeController::class, 'assets'])->name('employees.assets');
    Route::get('employees/{employee}/accountability.docx', [EmployeeController::class, 'accountability'])->name('employees.accountability');
    Route::resource('signatories', SignatoryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::middleware('admin')->group(function () {
        Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
    });

    Route::resource('permits',          PermitController::class)->parameters(['permits' => 'permit']);
    Route::resource('incidents',        IncidentReportController::class)->parameters(['incidents' => 'incident']);
    Route::resource('recommendations',  RecommendationController::class);

    Route::get('accountability',                            [AccountabilityController::class, 'index'])->name('accountability.index');
    Route::get('accountability/{employee}/download',        [AccountabilityController::class, 'download'])->name('accountability.download');
    Route::get('accountability/{employee}/attachments',     [AccountabilityController::class, 'attachments'])->name('accountability.attachments');

    // Files gallery — grid view of every uploaded attachment across all entities
    Route::get('gallery',                                   [GalleryController::class, 'index'])->name('gallery.index');

    // Backups (admin only)
    Route::middleware('admin')->group(function () {
        Route::get('backups',                     [BackupController::class, 'index'])->name('backups.index');
        Route::post('backups/sql',                [BackupController::class, 'storeSql'])->name('backups.storeSql');
        Route::post('backups/full',               [BackupController::class, 'storeFull'])->name('backups.storeFull');
        Route::get('backups/{filename}/download', [BackupController::class, 'download'])->name('backups.download');
        Route::delete('backups/{filename}',       [BackupController::class, 'destroy'])->name('backups.destroy');
    });

    // Global search
    Route::get('search',            [SearchController::class, 'page'])->name('search.page');
    Route::get('search/suggest',    [SearchController::class, 'suggest'])->name('search.suggest');

    // AI Assistant
    Route::post('ai/chat',          [AiChatController::class, 'chat'])->name('ai.chat');

    // Polymorphic attachments (supports recommendations, incidents, permits, accountability)
    Route::post('attachments/{entity}/{id}',                              [AttachmentController::class, 'store'])->name('attachments.store');
    Route::get('attachments/{entity}/{id}/{attachment}/download',         [AttachmentController::class, 'download'])->name('attachments.download');
    Route::get('attachments/{entity}/{id}/{attachment}/preview',          [AttachmentController::class, 'preview'])->name('attachments.preview');
    Route::delete('attachments/{entity}/{id}/{attachment}',               [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    Route::get('permits/{permit}/docx',                  [PermitController::class, 'docx'])->name('permits.docx');
    Route::get('incidents/{incident}/docx',              [IncidentReportController::class, 'docx'])->name('incidents.docx');
    Route::get('recommendations/{recommendation}/docx',  [RecommendationController::class, 'docx'])->name('recommendations.docx');

    Route::get('/docs', fn () => Inertia::render('Docs/Index'))->name('docs');

    // ── Mobile scan flow ──
    Route::get('scan',        [ScanController::class, 'index'])->name('scan.index');
    Route::get('scan/lookup', [ScanController::class, 'lookup'])->name('scan.lookup');
});
