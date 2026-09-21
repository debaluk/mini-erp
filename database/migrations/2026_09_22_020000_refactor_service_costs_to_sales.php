<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Upgrade databases that already ran the old delivery-based service cost migration.
        if (Schema::hasTable('service_costs') && Schema::hasColumn('service_costs', 'delivery_id')) {
            Schema::dropIfExists('service_costs');
        }

        if (Schema::hasTable('deliveries')) {
            Schema::table('deliveries', function (Blueprint $t) {
                if (Schema::hasColumn('deliveries', 'tariff_id')) {
                    $t->dropForeign(['tariff_id']);
                    $t->dropColumn('tariff_id');
                }
                if (Schema::hasColumn('deliveries', 'service_revenue')) {
                    $t->dropColumn('service_revenue');
                }
            });
        }

        if (!Schema::hasTable('service_costs')) {
            Schema::create('service_costs', function (Blueprint $t) {
                $t->id();
                $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
                $t->foreignId('business_unit_id')->constrained('business_units')->restrictOnDelete();
                $t->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
                $t->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
                $t->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
                $t->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
                $t->dateTime('cost_date');
                $t->string('description');
                $t->decimal('amount', 18, 2);
                $t->string('source')->nullable();
                $t->string('reference_type')->nullable();
                $t->unsignedBigInteger('reference_id')->nullable();
                $t->timestamps();
                $t->index(['entity_id', 'business_unit_id', 'sale_id'], 'service_cost_sale_idx');
                $t->index(['account_id', 'cost_date']);
            });
        }
    }

    public function down(): void
    {
        // The old delivery-based structure is intentionally not restored.
        Schema::dropIfExists('service_costs');
    }
};
