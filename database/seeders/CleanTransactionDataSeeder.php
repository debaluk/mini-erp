<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class CleanTransactionDataSeeder extends Seeder
{
    /**
     * Bersihkan data transaksi untuk memulai pengujian dari awal.
     *
     * Seeder ini sengaja TIDAK dipanggil dari DatabaseSeeder agar
     * menjalankan "php artisan db:seed" biasa tidak menghapus transaksi.
     *
     * Master yang dipertahankan: entitas, BU, user, COA, mapping akun,
     * produk, satuan, gudang, customer, supplier, pekerja, BOM,
     * konversi satuan, dan daftar harga.
     */
    public function run(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            throw new RuntimeException(
                'CleanTransactionDataSeeder hanya mendukung database MySQL.'
            );
        }

        $tables = [
            // Detail dan dokumen pembelian
            'purchase_additional_cost_allocations',
            'purchase_additional_costs',
            'purchase_corrections',
            'supplier_advance_allocations',
            'supplier_advances',
            'supplier_payment_allocations',
            'supplier_payments',
            'purchase_return_items',
            'purchase_returns',
            'receipt_invoice_allocations',
            'receipt_items',
            'receipts',
            'purchase_items',
            'purchases',
            'purchase_order_cancellation_items',
            'purchase_order_cancellations',
            'purchase_order_items',
            'purchase_orders',

            // Penjualan dan retur
            'sales_return_items',
            'sales_returns',
            'sale_items',
            'payments',
            'sales',

            // Produksi
            'production_work_order_result_lines',
            'production_work_order_results',
            'production_material_usage_items',
            'production_wo_material_usages',
            'production_work_order_costs',
            'production_work_order_workers',
            'production_work_orders',
            'production_material_usages',
            'production_costs',
            'production_outputs',
            'production_rejects',
            'productions',

            // Persediaan dan pergerakan antar gudang
            'stock_transfer_items',
            'stock_transfers',
            'stock_adjustment_items',
            'stock_adjustments',
            'stock_opname_items',
            'stock_opnames',
            'stock_movements',
            'item_initial_setups',

            // Jurnal, periode, dan histori transaksi
            'journal_entries',
            'journals',
            'accounting_periods',
            'purchase_price_histories',
            'product_price_histories',
            'sync_logs',
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($tables as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->truncate();
                }
            }

            // Jangan menghapus baris stok/master gudang-produk; kembalikan
            // kuantitas dan moving average ke nol setelah mutasi dibersihkan.
            if (Schema::hasTable('warehouses_stocks')) {
                DB::table('warehouses_stocks')->update([
                    'qty' => 0,
                    'avg_cost' => 0,
                    'updated_at' => now(),
                ]);
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->command?->info(
            'Data transaksi dibersihkan. Master data dipertahankan; stok dan moving average direset ke nol.'
        );
    }
}
