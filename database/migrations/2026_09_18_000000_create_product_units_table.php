<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $t->decimal('conversion_factor', 18, 6)->default(1);
            $t->boolean('is_default')->default(false);
            $t->timestamps();
            $t->unique(['product_id', 'unit_id']);
            $t->index(['entity_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_units');
    }
};