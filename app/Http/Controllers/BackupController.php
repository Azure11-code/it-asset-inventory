<?php

namespace App\Http\Controllers;

use App\Services\DatabaseBackupService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BackupController extends Controller
{
    public function index(DatabaseBackupService $service)
    {
        return Inertia::render('Backups/Index', [
            'backups'   => $service->listBackups(),
            'retention' => DatabaseBackupService::RETENTION,
        ]);
    }

    public function storeSql(DatabaseBackupService $service)
    {
        $name = $service->createSql();
        return back()->with('success', "SQL backup created: {$name}");
    }

    public function storeFull(DatabaseBackupService $service)
    {
        $name = $service->createFullZip();
        return back()->with('success', "Full backup created: {$name}");
    }

    public function download(string $filename, DatabaseBackupService $service)
    {
        $path = $service->pathFor($filename);
        abort_unless($path, 404);
        return response()->download($path);
    }

    public function destroy(string $filename, DatabaseBackupService $service)
    {
        $service->delete($filename);
        return back()->with('success', "Backup deleted: {$filename}");
    }
}
