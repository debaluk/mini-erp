<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductPriceController extends Controller
{
    public function index(Request $request)
    {
        $entity = (int) auth()->user()->entity_id;

        $businessUnits = DB::table('business_units')
            ->where('entity_id', $entity)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $query = DB::table('product_prices as pp')
            ->join('products as p', 'p.id', '=', 'pp.product_id')
            ->join('business_units as bu', 'bu.id', '=', 'pp.business_unit_id')
            ->join('units as u', 'u.id', '=', 'pp.unit_id')
            ->where('p.entity_id', $entity)
            ->select(
                'pp.id',
                'pp.product_id',
                'pp.business_unit_id',
                'pp.unit_id',
                'pp.price_type',
                'pp.selling_price',
                'p.code as product_code',
                'p.name as product_name',
                'bu.code as business_unit_code',
                'bu.name as business_unit_name',
                'u.code as unit_code',
                'u.name as unit_name'
            );

        if ($request->filled('business_unit_id')) {
            $query->where('pp.business_unit_id', (int) $request->business_unit_id);
        }

        if ($request->filled('price_type')) {
            $query->where('pp.price_type', $request->price_type);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('p.code', 'like', "%{$search}%")
                    ->orWhere('p.name', 'like', "%{$search}%");
            });
        }

        $prices = $query
            ->orderBy('p.name')
            ->orderBy('pp.price_type')
            ->orderBy('u.name')
            ->get();

        $products = DB::table('products as p')
            ->join('product_business_units as pbu', 'pbu.product_id', '=', 'p.id')
            ->join('business_units as bu', 'bu.id', '=', 'pbu.business_unit_id')
            ->join('units as u', 'u.id', '=', 'p.base_unit_id')
            ->where('p.entity_id', $entity)
            ->where('p.is_active', 1)
            ->select(
                'p.id',
                'p.code',
                'p.name',
                'p.base_unit_id',
                'u.code as unit_code',
                'u.name as unit_name',
                'bu.id as business_unit_id',
                'bu.code as business_unit_code',
                'bu.name as business_unit_name'
            )
            ->orderBy('p.name')
            ->get();

        return view('master.harga-jual', compact(
            'prices',
            'products',
            'businessUnits'
        ));
    }

    public function store(Request $request)
    {
        $entity = (int) auth()->user()->entity_id;

        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'business_unit_id' => ['required', 'integer'],
            'unit_id' => ['required', 'integer'],
            'price_type' => ['required', 'in:retail,grosir'],
            'selling_price' => ['required', 'numeric', 'gt:0'],
        ]);

        $valid = DB::table('products as p')
            ->join('product_business_units as pbu', 'pbu.product_id', '=', 'p.id')
            ->join('units as u', 'u.id', '=', $data['unit_id'])
            ->where('p.id', $data['product_id'])
            ->where('p.entity_id', $entity)
            ->where('p.is_active', 1)
            ->where('pbu.business_unit_id', $data['business_unit_id'])
            ->where('u.entity_id', $entity)
            ->exists();

        abort_unless($valid, 422, 'Item, unit bisnis, atau satuan tidak valid.');

        DB::table('product_prices')->updateOrInsert(
            [
                'product_id' => $data['product_id'],
                'business_unit_id' => $data['business_unit_id'],
                'unit_id' => $data['unit_id'],
                'price_type' => $data['price_type'],
            ],
            [
                'selling_price' => $data['selling_price'],
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return back()->with('success', 'Harga jual berhasil disimpan.');
    }

    public function update(Request $request, int $id)
    {
        $entity = (int) auth()->user()->entity_id;

        $data = $request->validate([
            'selling_price' => ['required', 'numeric', 'gt:0'],
        ]);

        $updated = DB::table('product_prices as pp')
            ->join('products as p', 'p.id', '=', 'pp.product_id')
            ->where('pp.id', $id)
            ->where('p.entity_id', $entity)
            ->update([
                'pp.selling_price' => $data['selling_price'],
                'pp.updated_at' => now(),
            ]);

        abort_unless($updated, 404, 'Harga jual tidak ditemukan.');

        return back()->with('success', 'Harga jual berhasil diperbarui.');
    }

    public function destroy(int $id)
    {
        $entity = (int) auth()->user()->entity_id;

        $deleted = DB::table('product_prices as pp')
            ->join('products as p', 'p.id', '=', 'pp.product_id')
            ->where('pp.id', $id)
            ->where('p.entity_id', $entity)
            ->delete();

        abort_unless($deleted, 404, 'Harga jual tidak ditemukan.');

        return back()->with('success', 'Harga jual berhasil dihapus.');
    }
}
