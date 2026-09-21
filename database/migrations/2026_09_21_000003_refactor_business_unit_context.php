<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Business Unit is the business context/source of operational transactions.
     *
     * Entity = legal/company entity
     * Business Unit = business operation inside the entity
     * Unit/UOM = item measurement unit
     */
    public function up(): void
    {
        // A user may work with many business units.
        if (! Schema::hasTable('user_business_units')) {
            Schema::create('user_business_units', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $t->foreignId('business_unit_id')->constrained('business_units')->cascadeOnDelete();
                $t->boolean('is_default')->default(false);
                $t->timestamps();

                $t->unique(['user_id', 'business_unit_id']);
                $t->index(['business_unit_id', 'user_id']);
            });
        }

        // Default working unit is optional; actual transaction access is still
        // validated through user_business_units.
        if (! Schema::hasColumn('users', 'default_business_unit_id')) {
            Schema::table('users', function (Blueprint $t) {
                $t->foreignId('default_business_unit_id')
                    ->nullable()
                    ->after('is_active')
                    ->constrained('business_units')
                    ->nullOnDelete();
            });
        }

        // Warehouse belongs to exactly one business unit.
        if (! Schema::hasColumn('warehouses', 'business_unit_id')) {
            Schema::table('warehouses', function (Blueprint $t) {
                $t->foreignId('business_unit_id')
                    ->nullable()
                    ->after('entity_id')
                    ->constrained('business_units')
                    ->nullOnDelete();
                $t->index(['entity_id', 'business_unit_id']);
            });
        }

        // Operational transaction/document headers.
        $tables = [
            'cash_shifts' => 'user_id',
            'sales' => 'customer_id',
            'purchases' => 'supplier_id',
            'boms' => 'product_id',
            'productions' => 'warehouse_id',
            'deliveries' => 'vehicle_id',
            'vehicle_operations' => 'vehicle_id',
            'fleet_costs' => 'vehicle_id',
            'stock_opnames' => 'warehouse_id',
            'journals' => 'journal_date',
        ];

        foreach ($tables as $table => $after) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'business_unit_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($after) {
                $t->foreignId('business_unit_id')
                    ->nullable()
                    ->after($after)
                    ->constrained('business_units')
                    ->nullOnDelete();
            });

            Schema::table($table, function (Blueprint $t) {
                $t->index('business_unit_id');
            });
        }

        if (Schema::hasTable('payments') && ! Schema::hasColumn('payments', 'business_unit_id')) {
            Schema::table('payments', function (Blueprint $t) {
                $t->foreignId('business_unit_id')
                    ->nullable()
                    ->after('sale_id')
                    ->constrained('business_units')
                    ->nullOnDelete();
                $t->index('business_unit_id');
            });
        }

        // Stock movement must retain the business source even when a warehouse
        // later serves more than one operational flow.
        if (Schema::hasTable('stock_movements') && ! Schema::hasColumn('stock_movements', 'business_unit_id')) {
            Schema::table('stock_movements', function (Blueprint $t) {
                $t->foreignId('business_unit_id')
                    ->nullable()
                    ->after('entity_id')
                    ->constrained('business_units')
                    ->nullOnDelete();
                $t->index(['entity_id', 'business_unit_id']);
            });
        }

        // Migrate the legacy sales.unit_id into the canonical business_unit_id.
        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'unit_id')) {
            if (Schema::hasColumn('sales', 'business_unit_id')) {
                DB::statement(
                    'UPDATE sales SET business_unit_id = unit_id WHERE business_unit_id IS NULL AND unit_id IS NOT NULL'
                );
            }

            // Drop the legacy FK/index safely.
            try {
                Schema::table('sales', function (Blueprint $t) {
                    $t->dropForeign(['unit_id']);
                });
            } catch (\Throwable $e) {
                // Legacy constraint may already have been removed.
            }

            try {
                Schema::table('sales', function (Blueprint $t) {
                    $t->dropIndex(['entity_id', 'unit_id']);
                });
            } catch (\Throwable $e) {
                // Legacy index may already have been removed.
            }

            Schema::table('sales', function (Blueprint $t) {
                $t->dropColumn('unit_id');
            });
        }

        // Existing rows need a valid business context before the application
        // starts enforcing it. Prefer RET, otherwise the first active unit.
        if (Schema::hasTable('business_units')) {
            $entities = DB::table('entities')->select('id')->get();

            foreach ($entities as $entity) {
                $bu = DB::table('business_units')
                    ->where('entity_id', $entity->id)
                    ->where('is_active', true)
                    ->orderByRaw("CASE WHEN UPPER(code) LIKE 'RET%' THEN 0 ELSE 1 END")
                    ->orderBy('id')
                    ->first();

                if (! $bu) {
                    continue;
                }

                foreach (array_keys($tables) as $table) {
                    if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'business_unit_id')) {
                        continue;
                    }

                    DB::table($table)
                        ->whereNull('business_unit_id')
                        ->where('entity_id', $entity->id)
                        ->update(['business_unit_id' => $bu->id]);
                }

                if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'business_unit_id')) {
                    DB::table('payments as p')
                        ->join('sales as s', 's.id', '=', 'p.sale_id')
                        ->whereNull('p.business_unit_id')
                        ->where('p.entity_id', $entity->id)
                        ->update(['p.business_unit_id' => DB::raw('s.business_unit_id')]);
                }

                if (Schema::hasTable('stock_movements') && Schema::hasColumn('stock_movements', 'business_unit_id')) {
                    DB::table('stock_movements')
                        ->whereNull('business_unit_id')
                        ->where('entity_id', $entity->id)
                        ->update(['business_unit_id' => $bu->id]);
                }
            }
        }

        // Default unit for users: choose RET when available, otherwise first
        // active unit in the user's entity. The many-to-many table remains the
        // authoritative access relation.
        if (Schema::hasColumn('users', 'default_business_unit_id')) {
            $users = DB::table('users')->select('id')->get();

            foreach ($users as $user) {
                if (DB::table('users')->where('id', $user->id)->value('default_business_unit_id')) {
                    continue;
                }

                $bu = DB::table('business_units')
                    ->where('is_active', true)
                    ->orderByRaw("CASE WHEN UPPER(code) LIKE 'RET%' THEN 0 ELSE 1 END")
                    ->orderBy('id')
                    ->first();

                if ($bu) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['default_business_unit_id' => $bu->id]);

                    DB::table('user_business_units')->updateOrInsert(
                        ['user_id' => $user->id, 'business_unit_id' => $bu->id],
                        ['is_default' => true, 'created_at' => now(), 'updated_at' => now()]
                    );
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sales') && ! Schema::hasColumn('sales', 'unit_id')) {
            Schema::table('sales', function (Blueprint $t) {
                $t->foreignId('unit_id')
                    ->nullable()
                    ->after('customer_id')
                    ->constrained('business_units')
                    ->nullOnDelete();
                $t->index(['entity_id', 'unit_id']);
            });

            if (Schema::hasColumn('sales', 'business_unit_id')) {
                DB::statement(
                    'UPDATE sales SET unit_id = business_unit_id WHERE unit_id IS NULL AND business_unit_id IS NOT NULL'
                );
            }
        }

        foreach ([
            'payments',
            'stock_movements',
            'journals',
            'stock_opnames',
            'fleet_costs',
            'vehicle_operations',
            'deliveries',
            'productions',
            'boms',
            'purchases',
            'sales',
            'cash_shifts',
        ] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'business_unit_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                try {
                    $t->dropForeign(['business_unit_id']);
                } catch (\Throwable $e) {
                }
                try {
                    $t->dropIndex(['business_unit_id']);
                } catch (\Throwable $e) {
                }
                $t->dropColumn('business_unit_id');
            });
        }

        if (Schema::hasTable('warehouses') && Schema::hasColumn('warehouses', 'business_unit_id')) {
            Schema::table('warehouses', function (Blueprint $t) {
                $t->dropForeign(['business_unit_id']);
                $t->dropIndex(['entity_id', 'business_unit_id']);
                $t->dropColumn('business_unit_id');
            });
        }

        if (Schema::hasColumn('users', 'default_business_unit_id')) {
            Schema::table('users', function (Blueprint $t) {
                $t->dropForeign(['default_business_unit_id']);
                $t->dropColumn('default_business_unit_id');
            });
        }

        Schema::dropIfExists('user_business_units');
    }
};
