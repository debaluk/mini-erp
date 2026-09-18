<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MasterProductSeeder extends Seeder
{
    public function run(): void
    {
        $entity = DB::table('entities')->orderBy('id')->first();

        if (!$entity) {
            $entityId = DB::table('entities')->insertGetId([
                'code' => 'ENT-001',
                'name' => 'Entitas Utama',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $entityId = $entity->id;
        }

        DB::transaction(function () use ($entityId) {
            /*
             * RESET DATA UAT YANG BERHUBUNGAN DENGAN BARANG.
             * User, entity, supplier, customer, gudang, kendaraan, driver,
             * tarif dan akun tidak disentuh.
             */
            $tables = [
                'stock_opname_items',
                'stock_opnames',
                'sale_items',
                'payments',
                'sales',
                'purchase_items',
                'purchases',
                'productions',
                'bom_items',
                'boms',
                'stock_movements',
                'warehouses_stocks',
                'purchase_price_histories',
                'product_units',
            ];

            foreach ($tables as $table) {
                if (DB::getSchemaBuilder()->hasTable($table)) {
                    DB::table($table)->delete();
                }
            }

            // Lepaskan FK products.unit_id dan products.category_id.
            DB::table('products')
                ->where('entity_id', $entityId)
                ->update([
                    'unit_id' => null,
                    'category_id' => null,
                    'updated_at' => now(),
                ]);

            // Bersihkan master barang, kategori barang, dan satuan entitas ini.
            DB::table('products')->where('entity_id', $entityId)->delete();
            DB::table('product_categories')->where('entity_id', $entityId)->delete();
            DB::table('units')->where('entity_id', $entityId)->delete();

            $now = now();

            $units = [
                ['code' => 'PCS', 'name' => 'Pieces'],
                ['code' => 'KG', 'name' => 'Kilogram'],
                ['code' => 'GRAM', 'name' => 'Gram'],
                ['code' => 'TON', 'name' => 'Ton'],
                ['code' => 'M3', 'name' => 'Meter Kubik'],
                ['code' => 'LITER', 'name' => 'Liter'],
                ['code' => 'ML', 'name' => 'Mililiter'],
                ['code' => 'SAK', 'name' => 'Sak'],
                ['code' => 'DUS', 'name' => 'Dus'],
                ['code' => 'BOX', 'name' => 'Box'],
                ['code' => 'PACK', 'name' => 'Pack'],
                ['code' => 'BATANG', 'name' => 'Batang'],
                ['code' => 'PAIL', 'name' => 'Pail'],
                ['code' => 'M', 'name' => 'Meter'],
                ['code' => 'M2', 'name' => 'Meter Persegi'],
                ['code' => 'TRIP', 'name' => 'Trip'],
                ['code' => 'JAM', 'name' => 'Jam'],
            ];

            foreach ($units as &$unit) {
                $unit['entity_id'] = $entityId;
                $unit['is_active'] = 1;
                $unit['created_at'] = $now;
                $unit['updated_at'] = $now;
            }
            unset($unit);

            DB::table('units')->insert($units);

            $unitIds = [];
            foreach (DB::table('units')->where('entity_id', $entityId)->get(['id', 'code']) as $unit) {
                $unitIds[$unit->code] = $unit->id;
            }

            $products = [
                // Bahan baku produksi batako
                ['sku' => 'BB-001', 'barcode' => '899100000001', 'name' => 'Semen Portland', 'type' => 'raw_material', 'unit' => 'KG', 'cost' => 1800, 'sell' => 2200, 'min' => 500],
                ['sku' => 'BB-002', 'barcode' => '899100000002', 'name' => 'Pasir Beton', 'type' => 'raw_material', 'unit' => 'M3', 'cost' => 250000, 'sell' => 300000, 'min' => 5],
                ['sku' => 'BB-003', 'barcode' => '899100000003', 'name' => 'Abu Batu', 'type' => 'raw_material', 'unit' => 'M3', 'cost' => 220000, 'sell' => 270000, 'min' => 3],
                ['sku' => 'BB-004', 'barcode' => '899100000004', 'name' => 'Air Produksi', 'type' => 'raw_material', 'unit' => 'LITER', 'cost' => 5, 'sell' => 10, 'min' => 1000],

                // Barang jadi produksi
                ['sku' => 'FG-001', 'barcode' => '899100000101', 'name' => 'Batako 10x20x40', 'type' => 'finished_goods', 'unit' => 'PCS', 'cost' => 2500, 'sell' => 4000, 'min' => 100],
                ['sku' => 'FG-002', 'barcode' => '899100000102', 'name' => 'Batako 12x20x40', 'type' => 'finished_goods', 'unit' => 'PCS', 'cost' => 3000, 'sell' => 4500, 'min' => 100],
                ['sku' => 'FG-003', 'barcode' => '899100000103', 'name' => 'Batako 15x20x40', 'type' => 'finished_goods', 'unit' => 'PCS', 'cost' => 3500, 'sell' => 5000, 'min' => 50],
                ['sku' => 'FG-004', 'barcode' => '899100000104', 'name' => 'Paving Block 6 cm', 'type' => 'finished_goods', 'unit' => 'PCS', 'cost' => 1800, 'sell' => 3000, 'min' => 100],
                ['sku' => 'FG-005', 'barcode' => '899100000105', 'name' => 'Paving Block 8 cm', 'type' => 'finished_goods', 'unit' => 'PCS', 'cost' => 2200, 'sell' => 3500, 'min' => 100],

                // Barang toko bangunan
                ['sku' => 'TB-001', 'barcode' => '899100000201', 'name' => 'Semen Instan', 'type' => 'merchandise', 'unit' => 'SAK', 'cost' => 65000, 'sell' => 75000, 'min' => 20],
                ['sku' => 'TB-002', 'barcode' => '899100000202', 'name' => 'Kapur Bangunan', 'type' => 'merchandise', 'unit' => 'SAK', 'cost' => 35000, 'sell' => 45000, 'min' => 10],
                ['sku' => 'TB-003', 'barcode' => '899100000203', 'name' => 'Paku 2 Inch', 'type' => 'merchandise', 'unit' => 'KG', 'cost' => 28000, 'sell' => 35000, 'min' => 10],
                ['sku' => 'TB-004', 'barcode' => '899100000204', 'name' => 'Paku 3 Inch', 'type' => 'merchandise', 'unit' => 'KG', 'cost' => 28000, 'sell' => 35000, 'min' => 10],
                ['sku' => 'TB-005', 'barcode' => '899100000205', 'name' => 'Kawat Beton', 'type' => 'merchandise', 'unit' => 'KG', 'cost' => 18000, 'sell' => 25000, 'min' => 10],
                ['sku' => 'TB-006', 'barcode' => '899100000206', 'name' => 'Besi Beton 8 mm', 'type' => 'merchandise', 'unit' => 'BATANG', 'cost' => 48000, 'sell' => 55000, 'min' => 20],
                ['sku' => 'TB-007', 'barcode' => '899100000207', 'name' => 'Besi Beton 10 mm', 'type' => 'merchandise', 'unit' => 'BATANG', 'cost' => 72000, 'sell' => 82000, 'min' => 20],
                ['sku' => 'TB-008', 'barcode' => '899100000208', 'name' => 'Bata Merah', 'type' => 'merchandise', 'unit' => 'PCS', 'cost' => 1200, 'sell' => 1800, 'min' => 500],
                ['sku' => 'TB-009', 'barcode' => '899100000209', 'name' => 'Keramik 40x40', 'type' => 'merchandise', 'unit' => 'DUS', 'cost' => 85000, 'sell' => 105000, 'min' => 10],
                ['sku' => 'TB-010', 'barcode' => '899100000210', 'name' => 'Cat Tembok 5 Kg', 'type' => 'merchandise', 'unit' => 'PAIL', 'cost' => 95000, 'sell' => 120000, 'min' => 5],
                ['sku' => 'TB-011', 'barcode' => '899100000211', 'name' => 'Lem Keramik', 'type' => 'merchandise', 'unit' => 'KG', 'cost' => 9000, 'sell' => 12000, 'min' => 20],
                ['sku' => 'TB-012', 'barcode' => '899100000212', 'name' => 'Pipa PVC 1/2 Inch', 'type' => 'merchandise', 'unit' => 'BATANG', 'cost' => 18000, 'sell' => 25000, 'min' => 20],
                ['sku' => 'TB-013', 'barcode' => '899100000213', 'name' => 'Elbow PVC 1/2 Inch', 'type' => 'merchandise', 'unit' => 'PCS', 'cost' => 2500, 'sell' => 4000, 'min' => 20],
                ['sku' => 'TB-014', 'barcode' => '899100000214', 'name' => 'Kran Air 1/2 Inch', 'type' => 'merchandise', 'unit' => 'PCS', 'cost' => 18000, 'sell' => 25000, 'min' => 5],
                ['sku' => 'TB-015', 'barcode' => '899100000215', 'name' => 'Kabel NYM 2x1.5 mm', 'type' => 'merchandise', 'unit' => 'M', 'cost' => 8500, 'sell' => 11000, 'min' => 100],
                ['sku' => 'TB-016', 'barcode' => '899100000216', 'name' => 'Kabel NYM 3x2.5 mm', 'type' => 'merchandise', 'unit' => 'M', 'cost' => 14500, 'sell' => 18000, 'min' => 100],

                // Operasional armada
                ['sku' => 'OP-001', 'barcode' => '899100000301', 'name' => 'Oli Mesin', 'type' => 'merchandise', 'unit' => 'LITER', 'cost' => 55000, 'sell' => 70000, 'min' => 10],
                ['sku' => 'OP-002', 'barcode' => '899100000302', 'name' => 'Grease', 'type' => 'merchandise', 'unit' => 'KG', 'cost' => 35000, 'sell' => 45000, 'min' => 5],
                ['sku' => 'OP-003', 'barcode' => '899100000303', 'name' => 'Solar', 'type' => 'merchandise', 'unit' => 'LITER', 'cost' => 10000, 'sell' => 12000, 'min' => 50],
                ['sku' => 'OP-004', 'barcode' => '899100000304', 'name' => 'Ban Truk', 'type' => 'merchandise', 'unit' => 'PCS', 'cost' => 2500000, 'sell' => 2800000, 'min' => 2],
            ];

            $productIds = [];

            foreach ($products as $p) {
                $productId = DB::table('products')->insertGetId([
                    'entity_id' => $entityId,
                    'category_id' => null,
                    'unit_id' => $unitIds[$p['unit']],
                    'sku' => $p['sku'],
                    'barcode' => $p['barcode'],
                    'name' => $p['name'],
                    'type' => $p['type'],
                    'cost_price' => $p['cost'],
                    'selling_price' => $p['sell'],
                    'minimum_stock' => $p['min'],
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $productIds[$p['sku']] = $productId;
            }

            /*
             * Konversi satuan produk-spesifik.
             * Faktor = 1 satuan transaksi berapa satuan dasar.
             */
            $conversions = [
                ['sku' => 'BB-001', 'unit' => 'SAK', 'factor' => 50],
                ['sku' => 'FG-001', 'unit' => 'DUS', 'factor' => 20],
                ['sku' => 'FG-002', 'unit' => 'DUS', 'factor' => 20],
                ['sku' => 'FG-003', 'unit' => 'DUS', 'factor' => 20],
                ['sku' => 'FG-004', 'unit' => 'DUS', 'factor' => 20],
                ['sku' => 'FG-005', 'unit' => 'DUS', 'factor' => 20],
                ['sku' => 'TB-003', 'unit' => 'BOX', 'factor' => 20],
                ['sku' => 'TB-004', 'unit' => 'BOX', 'factor' => 20],
                ['sku' => 'TB-009', 'unit' => 'PCS', 'factor' => 10],
                ['sku' => 'TB-015', 'unit' => 'BOX', 'factor' => 100],
                ['sku' => 'TB-016', 'unit' => 'BOX', 'factor' => 100],
            ];

            foreach ($conversions as $c) {
                if (!isset($productIds[$c['sku']], $unitIds[$c['unit']])) {
                    continue;
                }

                DB::table('product_units')->insert([
                    'entity_id' => $entityId,
                    'product_id' => $productIds[$c['sku']],
                    'unit_id' => $unitIds[$c['unit']],
                    'conversion_factor' => $c['factor'],
                    'is_default' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Pastikan satuan dasar tiap produk selalu tersedia di product_units.
            foreach ($products as $p) {
                DB::table('product_units')->insert([
                    'entity_id' => $entityId,
                    'product_id' => $productIds[$p['sku']],
                    'unit_id' => $unitIds[$p['unit']],
                    'conversion_factor' => 1,
                    'is_default' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Gudang utama.
            $warehouse = DB::table('warehouses')
                ->where('entity_id', $entityId)
                ->where('code', 'GUD-001')
                ->first();

            if (!$warehouse) {
                $warehouseId = DB::table('warehouses')->insertGetId([
                    'entity_id' => $entityId,
                    'code' => 'GUD-001',
                    'name' => 'Gudang Utama',
                    'type' => 'general',
                    'address' => 'Gudang Utama',
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $warehouseId = $warehouse->id;
            }

            // Stok awal sengaja dibuat cukup untuk UAT POS + produksi.
            $initialStock = [
                'BB-001' => 2000,
                'BB-002' => 20,
                'BB-003' => 10,
                'BB-004' => 5000,
                'FG-001' => 500,
                'FG-002' => 300,
                'FG-003' => 200,
                'FG-004' => 300,
                'FG-005' => 200,
                'TB-001' => 50,
                'TB-002' => 30,
                'TB-003' => 50,
                'TB-004' => 50,
                'TB-005' => 50,
                'TB-006' => 100,
                'TB-007' => 80,
                'TB-008' => 2000,
                'TB-009' => 50,
                'TB-010' => 30,
                'TB-011' => 50,
                'TB-012' => 50,
                'TB-013' => 100,
                'TB-014' => 30,
                'TB-015' => 500,
                'TB-016' => 300,
                'OP-001' => 20,
                'OP-002' => 10,
                'OP-003' => 500,
                'OP-004' => 8,
            ];

            foreach ($initialStock as $sku => $qty) {
                DB::table('warehouses_stocks')->insert([
                    'entity_id' => $entityId,
                    'warehouse_id' => $warehouseId,
                    'product_id' => $productIds[$sku],
                    'qty' => $qty,
                    'avg_cost' => $products[array_search($sku, array_column($products, 'sku'))]['cost'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // BOM produksi utama untuk UAT produksi batako.
            $bomId = DB::table('boms')->insertGetId([
                'entity_id' => $entityId,
                'product_id' => $productIds['FG-001'],
                'code' => 'BOM-BATAKO-10',
                'name' => 'Formula Batako 10x20x40',
                'output_qty' => 10,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('bom_items')->insert([
                [
                    'bom_id' => $bomId,
                    'product_id' => $productIds['BB-001'],
                    'qty' => 2,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'bom_id' => $bomId,
                    'product_id' => $productIds['BB-002'],
                    'qty' => 0.25,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'bom_id' => $bomId,
                    'product_id' => $productIds['BB-003'],
                    'qty' => 0.10,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        });
    }
}
