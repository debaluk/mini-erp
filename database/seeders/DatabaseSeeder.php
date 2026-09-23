<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // ================================================================
        // ENTITY
        // ================================================================
        $entityId = DB::table('entities')->updateOrInsert(
            ['code' => 'ENT-001'],
            [
                'name' => 'Entitas Utama',
                'address' => 'Bali, Indonesia',
                'phone' => null,
                'email' => null,
                'is_active' => true,
                'updated_at' => $now,
            ]
        );

        $entityId = DB::table('entities')->where('code', 'ENT-001')->value('id');

        // ================================================================
        // BUSINESS UNITS
        // BU menentukan tipe usaha + metode HPP.
        // ================================================================
        $businessUnits = [
            ['code' => 'RET',  'name' => 'Retail Toko Bangunan', 'business_type' => 'retail',     'hpp_method' => 'perpetual'],
            ['code' => 'PROD', 'name' => 'Produksi Batako',       'business_type' => 'production', 'hpp_method' => 'perpetual'],
            ['code' => 'JASA', 'name' => 'Jasa Armada',           'business_type' => 'service',    'hpp_method' => 'direct_cost'],
        ];

        $buIds = [];

        foreach ($businessUnits as $bu) {
            DB::table('business_units')->updateOrInsert(
                ['entity_id' => $entityId, 'code' => $bu['code']],
                [
                    'name' => $bu['name'],
                    'business_type' => $bu['business_type'],
                    'hpp_method' => $bu['hpp_method'],
                    'is_active' => true,
                    'updated_at' => $now,
                ]
            );

            $buIds[$bu['code']] = DB::table('business_units')
                ->where('entity_id', $entityId)
                ->where('code', $bu['code'])
                ->value('id');
        }

        // ================================================================
        // USERS / LOGIN
        // ================================================================
        $users = [
            ['name' => 'Owner',     'email' => 'owner@minierp.local',     'role' => 'owner',     'default_bu' => 'RET'],
            ['name' => 'Admin',     'email' => 'admin@minierp.local',     'role' => 'admin',     'default_bu' => 'RET'],
            ['name' => 'Kasir',    'email' => 'kasir@minierp.local',    'role' => 'kasir',     'default_bu' => 'RET'],
            ['name' => 'Inventori', 'email' => 'inventori@minierp.local','role' => 'inventori', 'default_bu' => 'RET'],
            ['name' => 'Akuntansi', 'email' => 'akuntansi@minierp.local','role' => 'akuntansi', 'default_bu' => 'RET'],
        ];

        $userIds = [];

        foreach ($users as $user) {
            DB::table('users')->updateOrInsert(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make('password'),
                    'role' => $user['role'],
                    'entity_id' => $entityId,
                    'default_business_unit_id' => $buIds[$user['default_bu']],
                    'is_active' => true,
                    'updated_at' => $now,
                ]
            );

            $userIds[$user['role']] = DB::table('users')
                ->where('email', $user['email'])
                ->value('id');
        }

        // Semua user baseline diberi akses ke semua BU.
        foreach ($userIds as $userId) {
            foreach ($buIds as $buId) {
                DB::table('user_business_units')->updateOrInsert(
                    ['user_id' => $userId, 'business_unit_id' => $buId],
                    [
                        'is_default' => $buId === $buIds['RET'],
                        'updated_at' => $now,
                    ]
                );
            }
        }

        // ================================================================
        // UNITS / SATUAN
        // ================================================================
        $units = [
            ['code' => 'PCS', 'name' => 'Pcs'],
            ['code' => 'SAK', 'name' => 'Sak'],
            ['code' => 'KG',  'name' => 'Kilogram'],
            ['code' => 'M3',  'name' => 'Meter Kubik'],
            ['code' => 'TRIP','name' => 'Trip'],
            ['code' => 'JAM', 'name' => 'Jam'],
        ];

        $unitIds = [];

        foreach ($units as $unit) {
            DB::table('units')->updateOrInsert(
                ['entity_id' => $entityId, 'code' => $unit['code']],
                [
                    'name' => $unit['name'],
                    'is_active' => true,
                    'updated_at' => $now,
                ]
            );

            $unitIds[$unit['code']] = DB::table('units')
                ->where('entity_id', $entityId)
                ->where('code', $unit['code'])
                ->value('id');
        }

        // ================================================================
        // PRODUCT CATEGORIES
        // ================================================================
        $categories = [
            ['code' => 'BAHAN', 'name' => 'Bahan Baku'],
            ['code' => 'DAGANG', 'name' => 'Barang Dagangan'],
            ['code' => 'PRODUK', 'name' => 'Hasil Produksi'],
            ['code' => 'JASA', 'name' => 'Jasa'],
        ];

        $categoryIds = [];

        foreach ($categories as $category) {
            DB::table('product_categories')->updateOrInsert(
                ['entity_id' => $entityId, 'name' => $category['name']],
                [
                    'updated_at' => $now,
                ]
            );

            $categoryIds[$category['code']] = DB::table('product_categories')
                ->where('entity_id', $entityId)
                ->where('name', $category['name'])
                ->value('id');
        }

        // ================================================================
        // PRODUCTS
        // ================================================================
        $products = [
            [
                'code' => 'SEMEN-001',
                'name' => 'Semen',
                'category' => 'BAHAN',
                'unit' => 'KG',
                'item_type' => 'barang',
                'type' => 'raw_material',
                'sku' => 'SEMEN-001',
                'barcode' => '899000000001',
                'cost_price' => 1300,
                'selling_price' => 1400,
                'minimum_stock' => 500,
                'manage_stock' => true,
                'bus' => ['RET', 'PROD'],
            ],
            [
                'code' => 'PASIR-001',
                'name' => 'Pasir',
                'category' => 'BAHAN',
                'unit' => 'M3',
                'item_type' => 'barang',
                'type' => 'raw_material',
                'sku' => 'PASIR-001',
                'barcode' => '899000000002',
                'cost_price' => 250000,
                'selling_price' => 300000,
                'minimum_stock' => 5,
                'manage_stock' => true,
                'bus' => ['RET', 'PROD'],
            ],
            [
                'code' => 'BATAKO-001',
                'name' => 'Batako 10x20x40',
                'category' => 'PRODUK',
                'unit' => 'PCS',
                'item_type' => 'barang',
                'type' => 'finished_goods',
                'sku' => 'BATAKO-001',
                'barcode' => '899000000003',
                'cost_price' => 2500,
                'selling_price' => 4000,
                'minimum_stock' => 100,
                'manage_stock' => true,
                'bus' => ['RET', 'PROD'],
            ],
            [
                'code' => 'ANGKUT-001',
                'name' => 'Jasa Angkut',
                'category' => 'JASA',
                'unit' => 'TRIP',
                'item_type' => 'jasa',
                'type' => 'merchandise',
                'sku' => 'ANGKUT-001',
                'barcode' => null,
                'cost_price' => 0,
                'selling_price' => 500000,
                'minimum_stock' => 0,
                'manage_stock' => false,
                'bus' => ['JASA'],
            ],
        ];

        $productIds = [];

        foreach ($products as $product) {
            DB::table('products')->updateOrInsert(
                ['entity_id' => $entityId, 'code' => $product['code']],
                [
                    'name' => $product['name'],
                    'category_id' => $categoryIds[$product['category']],
                    'base_unit_id' => $unitIds[$product['unit']],
                    'item_type' => $product['item_type'],
                    'type' => $product['type'],
                    'sku' => $product['sku'],
                    'barcode' => $product['barcode'],
                    'cost_price' => $product['cost_price'],
                    'selling_price' => $product['selling_price'],
                    'minimum_stock' => $product['minimum_stock'],
                    'manage_stock' => $product['manage_stock'],
                    'is_active' => true,
                    'updated_at' => $now,
                ]
            );

            $productIds[$product['code']] = DB::table('products')
                ->where('entity_id', $entityId)
                ->where('code', $product['code'])
                ->value('id');

            DB::table('product_business_units')
                ->where('product_id', $productIds[$product['code']])
                ->delete();

            foreach ($product['bus'] as $buCode) {
                DB::table('product_business_units')->insert([
                    'product_id' => $productIds[$product['code']],
                    'business_unit_id' => $buIds[$buCode],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // Item-specific transaction UOM: 1 SAK semen = 50 KG Base Unit.
        DB::table('product_unit_conversions')->updateOrInsert(
            [
                'product_id' => $productIds['SEMEN-001'],
                'unit_id' => $unitIds['SAK'],
            ],
            [
                'conversion_factor' => 50,
                'is_default_purchase' => true,
                'is_default_sale' => true,
                'is_active' => true,
                'updated_at' => $now,
            ]
        );

        // ================================================================
        // CUSTOMER / SUPPLIER
        // ================================================================
        DB::table('customers')->updateOrInsert(
            ['entity_id' => $entityId, 'code' => 'CUS-001'],
            [
                'name' => 'Customer Demo',
                'phone' => '081234567890',
                'address' => 'Bali',
                'credit_limit' => 0,
                'is_active' => true,
                'updated_at' => $now,
            ]
        );

        DB::table('suppliers')->updateOrInsert(
            ['entity_id' => $entityId, 'code' => 'SUP-001'],
            [
                'name' => 'Supplier Demo',
                'phone' => '081298765432',
                'address' => 'Bali',
                'credit_limit' => 0,
                'is_active' => true,
                'updated_at' => $now,
            ]
        );

        // ================================================================
        // WAREHOUSES
        // ================================================================
        $warehouseIds = [];

        foreach ([
            ['code' => 'RET-GUD', 'name' => 'Gudang Retail', 'bu' => 'RET'],
            ['code' => 'PROD-GUD', 'name' => 'Gudang Produksi', 'bu' => 'PROD'],
        ] as $warehouse) {
            DB::table('warehouses')->updateOrInsert(
                ['entity_id' => $entityId, 'code' => $warehouse['code']],
                [
                    'business_unit_id' => $buIds[$warehouse['bu']],
                    'name' => $warehouse['name'],
                    'type' => 'general',
                    'address' => $warehouse['name'],
                    'is_active' => true,
                    'updated_at' => $now,
                ]
            );

            $warehouseIds[$warehouse['bu']] = DB::table('warehouses')
                ->where('entity_id', $entityId)
                ->where('code', $warehouse['code'])
                ->value('id');

            DB::table('warehouse_business_units')->updateOrInsert(
                [
                    'warehouse_id' => $warehouseIds[$warehouse['bu']],
                    'business_unit_id' => $buIds[$warehouse['bu']],
                ],
                [
                    'entity_id' => $entityId,
                    'updated_at' => $now,
                ]
            );
        }

        // ================================================================
        // ARMADA MASTER
        // ================================================================
        DB::table('vehicles')->updateOrInsert(
            ['entity_id' => $entityId, 'code' => 'TRK-001'],
            [
                'plate_number' => 'DK 1001 XX',
                'model' => 'Truck Bak',
                'vehicle_type' => 'Truck',
                'capacity' => 5,
                'current_km' => 0,
                'acquisition_value' => 0,
                'status' => 'active',
                'updated_at' => $now,
            ]
        );

        DB::table('drivers')->updateOrInsert(
            ['entity_id' => $entityId, 'code' => 'DRV-001'],
            [
                'name' => 'Driver Demo',
                'phone' => '0800000000',
                'license_no' => 'SIM-B1',
                'license_expiry' => null,
                'is_active' => true,
                'updated_at' => $now,
            ]
        );

        DB::table('tariffs')->updateOrInsert(
            ['entity_id' => $entityId, 'code' => 'TRF-001'],
            [
                'name' => 'Pengiriman Standar',
                'tariff_type' => 'per_km',
                'base_price' => 0,
                'price_per_km' => 5000,
                'price_per_hour' => 0,
                'minimum_charge' => 25000,
                'is_active' => true,
                'updated_at' => $now,
            ]
        );

        // ================================================================
        // CHART OF ACCOUNTS
        // ================================================================
        $accounts = [
            ['code'=>'100',     'name'=>'Asset',                         'type'=>'asset',     'level'=>1, 'parent'=>null,   'postable'=>false, 'cash'=>false, 'normal'=>'debit'],
            ['code'=>'10001',   'name'=>'Kas',                           'type'=>'asset',     'level'=>2, 'parent'=>'100', 'postable'=>false, 'cash'=>true,  'normal'=>'debit'],
            ['code'=>'1000101', 'name'=>'Kas Kecil',                     'type'=>'asset',     'level'=>3, 'parent'=>'10001','postable'=>true,  'cash'=>true,  'normal'=>'debit'],
            ['code'=>'1000102', 'name'=>'Kas Besar',                     'type'=>'asset',     'level'=>3, 'parent'=>'10001','postable'=>true,  'cash'=>true,  'normal'=>'debit'],
            ['code'=>'10002',   'name'=>'Bank',                          'type'=>'asset',     'level'=>2, 'parent'=>'100', 'postable'=>false, 'cash'=>true,  'normal'=>'debit'],
            ['code'=>'1000201', 'name'=>'Bank BCA',                      'type'=>'asset',     'level'=>3, 'parent'=>'10002','postable'=>true,  'cash'=>true,  'normal'=>'debit'],
            ['code'=>'10003',   'name'=>'Piutang',                       'type'=>'asset',     'level'=>2, 'parent'=>'100', 'postable'=>false, 'cash'=>false, 'normal'=>'debit'],
            ['code'=>'1000301', 'name'=>'Piutang Usaha',                 'type'=>'asset',     'level'=>3, 'parent'=>'10003','postable'=>true, 'cash'=>false, 'normal'=>'debit'],
            ['code'=>'1000302', 'name'=>'Piutang Pengurus',              'type'=>'asset',     'level'=>3, 'parent'=>'10003','postable'=>true, 'cash'=>false, 'normal'=>'debit'],
            ['code'=>'200',     'name'=>'Hutang',                        'type'=>'liability','level'=>1, 'parent'=>null,   'postable'=>false, 'cash'=>false, 'normal'=>'credit'],
            ['code'=>'20001',   'name'=>'Hutang Usaha',                  'type'=>'liability','level'=>2, 'parent'=>'200', 'postable'=>true,  'cash'=>false, 'normal'=>'credit'],
            ['code'=>'20002',   'name'=>'Hutang Pengurus',               'type'=>'liability','level'=>2, 'parent'=>'200', 'postable'=>true,  'cash'=>false, 'normal'=>'credit'],
            ['code'=>'300',     'name'=>'Modal',                         'type'=>'equity',   'level'=>1, 'parent'=>null,   'postable'=>false, 'cash'=>false, 'normal'=>'credit'],
            ['code'=>'30001',   'name'=>'Modal Disetor',                 'type'=>'equity',   'level'=>2, 'parent'=>'300', 'postable'=>true,  'cash'=>false, 'normal'=>'credit'],
            ['code'=>'400',     'name'=>'Pendapatan',                    'type'=>'revenue',  'level'=>1, 'parent'=>null,   'postable'=>false, 'cash'=>false, 'normal'=>'credit'],
            ['code'=>'40001',   'name'=>'Pendapatan Usaha',              'type'=>'revenue',  'level'=>2, 'parent'=>'400', 'postable'=>false, 'cash'=>false, 'normal'=>'credit'],
            ['code'=>'4000101', 'name'=>'Penjualan Barang Dagangan',     'type'=>'revenue',  'level'=>3, 'parent'=>'40001','postable'=>true,  'cash'=>false, 'normal'=>'credit'],
            ['code'=>'4000102', 'name'=>'Penjualan Hasil Produksi',      'type'=>'revenue',  'level'=>3, 'parent'=>'40001','postable'=>true,  'cash'=>false, 'normal'=>'credit'],
            ['code'=>'4000103', 'name'=>'Pendapatan Jasa Armada',        'type'=>'revenue',  'level'=>3, 'parent'=>'40001','postable'=>true,  'cash'=>false, 'normal'=>'credit'],
            ['code'=>'4000104', 'name'=>'Pendapatan Usaha Lainnya',      'type'=>'revenue',  'level'=>3, 'parent'=>'40001','postable'=>true,  'cash'=>false, 'normal'=>'credit'],
            ['code'=>'500',     'name'=>'HPP',                           'type'=>'cogs',     'level'=>1, 'parent'=>null,   'postable'=>false, 'cash'=>false, 'normal'=>'debit'],
            ['code'=>'50001',   'name'=>'Harga Pokok Pendapatan',        'type'=>'cogs',     'level'=>2, 'parent'=>'500', 'postable'=>false, 'cash'=>false, 'normal'=>'debit'],
            ['code'=>'5000101', 'name'=>'HPP Barang Dagangan',           'type'=>'cogs',     'level'=>3, 'parent'=>'50001','postable'=>true,  'cash'=>false, 'normal'=>'debit'],
            ['code'=>'5000102', 'name'=>'HPP Hasil Produksi',            'type'=>'cogs',     'level'=>3, 'parent'=>'50001','postable'=>true,  'cash'=>false, 'normal'=>'debit'],
            ['code'=>'50002',   'name'=>'Beban Langsung Pendapatan',     'type'=>'cogs',     'level'=>2, 'parent'=>'500', 'postable'=>false, 'cash'=>false, 'normal'=>'debit'],
            ['code'=>'5000201', 'name'=>'Beban Langsung Tenaga Kerja',   'type'=>'cogs',     'level'=>3, 'parent'=>'50002','postable'=>true,  'cash'=>false, 'normal'=>'debit'],
            ['code'=>'5000202', 'name'=>'Beban Langsung Lainya (Overhead)','type'=>'cogs',   'level'=>3, 'parent'=>'50002','postable'=>true,  'cash'=>false, 'normal'=>'debit'],
            ['code'=>'5000203', 'name'=>'Bahan Baku Langsung',           'type'=>'cogs',     'level'=>3, 'parent'=>'50002','postable'=>true,  'cash'=>false, 'normal'=>'debit'],
            ['code'=>'600',     'name'=>'Biaya',                         'type'=>'expense',  'level'=>1, 'parent'=>null,   'postable'=>false, 'cash'=>false, 'normal'=>'debit'],
            ['code'=>'60001',   'name'=>'Beban Operasional',             'type'=>'expense',  'level'=>2, 'parent'=>'600', 'postable'=>false, 'cash'=>false, 'normal'=>'debit'],
            ['code'=>'6000101', 'name'=>'Beban Gaji',                    'type'=>'expense',  'level'=>3, 'parent'=>'60001','postable'=>true, 'cash'=>false, 'normal'=>'debit'],
            ['code'=>'6000102', 'name'=>'Beban Listrik',                 'type'=>'expense',  'level'=>3, 'parent'=>'60001','postable'=>true, 'cash'=>false, 'normal'=>'debit'],
        ];

        $coaIds = [];

        foreach ($accounts as $account) {
            DB::table('chart_of_accounts')->updateOrInsert(
                ['entity_id' => $entityId, 'code' => $account['code']],
                [
                    'name' => $account['name'],
                    'level' => $account['level'],
                    'type' => $account['type'],
                    'normal_balance' => $account['normal'],
                    'parent_id' => null,
                    'is_postable' => $account['postable'],
                    'is_cash_bank' => $account['cash'],
                    'is_active' => true,
                    'updated_at' => $now,
                ]
            );

            $coaIds[$account['code']] = DB::table('chart_of_accounts')
                ->where('entity_id', $entityId)
                ->where('code', $account['code'])
                ->value('id');
        }

        foreach ($accounts as $account) {
            if ($account['parent']) {
                DB::table('chart_of_accounts')
                    ->where('id', $coaIds[$account['code']])
                    ->update(['parent_id' => $coaIds[$account['parent']], 'updated_at' => $now]);
            }
        }

        // ================================================================
        // BU ACCOUNT MAPPINGS
        // Single source of truth untuk engine accounting/HPP.
        // ================================================================
        $mappingAccounts = [
            'cash' => '1000101',
            'bank' => '1000201',
            'receivable' => '1000301',
            'payable' => '20001',
            'inventory' => '12000',
            'sales_merchandise' => '4000101',
            'sales_finished_goods' => '4000102',
            'sales_service' => '4000103',
            'cogs_merchandise' => '5000101',
            'cogs_finished_goods' => '5000102',
            'direct_labor' => '5000201',
            'direct_overhead' => '5000202',
            'direct_material' => '5000203',
        ];

        // Persediaan belum ada di daftar legacy COA di atas.
        // Tambahkan akun postable agar mapping inventory valid.
        DB::table('chart_of_accounts')->updateOrInsert(
            ['entity_id' => $entityId, 'code' => '12000'],
            [
                'name' => 'Persediaan',
                'level' => 2,
                'type' => 'asset',
                'normal_balance' => 'debit',
                'parent_id' => $coaIds['100'],
                'is_postable' => true,
                'is_cash_bank' => false,
                'is_active' => true,
                'updated_at' => $now,
            ]
        );
        $coaIds['12000'] = DB::table('chart_of_accounts')
            ->where('entity_id', $entityId)->where('code', '12000')->value('id');

        $buMappings = [
            'RET' => [
                'cash', 'bank', 'receivable', 'payable', 'inventory',
                'sales_merchandise', 'cogs_merchandise',
            ],
            'PROD' => [
                'cash', 'bank', 'receivable', 'payable', 'inventory',
                'sales_finished_goods', 'cogs_finished_goods',
                'direct_material', 'direct_labor', 'direct_overhead',
            ],
            'JASA' => [
                'cash', 'bank', 'receivable', 'payable',
                'sales_service', 'direct_labor', 'direct_overhead', 'direct_material',
            ],
        ];

        foreach ($buMappings as $buCode => $keys) {
            foreach ($keys as $key) {
                DB::table('business_unit_account_mappings')->updateOrInsert(
                    [
                        'business_unit_id' => $buIds[$buCode],
                        'mapping_key' => $key,
                    ],
                    [
                        'entity_id' => $entityId,
                        'account_id' => $coaIds[$mappingAccounts[$key]],
                        'updated_at' => $now,
                    ]
                );
            }
        }

        // ================================================================
        // INITIAL STOCK / WAREHOUSE STOCK
        // Hanya baseline ringan untuk UAT. HPP engine tetap menghitung
        // berdasarkan transaksi/movement, bukan nilai hardcoded produk.
        // ================================================================
        $initialStocks = [
            ['bu'=>'RET',  'product'=>'SEMEN-001',   'warehouse'=>'RET',  'qty'=>100, 'cost'=>1300],
            ['bu'=>'RET',  'product'=>'PASIR-001',   'warehouse'=>'RET',  'qty'=>50,  'cost'=>250000],
            ['bu'=>'RET',  'product'=>'BATAKO-001', 'warehouse'=>'RET',  'qty'=>200, 'cost'=>2500],
            ['bu'=>'PROD', 'product'=>'SEMEN-001',   'warehouse'=>'PROD', 'qty'=>100, 'cost'=>1300],
            ['bu'=>'PROD', 'product'=>'PASIR-001',   'warehouse'=>'PROD', 'qty'=>50,  'cost'=>250000],
        ];

        foreach ($initialStocks as $stock) {
            DB::table('warehouses_stocks')->updateOrInsert(
                [
                    'warehouse_id' => $warehouseIds[$stock['warehouse']],
                    'product_id' => $productIds[$stock['product']],
                ],
                [
                    'entity_id' => $entityId,
                    'qty' => $stock['qty'],
                    'avg_cost' => $stock['cost'],
                    'updated_at' => $now,
                ]
            );

            DB::table('item_initial_setups')->updateOrInsert(
                [
                    'entity_id' => $entityId,
                    'product_id' => $productIds[$stock['product']],
                ],
                [
                    'business_unit_id' => $buIds[$stock['bu']],
                    'warehouse_id' => $warehouseIds[$stock['warehouse']],
                    'setup_date' => now()->toDateString(),
                    'purchase_price' => $stock['cost'],
                    'initial_stock' => $stock['qty'],
                    'markup_percent' => 0,
                    'selling_price' => $products[array_search($stock['product'], array_column($products, 'code'))]['selling_price'],
                    'updated_at' => $now,
                ]
            );
        }

        // ================================================================
        // BOM BATako
        // 10 pcs output: 1 sak semen + 2 m3 pasir.
        // BOM hanya standard reference; HPP final memakai actual usage.
        // ================================================================
        DB::table('boms')->updateOrInsert(
            [
                'entity_id' => $entityId,
                'business_unit_id' => $buIds['PROD'],
                'code' => 'BOM-BATAKO',
            ],
            [
                'product_id' => $productIds['BATAKO-001'],
                'name' => 'Formula Batako Standar',
                'output_qty' => 10,
                'is_active' => true,
                'updated_at' => $now,
            ]
        );

        $bomId = DB::table('boms')
            ->where('entity_id', $entityId)
            ->where('business_unit_id', $buIds['PROD'])
            ->where('code', 'BOM-BATAKO')
            ->value('id');

        DB::table('bom_items')->where('bom_id', $bomId)->delete();

        DB::table('bom_items')->insert([
            [
                'bom_id' => $bomId,
                'product_id' => $productIds['SEMEN-001'],
                'qty' => 50,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'bom_id' => $bomId,
                'product_id' => $productIds['PASIR-001'],
                'qty' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // ================================================================
        // MODULE PERMISSIONS
        // Baseline permission sederhana sesuai role yang dikunci.
        // ================================================================
        $roleModules = [
            'owner' => ['dashboard','master','pos','inventory','production','fleet','accounting','reports','settings'],
            'admin' => ['dashboard','master','settings'],
            'kasir' => ['dashboard','pos'],
            'inventori' => ['dashboard','master','inventory','production','fleet'],
            'akuntansi' => ['dashboard','accounting','reports'],
        ];

        foreach ($users as $user) {
            $userId = $userIds[$user['role']];

            foreach ($roleModules[$user['role']] as $module) {
                DB::table('user_module_permissions')->updateOrInsert(
                    ['user_id' => $userId, 'module' => $module],
                    ['updated_at' => $now]
                );
            }
        }
    }
}
