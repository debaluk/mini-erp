<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $t) {
            $t->foreignId('tariff_id')->nullable()->after('driver_id')->constrained('tariffs')->nullOnDelete();
            $t->decimal('service_revenue', 18, 2)->default(0)->after('distance_km');
            $t->index(['entity_id', 'business_unit_id', 'delivery_date', 'status'], 'deliveries_cost_report_idx');
        });

        Schema::create('service_costs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('business_unit_id')->constrained('business_units')->restrictOnDelete();
            $t->foreignId('delivery_id')->constrained('deliveries')->cascadeOnDelete();
            $t->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $t->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $t->dateTime('cost_date');
            $t->string('cost_type', 50);
            $t->string('description');
            $t->decimal('amount', 18, 2);
            $t->string('source')->nullable();
            $t->string('reference_type')->nullable();
            $t->unsignedBigInteger('reference_id')->nullable();
            $t->timestamps();
            $t->index(['entity_id', 'business_unit_id', 'delivery_id'], 'service_cost_delivery_idx');
            $t->index(['cost_type', 'cost_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_costs');

        Schema::table('deliveries', function (Blueprint $t) {
            $t->dropIndex('deliveries_cost_report_idx');
            $t->dropForeign(['tariff_id']);
            $t->dropColumn(['tariff_id', 'service_revenue']);
        });
    }
};
