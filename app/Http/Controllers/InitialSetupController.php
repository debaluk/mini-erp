<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InitialSetupController extends Controller
{
    private function entityId(): int
    {
        $entity = DB::table('entities')->first();
        abort_unless($entity, 500, 'Entitas belum tersedia.');
        return (int) $entity->id;
    }

    public function index(Request $request)
    {
        $entity = $this->entityId();
        $entityName = DB::table('entities')->where('id', $entity)->value('name') ?? 'NAMA ENTITAS';

        $businessUnits = DB::table('business_units')
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->orderBy('id')
            ->get(['id', 'code', 'name']);

        abort_unless($businessUnits->isNotEmpty(), 422, 'Belum ada Business Unit aktif.');

        $businessUnitParam = $request->input('business_unit_id', 'all');
        $businessUnitId = $businessUnitParam === 'all' ? null : (int) $businessUnitParam;

        if ($businessUnitId !== null) {
            abort_unless(
                $businessUnits->contains(fn ($unit) => (int) $unit->id === $businessUnitId),
                422,
                'Business Unit tidak valid.'
            );
        }

        $rows = DB::table('products as p')
            ->join('product_business_units as pu', function ($join) use ($businessUnitId) {
                $join->on('pu.product_id', '=', 'p.id');
                if ($businessUnitId !== null) {
                    $join->where('pu.business_unit_id', $businessUnitId);
                }
            })
            ->join('business_units as bu', 'bu.id', '=', 'pu.business_unit_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->leftJoin('item_initial_setups as s', function ($join) use ($entity) {
                $join->on('s.product_id', '=', 'p.id')
                    ->whereColumn('s.business_unit_id', 'pu.business_unit_id')
                    ->where('s.entity_id', $entity);
            })
            ->where('p.entity_id', $entity)
            ->where('p.is_active', 1)
            ->where('p.item_type', 'barang')
            ->where('p.manage_stock', 1)
            ->select(
                'p.id',
                'bu.id as business_unit_id',
                'bu.name as business_unit_name',
                'p.code',
                'p.name',
                'p.item_type',
                's.id as setup_id',
                's.selling_price',
                'u.code as unit_code',
                'u.name as unit_name',
                's.setup_date',
                's.purchase_price as initial_purchase_price',
                's.initial_stock',
                's.markup_percent'
            )
            ->selectRaw("CASE WHEN EXISTS (
                SELECT 1
                FROM stock_movements sm
                WHERE sm.entity_id = ?
                  AND sm.business_unit_id = bu.id
                  AND sm.product_id = p.id
                  AND NOT (
                      sm.movement_type = 'opening'
                      AND sm.reference_type = 'item_initial_setup'
                      AND sm.reference_id = p.id
                  )
            ) THEN 1 ELSE 0 END as has_later_movements", [$entity])
            ->orderBy('bu.name')
            ->orderBy('p.name')
            ->distinct()
            ->get();

        $products = DB::table('products as p')
            ->join('product_business_units as pu', function ($join) use ($businessUnitId) {
                $join->on('pu.product_id', '=', 'p.id');
                if ($businessUnitId !== null) {
                    $join->where('pu.business_unit_id', $businessUnitId);
                }
            })
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->where('p.entity_id', $entity)
            ->where('p.is_active', 1)
            ->where('p.item_type', 'barang')
            ->where('p.manage_stock', 1)
            ->whereNotExists(function ($q) use ($entity, $businessUnitId) {
                $q->select(DB::raw(1))
                    ->from('item_initial_setups as existing')
                    ->whereColumn('existing.product_id', 'p.id')
                    ->where('existing.entity_id', $entity)
                    ->where('existing.business_unit_id', $businessUnitId);
            })
            ->select(
                'p.id',
                'p.code',
                'p.barcode',
                'p.name',
                'u.code as unit_code',
                'u.name as unit_name'
            )
            ->orderBy('p.name')
            ->distinct()
            ->get();

        return view(
            'inventori.persediaan.initial-setup.index',
            compact('rows', 'products', 'entityName', 'businessUnits', 'businessUnitId')
        );
    }

    public function storeInitial(Request $request)
    {
        $entity = $this->entityId();

        $data = $request->validate([
            'business_unit_id' => ['required', 'integer'],
            'setup_date' => ['required', 'date'],
            'product_id' => ['required', 'integer'],
            'purchase_price' => ['required', 'numeric', 'gt:0'],
            'initial_stock' => ['required', 'numeric', 'gt:0'],
            'markup_percent' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'gt:0'],
        ]);

        $businessUnit = DB::table('business_units')
            ->where('entity_id', $entity)
            ->where('id', $data['business_unit_id'])
            ->where('is_active', 1)
            ->first();

        abort_unless($businessUnit, 422, 'Business Unit tidak valid.');

        $product = DB::table('products')
            ->where('entity_id', $entity)
            ->where('id', $data['product_id'])
            ->where('is_active', 1)
            ->first();

        abort_unless($product, 422, 'Item tidak valid.');
        abort_unless(
            ($product->item_type ?? null) === 'barang' && (bool) ($product->manage_stock ?? false),
            422,
            'Initial Setup hanya berlaku untuk barang yang mengelola stok.'
        );

        abort_unless(
            DB::table('product_business_units')
                ->where('product_id', $product->id)
                ->where('business_unit_id', $businessUnit->id)
                ->exists(),
            422,
            'Item belum dipilih untuk Business Unit ini.'
        );

        abort_if(
            DB::table('item_initial_setups')
                ->where('entity_id', $entity)
                ->where('business_unit_id', $businessUnit->id)
                ->where('product_id', $product->id)
                ->exists(),
            422,
            'Setup awal item ini untuk Business Unit tersebut sudah ada.'
        );

        $warehouse = DB::table('warehouses')
            ->where('entity_id', $entity)
            ->where('business_unit_id', $businessUnit->id)
            ->where('is_active', 1)
            ->orderBy('id')
            ->first();

        abort_unless(
            $warehouse,
            422,
            'Belum ada gudang aktif untuk Business Unit ini.'
        );

        DB::transaction(function () use ($entity, $data, $product, $businessUnit, $warehouse): void {
            // Kunci master produk agar dua request setup untuk item yang sama tidak berjalan bersamaan.
            DB::table('products')->where('entity_id', $entity)->where('id', $product->id)->lockForUpdate()->first();

            abort_if(
                DB::table('item_initial_setups')
                    ->where('entity_id', $entity)
                    ->where('business_unit_id', $businessUnit->id)
                    ->where('product_id', $product->id)
                    ->exists(),
                422,
                'Setup awal item ini untuk Business Unit tersebut sudah ada.'
            );

            // Setup awal hanya boleh menjadi mutasi pertama barang pada gudang ini.
            // Saldo nol tidak cukup sebagai bukti bahwa gudang belum pernah bergerak.
            $stock = DB::table('warehouses_stocks')
                ->where('entity_id', $entity)
                ->where('warehouse_id', $warehouse->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            abort_if(
                DB::table('stock_movements')
                    ->where('entity_id', $entity)
                    ->where('warehouse_id', $warehouse->id)
                    ->where('product_id', $product->id)
                    ->exists(),
                422,
                'Barang sudah memiliki riwayat mutasi pada gudang ini. Initial Setup dibatalkan agar saldo stok dan kartu stok tidak menyimpang.'
            );

            DB::table('item_initial_setups')->insert([
                'entity_id' => $entity,
                'business_unit_id' => $businessUnit->id,
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'setup_date' => $data['setup_date'],
                'purchase_price' => $data['purchase_price'],
                'initial_stock' => $data['initial_stock'],
                'markup_percent' => $data['markup_percent'] ?? 0,
                'selling_price' => $data['selling_price'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($stock) {
                abort_unless(
                    (float) $stock->qty == 0.0,
                    422,
                    'Item sudah memiliki stok. Setup awal tidak dapat menambah stok ke stok yang sudah ada.'
                );

                DB::table('warehouses_stocks')
                    ->where('id', $stock->id)
                    ->update([
                        'qty' => $data['initial_stock'],
                        'avg_cost' => $data['purchase_price'],
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('warehouses_stocks')->insert([
                    'entity_id' => $entity,
                    'warehouse_id' => $warehouse->id,
                    'product_id' => $product->id,
                    'qty' => $data['initial_stock'],
                    'avg_cost' => $data['purchase_price'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('stock_movements')->insert([
                'entity_id' => $entity,
                'business_unit_id' => $businessUnit->id,
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'unit_id' => $product->base_unit_id,
                'transaction_qty' => $data['initial_stock'],
                'conversion_factor' => 1,
                'movement_type' => 'opening',
                'qty' => $data['initial_stock'],
                'unit_cost' => $data['purchase_price'],
                'reference_type' => 'item_initial_setup',
                'reference_id' => $product->id,
                'occurred_at' => $data['setup_date'].' 00:00:00',
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Setup awal item berhasil disimpan.']);
    }

    public function update(Request $request, int $product)
    {
        $entity = $this->entityId();

        $data = $request->validate([
            'business_unit_id' => ['required', 'integer'],
            'setup_date' => ['required', 'date'],
            'purchase_price' => ['required', 'numeric', 'gt:0'],
            'initial_stock' => ['required', 'numeric', 'min:0'],
            'markup_percent' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'gt:0'],
        ]);

        $businessUnit = DB::table('business_units')
            ->where('entity_id', $entity)
            ->where('id', $data['business_unit_id'])
            ->where('is_active', 1)
            ->first();

        abort_unless($businessUnit, 422, 'Business Unit tidak valid.');

        DB::transaction(function () use ($entity, $product, $data, $businessUnit): void {
            $setup = DB::table('item_initial_setups')
                ->where('entity_id', $entity)
                ->where('business_unit_id', $businessUnit->id)
                ->where('product_id', $product)
                ->lockForUpdate()
                ->first();

            abort_unless($setup, 404, 'Setup awal item belum ada.');

            $hasBusinessUnitMovements = DB::table('stock_movements')
                ->where('entity_id', $entity)
                ->where('business_unit_id', $businessUnit->id)
                ->where('product_id', $product)
                ->where(function ($query) use ($product) {
                    $query->where('movement_type', '!=', 'opening')
                        ->orWhere('reference_type', '!=', 'item_initial_setup')
                        ->orWhere('reference_id', '!=', $product);
                })
                ->exists();

            abort_if(
                $hasBusinessUnitMovements,
                422,
                'Initial Setup tidak dapat diedit karena barang ini sudah memiliki mutasi stok pada Business Unit tersebut.'
            );

            $warehouse = DB::table('warehouses')
                ->where('entity_id', $entity)
                ->where('business_unit_id', $businessUnit->id)
                ->where('id', $setup->warehouse_id)
                ->first();

            abort_unless($warehouse, 422, 'Gudang setup awal tidak sesuai dengan Unit Bisnis.');

            $stock = DB::table('warehouses_stocks')
                ->where('entity_id', $entity)
                ->where('warehouse_id', $setup->warehouse_id)
                ->where('product_id', $product)
                ->lockForUpdate()
                ->first();

            $openingMovement = DB::table('stock_movements')
                ->where('entity_id', $entity)
                ->where('business_unit_id', $businessUnit->id)
                ->where('warehouse_id', $setup->warehouse_id)
                ->where('product_id', $product)
                ->where('movement_type', 'opening')
                ->where('reference_type', 'item_initial_setup')
                ->where('reference_id', $product)
                ->lockForUpdate()
                ->first();

            $hasOtherMovements = DB::table('stock_movements')
                ->where('entity_id', $entity)
                ->where('warehouse_id', $setup->warehouse_id)
                ->where('product_id', $product)
                ->where(function ($query) use ($businessUnit, $setup, $product) {
                    $query->where('business_unit_id', '!=', $businessUnit->id)
                        ->orWhere(function ($opening) use ($setup, $product) {
                            $opening->where('business_unit_id', $setup->business_unit_id)
                                ->where(function ($notOpening) use ($product) {
                                    $notOpening->where('movement_type', '!=', 'opening')
                                        ->orWhere('reference_type', '!=', 'item_initial_setup')
                                        ->orWhere('reference_id', '!=', $product);
                                });
                        });
                })
                ->exists();

            $stockInputsChanged =
                (string) $data['setup_date'] !== (string) $setup->setup_date
                || abs((float) $data['purchase_price'] - (float) $setup->purchase_price) > 0.0001
                || abs((float) $data['initial_stock'] - (float) $setup->initial_stock) > 0.0001;

            if ($stockInputsChanged) {
                abort_unless($stock, 422, 'Saldo stok setup awal tidak ditemukan. Perubahan stok/HPP dibatalkan agar saldo tidak makin menyimpang.');

                abort_if(
                    $hasOtherMovements,
                    422,
                    'Tanggal, stok awal, dan HPP awal tidak dapat diubah karena sudah ada mutasi stok setelah setup. Kembalikan ketiga nilai tersebut seperti semula, lalu simpan perubahan harga jual atau markup.'
                );

                abort_unless(
                    $openingMovement,
                    422,
                    'Mutasi saldo awal tidak ditemukan. Perubahan stok/HPP dibatalkan; periksa histori stok terlebih dahulu.'
                );

                abort_unless(
                    abs((float) $stock->qty - (float) $setup->initial_stock) <= 0.0001
                    && abs((float) $stock->avg_cost - (float) $setup->purchase_price) <= 0.0001,
                    422,
                    'Saldo stok atau HPP saat ini berbeda dari setup awal. Perubahan stok/HPP dibatalkan agar tidak menimpa saldo berjalan.'
                );

                DB::table('warehouses_stocks')
                    ->where('id', $stock->id)
                    ->update([
                        'qty' => $data['initial_stock'],
                        'avg_cost' => $data['purchase_price'],
                        'updated_at' => now(),
                    ]);

                DB::table('stock_movements')
                    ->where('id', $openingMovement->id)
                    ->update([
                        'qty' => $data['initial_stock'],
                        'unit_cost' => $data['purchase_price'],
                        'occurred_at' => $data['setup_date'].' 00:00:00',
                        'updated_at' => now(),
                    ]);
            }

            DB::table('item_initial_setups')
                ->where('id', $setup->id)
                ->update([
                    'setup_date' => $data['setup_date'],
                    'purchase_price' => $data['purchase_price'],
                    'initial_stock' => $data['initial_stock'],
                    'markup_percent' => $data['markup_percent'],
                    'selling_price' => $data['selling_price'],
                    'updated_at' => now(),
                ]);
        });

        return response()->json(['message' => 'Initial Setup berhasil diperbarui.']);
    }

}
