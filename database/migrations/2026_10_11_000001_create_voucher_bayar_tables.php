<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('voucher_bayar', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('business_unit_id')->constrained('business_units')->restrictOnDelete();
            $t->string('voucher_no', 40);
            $t->string('category', 40);
            $t->foreignId('worker_id')->nullable()->constrained('workers')->restrictOnDelete();
            $t->date('request_date');
            $t->date('scheduled_payment_date');
            $t->string('description');
            $t->decimal('total_amount', 18, 2)->default(0);
            $t->decimal('paid_amount', 18, 2)->default(0);
            $t->string('status', 30)->default('MENUNGGU_PEMBAYARAN');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['entity_id', 'voucher_no']);
            $t->index(['entity_id', 'category', 'status', 'scheduled_payment_date'], 'vb_entity_cat_status_schedule_idx');
        });
        Schema::create('voucher_bayar_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('voucher_bayar_id')->constrained('voucher_bayar')->restrictOnDelete();
            $t->string('source_type', 50)->nullable();
            $t->unsignedBigInteger('source_id')->nullable();
            $t->foreignId('production_cost_id')->nullable()->unique()->constrained('production_costs')->restrictOnDelete();
            $t->foreignId('production_id')->nullable()->constrained('productions')->restrictOnDelete();
            $t->foreignId('work_order_id')->nullable()->constrained('production_work_orders')->restrictOnDelete();
            $t->foreignId('worker_id')->nullable()->constrained('workers')->restrictOnDelete();
            $t->string('description');
            $t->decimal('amount', 18, 2);
            $t->timestamps();
            $t->index(['voucher_bayar_id', 'worker_id'], 'vbi_voucher_worker_idx');
            $t->index(['source_type', 'source_id'], 'vbi_source_idx');
        });
        Schema::create('voucher_bayar_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('voucher_bayar_id')->constrained('voucher_bayar')->restrictOnDelete();
            $t->date('payment_date');
            $t->foreignId('cash_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $t->decimal('amount', 18, 2);
            $t->foreignId('journal_id')->nullable()->constrained('journals')->restrictOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['voucher_bayar_id', 'payment_date'], 'vbp_voucher_date_idx');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('voucher_bayar_payments');
        Schema::dropIfExists('voucher_bayar_items');
        Schema::dropIfExists('voucher_bayar');
    }
};
