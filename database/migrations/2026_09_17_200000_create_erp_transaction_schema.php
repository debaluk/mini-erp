<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cash_shifts', function (Blueprint $t) {
            $t->id(); $t->foreignId('entity_id')->constrained(); $t->foreignId('user_id')->constrained();
            $t->dateTime('opened_at'); $t->dateTime('closed_at')->nullable();
            $t->decimal('opening_cash',18,2)->default(0); $t->decimal('closing_cash',18,2)->default(0); $t->string('status')->default('open'); $t->timestamps();
        });
        Schema::create('sales', function (Blueprint $t) {
            $t->id(); $t->foreignId('entity_id')->constrained(); $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->constrained(); $t->foreignId('shift_id')->nullable()->constrained('cash_shifts')->nullOnDelete();
            $t->string('invoice_no'); $t->dateTime('sale_date'); $t->decimal('subtotal',18,2)->default(0); $t->decimal('discount',18,2)->default(0); $t->decimal('total',18,2)->default(0); $t->string('status')->default('posted'); $t->timestamps();
            $t->unique(['entity_id','invoice_no']);
        });
        Schema::create('sale_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('sale_id')->constrained()->cascadeOnDelete(); $t->foreignId('product_id')->constrained(); $t->decimal('qty',18,3); $t->decimal('unit_price',18,2); $t->decimal('discount',18,2)->default(0); $t->decimal('total',18,2); $t->timestamps();
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id(); $t->foreignId('entity_id')->constrained(); $t->foreignId('sale_id')->nullable()->constrained()->nullOnDelete(); $t->foreignId('user_id')->constrained();
            $t->dateTime('payment_date'); $t->string('method'); $t->decimal('amount',18,2); $t->string('reference')->nullable(); $t->timestamps();
        });
        Schema::create('purchases', function (Blueprint $t) {
            $t->id(); $t->foreignId('entity_id')->constrained(); $t->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete(); $t->foreignId('user_id')->constrained();
            $t->string('purchase_no'); $t->dateTime('purchase_date'); $t->decimal('subtotal',18,2)->default(0); $t->decimal('total',18,2)->default(0); $t->string('status')->default('received'); $t->timestamps();
            $t->unique(['entity_id','purchase_no']);
        });
        Schema::create('purchase_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('purchase_id')->constrained()->cascadeOnDelete(); $t->foreignId('product_id')->constrained(); $t->decimal('qty',18,3); $t->decimal('unit_cost',18,2); $t->decimal('total',18,2); $t->timestamps();
        });
        Schema::create('boms', function (Blueprint $t) {
            $t->id(); $t->foreignId('entity_id')->constrained(); $t->foreignId('product_id')->constrained(); $t->string('code'); $t->string('name'); $t->decimal('output_qty',18,3)->default(1); $t->boolean('is_active')->default(true); $t->timestamps();
        });
        Schema::create('bom_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('bom_id')->constrained()->cascadeOnDelete(); $t->foreignId('product_id')->constrained(); $t->decimal('qty',18,3); $t->timestamps();
        });
        Schema::create('productions', function (Blueprint $t) {
            $t->id(); $t->foreignId('entity_id')->constrained(); $t->foreignId('warehouse_id')->constrained(); $t->foreignId('bom_id')->constrained(); $t->foreignId('user_id')->constrained();
            $t->string('production_no'); $t->dateTime('production_date'); $t->decimal('qty',18,3); $t->decimal('total_cost',18,2)->default(0); $t->string('status')->default('posted'); $t->timestamps();
        });
        Schema::create('deliveries', function (Blueprint $t) {
            $t->id(); $t->foreignId('entity_id')->constrained(); $t->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete(); $t->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $t->string('delivery_no'); $t->dateTime('delivery_date'); $t->string('destination')->nullable(); $t->decimal('distance_km',18,2)->default(0); $t->string('status')->default('planned'); $t->timestamps();
        });
        Schema::create('vehicle_operations', function (Blueprint $t) {
            $t->id(); $t->foreignId('entity_id')->constrained(); $t->foreignId('vehicle_id')->constrained(); $t->date('operation_date'); $t->unsignedInteger('km_start')->default(0); $t->unsignedInteger('km_end')->default(0); $t->decimal('fuel_cost',18,2)->default(0); $t->decimal('other_cost',18,2)->default(0); $t->text('notes')->nullable(); $t->timestamps();
        });
        Schema::create('fleet_costs', function (Blueprint $t) {
            $t->id(); $t->foreignId('entity_id')->constrained(); $t->foreignId('vehicle_id')->constrained(); $t->date('cost_date'); $t->string('cost_type'); $t->decimal('amount',18,2); $t->string('description')->nullable(); $t->timestamps();
        });
        Schema::create('stock_opnames', function (Blueprint $t) {
            $t->id(); $t->foreignId('entity_id')->constrained(); $t->foreignId('warehouse_id')->constrained(); $t->foreignId('user_id')->constrained(); $t->date('opname_date'); $t->string('opname_no'); $t->string('status')->default('posted'); $t->timestamps();
        });
        Schema::create('stock_opname_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('stock_opname_id')->constrained()->cascadeOnDelete(); $t->foreignId('product_id')->constrained(); $t->decimal('system_qty',18,3); $t->decimal('actual_qty',18,3); $t->decimal('difference',18,3); $t->timestamps();
        });
    }
    public function down(): void
    {
        foreach (['stock_opname_items','stock_opnames','fleet_costs','vehicle_operations','deliveries','productions','bom_items','boms','purchase_items','purchases','payments','sale_items','sales','cash_shifts'] as $table) Schema::dropIfExists($table);
    }
};