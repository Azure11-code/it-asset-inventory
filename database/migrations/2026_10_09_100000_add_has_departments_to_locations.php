<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marks which locations are organised into departments.
 *
 * Head Office is split across departments; warehouses and sites are not. The
 * asset form asks for a department only at a location flagged here, so the
 * rule is data, not a hardcoded location name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->boolean('has_departments')->default(false)->after('is_active');
        });

        // Seed the flag for the obvious case so the feature works on day one;
        // it is editable per location from the Locations page afterwards.
        DB::table('locations')
            ->whereRaw('UPPER(name) LIKE ?', ['%HEAD OFFICE%'])
            ->update(['has_departments' => true]);

        // Backfill: a Head Office asset inherits its holder's department.
        // Only fills blanks — nothing already set is touched.
        DB::statement("
            UPDATE assets
              JOIN locations ON locations.id = assets.current_location_id
              JOIN employees ON employees.id = assets.current_holder_id
               SET assets.department_id = employees.department_id
             WHERE assets.department_id IS NULL
               AND employees.department_id IS NOT NULL
               AND locations.has_departments = 1
        ");

        // A department on an asset parked at a location without departments is
        // meaningless, so clear it.
        DB::statement("
            UPDATE assets
              JOIN locations ON locations.id = assets.current_location_id
               SET assets.department_id = NULL
             WHERE locations.has_departments = 0
        ");
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('has_departments');
        });
    }
};
