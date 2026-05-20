<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained('inventories')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->string('asset_tag')->nullable()->unique();
            $table->string('serial_number')->nullable();
            $table->string('model')->nullable();
            $table->string('description')->nullable();
            $table->json('specifications')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->date('warranty_until')->nullable();
            $table->enum('condition', ['new', 'good', 'fair', 'poor', 'defective'])->default('new');
            $table->enum('status', ['in_stock', 'assigned', 'returned', 'disposed', 'lost'])->default('in_stock');
            $table->timestamps();

            $table->index(['inventory_id', 'status']);
            $table->index('serial_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_details');
    }
};
