<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Product-specific transaction UOM conversions.
        if (!Schema::hasTable('product_unit_conversions')) {
            Schema::create('product_unit_conversions', function (Blueprint $t) {
                $t->id();
                $t->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $t->foreignId('unit_id')->constrained('units')->restrictOnDelete();
                $t->decimal('conversion_factor', 18, 6);
                $t->boolean('is_default_purchase')->default(false);
                $t->boolean('is_default_sale')->default(false);
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->unique(['product_id', 'unit_id']);
                $t->index(['product_id', 'is_active']);
            });
        }

        // Migrate the old generic product-unit mappings into the new
        // product-specific conversion master. Legacy product_units is kept
        // temporarily for backward compatibility and data verification.
        if (Schema::hasTable('product_units')) {
            $legacyRows = DB::table('product_units')->get();
            foreach ($legacyRows as $row) {
                DB::table('product_unit_conversions')->updateOrInsert(
                    ['product_id' => $row->product_id, 'unit_id' => $row->unit_id],
                    [
                        'conversion_factor' => $row->conversion_factor,
                        'is_default_purchase' => (bool) $row->is_default,
                        'is_default_sale' => (bool) $row->is_default,
                        'is_active' => true,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        Schema::table('purchase_items', function (Blueprint $t) {
            $t->foreignId('unit_id')->nullable()->after('product_id')->constrained('units')->restrictOnDelete();
            $t->decimal('conversion_factor', 18, 6)->default(1)->after('qty');
            $t->decimal('base_qty', 18, 6)->default(0)->after('conversion_factor');
            $t->decimal('base_unit_cost', 18, 4)->default(0)->after('unit_cost');
        });

        Schema::table('sale_items', function (Blueprint $t) {
            $t->foreignId('unit_id')->nullable()->after('product_id')->constrained('units')->restrictOnDelete();
            $t->decimal('conversion_factor', 18, 6)->default(1)->after('qty');
            $t->decimal('base_qty', 18, 6)->default(0)->after('conversion_factor');
            $t->decimal('base_unit_cost', 18, 4)->default(0)->after('unit_price');
        });

        Schema::table('stock_movements', function (Blueprint $t) {
            $t->foreignId('unit_id')->nullable()->after('product_id')->constrained('units')->restrictOnDelete();
            $t->decimal('transaction_qty', 18, 6)->default(0)->after('unit_id');
            $t->decimal('conversion_factor', 18, 6)->default(1)->after('transaction_qty');
        });

        Schema::table('purchase_price_histories', function (Blueprint $t) {
            $t->foreignId('unit_id')->nullable()->after('product_id')->constrained('units')->restrictOnDelete();
            $t->decimal('conversion_factor', 18, 6)->default(1)->after('qty');
            $t->decimal('base_qty', 18, 6)->default(0)->after('conversion_factor');
            $t->decimal('base_unit_price', 18, 4)->default(0)->after('unit_price');
        });

        Schema::table('sales_return_items', function (Blueprint $t) {
            $t->foreignId('unit_id')->nullable()->after('product_id')->constrained('units')->restrictOnDelete();
            $t->decimal('conversion_factor', 18, 6)->default(1)->after('qty');
            $t->decimal('base_qty', 18, 6)->default(0)->after('conversion_factor');
        });

        // Existing records were historically stored in their product base
        // quantity, so initialize the new transaction/base fields consistently.
        DB::statement("UPDATE purchase_items pi JOIN products p ON p.id = pi.product_id SET pi.unit_id = p.base_unit_id, pi.conversion_factor = 1, pi.base_qty = pi.qty, pi.base_unit_cost = pi.unit_cost WHERE pi.unit_id IS NULL");
        DB::statement("UPDATE sale_items si JOIN products p ON p.id = si.product_id SET si.unit_id = p.base_unit_id, si.conversion_factor = 1, si.base_qty = si.qty, si.base_unit_cost = si.hpp_unit WHERE si.unit_id IS NULL");
        DB::statement("UPDATE stock_movements sm JOIN products p ON p.id = sm.product_id SET sm.unit_id = p.base_unit_id, sm.transaction_qty = ABS(sm.qty), sm.conversion_factor = 1 WHERE sm.unit_id IS NULL");
        DB::statement("UPDATE purchase_price_histories ph JOIN products p ON p.id = ph.product_id SET ph.unit_id = p.base_unit_id, ph.conversion_factor = 1, ph.base_qty = ph.qty, ph.base_unit_price = ph.unit_price WHERE ph.unit_id IS NULL");
        DB::statement("UPDATE sales_return_items ri JOIN products p ON p.id = ri.product_id SET ri.unit_id = p.base_unit_id, ri.conversion_factor = 1, ri.base_qty = ri.qty WHERE ri.unit_id IS NULL");
    }

    public function down(): void
    {
        Schema::table('sales_return_items', function (Blueprint $t) {
            $t->dropForeign(['unit_id']);
            $t->dropColumn(['unit_id', 'conversion_factor', 'base_qty']);
        });

        Schema::table('purchase_price_histories', function (Blueprint $t) {
            $t->dropForeign(['unit_id']);
            $t->dropColumn(['unit_id', 'conversion_factor', 'base_qty', 'base_unit_price']);
        });

        Schema::table('stock_movements', function (Blueprint $t) {
            $t->dropForeign(['unit_id']);
            $t->dropColumn(['unit_id', 'transaction_qty', 'conversion_factor']);
        });

        Schema::table('sale_items', function (Blueprint $t) {
            $t->dropForeign(['unit_id']);
            $t->dropColumn(['unit_id', 'conversion_factor', 'base_qty', 'base_unit_cost']);
        });

        Schema::table('purchase_items', function (Blueprint $t) {
            $t->dropForeign(['unit_id']);
            $t->dropColumn(['unit_id', 'conversion_factor', 'base_qty', 'base_unit_cost']);
        });

        Schema::dropIfExists('product_unit_conversions');
    }
};
