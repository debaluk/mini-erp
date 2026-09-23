<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->foreignId('business_unit_id')
                ->nullable()
                ->change();
        });

        Schema::create('warehouse_business_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('business_unit_id')->constrained('business_units')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['warehouse_id', 'business_unit_id'], 'wbu_warehouse_bu_unique');
            $table->unique(['business_unit_id'], 'wbu_business_unit_unique');
            $table->index(['entity_id', 'warehouse_id']);
        });

        DB::table('warehouses')
            ->whereNotNull('business_unit_id')
            ->orderBy('id')
            ->get(['id', 'entity_id', 'business_unit_id'])
            ->each(function ($warehouse): void {
                DB::table('warehouse_business_units')->updateOrInsert(
                    [
                        'warehouse_id' => $warehouse->id,
                        'business_unit_id' => $warehouse->business_unit_id,
                    ],
                    [
                        'entity_id' => $warehouse->entity_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_business_units');

        // Legacy column remains nullable because Master Gudang is independent from BU.

    }
};
