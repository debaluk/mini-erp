<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id');
            $table->string('code', 50);
            $table->string('name', 150);
            $table->enum('business_type', ['retail', 'production', 'service']);
            $table->enum('hpp_method', ['perpetual', 'periodic']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['entity_id', 'code']);
            $table->index(['entity_id', 'business_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_units');
    }
};
