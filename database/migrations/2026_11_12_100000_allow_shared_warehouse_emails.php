<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets warehouse staff share a station mailbox.
 *
 * Warehouse clerks and BDOs work a shared counter and share one address
 * (bacolodwhse@…, cebu_bdo@…), so email cannot be unique per employee. The
 * unique index is replaced with a plain index: lookups stay fast, duplicates
 * become possible.
 *
 * Personal addresses are still protected — EmployeeController rejects a
 * duplicate unless it matches the shared-mailbox pattern.
 *
 * No data is touched and no column is added or removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique('employees_email_unique');
            $table->index('email', 'employees_email_index');
        });
    }

    public function down(): void
    {
        // Reversible only while no address is actually shared; if duplicates
        // exist the unique index cannot be rebuilt and this will fail loudly
        // rather than discard rows.
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex('employees_email_index');
            $table->unique('email', 'employees_email_unique');
        });
    }
};
