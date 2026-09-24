<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    private function entityId(): int
    {
        return (int) (DB::table('entities')->value('id') ?? 1);
    }

    public function index(Request $request)
    {
        $entity = $this->entityId();

        $suppliers = DB::table('suppliers')
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        $units = DB::table('business_units')
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return view('inventori.pembelian.po.index', compact('suppliers', 'units'));
    }

    public function create()
    {
        $entity = $this->entityId();

        $suppliers = DB::table('suppliers')
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'phone', 'address']);

        $units = DB::table('business_units')
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $products = DB::table('products as p')
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->where('p.entity_id', $entity)
            ->where('p.is_active', 1)
            ->orderBy('p.name')
            ->get(['p.id', 'p.code', 'p.sku', 'p.name', 'p.cost_price', 'u.code as unit_code']);

        return view('inventori.pembelian.po.create', compact('suppliers', 'units', 'products'));
    }
}
