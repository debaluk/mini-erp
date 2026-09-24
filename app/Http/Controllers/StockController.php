<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    private function entityId(): int
    {
        return (int) (DB::table('entities')->first()->id ?? 1);
    }

    public function index(Request $request)
    {
        $entity = $this->entityId();
        $warehouses = DB::table('warehouses')->where('entity_id',$entity)->where('is_active',1)->orderBy('name')->get();
        $query = DB::table('warehouses_stocks as ws')
            ->join('products as p','p.id','=','ws.product_id')
            ->leftJoin('units as u','u.id','=','p.base_unit_id')
            ->join('warehouses as w','w.id','=','ws.warehouse_id')
            ->where('ws.entity_id',$entity)
            ->where('p.entity_id',$entity)
            ->select('ws.id','p.id as product_id','p.sku','p.code','p.name as product_name','u.code as unit_code','u.name as unit_name','w.id as warehouse_id','w.name as warehouse_name','ws.qty','ws.avg_cost',DB::raw('(ws.qty * ws.avg_cost) as stock_value'))
            ->orderBy('p.name')->orderBy('w.name');

        if ($request->filled('warehouse_id')) $query->where('ws.warehouse_id',$request->integer('warehouse_id'));
        if ($request->filled('search')) {
            $s='%'.$request->input('search').'%';
            $query->where(function($q) use ($s){$q->where('p.name','like',$s)->orWhere('p.code','like',$s)->orWhere('p.sku','like',$s);});
        }

        $rows=$query->paginate(20)->withQueryString();
        return view('inventori.persediaan.index',compact('rows','warehouses'));
    }

    public function export(Request $request)
    {
        $entity = $this->entityId();
        $query = DB::table('warehouses_stocks as ws')
            ->join('products as p','p.id','=','ws.product_id')
            ->leftJoin('units as u','u.id','=','p.base_unit_id')
            ->join('warehouses as w','w.id','=','ws.warehouse_id')
            ->where('ws.entity_id',$entity)->where('p.entity_id',$entity)
            ->select('p.code','p.sku','p.name as product_name','u.code as unit_code','u.name as unit_name','w.name as warehouse_name','ws.qty','ws.avg_cost',DB::raw('(ws.qty * ws.avg_cost) as stock_value'))
            ->orderBy('p.name')->orderBy('w.name');
        if ($request->filled('warehouse_id')) $query->where('ws.warehouse_id',$request->integer('warehouse_id'));
        if ($request->filled('search')) {
            $s='%'.$request->input('search').'%';
            $query->where(function($q) use ($s){$q->where('p.name','like',$s)->orWhere('p.code','like',$s)->orWhere('p.sku','like',$s);});
        }
        $rows=$query->get();
        return response()->json([
            'entity_name'=>DB::table('entities')->where('id',$entity)->value('name') ?? 'NAMA ENTITAS',
            'rows'=>$rows,
        ]);
    }

    public function detail(Request $request, int $product, int $warehouse)
    {
        $entity=$this->entityId();
        $stock=DB::table('warehouses_stocks as ws')
            ->join('products as p','p.id','=','ws.product_id')
            ->leftJoin('units as u','u.id','=','p.base_unit_id')
            ->join('warehouses as w','w.id','=','ws.warehouse_id')
            ->where('ws.entity_id',$entity)->where('ws.product_id',$product)->where('ws.warehouse_id',$warehouse)
            ->select('p.code','p.sku','p.name as product_name','u.code as unit_code','u.name as unit_name','w.name as warehouse_name','ws.qty','ws.avg_cost',DB::raw('(ws.qty * ws.avg_cost) as stock_value'))->first();
        abort_unless($stock,404);

        $movements=DB::table('stock_movements as sm')
            ->where('sm.entity_id',$entity)->where('sm.product_id',$product)->where('sm.warehouse_id',$warehouse)
            ->orderBy('sm.occurred_at')->orderBy('sm.id')
            ->get();

        $saldo=0;
        $history=$movements->map(function($m) use (&$saldo){
            $qty=(float)$m->qty;
            $in=0; $out=0;
            if (in_array($m->movement_type,['opening','purchase_in','receipt_in','production_in','transfer_in','adjustment_in','return_in'],true)) $in=$qty;
            elseif (in_array($m->movement_type,['sale_out','purchase_return','production_out','transfer_out','adjustment_out','reject_out','return_out'],true)) $out=$qty;
            else { $in=$qty>0?$qty:0; $out=$qty<0?abs($qty):0; }
            $saldo += $in-$out;
            return (object)['date'=>$m->occurred_at,'reference'=>$m->reference_type && $m->reference_id ? strtoupper(str_replace('_',' ',$m->reference_type)).' #'.$m->reference_id : '-', 'movement_type'=>$m->movement_type,'in'=>$in,'out'=>$out,'balance'=>$saldo,'unit_cost'=>$m->unit_cost];
        });
        return view('inventori.persediaan.kartu-stok',compact('stock','history'));
    }
}
