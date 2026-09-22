<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * UOM contract:
         * - units = pure master UOM.
         * - products.base_unit_id = the product's stock/HPP base unit.
         * - product_business_units = product <-> business unit mapping.
         * - product_unit_conversions = product-specific transaction UOM conversion.
         *
         * Stock quantities and HPP are always stored in base units.
         */
        if (!Schema::hasTable('product_unit_conversions')) {
            Schema::create('product_unit_conversions', function (Blueprint $t) {
                $t->id();
                $t->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $t->foreignId('unit_id')->constrained('units')->restrictOnDelete();
                $t->decimal('conversion_factor', 24, 9);
                $t->boolean('is_default_purchase')->default(false);
                $t->boolean('is_default_sale')->default(false);
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->unique(['product_id', 'unit_id']);
                $t->index(['product_id', 'is_active']);
            });
        }

        // The baseline migration accidentally used product_units for two concepts.
        // Preserve any existing conversion rows, then remove the ambiguous table.
        if (Schema::hasTable('product_units') && Schema::hasColumn('product_units', 'conversion_factor')) {
            DB::statement(
                'INSERT INTO product_unit_conversions
                    (product_id, unit_id, conversion_factor, is_default_purchase, is_default_sale, is_active, created_at, updated_at)
                 SELECT pu.product_id, pu.unit_id, pu.conversion_factor, 0, 0, 1, pu.created_at, pu.updated_at
                 FROM product_units pu
                 LEFT JOIN product_unit_conversions puc
                    ON puc.product_id = pu.product_id AND puc.unit_id = pu.unit_id
                 WHERE puc.id IS NULL
                   AND pu.unit_id <> (
                       SELECT p.base_unit_id
                       FROM products p
                       WHERE p.id = pu.product_id
                   )'
            );
            Schema::dropIfExists('product_units');
        }

        // Current baseline item type list omitted aset, although the UI/controller already supports it.
        DB::statement("ALTER TABLE products MODIFY item_type ENUM('barang','jasa','aset') NOT NULL DEFAULT 'barang'");
        DB::statement("ALTER TABLE products MODIFY type ENUM('raw_material','merchandise','wip','finished_goods','service','asset') NOT NULL DEFAULT 'merchandise'");

        foreach (['purchase_items', 'sale_items'] as $table) {
            if (!Schema::hasColumn($table, 'unit_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->foreignId('unit_id')->nullable()->after('product_id')->constrained('units')->restrictOnDelete();
                    $t->decimal('conversion_factor', 24, 9)->nullable()->after('qty');
                    $t->decimal('base_qty', 24, 9)->nullable()->after('conversion_factor');
                    $t->decimal('base_unit_cost', 24, 9)->nullable()->after('unit_cost');
                });
            }
        }

        if (Schema::hasColumn('purchase_items', 'unit_id')) {
            DB::statement(
                'UPDATE purchase_items pi
                 INNER JOIN products p ON p.id = pi.product_id
                 SET pi.unit_id = p.base_unit_id,
                     pi.conversion_factor = COALESCE(pi.conversion_factor, 1),
                     pi.base_qty = COALESCE(pi.base_qty, pi.qty),
                     pi.base_unit_cost = COALESCE(pi.base_unit_cost, pi.unit_cost)
                 WHERE pi.unit_id IS NULL'
            );
        }

        if (Schema::hasColumn('sale_items', 'unit_id')) {
            DB::statement(
                'UPDATE sale_items si
                 INNER JOIN products p ON p.id = si.product_id
                 SET si.unit_id = p.base_unit_id,
                     si.conversion_factor = COALESCE(si.conversion_factor, 1),
                     si.base_qty = COALESCE(si.base_qty, si.qty)
                 WHERE si.unit_id IS NULL'
            );
        }

        if (!Schema::hasColumn('purchase_price_histories', 'unit_id')) {
            Schema::table('purchase_price_histories', function (Blueprint $t) {
                $t->foreignId('unit_id')->nullable()->after('product_id')->constrained('units')->restrictOnDelete();
                $t->decimal('conversion_factor', 24, 9)->nullable()->after('qty');
                $t->decimal('base_qty', 24, 9)->nullable()->after('conversion_factor');
                $t->decimal('base_unit_price', 24, 9)->nullable()->after('unit_price');
            });

            DB::statement(
                'UPDATE purchase_price_histories ph
                 INNER JOIN products p ON p.id = ph.product_id
                 SET ph.unit_id = p.base_unit_id,
                     ph.conversion_factor = 1,
                     ph.base_qty = ph.qty,
                     ph.base_unit_price = ph.unit_price
                 WHERE ph.unit_id IS NULL'
            );
        }

        if (!Schema::hasColumn('stock_movements', 'unit_id')) {
            Schema::table('stock_movements', function (Blueprint $t) {
                $t->foreignId('unit_id')->nullable()->after('product_id')->constrained('units')->restrictOnDelete();
                $t->decimal('transaction_qty', 24, 9)->nullable()->after('qty');
                $t->decimal('conversion_factor', 24, 9)->nullable()->after('transaction_qty');
            });

            DB::statement(
                'UPDATE stock_movements sm
                 INNER JOIN products p ON p.id = sm.product_id
                 SET sm.unit_id = p.base_unit_id,
                     sm.transaction_qty = ABS(sm.qty),
                     sm.conversion_factor = 1
                 WHERE sm.unit_id IS NULL'
            );
        }

        if (!Schema::hasColumn('sales_return_items', 'unit_id')) {
            Schema::table('sales_return_items', function (Blueprint $t) {
                $t->foreignId('unit_id')->nullable()->after('product_id')->constrained('units')->restrictOnDelete();
                $t->decimal('conversion_factor', 24, 9)->nullable()->after('qty');
                $t->decimal('base_qty', 24, 9)->nullable()->after('conversion_factor');
            });

            DB::statement(
                'UPDATE sales_return_items sri
                 INNER JOIN products p ON p.id = sri.product_id
                 SET sri.unit_id = p.base_unit_id,
                     sri.conversion_factor = 1,
                     sri.base_qty = sri.qty
                 WHERE sri.unit_id IS NULL'
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales_return_items', 'unit_id')) {
            Schema::table('sales_return_items', function (Blueprint $t) {
                $t->dropForeign(['unit_id']);
                $t->dropColumn(['unit_id', 'conversion_factor', 'base_qty']);
            });
        }

        if (Schema::hasColumn('stock_movements', 'unit_id')) {
            Schema::table('stock_movements', function (Blueprint $t) {
                $t->dropForeign(['unit_id']);
                $t->dropColumn(['unit_id', 'transaction_qty', 'conversion_factor']);
            });
        }

        if (Schema::hasColumn('purchase_price_histories', 'unit_id')) {
            Schema::table('purchase_price_histories', function (Blueprint $t) {
                $t->dropForeign(['unit_id']);
                $t->dropColumn(['unit_id', 'conversion_factor', 'base_qty', 'base_unit_price']);
            });
        }

        foreach (['purchase_items', 'sale_items'] as $table) {
            if (Schema::hasColumn($table, 'unit_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropForeign(['unit_id']);
                    $t->dropColumn(['unit_id', 'conversion_factor', 'base_qty', 'base_unit_cost']);
                });
            }
        }

        Schema::dropIfExists('product_unit_conversions');
        // Do not recreate the ambiguous legacy product_units table on rollback.
    }
};
