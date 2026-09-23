<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductPriceController extends Controller
{
    public function index(Request $request)
    {
        $entity = (int) auth()->user()->entity_id;
        $businessUnitId = $request->filled('business_unit_id') ? (int) $request->business_unit_id : null;

        $businessUnits = DB::table('business_units')
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $productUnits = DB::table('products as p')
            ->where('p.entity_id', $entity)
            ->where('p.is_active', 1)
            ->select('p.id as product_id', 'p.base_unit_id as unit_id')
            ->union(
                DB::table('product_unit_conversions as puc')
                    ->join('products as pc', 'pc.id', '=', 'puc.product_id')
                    ->where('pc.entity_id', $entity)
                    ->where('pc.is_active', 1)
                    ->where('puc.is_active', 1)
                    ->select('puc.product_id', 'puc.unit_id')
            );

        $query = DB::query()
            ->fromSub(
                DB::query()
                    ->fromSub($productUnits, 'pu_source')
                    ->select('product_id', 'unit_id')
                    ->distinct(),
                'pu'
            )
            ->join('products as p', 'p.id', '=', 'pu.product_id')
            ->join('units as u', 'u.id', '=', 'pu.unit_id')
            ->join('product_business_units as pbu', 'pbu.product_id', '=', 'p.id')
            ->join('business_units as bu', 'bu.id', '=', 'pbu.business_unit_id')
            ->leftJoin('product_prices as pp', function ($join) {
                $join->on('pp.product_id', '=', 'p.id')
                    ->on('pp.unit_id', '=', 'pu.unit_id')
                    ->on('pp.business_unit_id', '=', 'pbu.business_unit_id')
                    ->where('pp.price_type', '=', 'retail');
            })
            ->where('p.entity_id', $entity)
            ->where('p.is_active', 1)
            ->where('bu.entity_id', $entity)
            ->where('bu.is_active', 1)
            ->where('u.entity_id', $entity)
            ->when($businessUnitId, function ($q) use ($businessUnitId) {
                $q->where('pbu.business_unit_id', $businessUnitId);
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = trim($request->search);
                $q->where(function ($sub) use ($search) {
                    $sub->where('p.code', 'like', "%{$search}%")
                        ->orWhere('p.name', 'like', "%{$search}%");
                });
            })
            ->select(
                'p.id as product_id',
                'p.code as product_code',
                'p.name as product_name',
                'pbu.business_unit_id',
                'bu.code as business_unit_code',
                'bu.name as business_unit_name',
                'pu.unit_id',
                'u.code as unit_code',
                'u.name as unit_name',
                'pp.id as price_id',
                'pp.selling_price as selling_price',
                DB::raw('(SELECT MAX(h.change_date) FROM product_price_histories h WHERE h.product_price_id = pp.id) as updated_price_date')
            )
            ->orderBy('bu.name')
            ->orderBy('p.name')
            ->orderBy('u.name');

        if ($request->expectsJson()) {
            $perPage = 25;
            $paginator = $query->paginate($perPage);
            return response()->json([
                'data' => $paginator->items(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => max($paginator->lastPage(), 1),
                ],
            ]);
        }

        $prices = $query->get();

        return view('master.harga-jual', compact('prices', 'businessUnits'));
    }

    public function store(Request $request)
    {
        $entity = (int) auth()->user()->entity_id;

        $data = $request->validate([
            'business_unit_id' => ['required', 'integer'],
            'product_id' => ['required', 'integer'],
            'unit_id' => ['required', 'integer'],
            'change_date' => ['required', 'date'],
            'selling_price' => ['required', 'numeric', 'gt:0'],
        ]);

        $this->validateContext($entity, $data);

        DB::transaction(function () use ($data): void {
            $price = DB::table('product_prices')
                ->where('product_id', $data['product_id'])
                ->where('business_unit_id', $data['business_unit_id'])
                ->where('unit_id', $data['unit_id'])
                ->where('price_type', 'retail')
                ->lockForUpdate()
                ->first();

            abort_if($price, 422, 'Harga jual sudah ada. Gunakan Edit untuk memperbarui harga.');

            DB::table('product_prices')->insert([
                'product_id' => $data['product_id'],
                'business_unit_id' => $data['business_unit_id'],
                'unit_id' => $data['unit_id'],
                'price_type' => 'retail',
                'selling_price' => $data['selling_price'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $priceId = DB::getPdo()->lastInsertId();

            DB::table('product_price_histories')->insert([
                'product_price_id' => $priceId,
                'product_id' => $data['product_id'],
                'business_unit_id' => $data['business_unit_id'],
                'unit_id' => $data['unit_id'],
                'price_type' => 'retail',
                'change_date' => $data['change_date'],
                'old_price' => null,
                'new_price' => $data['selling_price'],
                'change_percent' => null,
                'changed_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Harga jual berhasil disimpan.']);
        }

        return back()->with('success', 'Harga jual berhasil disimpan.');
    }

    public function update(Request $request, int $id)
    {
        $entity = (int) auth()->user()->entity_id;

        $data = $request->validate([
            'change_date' => ['required', 'date'],
            'selling_price' => ['required', 'numeric', 'gt:0'],
        ]);

        $price = DB::table('product_prices as pp')
            ->join('products as p', 'p.id', '=', 'pp.product_id')
            ->where('pp.id', $id)
            ->where('p.entity_id', $entity)
            ->select('pp.*')
            ->first();

        abort_unless($price, 404, 'Harga jual tidak ditemukan.');

        $oldPrice = (float) $price->selling_price;
        $newPrice = (float) $data['selling_price'];

        abort_if(
            abs($oldPrice - $newPrice) < 0.0000001,
            422,
            'Harga baru sama dengan harga yang sedang berlaku.'
        );

        $changePercent = $oldPrice == 0
            ? null
            : (($newPrice - $oldPrice) / $oldPrice) * 100;

        DB::transaction(function () use ($id, $price, $data, $newPrice, $changePercent): void {
            DB::table('product_prices')
                ->where('id', $id)
                ->update([
                    'selling_price' => $newPrice,
                    'updated_at' => now(),
                ]);

            DB::table('product_price_histories')->insert([
                'product_price_id' => $id,
                'product_id' => $price->product_id,
                'business_unit_id' => $price->business_unit_id,
                'unit_id' => $price->unit_id,
                'price_type' => $price->price_type,
                'change_date' => $data['change_date'],
                'old_price' => $price->selling_price,
                'new_price' => $newPrice,
                'change_percent' => $changePercent,
                'changed_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Harga jual berhasil diperbarui.']);
        }

        return back()->with('success', 'Harga jual berhasil diperbarui.');
    }

    public function export(Request $request)
    {
        $entity = (int) auth()->user()->entity_id;
        $businessUnitId = $request->filled('business_unit_id') ? (int) $request->business_unit_id : null;
        $search = trim((string) $request->query('search', ''));

        $businessUnitName = 'Semua Unit Bisnis';
        if ($businessUnitId) {
            $businessUnitName = DB::table('business_units')
                ->where('id', $businessUnitId)
                ->where('entity_id', $entity)
                ->where('is_active', 1)
                ->value('name');

            abort_unless($businessUnitName, 422, 'Unit bisnis tidak valid.');
        }

        $productUnits = DB::table('products as p')
            ->where('p.entity_id', $entity)
            ->where('p.is_active', 1)
            ->select('p.id as product_id', 'p.base_unit_id as unit_id')
            ->union(
                DB::table('product_unit_conversions as puc')
                    ->join('products as pc', 'pc.id', '=', 'puc.product_id')
                    ->where('pc.entity_id', $entity)
                    ->where('pc.is_active', 1)
                    ->where('puc.is_active', 1)
                    ->select('puc.product_id', 'puc.unit_id')
            );

        $rows = DB::query()
            ->fromSub(
                DB::query()->fromSub($productUnits, 'pu_source')->select('product_id', 'unit_id')->distinct(),
                'pu'
            )
            ->join('products as p', 'p.id', '=', 'pu.product_id')
            ->join('units as u', 'u.id', '=', 'pu.unit_id')
            ->join('product_business_units as pbu', 'pbu.product_id', '=', 'p.id')
            ->join('business_units as bu', 'bu.id', '=', 'pbu.business_unit_id')
            ->leftJoin('product_prices as pp', function ($join) {
                $join->on('pp.product_id', '=', 'p.id')
                    ->on('pp.unit_id', '=', 'pu.unit_id')
                    ->on('pp.business_unit_id', '=', 'pbu.business_unit_id')
                    ->where('pp.price_type', '=', 'retail');
            })
            ->where('p.entity_id', $entity)
            ->where('p.is_active', 1)
            ->where('bu.entity_id', $entity)
            ->where('bu.is_active', 1)
            ->where('u.entity_id', $entity)
            ->when($businessUnitId, fn ($q) => $q->where('pbu.business_unit_id', $businessUnitId))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('p.code', 'like', "%{$search}%")
                        ->orWhere('p.name', 'like', "%{$search}%");
                });
            })
            ->select(
                'bu.name as business_unit_name',
                'p.code as product_code',
                'p.name as product_name',
                'u.name as unit_name',
                'pp.selling_price',
                DB::raw('(SELECT MAX(h.change_date) FROM product_price_histories h WHERE h.product_price_id = pp.id) as updated_price_date')
            )
            ->orderBy('bu.name')
            ->orderBy('p.name')
            ->orderBy('u.name')
            ->get();

        $entityName = DB::table('entities')->where('id', $entity)->value('name') ?? 'Entity';

        return response()->json([
            'entity_name' => $entityName,
            'business_unit_name' => $businessUnitName,
            'rows' => $rows,
        ]);
    }

    public function history(Request $request)
    {
        $entity = (int) auth()->user()->entity_id;
        $product = (int) $request->query('product');
        $unit = (int) $request->query('unit');
        $businessUnitId = (int) $request->query('business_unit_id');

        abort_unless($product && $unit && $businessUnitId, 422, 'Parameter history tidak valid.');

        $histories = DB::table('product_price_histories as h')
            ->leftJoin('users as usr', 'usr.id', '=', 'h.changed_by')
            ->join('products as p', 'p.id', '=', 'h.product_id')
            ->join('business_units as bu', 'bu.id', '=', 'h.business_unit_id')
            ->join('units as u', 'u.id', '=', 'h.unit_id')
            ->where('p.entity_id', $entity)
            ->where('h.product_id', $product)
            ->where('h.unit_id', $unit)
            ->where('h.business_unit_id', $businessUnitId)
            ->orderByDesc('h.change_date')
            ->orderByDesc('h.id')
            ->get([
                'h.id',
                'h.price_type',
                'h.change_date',
                'h.old_price',
                'h.new_price',
                'h.change_percent',
                'usr.name as changed_by_name',
                'p.code as product_code',
                'p.name as product_name',
                'bu.name as business_unit_name',
                'u.name as unit_name',
            ]);

        return response()->json($histories);
    }

    private function validateContext(int $entity, array $data): void
    {
        $context = DB::table('product_business_units as pbu')
            ->join('products as p', 'p.id', '=', 'pbu.product_id')
            ->join('business_units as bu', 'bu.id', '=', 'pbu.business_unit_id')
            ->join('units as u', 'u.id', '=', DB::raw((int) $data['unit_id']))
            ->where('p.id', $data['product_id'])
            ->where('p.entity_id', $entity)
            ->where('p.is_active', 1)
            ->where('pbu.business_unit_id', $data['business_unit_id'])
            ->where('bu.entity_id', $entity)
            ->where('bu.is_active', 1)
            ->where('u.entity_id', $entity)
            ->where(function ($q) use ($data) {
                $q->where('p.base_unit_id', $data['unit_id'])
                    ->orWhereExists(function ($sub) use ($data) {
                        $sub->select(DB::raw(1))
                            ->from('product_unit_conversions as puc')
                            ->whereColumn('puc.product_id', 'p.id')
                            ->where('puc.unit_id', $data['unit_id'])
                            ->where('puc.is_active', 1);
                    });
            })
            ->select('p.id')
            ->first();

        abort_unless($context, 422, 'Item, unit bisnis, atau satuan tidak valid.');
    }
}
