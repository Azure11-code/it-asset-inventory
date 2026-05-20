<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag')->unique();
            $table->string('serial_number')->nullable()->index();
            $table->string('model')->nullable();
            $table->string('description')->nullable();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 12, 2)->nullable();
            $table->unsignedTinyInteger('expected_lifespan_years')->default(5);
            $table->date('warranty_until')->nullable();
            $table->enum('condition', ['new', 'good', 'fair', 'poor', 'defective'])->default('new');
            $table->enum('current_status', [
                'in_stock', 'assigned', 'for_repair', 'defective', 'retired', 'replaced',
            ])->default('in_stock');
            $table->foreignId('current_holder_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('current_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('received_from_inventory_id')->nullable()->constrained('inventories')->nullOnDelete();
            $table->foreignId('replaced_by_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->foreignId('replaces_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['current_status', 'current_holder_id']);
            $table->index('warranty_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
