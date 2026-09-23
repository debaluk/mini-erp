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

        $prices = collect();

        if ($businessUnitId) {
            $query = DB::table('product_business_units as pbu')
                ->join('products as p', 'p.id', '=', 'pbu.product_id')
                ->join('product_units as pu', 'pu.product_id', '=', 'p.id')
                ->join('units as u', 'u.id', '=', 'pu.unit_id')
                ->leftJoin('product_prices as retail', function ($join) use ($businessUnitId) {
                    $join->on('retail.product_id', '=', 'p.id')
                        ->on('retail.unit_id', '=', 'pu.unit_id')
                        ->where('retail.business_unit_id', $businessUnitId)
                        ->where('retail.price_type', 'retail');
                })
                ->leftJoin('product_prices as grosir', function ($join) use ($businessUnitId) {
                    $join->on('grosir.product_id', '=', 'p.id')
                        ->on('grosir.unit_id', '=', 'pu.unit_id')
                        ->where('grosir.business_unit_id', $businessUnitId)
                        ->where('grosir.price_type', 'grosir');
                })
                ->where('p.entity_id', $entity)
                ->where('p.is_active', 1)
                ->where('pbu.business_unit_id', $businessUnitId)
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
                    'pu.unit_id',
                    'u.code as unit_code',
                    'u.name as unit_name',
                    'retail.id as retail_price_id',
                    'retail.selling_price as retail_price',
                    'grosir.id as grosir_price_id',
                    'grosir.selling_price as grosir_price'
                );

             $prices = $query
                ->orderBy('p.name')
                ->orderBy('u.name')
                ->get();
        }

        return view('master.harga-jual', compact(
            'prices',
            'businessUnits'
        ));
    }

    public function store(Request $request)
    {
        $entity = (int) auth()->user()->entity_id;

        $data = $request->validate([
            'business_unit_id' => ['required', 'integer'],
            'product_id' => ['required', 'integer'],
            'unit_id' => ['required', 'integer'],
            'price_type' => ['required', 'in:retail,grosir'],
            'change_date' => ['required', 'date'],
            'selling_price' => ['required', 'numeric', 'gt:0'],
        ]);

        $context = $this->validateContext($entity, $data);

        DB::transaction(function () use ($entity, $data, $context): void {
            $price = DB::table('product_prices')
                ->where('product_id', $data['product_id'])
                ->where('business_unit_id', $data['business_unit_id'])
                ->where('unit_id', $data['unit_id'])
                ->where('price_type', $data['price_type'])
                ->lockForUpdate()
                ->first();

            abort_if($price, 422, 'Harga jual sudah ada. Gunakan Edit untuk memperbarui harga.');

            DB::table('product_prices')->insert([
                'product_id' => $data['product_id'],
                'business_unit_id' => $data['business_unit_id'],
                'unit_id' => $data['unit_id'],
                'price_type' => $data['price_type'],
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
                'price_type' => $data['price_type'],
                'change_date' => $data['change_date'],
                'old_price' => null,
                'new_price' => $data['selling_price'],
                'change_percent' => null,
                'changed_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

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

        return back()->with('success', 'Harga jual berhasil diperbarui.');
    }

    public function history(Request $request)
    {
        $entity = (int) auth()->user()->entity_id;
        $product = (int) $request->query('product');
        $unit = (int) $request->query('unit');
        $businessUnitId = (int) $request->query('business_unit_id');
        abort_unless($product && $unit && $businessUnitId, 422, 'Parameter history tidak valid.');

        abort_unless(in_array($priceType, ['retail', 'grosir'], true), 422, 'Jenis harga tidak valid.');

        $histories = DB::table('product_price_histories as h')
            ->leftJoin('users as usr', 'usr.id', '=', 'h.changed_by')
            ->join('products as p', 'p.id', '=', 'h.product_id')
            ->join('business_units as bu', 'bu.id', '=', 'h.business_unit_id')
            ->join('units as u', 'u.id', '=', 'h.unit_id')
            ->where('p.entity_id', $entity)
            ->where('h.product_id', $product)
            ->where('h.unit_id', $unit)
            ->where('h.business_unit_id', $businessUnitId)
            ->where('h.price_type', $priceType)
            ->orderByDesc('h.change_date')
            ->orderByDesc('h.id')
            ->get([
                'h.id',
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

    private function validateContext(int $entity, array $data): object
    {
        $context = DB::table('product_business_units as pbu')
            ->join('products as p', 'p.id', '=', 'pbu.product_id')
            ->join('product_units as pu', function ($join) use ($data) {
                $join->on('pu.product_id', '=', 'pbu.product_id')
                    ->where('pu.unit_id', $data['unit_id']);
            })
            ->join('units as u', 'u.id', '=', 'pu.unit_id')
            ->join('business_units as bu', 'bu.id', '=', 'pbu.business_unit_id')
            ->where('p.id', $data['product_id'])
            ->where('p.entity_id', $entity)
            ->where('p.is_active', 1)
            ->where('pbu.business_unit_id', $data['business_unit_id'])
            ->where('bu.entity_id', $entity)
            ->where('bu.is_active', 1)
            ->where('u.entity_id', $entity)
            ->select('p.id', 'p.name')
            ->first();

        abort_unless($context, 422, 'Item, unit bisnis, atau satuan tidak valid.');

        return $context;
    }
}
