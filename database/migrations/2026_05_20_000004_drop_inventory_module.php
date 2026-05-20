<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove the FK on assets first so we can drop the inventories table
        if (Schema::hasColumn('assets', 'received_from_inventory_id')) {
            Schema::table('assets', function (Blueprint $table) {
                $table->dropForeign(['received_from_inventory_id']);
                $table->dropColumn('received_from_inventory_id');
            });
        }

        Schema::dropIfExists('inventory_details');
        Schema::dropIfExists('inventories');
    }

    public function down(): void
    {
        // Intentionally minimal — recreating these tables for rollback is
        // out of scope. Restore from a database backup if needed.
        Schema::table('assets', function (Blueprint $table) {
            $table->unsignedBigInteger('received_from_inventory_id')->nullable()->after('purchase_cost');
        });
    }
};
