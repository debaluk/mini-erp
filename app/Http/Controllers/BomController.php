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
            return $entity->id;
        }

        return DB::table('entities')->insertGetId([
            'code' => 'ENT-001',
            'name' => 'Entitas Utama',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function show()
    {
        $entity = $this->entityId();

        return view('erp.bom', [
            'title' => 'Formula / BOM',
            'products' => DB::table('products')
                ->where('entity_id', $entity)
                ->where('is_active', 1)
                ->orderBy('name')
                ->get(),
            'boms' => DB::table('boms')
                ->where('entity_id', $entity)
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'code' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'output_qty' => ['required', 'numeric', 'min:0.001'],
            'material_product_id' => ['required', 'array', 'min:1'],
            'material_product_id.*' => ['required', 'integer'],
            'material_qty' => ['required', 'array', 'min:1'],
            'material_qty.*' => ['required', 'numeric', 'min:0.001'],
        ]);

        if (count($data['material_product_id']) !== count($data['material_qty'])) {
            abort(422, 'Detail bahan baku tidak lengkap.');
        }

        $entity = $this->entityId();

        DB::transaction(function () use ($data, $entity) {
            $product = DB::table('products')
                ->where('entity_id', $entity)
                ->where('id', $data['product_id'])
                ->first();
            abort_unless($product, 404);

            $materialIds = array_map('intval', $data['material_product_id']);
            $validMaterials = DB::table('products')
                ->where('entity_id', $entity)
                ->whereIn('id', $materialIds)
                ->count();
            abort_unless($validMaterials === count(array_unique($materialIds)), 422, 'Ada bahan baku yang tidak valid.');

            $businessUnitId = DB::table('business_units')
                ->where('entity_id', $entity)
                ->where('business_type', 'production')
                ->where('is_active', 1)
                ->orderBy('id')
                ->value('id');
            abort_unless($businessUnitId, 422, 'Business Unit Produksi belum tersedia.');

            $bom = DB::table('boms')->insertGetId([
                'entity_id' => $entity,
                'business_unit_id' => $businessUnitId,
                'product_id' => $product->id,
                'code' => $data['code'],
                'name' => $data['name'],
                'output_qty' => $data['output_qty'],
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $items = [];
            foreach ($data['material_product_id'] as $i => $materialId) {
                $items[] = [
                    'bom_id' => $bom,
                    'product_id' => $materialId,
                    'qty' => $data['material_qty'][$i],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('bom_items')->insert($items);
        });

        return back()->with('success', 'Formula / BOM berhasil disimpan dengan semua bahan baku.');
    }
}
