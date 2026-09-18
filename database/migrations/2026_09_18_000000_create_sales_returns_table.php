<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales_returns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained();
            $t->foreignId('sale_id')->constrained('sales');
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('warehouse_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('return_no');
            $t->dateTime('return_date');
            $t->decimal('total', 18, 2)->default(0);
            $t->text('reason')->nullable();
            $t->string('status')->default('posted');
            $t->timestamps();
            $t->unique(['entity_id', 'return_no']);
        });

        Schema::create('sales_return_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sales_return_id')->constrained('sales_returns')->cascadeOnDelete();
            $t->foreignId('sale_item_id')->constrained('sale_items');
            $t->foreignId('product_id')->constrained();
            $t->decimal('qty', 18, 3);
            $t->decimal('unit_price', 18, 2);
            $t->decimal('return_value', 18, 2);
            $t->decimal('hpp_unit', 18, 4)->default(0);
            $t->decimal('hpp_total', 18, 2)->default(0);
            $t->string('condition')->default('good');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_items');
        Schema::dropIfExists('sales_returns');
    }
};
