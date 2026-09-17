<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entities', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('units', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained();
            $t->string('code');
            $t->string('name');
            $t->timestamps();
        });

        Schema::create('product_categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained();
            $t->string('name');
            $t->timestamps();
        });

        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained();
            $t->foreignId('category_id')
                ->nullable()
                ->constrained('product_categories')
                ->nullOnDelete();
            $t->foreignId('unit_id')
                ->nullable()
                ->constrained('units')
                ->nullOnDelete();
            $t->string('sku')->nullable();
            $t->string('barcode')->nullable();
            $t->string('name');
            $t->enum('type', [
                'raw_material',
                'merchandise',
                'wip',
                'finished_goods',
            ])->default('merchandise');
            $t->decimal('cost_price', 18, 2)->default(0);
            $t->decimal('selling_price', 18, 2)->default(0);
            $t->decimal('minimum_stock', 18, 3)->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['entity_id', 'sku']);
            $t->index(['entity_id', 'barcode']);
        });

        foreach (['customers', 'suppliers'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id();
                $t->foreignId('entity_id')->constrained();
                $t->string('code');
                $t->string('name');
                $t->string('phone')->nullable();
                $t->text('address')->nullable();
                $t->decimal('credit_limit', 18, 2)->default(0);
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->unique(['entity_id', 'code']);
            });
        }

        Schema::create('warehouses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained();
            $t->string('code');
            $t->string('name');
            $t->string('type')->default('general');
            $t->text('address')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['entity_id', 'code']);
        });

        Schema::create('vehicles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained();
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
            $t->foreignId('entity_id')->constrained();
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
            $t->foreignId('entity_id')->constrained();
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

        Schema::create('warehouses_stocks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained();
            $t->foreignId('warehouse_id')->constrained();
            $t->foreignId('product_id')->constrained();
            $t->decimal('qty', 18, 3)->default(0);
            $t->decimal('avg_cost', 18, 4)->default(0);
            $t->timestamps();
            $t->unique(['warehouse_id', 'product_id']);
        });

        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained();
            $t->foreignId('warehouse_id')->constrained();
            $t->foreignId('product_id')->constrained();
            $t->string('movement_type');
            $t->decimal('qty', 18, 3);
            $t->decimal('unit_cost', 18, 4)->default(0);
            $t->string('reference_type')->nullable();
            $t->unsignedBigInteger('reference_id')->nullable();
            $t->timestamp('occurred_at');
            $t->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('chart_of_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained();
            $t->string('code');
            $t->string('name');
            $t->enum('type', [
                'asset',
                'liability',
                'equity',
                'revenue',
                'cogs',
                'expense',
            ]);
            $t->foreignId('parent_id')
                ->nullable()
                ->constrained('chart_of_accounts')
                ->nullOnDelete();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['entity_id', 'code']);
        });

        Schema::create('journals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained();
            $t->string('journal_no');
            $t->date('journal_date');
            $t->string('source_type')->nullable();
            $t->unsignedBigInteger('source_id')->nullable();
            $t->string('description');
            $t->string('status')->default('draft');
            $t->timestamps();
            $t->unique(['entity_id', 'journal_no']);
        });

        Schema::create('journal_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('journal_id')
                ->constrained()
                ->cascadeOnDelete();
            $t->foreignId('account_id')
                ->constrained('chart_of_accounts');
            $t->decimal('debit', 18, 2)->default(0);
            $t->decimal('credit', 18, 2)->default(0);
            $t->timestamps();
        });

        Schema::create('sync_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained();
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
            'journal_entries',
            'journals',
            'chart_of_accounts',
            'stock_movements',
            'warehouses_stocks',
            'tariffs',
            'drivers',
            'vehicles',
            'warehouses',
            'customers',
            'suppliers',
            'products',
            'product_categories',
            'units',
            'entities',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
