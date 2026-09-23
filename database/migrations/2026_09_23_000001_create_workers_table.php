<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->string('code', 50);
            $t->string('name', 150);
            $t->string('phone', 100)->nullable();
            $t->text('address')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();

            $t->unique(['entity_id', 'code']);
            $t->index(['entity_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workers');
    }
};
