<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_work_order_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('production_work_order_id');
            $table->date('production_date');
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['production_work_order_id', 'production_date'], 'pwor_wo_date_idx');
            $table->index(['entity_id', 'status'], 'pwor_entity_status_idx');
        });

        Schema::create('production_work_order_result_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('production_work_order_result_id');
            $table->unsignedBigInteger('worker_id');
            $table->string('pay_type', 20);
            $table->decimal('unit_rate', 18, 2)->default(0);
            $table->decimal('good_qty', 18, 3)->default(0);
            $table->decimal('reject_qty', 18, 3)->default(0);
            $table->timestamps();
            $table->unique(['production_work_order_result_id', 'worker_id'], 'pwor_line_worker_unique');
            $table->index('worker_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_work_order_result_lines');
        Schema::dropIfExists('production_work_order_results');
    }
};
