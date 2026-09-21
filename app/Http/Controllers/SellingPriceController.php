<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SellingPriceController extends Controller
{
    private function entityId(): int
    {
        $entityId=(int)(auth()->user()->entity_id??0);
        abort_unless($entityId>0 && DB::table('entities')->where('id',$entityId)->exists(),403,'Entitas pengguna tidak valid.');
        return $entityId;
    }

    private function retailUnitId(int $entity): int
    {
        $id=(int)DB::table('business_units')->where('entity_id',$entity)->where('code','RET')->where('is_active',1)->value('id');
        abort_unless($id>0,422,'Unit Retail aktif belum tersedia.');
        return $id;
    }

    public function index()
    {
        $entity=$this->entityId(); $retailUnitId=$this->retailUnitId($entity);
        $entityName=DB::table('entities')->where('id',$entity)->value('name')??'NAMA ENTITAS';
        $rows=DB::table('products as p')
            ->join('product_business_units as pbu',function($j)use($retailUnitId){$j->on('pbu.product_id','=','p.id')->where('pbu.business_unit_id',$retailUnitId);})
            ->leftJoin('units as u','u.id','=','p.base_unit_id')
            ->where('p.entity_id',$entity)->where('p.is_active',1)->where('p.item_type','barang')
            ->select('p.id','p.code','p.barcode','p.name','p.selling_price','u.code as unit_code')
            ->orderBy('p.name')->distinct()->get();
        return view('erp.selling-price',compact('rows','entityName'));
    }

    public function history(int $product)
    {
        $entity=$this->entityId(); $retailUnitId=$this->retailUnitId($entity);
        abort_unless(DB::table('products')->where('id',$product)->where('entity_id',$entity)->where('is_active',1)->exists(),404,'Item tidak ditemukan.');
        $rows=DB::table('selling_price_histories as h')->leftJoin('users as u','u.id','=','h.changed_by')
            ->where('h.entity_id',$entity)->where('h.product_id',$product)->where('h.business_unit_id',$retailUnitId)
            ->orderByDesc('h.effective_date')->orderByDesc('h.id')->select('h.*','u.name as changed_by_name')->get();
        return response()->json(['data'=>$rows]);
    }

    public function update(Request $request,int $product)
    {
        $entity=$this->entityId(); $retailUnitId=$this->retailUnitId($entity);
        $data=$request->validate(['effective_date'=>['required','date'],'markup_percent'=>['required','numeric','min:0'],'selling_price'=>['required','numeric','gt:0'],'reason'=>['nullable','string','max:255']]);
        $item=DB::table('products')->where('id',$product)->where('entity_id',$entity)->where('is_active',1)->where('item_type','barang')->first();
        abort_unless($item,404,'Item tidak ditemukan.');
        abort_unless(DB::table('product_business_units')->where('product_id',$product)->where('business_unit_id',$retailUnitId)->exists(),422,'Item belum dipilih untuk Unit Retail.');
        DB::transaction(function()use($entity,$retailUnitId,$product,$data,$item){
            $oldPrice=(float)$item->selling_price;
            $oldMarkup=(float)(DB::table('selling_price_histories')->where('entity_id',$entity)->where('product_id',$product)->where('business_unit_id',$retailUnitId)->orderByDesc('effective_date')->orderByDesc('id')->value('new_markup_percent')??0);
            if(abs($oldPrice-(float)$data['selling_price'])<.000001 && abs($oldMarkup-(float)$data['markup_percent'])<.000001)return;
            DB::table('products')->where('id',$product)->where('entity_id',$entity)->update(['selling_price'=>$data['selling_price'],'updated_at'=>now()]);
            DB::table('selling_price_histories')->insert(['entity_id'=>$entity,'product_id'=>$product,'business_unit_id'=>$retailUnitId,'old_price'=>$oldPrice,'new_price'=>$data['selling_price'],'old_markup_percent'=>$oldMarkup,'new_markup_percent'=>$data['markup_percent'],'effective_date'=>$data['effective_date'],'reason'=>$data['reason']??'Perubahan harga jual','changed_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
        });
        return response()->json(['message'=>'Harga jual berhasil diperbarui.']);
    }
}
