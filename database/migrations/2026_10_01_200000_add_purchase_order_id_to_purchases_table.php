<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $t) {
            $t->foreignId('purchase_order_id')->nullable()->after('supplier_id')
                ->constrained('purchase_orders')->nullOnDelete();
            $t->index(['purchase_order_id']);
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $t) {
            $t->dropForeign(['purchase_order_id']);
            $t->dropIndex(['purchase_order_id']);
            $t->dropColumn('purchase_order_id');
        });
    }
};