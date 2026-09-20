<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReceiptController extends Controller
{
    private function entityId(): int
    {
        return (int) (DB::table('entities')->value('id') ?? 1);
    }

    public function index(Request $request)
    {
        $entity = $this->entityId();
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        // UI stage: receipt data will be backed by dedicated receipt tables in the next implementation phase.
        $rows = collect();

        $suppliers = DB::table('suppliers')
            ->where('entity_id', $entity)->where('is_active', 1)
            ->orderBy('name')->get(['id','code','name']);

        $warehouses = DB::table('warehouses')
            ->where('entity_id', $entity)->where('is_active', 1)
            ->orderBy('name')->get(['id','code','name']);

        return view('erp.receipts.index', compact('rows','suppliers','warehouses','startDate','endDate'));
    }

    public function create()
    {
        $entity = $this->entityId();

        $suppliers = DB::table('suppliers')
            ->where('entity_id', $entity)->where('is_active', 1)
            ->orderBy('name')->get(['id','code','name','phone','address']);

        $warehouses = DB::table('warehouses')
            ->where('entity_id', $entity)->where('is_active', 1)
            ->orderBy('name')->get(['id','code','name']);

        $products = DB::table('products as p')
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->where('p.entity_id', $entity)->where('p.is_active', 1)
            ->orderBy('p.name')
            ->get(['p.id','p.code','p.sku','p.name','u.code as unit_code','u.name as unit_name']);

        return view('erp.receipts.create', compact('suppliers','warehouses','products'));
    }

    public function edit(int $id)
    {
        // UI placeholder: receipt persistence/detail will be implemented with the receipt schema.
        abort(404);
    }
}
