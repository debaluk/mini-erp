<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_wo_material_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('business_unit_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('production_work_order_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('journal_id')->nullable();
            $table->string('usage_no')->unique();
            $table->date('usage_date');
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('entity_id', 'pmu_entity_fk')->references('id')->on('entities');
            $table->foreign('business_unit_id', 'pmu_bu_fk')->references('id')->on('business_units');
            $table->foreign('warehouse_id', 'pmu_warehouse_fk')->references('id')->on('warehouses');
            $table->foreign('production_work_order_id', 'pmu_wo_fk')->references('id')->on('production_work_orders');
            $table->foreign('user_id', 'pmu_user_fk')->references('id')->on('users');
            $table->foreign('approved_by', 'pmu_approved_by_fk')->references('id')->on('users');
            $table->foreign('journal_id', 'pmu_journal_fk')->references('id')->on('journals');
        });

        Schema::create('production_material_usage_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('production_material_usage_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->decimal('planned_qty', 20, 6)->default(0);
            $table->decimal('actual_qty', 20, 6)->default(0);
            $table->decimal('conversion_factor', 20, 6)->default(1);
            $table->decimal('base_actual_qty', 20, 6)->default(0);
            $table->decimal('unit_cost', 20, 6)->default(0);
            $table->decimal('total_cost', 20, 6)->default(0);
            $table->timestamps();

            $table->foreign('production_material_usage_id', 'pmui_usage_fk')
                ->references('id')->on('production_wo_material_usages')->cascadeOnDelete();
            $table->foreign('product_id', 'pmui_product_fk')->references('id')->on('products');
            $table->foreign('unit_id', 'pmui_unit_fk')->references('id')->on('units');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_material_usage_items');
        Schema::dropIfExists('production_wo_material_usages');
    }
};
