<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_work_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('business_unit_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('bom_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('wo_no', 50)->unique();
            $table->date('wo_date');
            $table->decimal('batch_qty', 15, 3);
            $table->decimal('target_output_qty', 15, 3);
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['entity_id', 'business_unit_id']);
            $table->index(['wo_date', 'status']);
            $table->index(['bom_id']);
            $table->index(['warehouse_id']);
        });

        Schema::create('production_work_order_workers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('production_work_order_id');
            $table->unsignedBigInteger('worker_id');
            $table->string('role', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['production_work_order_id', 'worker_id'], 'pwo_workers_unique');
            $table->index(['worker_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_work_order_workers');
        Schema::dropIfExists('production_work_orders');
    }
};
