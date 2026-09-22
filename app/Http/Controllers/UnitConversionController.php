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

        $baseQuery = DB::table('product_unit_conversions as pu')
            ->join('products as p', 'p.id', '=', 'pu.product_id')
            ->join('units as u', 'u.id', '=', 'pu.unit_id')
            ->leftJoin('units as du', 'du.id', '=', 'p.base_unit_id')
            ->where('p.entity_id', $entity);

        if ($request->ajax() && $request->has('draw')) {
            $search = trim((string) $request->input('search.value', ''));
            $query = clone $baseQuery;

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('p.name', 'like', '%'.$search.'%')
                        ->orWhere('u.code', 'like', '%'.$search.'%')
                        ->orWhere('du.code', 'like', '%'.$search.'%');
                });
            }

            $total = DB::table('product_unit_conversions as uc')
                ->join('products as p', 'p.id', '=', 'uc.product_id')
                ->where('p.entity_id', $entity)
                ->count();

            $filtered = $query->count();

            $columns = [
                'p.name',
                'du.code',
                'u.code',
                'pu.conversion_factor',
                'p.base_unit_id',
            ];

            $orderIndex = (int) $request->input('order.0.column', 0);
            $orderDir = strtolower((string) $request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';
            $orderColumn = $columns[$orderIndex] ?? 'p.name';
            $length = (int) $request->input('length', 15);
            $start = max(0, (int) $request->input('start', 0));

            $rows = $query
                ->select(
                    'pu.id',
                    'pu.product_id',
                    'pu.unit_id',
                    'pu.conversion_factor',
                    'pu.is_default_purchase',
                    'pu.is_default_sale',
                    DB::raw('0 as is_default'),
                    'p.name as product_name',
                    'u.code as unit_code',
                    'u.name as unit_name',
                    'du.code as default_unit_code'
                )
                ->orderBy($orderColumn, $orderDir)
                ->orderBy('pu.id', 'desc')
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
                'pu.id',
                'pu.product_id',
                'pu.unit_id',
                'pu.conversion_factor',
                DB::raw('0 as is_default'),
                'p.name as product_name',
                'u.code as unit_code',
                'u.name as unit_name',
                'du.code as default_unit_code'
            )
            ->orderBy('pu.id', 'desc')
            ->paginate(15)
            ->withQueryString();

        $products = DB::table('products')
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'base_unit_id']);

        $units = DB::table('units')
            ->where('entity_id', $entity)
            ->orderBy('name')
            ->get();

        return view('erp.unit-conversions', compact('rows', 'products', 'units'));
    }

    private function save(Request $request, ?int $id = null)
    {
        $data = $request->validate([
            'product_id' => 'required|integer',
            'unit_id' => 'required|integer',
            'conversion_factor' => 'required|numeric|gt:0',
            'is_default' => 'nullable|boolean',
            'is_default_purchase' => 'nullable|boolean',
            'is_default_sale' => 'nullable|boolean',
        ]);

        $entity = $this->entityId();

        abort_unless(
            DB::table('products')->where('entity_id', $entity)->where('id', $data['product_id'])->exists(),
            422,
            'Produk tidak valid.'
        );

        abort_unless(
            DB::table('units')->where('entity_id', $entity)->where('id', $data['unit_id'])->exists(),
            422,
            'Satuan tidak valid.'
        );

        $baseUnitId = (int) DB::table('products')->where('id', $data['product_id'])->value('base_unit_id');
        abort_if($baseUnitId === (int)$data['unit_id'], 422, 'Satuan dasar tidak perlu dibuat sebagai konversi.');

        $values = [
            'product_id' => $data['product_id'],
            'unit_id' => $data['unit_id'],
            'conversion_factor' => $data['conversion_factor'],
            'is_default_purchase' => (bool)($data['is_default_purchase'] ?? false),
            'is_default_sale' => (bool)($data['is_default_sale'] ?? false),
            'is_active' => true,
            'updated_at' => now(),
        ];

        if (($data['is_default_purchase'] ?? false)) {
            DB::table('product_unit_conversions')
                ->where('product_id', $data['product_id'])
                ->where('id', '<>', $id ?? 0)
                ->update(['is_default_purchase' => false, 'updated_at' => now()]);
        }
        if (($data['is_default_sale'] ?? false)) {
            DB::table('product_unit_conversions')
                ->where('product_id', $data['product_id'])
                ->where('id', '<>', $id ?? 0)
                ->update(['is_default_sale' => false, 'updated_at' => now()]);
        }

        if ($id) {
            DB::table('product_unit_conversions')
                ->where('id', $id)
                ->where('product_id', $data['product_id'])
                ->update($values);
        } else {
            $values['created_at'] = now();

            DB::table('product_unit_conversions')->updateOrInsert(
                [
                    'product_id' => $data['product_id'],
                    'unit_id' => $data['unit_id'],
                ],
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
            DB::table('product_unit_conversions as uc')
                ->join('products as p', 'p.id', '=', 'uc.product_id')
                ->where('p.entity_id', $this->entityId())
                ->where('uc.id', $id)
                ->exists(),
            404
        );

        return $this->save($request, $id);
    }

    {
        $conversion = DB::table('product_unit_conversions as uc')
            ->join('products as p','p.id','=','uc.product_id')
            ->where('uc.id',$id)
            ->where('p.entity_id',$this->entityId())
            ->first(['uc.product_id','uc.unit_id']);

        abort_unless($conversion,404);

        $used = DB::table('purchase_items')->where('product_id',$conversion->product_id)->where('transaction_unit_id',$conversion->unit_id)->exists()
            || DB::table('sale_items')->where('product_id',$conversion->product_id)->where('transaction_unit_id',$conversion->unit_id)->exists()
            || DB::table('sales_return_items')->where('product_id',$conversion->product_id)->where('transaction_unit_id',$conversion->unit_id)->exists();

        abort_if($used,422,'Konversi sudah dipakai transaksi dan tidak dapat dihapus. Nonaktifkan konversi tersebut.');

        $deleted = DB::table('product_unit_conversions')
            ->where('id', $id)
            ->delete();

        abort_unless($deleted, 404);

        return response()->json([
            'message' => 'Konversi satuan berhasil dihapus.',
        ]);
    }
}
