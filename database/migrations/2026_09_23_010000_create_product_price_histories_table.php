<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_price_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_price_id')->nullable()->constrained('product_prices')->nullOnDelete();
            $t->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $t->foreignId('business_unit_id')->constrained('business_units')->cascadeOnDelete();
            $t->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $t->enum('price_type', ['retail', 'grosir'])->default('retail');
            $t->date('change_date');
            $t->decimal('old_price', 18, 4)->nullable();
            $t->decimal('new_price', 18, 4);
            $t->decimal('change_percent', 12, 4)->nullable();
            $t->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(
                ['product_id', 'business_unit_id', 'unit_id', 'price_type', 'change_date'],
                'product_price_histories_lookup_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_histories');
    }
};
