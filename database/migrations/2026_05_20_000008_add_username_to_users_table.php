<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
        });

        // Backfill: use part before @ in email as a default username
        foreach (DB::table('users')->get(['id', 'email']) as $u) {
            $candidate = strtolower(explode('@', $u->email)[0]);
            // Avoid collisions
            $username = $candidate;
            $i = 1;
            while (DB::table('users')->where('username', $username)->exists()) {
                $username = $candidate . $i++;
            }
            DB::table('users')->where('id', $u->id)->update(['username' => $username]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
