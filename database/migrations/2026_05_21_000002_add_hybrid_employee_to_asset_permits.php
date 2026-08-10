<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_permits', function (Blueprint $table) {
            // Drop FK so we can change to nullable, then re-add as nullable FK
            $table->dropForeign(['employee_id']);
        });

        Schema::table('asset_permits', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->change();
            $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();

            $table->string('employee_name', 255)->nullable()->after('employee_id');
            $table->string('position_text', 255)->nullable()->after('employee_name');
            $table->string('department_text', 255)->nullable()->after('position_text');
        });
    }

    public function down(): void
    {
        Schema::table('asset_permits', function (Blueprint $table) {
            $table->dropColumn(['employee_name', 'position_text', 'department_text']);
            $table->dropForeign(['employee_id']);
        });

        Schema::table('asset_permits', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable(false)->change();
            $table->foreign('employee_id')->references('id')->on('employees');
        });
    }
};
