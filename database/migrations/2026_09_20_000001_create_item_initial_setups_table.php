<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_initial_setups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->date('setup_date');
            $table->decimal('purchase_price', 18, 4);
            $table->decimal('initial_stock', 18, 3);
            $table->decimal('markup_percent', 9, 4);
            $table->decimal('selling_price', 18, 4);
            $table->timestamps();

            $table->unique(['entity_id', 'product_id']);
            $table->index(['entity_id', 'setup_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_initial_setups');
    }
};
