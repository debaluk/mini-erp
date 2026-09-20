<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $t) {
            $t->foreignId('unit_id')->nullable()->after('supplier_id')->constrained('business_units')->nullOnDelete();
            $t->decimal('discount', 18, 2)->default(0)->after('subtotal');
            $t->string('payment_method')->nullable()->after('total');
            $t->date('due_date')->nullable()->after('payment_method');
            $t->string('supplier_invoice_no')->nullable()->after('due_date');
            $t->date('supplier_invoice_date')->nullable()->after('supplier_invoice_no');
            $t->text('memo')->nullable()->after('supplier_invoice_date');
        });

        Schema::table('purchase_items', function (Blueprint $t) {
            $t->decimal('discount', 18, 2)->default(0)->after('unit_cost');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $t) {
            $t->dropColumn('discount');
        });

        Schema::table('purchases', function (Blueprint $t) {
            $t->dropForeign(['unit_id']);
            $t->dropColumn(['unit_id','discount','payment_method','due_date','supplier_invoice_no','supplier_invoice_date','memo']);
        });
    }
};