<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'shift_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropForeign(['shift_id']);
                $table->dropColumn('shift_id');
            });
        }

        Schema::dropIfExists('shift_cash_movements');
        Schema::dropIfExists('cash_shifts');
    }

    public function down(): void
    {
        if (!Schema::hasTable('cash_shifts')) {
            Schema::create('cash_shifts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
                $table->foreignId('business_unit_id')->constrained('business_units')->restrictOnDelete();
                $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
                $table->dateTime('opened_at');
                $table->dateTime('closed_at')->nullable();
                $table->decimal('opening_cash', 18, 2)->default(0);
                $table->decimal('closing_cash', 18, 2)->default(0);
                $table->decimal('expected_cash', 18, 2)->nullable();
                $table->decimal('cash_difference', 18, 2)->nullable();
                $table->string('status')->default('open');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('shift_cash_movements')) {
            Schema::create('shift_cash_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cash_shift_id')->constrained('cash_shifts')->cascadeOnDelete();
                $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
                $table->foreignId('business_unit_id')->constrained('business_units')->restrictOnDelete();
                $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
                $table->enum('movement_type', ['in', 'out']);
                $table->decimal('amount', 18, 2);
                $table->string('description');
                $table->timestamp('movement_at');
                $table->timestamps();
                $table->index(['cash_shift_id', 'movement_type']);
            });
        }

        if (Schema::hasTable('sales') && !Schema::hasColumn('sales', 'shift_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->foreignId('shift_id')->nullable()->constrained('cash_shifts')->nullOnDelete();
            });
        }
    }
};
