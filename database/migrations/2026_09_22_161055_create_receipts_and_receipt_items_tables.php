<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $t) {
            $t->id();

            $t->foreignId('entity_id')
                ->constrained('entities')
                ->cascadeOnDelete();

            $t->foreignId('business_unit_id')
                ->constrained('business_units')
                ->restrictOnDelete();

            $t->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->restrictOnDelete();

            $t->foreignId('supplier_id')
                ->nullable()
                ->constrained('suppliers')
                ->nullOnDelete();

            $t->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $t->string('receipt_no');
            $t->dateTime('receipt_date');
            $t->text('memo')->nullable();
            $t->string('status')->default('draft');

            $t->timestamps();

            $t->unique(['entity_id', 'receipt_no']);

            $t->index(
                ['entity_id', 'business_unit_id', 'receipt_date'],
                'receipts_entity_bu_date_idx'
            );

            $t->index(
                ['entity_id', 'warehouse_id', 'receipt_date'],
                'receipts_entity_warehouse_date_idx'
            );

            $t->index(
                ['entity_id', 'supplier_id', 'receipt_date'],
                'receipts_entity_supplier_date_idx'
            );
        });

        Schema::create('receipt_items', function (Blueprint $t) {
            $t->id();

            $t->foreignId('receipt_id')
                ->constrained('receipts')
                ->cascadeOnDelete();

            $t->foreignId('purchase_item_id')
                ->constrained('purchase_items')
                ->restrictOnDelete();

            $t->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            $t->foreignId('unit_id')
                ->nullable()
                ->constrained('units')
                ->restrictOnDelete();

            $t->decimal('qty', 18, 3);
            $t->decimal('conversion_factor', 18, 6)->default(1);
            $t->decimal('base_qty', 18, 6)->default(0);

            $t->timestamps();

            $t->index(
                ['receipt_id', 'purchase_item_id'],
                'receipt_items_receipt_purchase_item_idx'
            );

            $t->index(
                ['product_id'],
                'receipt_items_product_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_items');
        Schema::dropIfExists('receipts');
    }
};
