<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_permits', function (Blueprint $table) {
            $table->id();
            $table->string('permit_no', 50)->unique();

            // Requester / context
            $table->foreignId('employee_id')->constrained('employees');
            $table->string('destination', 255);
            $table->string('purpose', 255);
            $table->date('date_borrow');
            $table->date('date_return');
            $table->date('valid_from');
            $table->date('valid_to');

            // Signatures (system users / employees)
            $table->foreignId('requested_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('noted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('noted_by_secondary_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('approval_note', 255)->nullable();

            $table->string('status', 20)->default('draft'); // draft, approved, returned, cancelled

            $table->timestamps();
        });

        Schema::create('asset_permit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_permit_id')->constrained('asset_permits')->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->unsignedInteger('qty')->default(1);
            $table->string('unit', 20)->default('PC');
            $table->string('description', 255);
            $table->string('serial_no', 100)->nullable();
            $table->string('remarks', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_permit_items');
        Schema::dropIfExists('asset_permits');
    }
};
