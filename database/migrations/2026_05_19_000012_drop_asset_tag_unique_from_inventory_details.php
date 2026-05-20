<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_details', function (Blueprint $table) {
            $table->dropUnique('inventory_details_asset_tag_unique');
            $table->index('asset_tag');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_details', function (Blueprint $table) {
            $table->dropIndex(['asset_tag']);
            $table->unique('asset_tag');
        });
    }
};
