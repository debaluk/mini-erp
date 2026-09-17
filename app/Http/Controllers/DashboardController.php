<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $entity = DB::table('entities')->first();
        $entityId = $entity?->id;
        $products = DB::table('products')->when($entityId, fn($q)=>$q->where('entity_id',$entityId))->count();
        $customers = DB::table('customers')->when($entityId, fn($q)=>$q->where('entity_id',$entityId))->count();
        $suppliers = DB::table('suppliers')->when($entityId, fn($q)=>$q->where('entity_id',$entityId))->count();
        $vehicles = DB::table('vehicles')->when($entityId, fn($q)=>$q->where('entity_id',$entityId))->count();
        $stockValue = DB::table('warehouses_stocks')->when($entityId, fn($q)=>$q->where('entity_id',$entityId))->selectRaw('COALESCE(SUM(qty * avg_cost),0) value')->value('value');
        $salesToday = DB::table('sales')->whereDate('sale_date',today())->when($entityId, fn($q)=>$q->where('entity_id',$entityId))->where('status','posted')->sum('total');
        $purchasesToday = DB::table('purchases')->whereDate('purchase_date',today())->when($entityId, fn($q)=>$q->where('entity_id',$entityId))->where('status','received')->sum('total');
        return view('dashboard.index', compact('products','customers','suppliers','vehicles','stockValue','salesToday','purchasesToday'));
    }
}
