<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_prices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $t->foreignId('business_unit_id')->constrained('business_units')->cascadeOnDelete();
            $t->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $t->enum('price_type', ['retail', 'grosir'])->default('retail');
            $t->decimal('selling_price', 18, 4);
            $t->timestamps();

            $t->unique(
                ['product_id', 'business_unit_id', 'unit_id', 'price_type'],
                'product_prices_product_bu_unit_type_unique'
            );

            $t->index(
                ['business_unit_id', 'price_type'],
                'product_prices_bu_type_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
