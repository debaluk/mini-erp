<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItemInitialSetupController extends Controller
{
    private function entityId(): int { $id=(int)(auth()->user()->entity_id??0); abort_unless($id>0&&DB::table('entities')->where('id',$id)->exists(),403,'Entitas pengguna tidak valid.'); return $id; }
    private function retailUnitId(int $entity): int { $id=(int)DB::table('business_units')->where('entity_id',$entity)->where('code','RET')->where('is_active',1)->value('id'); abort_unless($id>0,422,'Unit Retail aktif belum tersedia.'); return $id; }

    public function index()
    {
        $entity=$this->entityId(); $bu=$this->retailUnitId($entity);
        $entityName=DB::table('entities')->where('id',$entity)->value('name')??'NAMA ENTITAS';
        $rows=DB::table('item_initial_setups as s')->join('products as p','p.id','=','s.product_id')->leftJoin('units as u','u.id','=','p.base_unit_id')->where('s.entity_id',$entity)->where('s.business_unit_id',$bu)->select('s.*','p.code','p.barcode','p.name','u.code as unit_code')->orderBy('p.name')->get();
        $products=DB::table('products as p')->join('product_business_units as pbu',function($j)use($bu){$j->on('pbu.product_id','=','p.id')->where('pbu.business_unit_id',$bu);})->leftJoin('units as u','u.id','=','p.base_unit_id')->where('p.entity_id',$entity)->where('p.is_active',1)->where('p.item_type','barang')->whereNotExists(function($q)use($entity,$bu){$q->select(DB::raw(1))->from('item_initial_setups as x')->whereColumn('x.product_id','p.id')->where('x.entity_id',$entity)->where('x.business_unit_id',$bu);})->select('p.id','p.code','p.barcode','p.name','u.code as unit_code')->orderBy('p.name')->distinct()->get();
        return view('erp.item-initial-setup',compact('rows','products','entityName'));
    }

    public function store(Request $request)
    {
        $entity=$this->entityId(); $bu=$this->retailUnitId($entity);
        $d=$request->validate(['setup_date'=>'required|date','product_id'=>'required|integer','purchase_price'=>'required|numeric|gt:0','initial_stock'=>'required|numeric|gt:0','markup_percent'=>'nullable|numeric|min:0','selling_price'=>'required|numeric|gt:0']);
        $product=DB::table('products')->where('entity_id',$entity)->where('id',$d['product_id'])->where('is_active',1)->where('item_type','barang')->first();
        abort_unless($product,422,'Item tidak valid.'); abort_unless(DB::table('product_business_units')->where('product_id',$product->id)->where('business_unit_id',$bu)->exists(),422,'Item belum dipilih untuk Unit Retail.');
        abort_if(DB::table('item_initial_setups')->where('entity_id',$entity)->where('product_id',$product->id)->where('business_unit_id',$bu)->exists(),422,'Setup stok awal item ini sudah ada.');
        $warehouse=DB::table('warehouses')->where('entity_id',$entity)->where('is_active',1)->orderBy('id')->first(); abort_unless($warehouse,422,'Belum ada gudang aktif untuk menerima stok awal.');
        DB::transaction(function()use($entity,$bu,$d,$product,$warehouse){
            $stock=DB::table('warehouses_stocks')->where('entity_id',$entity)->where('warehouse_id',$warehouse->id)->where('product_id',$product->id)->lockForUpdate()->first();
            abort_unless(!$stock||(float)$stock->qty===0.0,422,'Item sudah memiliki stok. Setup awal tidak dapat menambah stok yang sudah ada.');
            $setupId=DB::table('item_initial_setups')->insertGetId(['entity_id'=>$entity,'product_id'=>$product->id,'business_unit_id'=>$bu,'warehouse_id'=>$warehouse->id,'setup_date'=>$d['setup_date'],'purchase_price'=>$d['purchase_price'],'initial_stock'=>$d['initial_stock'],'markup_percent'=>$d['markup_percent']??0,'selling_price'=>$d['selling_price'],'created_at'=>now(),'updated_at'=>now()]);
            DB::table('products')->where('id',$product->id)->update(['selling_price'=>$d['selling_price'],'cost_price'=>$d['purchase_price'],'updated_at'=>now()]);
            DB::table('selling_price_histories')->insert(['entity_id'=>$entity,'product_id'=>$product->id,'business_unit_id'=>$bu,'old_price'=>null,'new_price'=>$d['selling_price'],'old_markup_percent'=>null,'new_markup_percent'=>$d['markup_percent']??0,'effective_date'=>$d['setup_date'],'reason'=>'Harga jual awal','changed_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
            if($stock)DB::table('warehouses_stocks')->where('id',$stock->id)->update(['qty'=>$d['initial_stock'],'avg_cost'=>$d['purchase_price'],'updated_at'=>now()]); else DB::table('warehouses_stocks')->insert(['entity_id'=>$entity,'warehouse_id'=>$warehouse->id,'product_id'=>$product->id,'qty'=>$d['initial_stock'],'avg_cost'=>$d['purchase_price'],'created_at'=>now(),'updated_at'=>now()]);
            DB::table('stock_movements')->insert(['entity_id'=>$entity,'business_unit_id'=>$bu,'warehouse_id'=>$warehouse->id,'product_id'=>$product->id,'movement_type'=>'opening','qty'=>$d['initial_stock'],'unit_cost'=>$d['purchase_price'],'reference_type'=>'item_initial_setup','reference_id'=>$setupId,'occurred_at'=>$d['setup_date'].' 00:00:00','created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
        });
        return response()->json(['message'=>'Setup stok & harga awal berhasil disimpan.']);
    }
}
