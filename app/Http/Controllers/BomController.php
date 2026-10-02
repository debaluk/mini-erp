<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BomController extends Controller
{
    private function entityId(): int
    {
        $entity = DB::table('entities')->first();

        if ($entity) {
            return (int) $entity->id;
        }

        return (int) DB::table('entities')->insertGetId([
            'code' => 'ENT-001',
            'name' => 'Entitas Utama',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function materialProducts(int $entity)
    {
        return DB::table('products as p')
            ->leftJoin('units as bu', 'bu.id', '=', 'p.base_unit_id')
            ->where('p.entity_id', $entity)
            ->where('p.is_active', 1)
            ->where('p.item_type', 'barang')
            ->orderBy('p.name')
            ->get([
                'p.id',
                'p.sku',
                'p.name',
                'p.base_unit_id',
                'bu.code as base_unit_code',
                'bu.name as base_unit_name',
            ]);
    }

    private function productUnits(int $entity, array $productIds): array
    {
        if (!$productIds) {
            return [];
        }

        $products = DB::table('products as p')
            ->leftJoin('units as bu', 'bu.id', '=', 'p.base_unit_id')
            ->where('p.entity_id', $entity)
            ->whereIn('p.id', $productIds)
            ->get([
                'p.id',
                'p.base_unit_id',
                'bu.code as base_unit_code',
                'bu.name as base_unit_name',
            ]);

        $conversions = DB::table('product_unit_conversions as puc')
            ->join('units as u', 'u.id', '=', 'puc.unit_id')
            ->whereIn('puc.product_id', $productIds)
            ->where('puc.is_active', 1)
            ->get([
                'puc.product_id',
                'puc.unit_id',
                'puc.conversion_factor',
                'u.code as unit_code',
                'u.name as unit_name',
            ]);

        $result = [];

        foreach ($products as $product) {
            $units = [];

            if ($product->base_unit_id) {
                $units[] = [
                    'id' => (int) $product->base_unit_id,
                    'code' => $product->base_unit_code,
                    'name' => $product->base_unit_name,
                    'factor' => 1,
                    'is_base' => true,
                ];
            }

            foreach ($conversions->where('product_id', $product->id) as $conversion) {
                $units[] = [
                    'id' => (int) $conversion->unit_id,
                    'code' => $conversion->unit_code,
                    'name' => $conversion->unit_name,
                    'factor' => (float) $conversion->conversion_factor,
                    'is_base' => false,
                ];
            }

            $result[$product->id] = $units;
        }

        return $result;
    }

    public function show()
    {
        $entity = $this->entityId();
        $products = $this->materialProducts($entity);

        $boms = DB::table('boms as b')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->where('b.entity_id', $entity)
            ->orderByDesc('b.id')
            ->select([
                'b.id',
                'b.entity_id',
                'b.business_unit_id',
                'b.product_id',
                'b.code',
                'b.name',
                'b.output_qty',
                'b.is_active',
                'b.created_at',
                'b.updated_at',
                'p.sku as product_sku',
                'p.name as product_name',
                'u.code as output_unit_code',
                'u.name as output_unit_name',
            ])
            ->paginate(15)
            ->withQueryString();

        $bomIds = $boms->getCollection()->pluck('id')->all();
        $items = $bomIds
            ? DB::table('bom_items as bi')
                ->join('products as p', 'p.id', '=', 'bi.product_id')
                ->join('units as u', 'u.id', '=', 'bi.unit_id')
                ->whereIn('bi.bom_id', $bomIds)
                ->orderBy('bi.id')
                ->get([
                    'bi.bom_id',
                    'bi.product_id',
                    'bi.unit_id',
                    'bi.qty',
                    'p.sku',
                    'p.name as product_name',
                    'u.code as unit_code',
                    'u.name as unit_name',
                ])
                ->groupBy('bom_id')
            : collect();

        $boms->getCollection()->transform(function ($bom) use ($items) {
            $bom->items = $items->get($bom->id, collect())->values();
            return $bom;
        });

        return view('master.bom.index', [
            'title' => 'Formula / BOM',
            'products' => $products,
            'productUnits' => $this->productUnits($entity, $products->pluck('id')->all()),
            'boms' => $boms,
        ]);
    }

    private function validateAndBuild(Request $request): array
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'code' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'output_qty' => ['required', 'numeric', 'min:0.001'],
            'material_product_id' => ['required', 'array', 'min:1'],
            'material_product_id.*' => ['required', 'integer'],
            'material_unit_id' => ['required', 'array', 'min:1'],
            'material_unit_id.*' => ['required', 'integer'],
            'material_qty' => ['required', 'array', 'min:1'],
            'material_qty.*' => ['required', 'numeric', 'min:0.001'],
        ]);

        $count = count($data['material_product_id']);

        abort_unless(
            $count === count($data['material_unit_id']) && $count === count($data['material_qty']),
            422,
            'Detail bahan baku tidak lengkap.'
        );

        return $data;
    }

    private function validateMaterials(int $entity, array $productIds, array $unitIds): void
    {
        $pairs = [];

        foreach ($productIds as $i => $productId) {
            $productId = (int) $productId;
            $unitId = (int) ($unitIds[$i] ?? 0);

            $product = DB::table('products')
                ->where('entity_id', $entity)
                ->where('id', $productId)
                ->where('is_active', 1)
                ->where('item_type', 'barang')
                ->first(['id', 'base_unit_id']);

            abort_unless($product, 422, 'Ada bahan baku yang tidak valid.');

            $validUnit = (int) $product->base_unit_id === $unitId
                || DB::table('product_unit_conversions')
                    ->where('product_id', $productId)
                    ->where('unit_id', $unitId)
                    ->where('is_active', 1)
                    ->exists();

            abort_unless($validUnit, 422, 'Satuan bahan tidak sesuai dengan konversi produk.');

            $pairs[] = $productId . ':' . $unitId;
        }

        abort_unless(count($pairs) === count(array_unique($pairs)), 422, 'Bahan dan satuan yang sama tidak boleh digandakan dalam satu BOM.');
    }

    private function productionBusinessUnitId(int $entity): int
    {
        $id = DB::table('business_units')
            ->where('entity_id', $entity)
            ->where('business_type', 'production')
            ->where('is_active', 1)
            ->orderBy('id')
            ->value('id');

        abort_unless($id, 422, 'Business Unit Produksi belum tersedia.');

        return (int) $id;
    }

    public function store(Request $request)
    {
        $data = $this->validateAndBuild($request);
        $entity = $this->entityId();

        DB::transaction(function () use ($data, $entity) {
            $product = DB::table('products')
                ->where('entity_id', $entity)
                ->where('id', $data['product_id'])
                ->where('is_active', 1)
                ->where('item_type', 'barang')
                ->first();

            abort_unless($product, 422, 'Produk jadi tidak valid.');

            $this->validateMaterials($entity, $data['material_product_id'], $data['material_unit_id']);

            $bomId = DB::table('boms')->insertGetId([
                'entity_id' => $entity,
                'business_unit_id' => $this->productionBusinessUnitId($entity),
                'product_id' => $product->id,
                'code' => $data['code'],
                'name' => $data['name'],
                'output_qty' => $data['output_qty'],
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $now = now();
            $items = [];

            foreach ($data['material_product_id'] as $i => $materialId) {
                $items[] = [
                    'bom_id' => $bomId,
                    'product_id' => (int) $materialId,
                    'unit_id' => (int) $data['material_unit_id'][$i],
                    'qty' => $data['material_qty'][$i],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('bom_items')->insert($items);
        });

        return back()->with('success', 'BOM berhasil disimpan.');
    }

    public function update(Request $request, int $id)
    {
        $data = $this->validateAndBuild($request);
        $entity = $this->entityId();

        DB::transaction(function () use ($data, $entity, $id) {
            $bom = DB::table('boms')
                ->where('entity_id', $entity)
                ->where('id', $id)
                ->first();

            abort_unless($bom, 404);

            $product = DB::table('products')
                ->where('entity_id', $entity)
                ->where('id', $data['product_id'])
                ->where('is_active', 1)
                ->where('item_type', 'barang')
                ->first();

            abort_unless($product, 422, 'Produk jadi tidak valid.');
            $this->validateMaterials($entity, $data['material_product_id'], $data['material_unit_id']);

            DB::table('boms')
                ->where('id', $id)
                ->update([
                    'product_id' => $product->id,
                    'code' => $data['code'],
                    'name' => $data['name'],
                    'output_qty' => $data['output_qty'],
                    'updated_at' => now(),
                ]);

            DB::table('bom_items')->where('bom_id', $id)->delete();

            $now = now();
            $items = [];

            foreach ($data['material_product_id'] as $i => $materialId) {
                $items[] = [
                    'bom_id' => $id,
                    'product_id' => (int) $materialId,
                    'unit_id' => (int) $data['material_unit_id'][$i],
                    'qty' => $data['material_qty'][$i],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('bom_items')->insert($items);
        });

        return back()->with('success', 'BOM berhasil diperbarui.');
    }

    public function destroy(int $id)
    {
        $entity = $this->entityId();

        $bom = DB::table('boms')
            ->where('entity_id', $entity)
            ->where('id', $id)
            ->first();

        abort_unless($bom, 404);

        $used = DB::table('productions')->where('bom_id', $id)->exists();

        if (!$used && DB::getSchemaBuilder()->hasTable('production_work_orders')) {
            $used = DB::table('production_work_orders')->where('bom_id', $id)->exists();
        }

        abort_if($used, 422, 'BOM sudah digunakan pada transaksi produksi/SPK dan tidak dapat dihapus.');

        DB::transaction(function () use ($id) {
            DB::table('bom_items')->where('bom_id', $id)->delete();
            DB::table('boms')->where('id', $id)->delete();
        });

        return back()->with('success', 'BOM berhasil dihapus.');
    }

    public function print(int $id)
    {
        $entity = DB::table('entities')->where('id', $this->entityId())->first();

        $bom = DB::table('boms as b')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->where('b.entity_id', $entity->id)
            ->where('b.id', $id)
            ->first([
                'b.*',
                'p.sku as product_sku',
                'p.name as product_name',
                'u.code as output_unit_code',
                'u.name as output_unit_name',
            ]);

        abort_unless($bom, 404);

        $items = DB::table('bom_items as bi')
            ->join('products as p', 'p.id', '=', 'bi.product_id')
            ->join('units as u', 'u.id', '=', 'bi.unit_id')
            ->where('bi.bom_id', $id)
            ->orderBy('bi.id')
            ->get([
                'p.sku',
                'p.name as product_name',
                'bi.qty',
                'u.code as unit_code',
                'u.name as unit_name',
            ]);

        return view('master.bom.print', compact('entity', 'bom', 'items'));
    }
}
