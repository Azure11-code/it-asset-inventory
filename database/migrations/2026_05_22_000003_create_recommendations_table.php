<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->string('doc_no', 50)->unique();
            $table->date('report_date');

            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();

            // Requestor (hybrid)
            $table->foreignId('requestor_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('requestor_name', 255)->nullable();
            $table->string('requestor_employee_no', 50)->nullable();
            $table->string('requestor_position', 255)->nullable();
            $table->string('requestor_department', 255)->nullable();

            $table->string('thru', 255)->default('Information Technology Department');
            $table->string('subject', 255);
            $table->text('body'); // the justification paragraph
            $table->json('quick_specs')->nullable(); // [{label, value, note}, ...]

            // Signatures (Prepared / Reviewed / Noted-by-Exec)
            $table->foreignId('prepared_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('noted_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status', 20)->default('draft'); // draft, submitted, approved, closed
            $table->timestamps();
        });

        Schema::table('asset_part_changes', function (Blueprint $table) {
            $table->foreignId('recommendation_id')->nullable()->after('incident_report_id')
                ->constrained('recommendations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('asset_part_changes', function (Blueprint $table) {
            $table->dropForeign(['recommendation_id']);
            $table->dropColumn('recommendation_id');
        });

        Schema::dropIfExists('recommendations');
    }
};
