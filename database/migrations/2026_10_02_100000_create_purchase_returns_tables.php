<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_returns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('business_unit_id')->constrained('business_units')->restrictOnDelete();
            $t->foreignId('purchase_id')->constrained('purchases')->restrictOnDelete();
            $t->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $t->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->string('return_no');
            $t->dateTime('return_date');
            $t->decimal('total', 18, 2)->default(0);
            $t->text('reason')->nullable();
            $t->string('status')->default('draft');
            $t->dateTime('posted_at')->nullable();
            $t->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['entity_id', 'return_no']);
            $t->index(['entity_id', 'purchase_id', 'return_date']);
        });

        Schema::create('purchase_return_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_return_id')->constrained('purchase_returns')->cascadeOnDelete();
            $t->foreignId('purchase_item_id')->constrained('purchase_items')->restrictOnDelete();
            $t->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $t->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $t->decimal('qty', 18, 3);
            $t->decimal('conversion_factor', 18, 6)->default(1);
            $t->decimal('base_qty', 18, 6);
            $t->decimal('unit_cost', 18, 4)->default(0);
            $t->decimal('total', 18, 2)->default(0);
            $t->string('condition')->default('good');
            $t->timestamps();
            $t->index(['purchase_item_id', 'purchase_return_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
    }
};
