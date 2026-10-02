<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_work_order_costs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('production_work_order_id');
            $table->unsignedBigInteger('worker_id')->nullable();
            $table->string('cost_group', 1);
            $table->string('description', 255)->nullable();
            $table->decimal('amount', 18, 2);
            $table->timestamps();

            $table->index(['production_work_order_id', 'cost_group']);
            $table->index(['worker_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_work_order_costs');
    }
};
