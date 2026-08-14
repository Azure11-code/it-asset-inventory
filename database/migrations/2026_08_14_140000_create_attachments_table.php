<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');            // attachable_type + attachable_id + index
            $table->string('disk', 20)->default('local');
            $table->string('path');                   // stored path relative to disk
            $table->string('original_name');          // original filename shown to user
            $table->string('mime_type', 120)->nullable();
            $table->unsignedInteger('size_bytes')->nullable();
            $table->string('label', 255)->nullable();  // optional short description
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
