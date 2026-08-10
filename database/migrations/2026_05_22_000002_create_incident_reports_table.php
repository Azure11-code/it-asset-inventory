<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_reports', function (Blueprint $table) {
            $table->id();
            $table->string('ir_no', 50)->unique();

            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->foreignId('end_user_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('end_user_name', 255)->nullable();

            $table->string('reported_problem', 500);
            $table->text('action_taken');     // newline-separated bullets
            $table->text('findings');         // newline-separated bullets
            $table->text('recommendation');   // newline-separated bullets

            $table->foreignId('prepared_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('noted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('noted_by_secondary_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->date('report_date');
            $table->string('status', 20)->default('draft'); // draft, submitted, approved, closed
            $table->timestamps();
        });

        Schema::table('asset_part_changes', function (Blueprint $table) {
            $table->foreignId('incident_report_id')->nullable()->after('asset_id')
                ->constrained('incident_reports')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('asset_part_changes', function (Blueprint $table) {
            $table->dropForeign(['incident_report_id']);
            $table->dropColumn('incident_report_id');
        });

        Schema::dropIfExists('incident_reports');
    }
};
