<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CATATAN ERP:
        // Histori harga dibuat otomatis dari transaksi pembelian.
        // User tidak perlu menginput histori harga secara manual.
        // BOM tetap TIDAK menyimpan harga; BOM hanya menyimpan bahan + qty.
        if (!Schema::hasTable('purchase_price_histories')) {
            return;
        }

        // CATATAN:
        // Setiap detail pembelian otomatis dicatat sebagai histori harga.
        // reference_id menunjuk ke transaksi pembelian sehingga histori dapat diaudit.
        DB::unprepared('DROP TRIGGER IF EXISTS trg_purchase_item_price_history');

        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_purchase_item_price_history
AFTER INSERT ON purchase_items
FOR EACH ROW
INSERT INTO purchase_price_histories
    (entity_id, product_id, supplier_id, price_date, qty, unit_price, source, reference_id, created_at, updated_at)
SELECT
    p.entity_id,
    NEW.product_id,
    p.supplier_id,
    p.purchase_date,
    NEW.qty,
    NEW.unit_cost,
    'purchase',
    NEW.purchase_id,
    NOW(),
    NOW()
FROM purchases p
WHERE p.id = NEW.purchase_id
SQL
        );
    }

    public function down(): void
    {
        // CATATAN:
        // Rollback hanya menghapus mekanisme otomatisnya.
        // Data histori yang sudah tersimpan tidak ikut dihapus.
        DB::unprepared('DROP TRIGGER IF EXISTS trg_purchase_item_price_history');
    }
};
