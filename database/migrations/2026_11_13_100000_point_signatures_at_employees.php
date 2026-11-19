<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Signatures are people, not login accounts.
 *
 * Incident reports, recommendations and permits each carry "prepared by",
 * "noted by", "reviewed by" and "approved by". These pointed at `users`, which
 * holds only the handful of accounts that can sign in — one, in practice — so
 * the dropdowns were effectively empty. They now point at `employees`, which
 * is where the IT and Management staff who actually sign these documents live.
 *
 * The column names keep their *_user_id spelling. Renaming eleven columns
 * would touch every model, controller, form and export that reads them, and
 * each rename is a chance to miss one and break a signature silently. The
 * foreign keys below are the authority on what they reference.
 *
 * Existing values are cleared rather than guessed at: the only two in the
 * database point at the "Administrator" login, which is not an employee.
 */
return new class extends Migration
{
    /** table => columns that should reference employees */
    private const SIGNATURE_COLUMNS = [
        'incident_reports' => ['prepared_by_user_id', 'noted_by_user_id', 'noted_by_secondary_user_id', 'approved_by_user_id'],
        'recommendations'  => ['prepared_by_user_id', 'reviewed_by_user_id', 'noted_by_user_id'],
        'asset_permits'    => ['issued_by_user_id', 'noted_by_user_id', 'noted_by_secondary_user_id', 'approved_by_user_id'],
    ];

    public function up(): void
    {
        foreach (self::SIGNATURE_COLUMNS as $table => $columns) {
            // A user id is meaningless once the column points at employees.
            DB::table($table)->update(array_fill_keys($columns, null));

            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns) {
                foreach ($columns as $column) {
                    $blueprint->dropForeign("{$table}_{$column}_foreign");
                    $blueprint->foreign($column)->references('id')->on('employees')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::SIGNATURE_COLUMNS as $table => $columns) {
            DB::table($table)->update(array_fill_keys($columns, null));

            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns) {
                foreach ($columns as $column) {
                    $blueprint->dropForeign("{$table}_{$column}_foreign");
                    $blueprint->foreign($column)->references('id')->on('users')->nullOnDelete();
                }
            });
        }
    }
};
