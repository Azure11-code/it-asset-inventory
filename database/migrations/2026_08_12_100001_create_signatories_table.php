<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signatories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('title')->nullable(); // e.g. "Junior IT Associate", "MIS Head"
            $table->enum('role', ['checked_by', 'reviewed_by', 'approved_by']);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['role', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signatories');
    }
};
