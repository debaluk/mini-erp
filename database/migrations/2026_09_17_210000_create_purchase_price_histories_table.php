<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // HISTORI HARGA BAHAN: satu baris untuk setiap harga pembelian yang terjadi.
        // Jangan taruh histori harga di BOM; BOM hanya menyimpan struktur dan qty bahan.
        Schema::create('purchase_price_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $t->dateTime('price_date');
            $t->decimal('qty', 18, 3)->default(0);
            $t->decimal('unit_price', 18, 2);
            $t->string('source')->default('purchase');
            $t->unsignedBigInteger('reference_id')->nullable();
            $t->timestamps();

            $t->index(['entity_id', 'product_id', 'price_date']);
            $t->index(['source', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_price_histories');
    }
};
