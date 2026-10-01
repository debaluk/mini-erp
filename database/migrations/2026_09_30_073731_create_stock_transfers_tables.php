<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignId('business_unit_id')->constrained('business_units')->restrictOnDelete();
            $table->string('transfer_no')->unique();
            $table->foreignId('from_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->date('transfer_date');
            $table->string('status')->default('draft');
            $table->text('memo')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_sender_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_sender_at')->nullable();
            $table->foreignId('approved_receiver_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_receiver_at')->nullable();
            $table->timestamps();

            $table->index(['business_unit_id', 'transfer_date']);
            $table->index(['status']);
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->timestamps();

            $table->index(['stock_transfer_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
    }
};
