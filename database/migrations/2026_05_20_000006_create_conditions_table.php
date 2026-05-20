<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create conditions table
        Schema::create('conditions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->string('tone', 20)->default('slate');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Seed defaults matching the previous enum
        $now = now();
        DB::table('conditions')->insert([
            ['name' => 'New',       'slug' => 'new',       'tone' => 'emerald', 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Good',      'slug' => 'good',      'tone' => 'sky',     'sort_order' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Fair',      'slug' => 'fair',      'tone' => 'amber',   'sort_order' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Poor',      'slug' => 'poor',      'tone' => 'amber',   'sort_order' => 4, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Defective', 'slug' => 'defective', 'tone' => 'rose',    'sort_order' => 5, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // 3. Add condition_id to assets
        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('condition_id')->nullable()->after('condition')->constrained('conditions')->nullOnDelete();
        });

        // 4. Backfill condition_id from the existing enum column
        $slugToId = DB::table('conditions')->pluck('id', 'slug');
        foreach ($slugToId as $slug => $id) {
            DB::table('assets')->where('condition', $slug)->update(['condition_id' => $id]);
        }

        // 5. Drop the old enum column
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('condition');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->enum('condition', ['new', 'good', 'fair', 'poor', 'defective'])->default('new')->after('condition_id');
        });

        // Restore old enum from condition_id
        $slugById = DB::table('conditions')->pluck('slug', 'id');
        foreach ($slugById as $id => $slug) {
            DB::table('assets')->where('condition_id', $id)->update(['condition' => $slug]);
        }

        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['condition_id']);
            $table->dropColumn('condition_id');
        });

        Schema::dropIfExists('conditions');
    }
};
