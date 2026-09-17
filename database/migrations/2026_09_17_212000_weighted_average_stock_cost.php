<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // COSTING ERP:
        // Stok menggunakan metode Weighted Average (rata-rata tertimbang).
        //
        // Rumus:
        //   average baru =
        //   ((qty lama x average lama) + (qty masuk x harga masuk)) / qty baru
        //
        // Dengan cara ini, pembelian dengan harga baru tidak langsung
        // menimpa avg_cost lama. Nilai ini kemudian dipakai sebagai dasar HPP.
        if (!Schema::hasTable('warehouses_stocks')) {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS trg_weighted_average_stock_cost');

        DB::unprepared("
            CREATE TRIGGER trg_weighted_average_stock_cost
            BEFORE UPDATE ON warehouses_stocks
            FOR EACH ROW
            BEGIN
                DECLARE qty_in DECIMAL(18,3);

                SET qty_in = NEW.qty - OLD.qty;

                -- Hanya proses penambahan stok yang sekaligus membawa cost baru.
                -- Jika stok berkurang, avg_cost tidak diubah.
                IF OLD.qty > 0 AND qty_in > 0 AND NEW.avg_cost <> OLD.avg_cost THEN
                    SET NEW.avg_cost =
                        ((OLD.qty * OLD.avg_cost) + (qty_in * NEW.avg_cost)) / NEW.qty;
                END IF;
            END
        ");
    }

    public function down(): void
    {
        // Rollback hanya menghapus mekanisme weighted average.
        DB::unprepared('DROP TRIGGER IF EXISTS trg_weighted_average_stock_cost');
    }
};
