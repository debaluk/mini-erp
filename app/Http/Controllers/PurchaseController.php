<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    private function entityId(): int
    {
        return (int) (DB::table('entities')->value('id') ?? 1);
    }

    private function products(int $entity)
    {
        $products = DB::table('products as p')
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->where('p.entity_id', $entity)
            ->where('p.is_active', 1)
            ->where('p.manage_stock', 1)
            ->orderBy('p.name')
            ->get(['p.id','p.code','p.sku','p.name','p.cost_price','p.base_unit_id','u.code as unit_code','u.name as unit_name']);

        foreach ($products as $product) {
            $product->transaction_units = DB::table('units as u')
                ->where('u.id',$product->base_unit_id)
                ->get(['u.id','u.code','u.name'])
                ->map(fn($u)=>[
                    'id'=>(int)$u->id,'code'=>$u->code,'name'=>$u->name,
                    'factor'=>1.0,'default_purchase'=>false,'price'=>(float)$product->cost_price,
                ])->values();

            foreach (DB::table('product_unit_conversions as puc')
                ->join('units as u','u.id','=','puc.unit_id')
                ->where('puc.product_id',$product->id)
                ->where('puc.is_active',1)
                ->orderBy('u.name')
                ->get(['u.id','u.code','u.name','puc.conversion_factor','puc.is_default_purchase']) as $conversion) {
                $product->transaction_units->push([
                    'id'=>(int)$conversion->id,'code'=>$conversion->code,'name'=>$conversion->name,
                    'factor'=>(float)$conversion->conversion_factor,
                    'default_purchase'=>(bool)$conversion->is_default_purchase,
                    'price'=>round((float)$product->cost_price*(float)$conversion->conversion_factor,2),
                ]);
            }
        }
        return $products;
    }

    public function index(Request $request)
    {
        $entity = $this->entityId();
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        $rows = DB::table('purchases as p')
            ->leftJoin('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'p.business_unit_id')
            ->where('p.entity_id', $entity)
            ->whereBetween('p.purchase_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->when($request->filled('supplier'), fn ($q) => $q->where('s.name', 'like', '%' . $request->supplier . '%'))
            ->when($request->filled('unit_id'), fn ($q) => $q->where('p.business_unit_id', $request->unit_id))
            ->when($request->filled('payment_method'), fn ($q) => $q->where('p.payment_method', $request->payment_method))
            ->when($request->filled('status'), fn ($q) => $q->where('p.status', $request->status))
            ->select('p.*', 's.name as supplier_name', 'bu.name as unit_name')
            ->orderByDesc('p.purchase_date')
            ->orderByDesc('p.id')
            ->paginate(10)
            ->withQueryString();

        $suppliers = DB::table('suppliers')
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $units = DB::table('business_units')
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view('erp.purchases.index', compact('rows', 'suppliers', 'units'));
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

        $warehouses = DB::table('warehouses')->where('entity_id',$entity)->where('is_active',1)->orderBy('name')->get();
        $products = $this->products($entity);

        return view('erp.purchases.create', compact('suppliers', 'units', 'warehouses', 'products'));
    }

    public function edit(int $id)
    {
        $entity = $this->entityId();

        $purchase = DB::table('purchases as p')
            ->leftJoin('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->where('p.entity_id', $entity)
            ->where('p.id', $id)
            ->select('p.*', 's.name as supplier_name', 's.phone as supplier_phone', 's.address as supplier_address')
            ->first();

        abort_unless($purchase, 404);

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

        $warehouses = DB::table('warehouses')->where('entity_id',$entity)->where('is_active',1)->orderBy('name')->get();
        $products = $this->products($entity);

        $items = DB::table('purchase_items as pi')
            ->join('products as p', 'p.id', '=', 'pi.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->where('pi.purchase_id', $id)
            ->orderBy('pi.id')
            ->get(['pi.product_id', 'p.code', 'p.sku', 'p.name', 'u.code as unit_code', 'pi.qty', 'pi.unit_cost', 'pi.total']);

        return view('erp.purchases.create', compact('purchase', 'suppliers', 'units', 'warehouses', 'products', 'items'));
    }
}
