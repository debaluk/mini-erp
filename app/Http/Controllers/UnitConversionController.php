<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UnitConversionController extends Controller
{
    private function entityId(): int
    {
        return (int) DB::table('entities')->orderBy('id')->value('id');
    }

    public function index(Request $request)
    {
        $entity = $this->entityId();
        $productId = $request->integer('product_id');

        $baseQuery = DB::table('product_unit_conversions as puc')
            ->join('products as p', 'p.id', '=', 'puc.product_id')
            ->join('units as u', 'u.id', '=', 'puc.unit_id')
            ->leftJoin('units as bu', 'bu.id', '=', 'p.base_unit_id')
            ->where('p.entity_id', $entity)
            ->where('puc.is_active', 1);

        if ($productId > 0) {
            $baseQuery->where('p.id', $productId);
        }

        if ($request->ajax() && $request->has('draw')) {
            $search = trim((string) $request->input('search.value', ''));
            $query = clone $baseQuery;

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('p.name', 'like', '%'.$search.'%')
                        ->orWhere('u.code', 'like', '%'.$search.'%')
                        ->orWhere('bu.code', 'like', '%'.$search.'%');
                });
            }

            $total = (clone $baseQuery)->count();
            $filtered = (clone $query)->count();

            $columns = ['p.name', 'bu.code', 'u.code', 'puc.conversion_factor'];
            $orderIndex = (int) $request->input('order.0.column', 0);
            $orderDir = strtolower((string) $request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';
            $orderColumn = $columns[$orderIndex] ?? 'p.name';
            $length = (int) $request->input('length', 15);
            $start = max(0, (int) $request->input('start', 0));

            $rows = $query
                ->select(
                    'puc.id',
                    'puc.product_id',
                    'puc.unit_id',
                    'puc.conversion_factor',
                    'puc.is_default_purchase',
                    'puc.is_default_sale',
                    'p.name as product_name',
                    'u.code as unit_code',
                    'u.name as unit_name',
                    'bu.code as default_unit_code'
                )
                ->orderBy($orderColumn, $orderDir)
                ->orderByDesc('puc.id')
                ->offset($start)
                ->limit($length > 0 ? $length : 15)
                ->get();

            return response()->json([
                'draw' => (int) $request->input('draw'),
                'recordsTotal' => $total,
                'recordsFiltered' => $filtered,
                'data' => $rows,
            ]);
        }

        $rows = $baseQuery
            ->select(
                'puc.id',
                'puc.product_id',
                'puc.unit_id',
                'puc.conversion_factor',
                'puc.is_default_purchase',
                'puc.is_default_sale',
                'p.name as product_name',
                'u.code as unit_code',
                'u.name as unit_name',
                'bu.code as default_unit_code'
            )
            ->orderByDesc('puc.id')
            ->paginate(15)
            ->withQueryString();

        $products = DB::table('products')
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'base_unit_id']);

        $units = DB::table('units')
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view('erp.unit-conversions', compact('rows', 'products', 'units'));
    }

    private function save(Request $request, ?int $id = null)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'unit_id' => ['required', 'integer'],
            'conversion_factor' => ['required', 'numeric', 'gt:0'],
            'is_default_purchase' => ['nullable', 'boolean'],
            'is_default_sale' => ['nullable', 'boolean'],
        ]);

        $entity = $this->entityId();
        $product = DB::table('products')
            ->where('entity_id', $entity)
            ->where('id', $data['product_id'])
            ->first();
        abort_unless($product, 422, 'Produk tidak valid.');

        abort_unless(
            DB::table('units')->where('entity_id', $entity)->where('is_active', 1)->where('id', $data['unit_id'])->exists(),
            422,
            'Satuan tidak valid.'
        );

        abort_if((int) $product->base_unit_id === (int) $data['unit_id'], 422, 'Base Unit tidak perlu dibuat sebagai konversi.');

        if (($data['is_default_purchase'] ?? false)) {
            DB::table('product_unit_conversions')
                ->where('product_id', $product->id)
                ->when($id, fn ($q) => $q->where('id', '<>', $id))
                ->update(['is_default_purchase' => 0, 'updated_at' => now()]);
        }

        if (($data['is_default_sale'] ?? false)) {
            DB::table('product_unit_conversions')
                ->where('product_id', $product->id)
                ->when($id, fn ($q) => $q->where('id', '<>', $id))
                ->update(['is_default_sale' => 0, 'updated_at' => now()]);
        }

        $values = [
            'product_id' => $product->id,
            'unit_id' => $data['unit_id'],
            'conversion_factor' => $data['conversion_factor'],
            'is_default_purchase' => (bool) ($data['is_default_purchase'] ?? false),
            'is_default_sale' => (bool) ($data['is_default_sale'] ?? false),
            'is_active' => 1,
            'updated_at' => now(),
        ];

        if ($id) {
            DB::table('product_unit_conversions')
                ->where('id', $id)
                ->where('product_id', $product->id)
                ->update($values);
        } else {
            $values['created_at'] = now();
            DB::table('product_unit_conversions')->updateOrInsert(
                ['product_id' => $product->id, 'unit_id' => $data['unit_id']],
                $values
            );
        }

        return response()->json([
            'message' => $id ? 'Konversi satuan berhasil diperbarui.' : 'Konversi satuan berhasil disimpan.',
        ]);
    }

    public function store(Request $request)
    {
        return $this->save($request);
    }

    public function update(Request $request, int $id)
    {
        abort_unless(
            DB::table('product_unit_conversions as puc')
                ->join('products as p', 'p.id', '=', 'puc.product_id')
                ->where('p.entity_id', $this->entityId())
                ->where('puc.id', $id)
                ->exists(),
            404
        );

        return $this->save($request, $id);
    }

    public function destroy(int $id)
    {
        $row = DB::table('product_unit_conversions as puc')
            ->join('products as p', 'p.id', '=', 'puc.product_id')
            ->where('p.entity_id', $this->entityId())
            ->where('puc.id', $id)
            ->select('puc.*')
            ->first();

        abort_unless($row, 404);

        DB::table('product_unit_conversions')
            ->where('id', $id)
            ->update(['is_active' => 0, 'updated_at' => now()]);

        return response()->json([
            'message' => 'Konversi satuan dinonaktifkan.',
        ]);
    }
}
