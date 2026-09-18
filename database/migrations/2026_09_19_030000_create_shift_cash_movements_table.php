<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_cash_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cash_shift_id')->constrained('cash_shifts')->cascadeOnDelete();
            $t->foreignId('entity_id')->constrained('entities');
            $t->foreignId('user_id')->constrained('users');
            $t->enum('movement_type', ['in', 'out']);
            $t->decimal('amount', 18, 2);
            $t->string('description');
            $t->timestamp('movement_at');
            $t->timestamps();
            $t->index(['cash_shift_id', 'movement_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_cash_movements');
    }
};
