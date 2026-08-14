<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

class DatabaseBackupService
{
    public const RETENTION = 5;

    public function backupsDir(): string
    {
        $dir = storage_path('app/backups');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    public function createSql(): string
    {
        $sql = $this->dumpDatabase();
        $filename = 'sql-' . Carbon::now()->format('Ymd_His') . '.sql';
        $path = $this->backupsDir() . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($path, $sql);
        $this->prune('sql');
        return $filename;
    }

    public function createFullZip(): string
    {
        $sql = $this->dumpDatabase();
        $filename = 'full-' . Carbon::now()->format('Ymd_His') . '.zip';
        $path = $this->backupsDir() . DIRECTORY_SEPARATOR . $filename;

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create zip file.');
        }
        $zip->addFromString('database.sql', $sql);

        $storageRoot = storage_path('app');
        if (is_dir($storageRoot)) {
            $backupsAbsolute = $this->backupsDir();
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($storageRoot, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $file) {
                if ($file->isDir()) continue;
                $absolute = $file->getPathname();
                if (str_starts_with($absolute, $backupsAbsolute)) continue;
                $relative = 'storage/' . ltrim(str_replace($storageRoot, '', $absolute), DIRECTORY_SEPARATOR . '/');
                $relative = str_replace('\\', '/', $relative);
                $zip->addFile($absolute, $relative);
            }
        }
        $zip->close();

        $this->prune('full');
        return $filename;
    }

    public function listBackups(): array
    {
        $files = glob($this->backupsDir() . DIRECTORY_SEPARATOR . '*') ?: [];
        $rows = [];
        foreach ($files as $path) {
            if (!is_file($path)) continue;
            $name = basename($path);
            $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $type = str_starts_with($name, 'full-') ? 'full' : ($ext === 'sql' ? 'sql' : 'other');
            $rows[] = [
                'name'       => $name,
                'type'       => $type,
                'size_bytes' => filesize($path),
                'created_at' => Carbon::createFromTimestamp(filemtime($path))->format('Y-m-d H:i:s'),
            ];
        }
        usort($rows, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));
        return $rows;
    }

    public function pathFor(string $filename): ?string
    {
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $filename)) return null;
        $path = $this->backupsDir() . DIRECTORY_SEPARATOR . $filename;
        return is_file($path) ? $path : null;
    }

    public function delete(string $filename): bool
    {
        $path = $this->pathFor($filename);
        return $path && unlink($path);
    }

    private function prune(string $type): void
    {
        $prefix = $type . '-';
        $files = glob($this->backupsDir() . DIRECTORY_SEPARATOR . $prefix . '*') ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        foreach (array_slice($files, self::RETENTION) as $stale) {
            @unlink($stale);
        }
    }

    private function dumpDatabase(): string
    {
        $pdo    = DB::connection()->getPdo();
        $dbName = DB::connection()->getDatabaseName();
        $tables = array_map(
            fn ($row) => (array) $row,
            DB::select('SHOW TABLES')
        );
        $tableKey = "Tables_in_{$dbName}";

        $sql  = "-- IT Asset Inventory Database Backup\n";
        $sql .= "-- Database: {$dbName}\n";
        $sql .= "-- Generated: " . Carbon::now()->format('Y-m-d H:i:s') . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $sql .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
        $sql .= "SET NAMES utf8mb4;\n\n";

        foreach ($tables as $row) {
            $table = $row[$tableKey] ?? array_values($row)[0];
            $sql .= $this->dumpTable($pdo, $table);
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        return $sql;
    }

    private function dumpTable(\PDO $pdo, string $table): string
    {
        $out  = "-- ─────────────────────────────────────────────\n";
        $out .= "-- Table: `{$table}`\n";
        $out .= "-- ─────────────────────────────────────────────\n";
        $out .= "DROP TABLE IF EXISTS `{$table}`;\n";

        $create = DB::select("SHOW CREATE TABLE `{$table}`");
        $createRow = (array) $create[0];
        $out .= ($createRow['Create Table'] ?? array_values($createRow)[1] ?? '') . ";\n\n";

        $count = (int) DB::table($table)->count();
        if ($count === 0) return $out . "\n";

        $columns = null;
        DB::table($table)->orderBy(DB::raw('1'))->chunk(500, function ($rows) use (&$columns, $pdo, $table, &$out) {
            if ($columns === null) {
                $columns = array_keys((array) $rows->first());
            }
            $columnsList = '`' . implode('`, `', $columns) . '`';
            $valuesParts = [];
            foreach ($rows as $r) {
                $arr = (array) $r;
                $vals = [];
                foreach ($columns as $col) {
                    $v = $arr[$col];
                    if (is_null($v))          $vals[] = 'NULL';
                    elseif (is_int($v))       $vals[] = (string) $v;
                    elseif (is_float($v))     $vals[] = (string) $v;
                    elseif (is_bool($v))      $vals[] = $v ? '1' : '0';
                    else                      $vals[] = $pdo->quote((string) $v);
                }
                $valuesParts[] = '(' . implode(', ', $vals) . ')';
            }
            $out .= "INSERT INTO `{$table}` ({$columnsList}) VALUES\n" . implode(",\n", $valuesParts) . ";\n\n";
        });

        return $out;
    }
}
