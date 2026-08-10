<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_part_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('part_name', 100);
            $table->string('old_value', 255)->nullable();
            $table->string('new_value', 255);
            $table->string('reason', 100)->nullable();
            $table->date('changed_at');
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_part_changes');
    }
};
