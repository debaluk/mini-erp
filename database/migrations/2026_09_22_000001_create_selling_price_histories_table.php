<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('selling_price_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('business_unit_id');
            $table->decimal('old_price', 18, 2)->nullable();
            $table->decimal('new_price', 18, 2);
            $table->decimal('old_markup_percent', 10, 4)->nullable();
            $table->decimal('new_markup_percent', 10, 4)->nullable();
            $table->date('effective_date');
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamps();
            $table->index(['entity_id', 'product_id', 'business_unit_id']);
            $table->index('effective_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('selling_price_histories');
    }
};
