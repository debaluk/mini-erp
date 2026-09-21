<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ================================================================
        // CORE / IDENTITY
        // ================================================================
        Schema::create('entities', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->text('address')->nullable();
            $t->string('phone')->nullable();
            $t->string('email')->nullable();
            $t->string('npwp')->nullable();
            $t->string('nib')->nullable();
            $t->string('logo_path')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('business_units', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->string('code', 50);
            $t->string('name', 150);
            $t->enum('business_type', ['retail', 'production', 'service']);
            $t->enum('hpp_method', ['perpetual', 'periodic', 'direct_cost']);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['entity_id', 'code']);
            $t->index(['entity_id', 'business_type']);
        });

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->enum('role', ['owner', 'admin', 'kasir', 'inventori', 'akuntansi'])->default('admin');
            $t->foreignId('entity_id')->nullable()->constrained('entities')->nullOnDelete();
            $t->boolean('is_active')->default(true);
            $t->foreignId('default_business_unit_id')
                ->nullable()
                ->constrained('business_units')
                ->nullOnDelete();
            $t->rememberToken();
            $t->timestamps();
            $t->index(['entity_id', 'role']);
        });

        Schema::create('user_business_units', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('business_unit_id')->constrained('business_units')->cascadeOnDelete();
            $t->boolean('is_default')->default(false);
            $t->timestamps();
            $t->unique(['user_id', 'business_unit_id']);
            $t->index(['business_unit_id', 'user_id']);
        });

        // ================================================================
        // MASTER
        // ================================================================
        Schema::create('units', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->string('code');
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['entity_id', 'code']);
        });

        Schema::create('product_categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->timestamps();
            $t->unique(['entity_id', 'name']);
        });

        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->string('code');
            $t->string('name');
            $t->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $t->foreignId('base_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $t->enum('item_type', ['barang', 'jasa'])->default('barang');
            $t->enum('type', ['raw_material', 'merchandise', 'wip', 'finished_goods'])->default('merchandise');
            $t->string('sku')->nullable();
            $t->string('barcode')->nullable();
            $t->decimal('cost_price', 18, 4)->default(0);
            $t->decimal('selling_price', 18, 4)->default(0);
            $t->decimal('minimum_stock', 18, 3)->default(0);
            $t->boolean('manage_stock')->default(true);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['entity_id', 'code']);
            $t->index(['entity_id', 'sku']);
            $t->index(['entity_id', 'barcode']);
            $t->index(['entity_id', 'item_type']);
        });

        Schema::create('product_business_units', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $t->foreignId('business_unit_id')->constrained('business_units')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['product_id', 'business_unit_id']);
        });

        Schema::create('product_units', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $t->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $t->decimal('conversion_factor', 18, 6)->default(1);
            $t->boolean('is_default')->default(false);
            $t->timestamps();
            $t->unique(['product_id', 'unit_id']);
        });

        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->string('code');
            $t->string('name');
            $t->string('phone')->nullable();
            $t->text('address')->nullable();
            $t->decimal('credit_limit', 18, 2)->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['entity_id', 'code']);
        });

        Schema::create('suppliers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->string('code');
            $t->string('name');
            $t->string('phone')->nullable();
            $t->text('address')->nullable();
            $t->decimal('credit_limit', 18, 2)->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['entity_id', 'code']);
        });

        Schema::create('warehouses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('business_unit_id')->constrained('business_units')->restrictOnDelete();
            $t->string('code');
            $t->string('name');
            $t->string('type')->default('general');
            $t->text('address')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['entity_id', 'code']);
            $t->index(['entity_id', 'business_unit_id']);
        });

        Schema::create('vehicles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->string('code');
            $t->string('plate_number');
            $t->string('model')->nullable();
            $t->string('vehicle_type')->nullable();
            $t->decimal('capacity', 18, 3)->nullable();
            $t->decimal('acquisition_value', 18, 2)->default(0);
            $t->unsignedInteger('current_km')->default(0);
            $t->string('status')->default('active');
            $t->date('acquisition_date')->nullable();
            $t->timestamps();
            $t->unique(['entity_id', 'code']);
        });

        Schema::create('drivers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->string('code');
            $t->string('name');
            $t->string('phone')->nullable();
            $t->string('license_no')->nullable();
            $t->date('license_expiry')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['entity_id', 'code']);
        });

        Schema::create('tariffs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->string('code');
            $t->string('name');
            $t->string('tariff_type');
            $t->decimal('base_price', 18, 2)->default(0);
            $t->decimal('price_per_km', 18, 2)->default(0);
            $t->decimal('price_per_hour', 18, 2)->default(0);
            $t->decimal('minimum_charge', 18, 2)->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['entity_id', 'code']);
        });

        // ================================================================
        // ACCOUNTING MASTER
        // ================================================================
        Schema::create('chart_of_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->string('code');
            $t->string('name');
            $t->unsignedTinyInteger('level')->default(1);
            $t->enum('type', ['asset', 'liability', 'equity', 'revenue', 'cogs', 'expense']);
            $t->enum('normal_balance', ['debit', 'credit'])->default('debit');
            $t->foreignId('parent_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $t->boolean('is_postable')->default(false);
            $t->boolean('is_cash_bank')->default(false);
            $t->text('description')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['entity_id', 'code']);
            $t->index(['entity_id', 'level', 'is_active']);
            $t->index(['entity_id', 'parent_id']);
        });

        // ================================================================
        // INVENTORY
        // ================================================================
        Schema::create('warehouses_stocks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $t->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $t->decimal('qty', 18, 3)->default(0);
            $t->decimal('avg_cost', 18, 4)->default(0);
            $t->timestamps();
            $t->unique(['warehouse_id', 'product_id']);
        });

        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('business_unit_id')->constrained('business_units')->restrictOnDelete();
            $t->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $t->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $t->string('movement_type');
            $t->decimal('qty', 18, 3);
            $t->decimal('unit_cost', 18, 4)->default(0);
            $t->string('reference_type')->nullable();
            $t->unsignedBigInteger('reference_id')->nullable();
            $t->timestamp('occurred_at');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['entity_id', 'business_unit_id', 'occurred_at']);
            $t->index(['warehouse_id', 'product_id', 'occurred_at']);
        });

        Schema::create('item_initial_setups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('business_unit_id')->constrained('business_units')->restrictOnDelete();
            $t->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $t->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $t->date('setup_date');
            $t->decimal('purchase_price', 18, 4);
            $t->decimal('initial_stock', 18, 3);
            $t->decimal('markup_percent', 9, 4);
            $t->decimal('selling_price', 18, 4);
            $t->timestamps();
            $t->unique(['entity_id', 'business_unit_id', 'product_id']);
            $t->index(['entity_id', 'business_unit_id', 'setup_date']);
        });

        Schema::create('purchases', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('business_unit_id')->constrained('business_units')->restrictOnDelete();
            $t->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->string('purchase_no');
            $t->dateTime('purchase_date');
            $t->decimal('subtotal', 18, 2)->default(0);
            $t->decimal('discount', 18, 2)->default(0);
            $t->decimal('total', 18, 2)->default(0);
            $t->string('payment_method')->nullable();
            $t->date('due_date')->nullable();
            $t->string('supplier_invoice_no')->nullable();
            $t->date('supplier_invoice_date')->nullable();
            $t->text('memo')->nullable();
            $t->string('status')->default('received');
            $t->timestamps();
            $t->unique(['entity_id', 'purchase_no']);
            $t->index(['entity_id', 'business_unit_id', 'purchase_date']);
        });

        Schema::create('purchase_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $t->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $t->decimal('qty', 18, 3);
            $t->decimal('unit_cost', 18, 4);
            $t->decimal('discount', 18, 2)->default(0);
            $t->decimal('total', 18, 2);
            $t->timestamps();
        });

        Schema::create('purchase_price_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('business_unit_id')->constrained('business_units')->restrictOnDelete();
            $t->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $t->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $t->dateTime('price_date');
            $t->decimal('qty', 18, 3)->default(0);
            $t->decimal('unit_price', 18, 4);
            $t->string('source')->default('purchase');
            $t->unsignedBigInteger('reference_id')->nullable();
            $t->timestamps();
            $t->index(['entity_id', 'business_unit_id', 'product_id', 'price_date'], 'pph_entity_bu_product_date_idx');
            $t->index(['source', 'reference_id']);
        });

        Schema::create('stock_opnames', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('business_unit_id')->constrained('business_units')->restrictOnDelete();
            $t->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->date('opname_date');
            $t->string('opname_no');
            $t->string('status')->default('posted');
            $t->timestamps();
            $t->unique(['entity_id', 'opname_no']);
        });

        Schema::create('stock_opname_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('stock_opname_id')->constrained('stock_opnames')->cascadeOnDelete();
            $t->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $t->decimal('system_qty', 18, 3);
            $t->decimal('actual_qty', 18, 3);
            $t->decimal('difference', 18, 3);
            $t->timestamps();
        });

        // ================================================================
        // POS / SALES
        // ================================================================
        Schema::create('cash_shifts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('business_unit_id')->constrained('business_units')->restrictOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->dateTime('opened_at');
            $t->dateTime('closed_at')->nullable();
            $t->decimal('opening_cash', 18, 2)->default(0);
            $t->decimal('closing_cash', 18, 2)->default(0);
            $t->decimal('expected_cash', 18, 2)->nullable();
            $t->decimal('cash_difference', 18, 2)->nullable();
            $t->string('status')->default('open');
            $t->timestamps();
        });

        Schema::create('shift_cash_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cash_shift_id')->constrained('cash_shifts')->cascadeOnDelete();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('business_unit_id')->constrained('business_units')->restrictOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->enum('movement_type', ['in', 'out']);
            $t->decimal('amount', 18, 2);
            $t->string('description');
            $t->timestamp('movement_at');
            $t->timestamps();
            $t->index(['cash_shift_id', 'movement_type']);
        });

        Schema::create('sales', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('business_unit_id')->constrained('business_units')->restrictOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('shift_id')->nullable()->constrained('cash_shifts')->nullOnDelete();
            $t->string('invoice_no');
            $t->dateTime('sale_date');
            $t->date('due_date')->nullable();
            $t->decimal('subtotal', 18, 2)->default(0);
            $t->decimal('discount', 18, 2)->default(0);
            $t->decimal('total', 18, 2)->default(0);
            $t->text('memo')->nullable();
            $t->string('status')->default('posted');
            $t->timestamps();
            $t->unique(['entity_id', 'invoice_no']);
            $t->index(['entity_id', 'business_unit_id', 'sale_date']);
        });

        Schema::create('sale_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $t->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $t->decimal('qty', 18, 3);
            $t->decimal('unit_price', 18, 4);
            $t->decimal('discount', 18, 2)->default(0);
            $t->decimal('total', 18, 2);
            $t->decimal('hpp_unit', 18, 4)->default(0);
            $t->decimal('hpp_total', 18, 2)->default(0);
            $t->timestamps();
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('business_unit_id')->constrained('business_units')->restrictOnDelete();
            $t->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->dateTime('payment_date');
            $t->string('method');
            $t->decimal('amount', 18, 2);
            $t->decimal('paid_amount', 18, 2)->default(0);
            $t->decimal('change_amount', 18, 2)->default(0);
            $t->string('reference')->nullable();
            $t->timestamps();
            $t->index(['entity_id', 'business_unit_id', 'payment_date']);
        });

        Schema::create('sales_returns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('business_unit_id')->constrained('business_units')->restrictOnDelete();
            $t->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $t->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
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
            $t->foreignId('sale_item_id')->constrained('sale_items')->restrictOnDelete();
            $t->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $t->decimal('qty', 18, 3);
            $t->decimal('unit_price', 18, 4);
            $t->decimal('return_value', 18, 2);
            $t->decimal('hpp_unit', 18, 4)->default(0);
            $t->decimal('hpp_total', 18, 2)->default(0);
            $t->string('condition')->default('good');
            $t->timestamps();
        });

        // ================================================================
        // PRODUCTION
        // ================================================================
        Schema::create('boms', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('business_unit_id')->constrained('business_units')->restrictOnDelete();
            $t->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $t->string('code');
            $t->string('name');
            $t->decimal('output_qty', 18, 3)->default(1);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['business_unit_id', 'code']);
        });

        Schema::create('bom_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('bom_id')->constrained('boms')->cascadeOnDelete();
            $t->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $t->decimal('qty', 18, 6);
            $t->timestamps();
        });

        Schema::create('productions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('business_unit_id')->constrained('business_units')->restrictOnDelete();
            $t->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $t->foreignId('bom_id')->constrained('boms')->restrictOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->string('production_no');
            $t->dateTime('production_date');
            $t->decimal('qty', 18, 3);
            $t->decimal('total_cost', 18, 2)->default(0);
            $t->decimal('good_output_qty', 18, 3)->default(0);
            $t->decimal('reject_qty', 18, 3)->default(0);
            $t->string('status')->default('posted');
            $t->timestamps();
            $t->unique(['entity_id', 'production_no']);
        });

        Schema::create('production_material_usages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('production_id')->constrained('productions')->cascadeOnDelete();
            $t->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $t->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $t->decimal('qty', 18, 3);
            $t->decimal('unit_cost', 18, 4);
            $t->decimal('total_cost', 18, 2);
            $t->string('source')->default('stock');
            $t->timestamps();
        });

        Schema::create('production_costs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('production_id')->constrained('productions')->cascadeOnDelete();
            $t->string('cost_group', 50);
            $t->string('description');
            $t->decimal('amount', 18, 2);
            $t->string('source')->nullable();
            $t->string('reference_type')->nullable();
            $t->unsignedBigInteger('reference_id')->nullable();
            $t->timestamps();
            $t->index(['production_id', 'cost_group']);
        });

        Schema::create('production_outputs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('production_id')->constrained('productions')->cascadeOnDelete();
            $t->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $t->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $t->decimal('qty', 18, 3);
            $t->decimal('unit_cost', 18, 4);
            $t->decimal('total_cost', 18, 2);
            $t->string('output_type')->default('good');
            $t->timestamps();
        });

        Schema::create('production_rejects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('production_id')->constrained('productions')->cascadeOnDelete();
            $t->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $t->decimal('qty', 18, 3);
            $t->string('reject_type')->default('scrap');
            $t->string('description')->nullable();
            $t->decimal('recoverable_value', 18, 2)->default(0);
            $t->timestamps();
        });

        // ================================================================
        // ARMADA & JASA
        // ================================================================
        Schema::create('deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('business_unit_id')->constrained('business_units')->restrictOnDelete();
            $t->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $t->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $t->string('delivery_no');
            $t->dateTime('delivery_date');
            $t->string('destination')->nullable();
            $t->decimal('distance_km', 18, 2)->default(0);
            $t->string('status')->default('planned');
            $t->timestamps();
            $t->unique(['entity_id', 'delivery_no']);
        });

        Schema::create('vehicle_operations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('business_unit_id')->constrained('business_units')->restrictOnDelete();
            $t->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $t->date('operation_date');
            $t->unsignedInteger('km_start')->default(0);
            $t->unsignedInteger('km_end')->default(0);
            $t->decimal('fuel_cost', 18, 2)->default(0);
            $t->decimal('other_cost', 18, 2)->default(0);
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('fleet_costs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('business_unit_id')->constrained('business_units')->restrictOnDelete();
            $t->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $t->date('cost_date');
            $t->string('cost_type');
            $t->decimal('amount', 18, 2);
            $t->string('description')->nullable();
            $t->timestamps();
        });

        // ================================================================
        // ACCOUNTING TRANSACTIONS
        // ================================================================
        Schema::create('journals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('business_unit_id')->constrained('business_units')->restrictOnDelete();
            $t->string('journal_no');
            $t->date('journal_date');
            $t->string('source_type')->nullable();
            $t->unsignedBigInteger('source_id')->nullable();
            $t->string('description');
            $t->string('status')->default('draft');
            $t->timestamps();
            $t->unique(['entity_id', 'journal_no']);
            $t->index(['entity_id', 'business_unit_id', 'journal_date']);
        });

        Schema::create('journal_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('journal_id')->constrained('journals')->cascadeOnDelete();
            $t->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $t->decimal('debit', 18, 2)->default(0);
            $t->decimal('credit', 18, 2)->default(0);
            $t->timestamps();
        });

        Schema::create('business_unit_account_mappings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('business_unit_id')->constrained('business_units')->cascadeOnDelete();
            $t->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $t->string('mapping_key', 50);
            $t->timestamps();
            $t->unique(['business_unit_id', 'mapping_key'], 'buam_bu_mapping_key_unique');
            $t->index(['entity_id', 'account_id']);
        });

        // ================================================================
        // SYSTEM PERMISSIONS / SYNC
        // ================================================================
        Schema::create('user_module_permissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('module', 50);
            $t->timestamps();
            $t->unique(['user_id', 'module']);
        });

        Schema::create('sync_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->uuid('sync_uuid')->unique();
            $t->string('module');
            $t->string('operation');
            $t->unsignedBigInteger('local_id')->nullable();
            $t->string('status')->default('pending');
            $t->text('payload')->nullable();
            $t->text('error_message')->nullable();
            $t->timestamp('synced_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'sync_logs',
            'user_module_permissions',
            'business_unit_account_mappings',
            'journal_entries',
            'journals',
            'fleet_costs',
            'vehicle_operations',
            'deliveries',
            'production_rejects',
            'production_outputs',
            'production_costs',
            'production_material_usages',
            'productions',
            'bom_items',
            'boms',
            'sales_return_items',
            'sales_returns',
            'payments',
            'sale_items',
            'sales',
            'shift_cash_movements',
            'cash_shifts',
            'stock_opname_items',
            'stock_opnames',
            'purchase_price_histories',
            'purchase_items',
            'purchases',
            'item_initial_setups',
            'stock_movements',
            'warehouses_stocks',
            'chart_of_accounts',
            'tariffs',
            'drivers',
            'vehicles',
            'warehouses',
            'customers',
            'suppliers',
            'product_units',
            'product_business_units',
            'products',
            'product_categories',
            'units',
            'user_business_units',
            'users',
            'business_units',
            'entities',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
