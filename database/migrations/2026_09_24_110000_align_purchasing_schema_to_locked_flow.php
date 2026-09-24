<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ================================================================
        // PURCHASE ORDER
        // PO is commitment only: no stock, payable, or journal.
        // ================================================================
        Schema::create('purchase_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $t->foreignId('business_unit_id')->constrained('business_units')->restrictOnDelete();
            $t->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $t->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->string('po_no');
            $t->dateTime('po_date');
            $t->string('status')->default('draft');
            $t->text('memo')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->dateTime('approved_at')->nullable();
            $t->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->dateTime('cancelled_at')->nullable();
            $t->text('cancellation_reason')->nullable();
            $t->timestamps();

            $t->unique(['entity_id', 'po_no']);
            $t->index(['entity_id', 'business_unit_id', 'po_date']);
            $t->index(['entity_id', 'supplier_id', 'po_date']);
            $t->index(['entity_id', 'warehouse_id', 'po_date']);
        });

        Schema::create('purchase_order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $t->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $t->foreignId('unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $t->decimal('qty', 18, 3);
            $t->decimal('conversion_factor', 18, 6)->default(1);
            $t->decimal('base_qty', 18, 6)->default(0);
            $t->decimal('unit_price', 18, 4)->default(0);
            $t->decimal('discount', 18, 2)->default(0);
            $t->decimal('total', 18, 2)->default(0);
            $t->text('memo')->nullable();
            $t->timestamps();

            $t->index(['purchase_order_id', 'product_id']);
        });

        Schema::create('purchase_order_cancellations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->dateTime('cancelled_at');
            $t->text('reason');
            $t->string('status')->default('posted');
            $t->timestamps();
        });

        Schema::create('purchase_order_cancellation_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_order_cancellation_id')
                ->constrained('purchase_order_cancellations')
                ->cascadeOnDelete();
            $t->foreignId('purchase_order_item_id')
                ->constrained('purchase_order_items')
                ->restrictOnDelete();
            $t->decimal('qty', 18, 3);
            $t->decimal('conversion_factor', 18, 6)->default(1);
            $t->decimal('base_qty', 18, 6)->default(0);
            $t->timestamps();

            $t->index(['purchase_order_item_id', 'purchase_order_cancellation_id']);
        });

        // ================================================================
        // RECEIPT: single stock-in gateway
        // Existing receipt tables are extended, not replaced.
        // ================================================================
        Schema::table('receipts', function (Blueprint $t) {
            $t->string('source_type')->default('po')->after('user_id');
            $t->foreignId('purchase_id')->nullable()->after('source_type')
                ->constrained('purchases')->nullOnDelete();
            $t->string('verification_status')->default('not_required')->after('status');
            $t->foreignId('verified_by')->nullable()->after('verification_status')
                ->constrained('users')->nullOnDelete();
            $t->dateTime('verified_at')->nullable()->after('verified_by');
            $t->foreignId('approved_by')->nullable()->after('verified_at')
                ->constrained('users')->nullOnDelete();
            $t->dateTime('approved_at')->nullable()->after('approved_by');
            $t->foreignId('cancelled_by')->nullable()->after('approved_at')
                ->constrained('users')->nullOnDelete();
            $t->dateTime('cancelled_at')->nullable()->after('cancelled_by');
            $t->text('cancellation_reason')->nullable()->after('cancelled_at');
            $t->text('unidentified_reason')->nullable()->after('cancellation_reason');

            $t->index(['entity_id', 'source_type', 'status']);
            $t->index(['purchase_id']);
        });

        Schema::table('receipt_items', function (Blueprint $t) {
            $t->foreignId('purchase_order_item_id')->nullable()->after('receipt_id')
                ->constrained('purchase_order_items')->restrictOnDelete();
            $t->foreignId('purchase_item_id')->nullable()->change();
            $t->decimal('unit_cost', 18, 4)->nullable()->after('base_qty');
            $t->decimal('base_unit_cost', 18, 4)->nullable()->after('unit_cost');
            $t->string('valuation_status')->default('pending')->after('base_unit_cost');

            $t->index(['purchase_order_item_id']);
            $t->index(['purchase_item_id']);
        });

        // ================================================================
        // PURCHASE INVOICE / PURCHASE DOCUMENT
        // Existing purchases table becomes the invoice/payable document.
        // ================================================================
        Schema::table('purchases', function (Blueprint $t) {
            $t->string('status')->default('draft')->change();
            $t->string('document_type')->default('invoice')->after('user_id');
            $t->string('source_type')->default('po')->after('document_type');
            $t->boolean('goods_received')->default(false)->after('source_type');
            $t->string('posting_status')->default('draft')->after('status');
            $t->dateTime('posted_at')->nullable()->after('posting_status');
            $t->foreignId('posted_by')->nullable()->after('posted_at')
                ->constrained('users')->nullOnDelete();
            $t->string('supplier_tax_status')->default('non_pkp')->after('supplier_invoice_date');
            $t->string('tax_condition')->default('non_ppn')->after('supplier_tax_status');
            $t->string('tax_invoice_no')->nullable()->after('tax_condition');
            $t->date('tax_invoice_date')->nullable()->after('tax_invoice_no');
            $t->string('tax_invoice_status')->default('belum_ada')->after('tax_invoice_date');
            $t->decimal('dpp', 18, 2)->default(0)->after('tax_invoice_status');
            $t->decimal('ppn_rate', 9, 4)->default(0)->after('dpp');
            $t->decimal('ppn_amount', 18, 2)->default(0)->after('ppn_rate');
            $t->decimal('document_discount', 18, 2)->default(0)->after('ppn_amount');
            $t->decimal('additional_cost', 18, 2)->default(0)->after('document_discount');
            $t->decimal('rounding_amount', 18, 2)->default(0)->after('additional_cost');
            $t->text('posting_memo')->nullable()->after('rounding_amount');
            $t->foreignId('cancelled_by')->nullable()->after('posting_memo')
                ->constrained('users')->nullOnDelete();
            $t->dateTime('cancelled_at')->nullable()->after('cancelled_by');
            $t->text('cancellation_reason')->nullable()->after('cancelled_at');

            $t->unique(['entity_id', 'supplier_id', 'supplier_invoice_no'], 'purchases_supplier_invoice_unique');
            $t->unique(['entity_id', 'tax_invoice_no'], 'purchases_tax_invoice_unique');
            $t->index(['entity_id', 'supplier_id', 'posting_status']);
            $t->index(['entity_id', 'tax_invoice_status', 'tax_invoice_date']);
        });

        Schema::table('purchase_items', function (Blueprint $t) {
            $t->foreignId('unit_id')->nullable()->change();
            $t->decimal('line_subtotal', 18, 2)->default(0)->after('total');
            $t->decimal('line_discount', 18, 2)->default(0)->after('line_subtotal');
            $t->decimal('taxable_amount', 18, 2)->default(0)->after('line_discount');
            $t->decimal('tax_rate', 9, 4)->default(0)->after('taxable_amount');
            $t->decimal('tax_amount', 18, 2)->default(0)->after('tax_rate');
            $t->index(['purchase_id', 'product_id']);
        });

        // ================================================================
        // RECEIPT <-> INVOICE MANY-TO-MANY ALLOCATION
        // ================================================================
        Schema::create('receipt_invoice_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('receipt_item_id')->constrained('receipt_items')->restrictOnDelete();
            $t->foreignId('purchase_item_id')->constrained('purchase_items')->restrictOnDelete();
            $t->decimal('qty', 18, 3);
            $t->decimal('conversion_factor', 18, 6)->default(1);
            $t->decimal('base_qty', 18, 6)->default(0);
            $t->decimal('allocated_value', 18, 2)->default(0);
            $t->string('status')->default('posted');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['receipt_item_id', 'purchase_item_id']);
            $t->index(['purchase_item_id', 'receipt_item_id']);
            $t->unique(['receipt_item_id', 'purchase_item_id'], 'receipt_invoice_alloc_unique');
        });

        // ================================================================
        // PURCHASE RETURNS
        // ================================================================
        Schema::create('purchase_returns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $t->foreignId('business_unit_id')->constrained('business_units')->restrictOnDelete();
            $t->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $t->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->string('return_no');
            $t->dateTime('return_date');
            $t->string('status')->default('draft');
            $t->text('reason')->nullable();
            $t->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->dateTime('posted_at')->nullable();
            $t->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->dateTime('cancelled_at')->nullable();
            $t->text('cancellation_reason')->nullable();
            $t->timestamps();

            $t->unique(['entity_id', 'return_no']);
            $t->index(['entity_id', 'supplier_id', 'return_date']);
        });

        Schema::create('purchase_return_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_return_id')->constrained('purchase_returns')->cascadeOnDelete();
            $t->foreignId('receipt_item_id')->constrained('receipt_items')->restrictOnDelete();
            $t->foreignId('purchase_item_id')->nullable()->constrained('purchase_items')->restrictOnDelete();
            $t->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $t->foreignId('unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $t->decimal('qty', 18, 3);
            $t->decimal('conversion_factor', 18, 6)->default(1);
            $t->decimal('base_qty', 18, 6)->default(0);
            $t->decimal('unit_value', 18, 4)->default(0);
            $t->decimal('return_value', 18, 2)->default(0);
            $t->decimal('tax_amount', 18, 2)->default(0);
            $t->timestamps();

            $t->index(['receipt_item_id', 'purchase_return_id']);
        });

        // ================================================================
        // SUPPLIER PAYMENT / HUTANG SUBLEDGER
        // Separate from POS customer payments.
        // ================================================================
        Schema::create('supplier_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $t->foreignId('business_unit_id')->constrained('business_units')->restrictOnDelete();
            $t->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->dateTime('payment_date');
            $t->string('payment_no');
            $t->foreignId('cash_bank_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $t->decimal('amount', 18, 2);
            $t->string('status')->default('draft');
            $t->string('payment_type')->default('invoice_payment');
            $t->string('reference')->nullable();
            $t->text('memo')->nullable();
            $t->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->dateTime('posted_at')->nullable();
            $t->foreignId('reversed_payment_id')->nullable()->constrained('supplier_payments')->nullOnDelete();
            $t->text('reversal_reason')->nullable();
            $t->timestamps();

            $t->unique(['entity_id', 'payment_no']);
            $t->index(['entity_id', 'supplier_id', 'payment_date']);
            $t->index(['entity_id', 'status', 'payment_date']);
        });

        Schema::create('supplier_payment_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('supplier_payment_id')->constrained('supplier_payments')->restrictOnDelete();
            $t->foreignId('purchase_id')->constrained('purchases')->restrictOnDelete();
            $t->decimal('amount', 18, 2);
            $t->string('status')->default('posted');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->unique(['supplier_payment_id', 'purchase_id']);
            $t->index(['purchase_id', 'status']);
        });

        // ================================================================
        // SUPPLIER ADVANCES / OVERPAYMENTS
        // ================================================================
        Schema::create('supplier_advances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $t->foreignId('business_unit_id')->constrained('business_units')->restrictOnDelete();
            $t->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $t->foreignId('supplier_payment_id')->nullable()->constrained('supplier_payments')->nullOnDelete();
            $t->dateTime('advance_date');
            $t->decimal('amount', 18, 2);
            $t->string('status')->default('posted');
            $t->text('memo')->nullable();
            $t->timestamps();

            $t->index(['entity_id', 'supplier_id', 'advance_date']);
        });

        Schema::create('supplier_advance_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('supplier_advance_id')->constrained('supplier_advances')->restrictOnDelete();
            $t->foreignId('purchase_id')->constrained('purchases')->restrictOnDelete();
            $t->decimal('amount', 18, 2);
            $t->string('status')->default('posted');
            $t->timestamps();

            $t->unique(['supplier_advance_id', 'purchase_id']);
            $t->index(['purchase_id', 'status']);
        });

        // ================================================================
        // ADDITIONAL PURCHASE COSTS
        // ================================================================
        Schema::create('purchase_additional_costs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $t->foreignId('business_unit_id')->constrained('business_units')->restrictOnDelete();
            $t->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $t->foreignId('purchase_id')->nullable()->constrained('purchases')->nullOnDelete();
            $t->foreignId('receipt_id')->nullable()->constrained('receipts')->nullOnDelete();
            $t->foreignId('expense_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $t->string('cost_type');
            $t->string('capitalization_status')->default('expense');
            $t->string('allocation_method')->nullable();
            $t->decimal('amount', 18, 2);
            $t->dateTime('cost_date');
            $t->string('reference_no')->nullable();
            $t->text('description')->nullable();
            $t->string('status')->default('draft');
            $t->timestamps();

            $t->index(['entity_id', 'business_unit_id', 'cost_date']);
            $t->index(['purchase_id', 'receipt_id']);
        });

        Schema::create('purchase_additional_cost_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_additional_cost_id')
                ->constrained('purchase_additional_costs')
                ->restrictOnDelete();
            $t->foreignId('receipt_item_id')->nullable()->constrained('receipt_items')->restrictOnDelete();
            $t->foreignId('purchase_item_id')->nullable()->constrained('purchase_items')->restrictOnDelete();
            $t->decimal('amount', 18, 2);
            $t->timestamps();

            $t->index(['purchase_additional_cost_id', 'receipt_item_id']);
            $t->index(['purchase_additional_cost_id', 'purchase_item_id']);
        });

        // ================================================================
        // POST-POSTING PRICE / VALUE CORRECTIONS
        // ================================================================
        Schema::create('purchase_corrections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $t->foreignId('business_unit_id')->constrained('business_units')->restrictOnDelete();
            $t->foreignId('purchase_id')->constrained('purchases')->restrictOnDelete();
            $t->foreignId('purchase_item_id')->nullable()->constrained('purchase_items')->restrictOnDelete();
            $t->foreignId('receipt_item_id')->nullable()->constrained('receipt_items')->restrictOnDelete();
            $t->string('correction_type');
            $t->decimal('before_value', 18, 2)->default(0);
            $t->decimal('correction_value', 18, 2)->default(0);
            $t->decimal('after_value', 18, 2)->default(0);
            $t->decimal('ppn_correction', 18, 2)->default(0);
            $t->string('reason');
            $t->dateTime('correction_date');
            $t->string('status')->default('posted');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();

            $t->index(['entity_id', 'business_unit_id', 'correction_date']);
            $t->index(['purchase_id', 'purchase_item_id']);
        });

        // ================================================================
        // STOCK TRACE
        // receipt_id is nullable because stock movements also originate
        // from sales, production, opening/adjustment, and returns.
        // Purchase stock-in must be enforced by the receiving service.
        // ================================================================
        Schema::table('stock_movements', function (Blueprint $t) {
            $t->foreignId('receipt_id')->nullable()->after('reference_id')
                ->constrained('receipts')->nullOnDelete();
            $t->index(['receipt_id', 'movement_type']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $t) {
            $t->dropForeign(['receipt_id']);
            $t->dropIndex(['receipt_id', 'movement_type']);
            $t->dropColumn('receipt_id');
        });

        Schema::dropIfExists('purchase_corrections');
        Schema::dropIfExists('purchase_additional_cost_allocations');
        Schema::dropIfExists('purchase_additional_costs');
        Schema::dropIfExists('supplier_advance_allocations');
        Schema::dropIfExists('supplier_advances');
        Schema::dropIfExists('supplier_payment_allocations');
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
        Schema::dropIfExists('receipt_invoice_allocations');

        Schema::table('purchase_items', function (Blueprint $t) {
            $t->dropIndex(['purchase_id', 'product_id']);
            $t->dropColumn([
                'line_subtotal', 'line_discount', 'taxable_amount',
                'tax_rate', 'tax_amount'
            ]);
            $t->decimal('base_qty', 18, 6)->default(0)->change();
            $t->decimal('base_unit_cost', 18, 4)->default(0)->change();
        });

        Schema::table('purchases', function (Blueprint $t) {
            $t->dropUnique('purchases_supplier_invoice_unique');
            $t->dropUnique('purchases_tax_invoice_unique');
            $t->string('status')->default('received')->change();
            $t->dropIndex(['entity_id', 'supplier_id', 'posting_status']);
            $t->dropIndex(['entity_id', 'tax_invoice_status', 'tax_invoice_date']);
            $t->dropForeign(['posted_by']);
            $t->dropForeign(['cancelled_by']);
            $t->dropColumn([
                'document_type', 'source_type', 'goods_received', 'posting_status',
                'posted_at', 'posted_by', 'supplier_tax_status', 'tax_condition', 'tax_invoice_no',
                'tax_invoice_date', 'tax_invoice_status', 'dpp', 'ppn_rate',
                'ppn_amount', 'document_discount', 'additional_cost',
                'rounding_amount', 'posting_memo', 'cancelled_by',
                'cancelled_at', 'cancellation_reason'
            ]);
        });

        Schema::table('receipt_items', function (Blueprint $t) {
            $t->dropForeign(['purchase_order_item_id']);
            $t->dropIndex(['purchase_order_item_id']);
            $t->dropColumn([
                'purchase_order_item_id', 'unit_cost', 'base_unit_cost',
                'valuation_status'
            ]);
        });

        Schema::table('receipts', function (Blueprint $t) {
            $t->dropForeign(['purchase_id']);
            $t->dropForeign(['verified_by']);
            $t->dropForeign(['approved_by']);
            $t->dropForeign(['cancelled_by']);
            $t->dropIndex(['entity_id', 'source_type', 'status']);
            $t->dropIndex(['purchase_id']);
            $t->dropColumn([
                'source_type', 'purchase_id', 'verification_status', 'verified_by',
                'verified_at', 'approved_by', 'approved_at', 'cancelled_by',
                'cancelled_at', 'cancellation_reason', 'unidentified_reason'
            ]);
        });

        Schema::dropIfExists('purchase_order_cancellation_items');
        Schema::dropIfExists('purchase_order_cancellations');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
