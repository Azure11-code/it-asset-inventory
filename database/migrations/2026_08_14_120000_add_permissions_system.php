<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
        });

        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('resource', 50);
            $table->string('action', 20);
            $table->timestamps();
            $table->unique(['user_id', 'resource', 'action']);
            $table->index(['resource', 'action']);
        });

        // Promote the first (oldest) user to admin so the deployer keeps access.
        DB::table('users')->orderBy('id')->limit(1)->update(['is_admin' => true]);
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
