<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('business_unit_id')->nullable();
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('user_id');
            $table->date('adjustment_date');
            $table->string('adjustment_no')->unique();
            $table->enum('status', ['draft', 'posted'])->default('draft');
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('entity_id', 'sa_entity_fk')
                ->references('id')->on('entities');

            $table->foreign('business_unit_id', 'sa_bu_fk')
                ->references('id')->on('business_units');

            $table->foreign('warehouse_id', 'sa_warehouse_fk')
                ->references('id')->on('warehouses');

            $table->foreign('user_id', 'sa_user_fk')
                ->references('id')->on('users');
        });

        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_adjustment_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('system_qty', 20, 6)->default(0);
            $table->decimal('adjustment_qty', 20, 6)->default(0);
            $table->decimal('final_qty', 20, 6)->default(0);
            $table->decimal('unit_cost', 20, 6)->default(0);
            $table->decimal('total_cost', 20, 6)->default(0);
            $table->timestamps();

            $table->foreign('stock_adjustment_id', 'sai_adjustment_fk')
                ->references('id')->on('stock_adjustments')
                ->cascadeOnDelete();

            $table->foreign('product_id', 'sai_product_fk')
                ->references('id')->on('products');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_items');
        Schema::dropIfExists('stock_adjustments');
    }
};
