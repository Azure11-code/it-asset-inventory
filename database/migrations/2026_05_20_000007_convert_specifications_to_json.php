<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve any existing text values by splitting on newlines into an array
        $rows = DB::table('assets')->whereNotNull('specifications')->get(['id', 'specifications']);

        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('specifications');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->json('specifications')->nullable()->after('description');
        });

        foreach ($rows as $row) {
            $items = array_values(array_filter(array_map('trim', preg_split('/[\r\n]+/', (string) $row->specifications))));
            if (!empty($items)) {
                DB::table('assets')->where('id', $row->id)->update([
                    'specifications' => json_encode($items),
                ]);
            }
        }
    }

    public function down(): void
    {
        $rows = DB::table('assets')->whereNotNull('specifications')->get(['id', 'specifications']);

        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('specifications');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->text('specifications')->nullable()->after('description');
        });

        foreach ($rows as $row) {
            $items = json_decode($row->specifications, true) ?: [];
            DB::table('assets')->where('id', $row->id)->update([
                'specifications' => implode("\n", $items) ?: null,
            ]);
        }
    }
};
