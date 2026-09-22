<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The baseline previously used product_units for two different concepts.
        // Normalize the live database to:
        // product_business_units = Item <-> Business Unit
        // product_unit_conversions = Item <-> Transaction Unit conversion.

        if (!Schema::hasTable('product_unit_conversions')) {
            Schema::create('product_unit_conversions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
                $table->decimal('conversion_factor', 18, 6);
                $table->boolean('is_default_purchase')->default(false);
                $table->boolean('is_default_sale')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['product_id', 'unit_id']);
                $table->index(['product_id', 'is_active']);
            });
        }

        // Migrate legacy product_units only when its old conversion columns exist.
        if (Schema::hasTable('product_units')) {
            if (Schema::hasColumn('product_units', 'unit_id') && Schema::hasColumn('product_units', 'conversion_factor')) {
                DB::table('product_units')->orderBy('id')->get()->each(function ($row) {
                    if ((int) $row->unit_id === (int) DB::table('products')->where('id', $row->product_id)->value('base_unit_id')) {
                        return;
                    }

                    DB::table('product_unit_conversions')->updateOrInsert(
                        ['product_id' => $row->product_id, 'unit_id' => $row->unit_id],
                        [
                            'conversion_factor' => $row->conversion_factor,
                            'is_default_purchase' => false,
                            'is_default_sale' => false,
                            'is_active' => true,
                            'created_at' => $row->created_at ?? now(),
                            'updated_at' => now(),
                        ]
                    );
                });
            }

            // Some earlier UAT code incorrectly stored Business Unit mappings here.
            if (Schema::hasColumn('product_units', 'business_unit_id')) {
                DB::table('product_units')->orderBy('id')->get()->each(function ($row) {
                    DB::table('product_business_units')->updateOrInsert(
                        ['product_id' => $row->product_id, 'business_unit_id' => $row->business_unit_id],
                        [
                            'created_at' => $row->created_at ?? now(),
                            'updated_at' => now(),
                        ]
                    );
                });
            }

            Schema::dropIfExists('product_units');
        }

        if (Schema::hasColumn('products', 'unit_id')) {
            // Keep legacy column only if it exists; application code no longer uses it.
            // Dropping it is deliberately deferred to avoid breaking older databases.
        }

        foreach (['purchase_items', 'sale_items', 'sales_return_items'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                if (!Schema::hasColumn($table, 'transaction_unit_id')) {
                    $t->foreignId('transaction_unit_id')->nullable()->after('product_id')->constrained('units')->restrictOnDelete();
                }
                if (!Schema::hasColumn($table, 'conversion_factor')) {
                    $t->decimal('conversion_factor', 18, 6)->default(1)->after('transaction_unit_id');
                }
                if (!Schema::hasColumn($table, 'base_qty')) {
                    $t->decimal('base_qty', 18, 6)->default(0)->after('conversion_factor');
                }
                if (!Schema::hasColumn($table, 'base_unit_id')) {
                    $t->foreignId('base_unit_id')->nullable()->after('base_qty')->constrained('units')->restrictOnDelete();
                }
            });

            DB::table($table)->orderBy('id')->get()->each(function ($row) use ($table) {
                $baseUnitId = DB::table('products')->where('id', $row->product_id)->value('base_unit_id');
                DB::table($table)->where('id', $row->id)->update([
                    'transaction_unit_id' => $row->transaction_unit_id ?? $baseUnitId,
                    'conversion_factor' => $row->conversion_factor ?? 1,
                    'base_qty' => $row->base_qty ?? $row->qty,
                    'base_unit_id' => $row->base_unit_id ?? $baseUnitId,
                ]);
            });
        }

        if (Schema::hasTable('purchase_price_histories')) {
            Schema::table('purchase_price_histories', function (Blueprint $t) {
                if (!Schema::hasColumn('purchase_price_histories', 'transaction_unit_id')) {
                    $t->foreignId('transaction_unit_id')->nullable()->after('product_id')->constrained('units')->restrictOnDelete();
                }
                if (!Schema::hasColumn('purchase_price_histories', 'conversion_factor')) {
                    $t->decimal('conversion_factor', 18, 6)->default(1)->after('transaction_unit_id');
                }
                if (!Schema::hasColumn('purchase_price_histories', 'base_qty')) {
                    $t->decimal('base_qty', 18, 6)->default(0)->after('conversion_factor');
                }
                if (!Schema::hasColumn('purchase_price_histories', 'base_unit_price')) {
                    $t->decimal('base_unit_price', 18, 6)->default(0)->after('base_qty');
                }
            });
        }

        foreach (['warehouses_stocks', 'stock_movements'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            if ($table === 'warehouses_stocks') {
                Schema::table($table, function (Blueprint $t) {
                    if (Schema::hasColumn('warehouses_stocks', 'qty')) {
                        $t->decimal('qty', 18, 6)->change();
                    }
                    if (Schema::hasColumn('warehouses_stocks', 'avg_cost')) {
                        $t->decimal('avg_cost', 18, 6)->change();
                    }
                });
            } else {
                Schema::table($table, function (Blueprint $t) {
                    if (Schema::hasColumn('stock_movements', 'qty')) {
                        $t->decimal('qty', 18, 6)->change();
                    }
                    if (Schema::hasColumn('stock_movements', 'unit_cost')) {
                        $t->decimal('unit_cost', 18, 6)->change();
                    }
                });
            }
        }

        if (Schema::hasTable('products') && Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE products MODIFY item_type ENUM('barang','jasa','aset') NOT NULL DEFAULT 'barang'");
        }
    }

    public function down(): void
    {
        // This migration intentionally does not recreate the ambiguous legacy
        // product_units table. The normalized schema is the source of truth.
    }
};
