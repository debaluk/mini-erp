<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UnitConversionController extends Controller
{
    private function entityId(): int
    {
        return (int) DB::table('entities')->orderBy('id')->value('id');
    }

    public function index()
    {
        $entity = $this->entityId();
        $rows = DB::table('product_units as pu')
            ->join('products as p','p.id','=','pu.product_id')
            ->join('units as u','u.id','=','pu.unit_id')
            ->leftJoin('units as du','du.id','=','p.unit_id')
            ->where('pu.entity_id',$entity)
            ->select('pu.*','p.name as product_name','u.code as unit_code','u.name as unit_name','du.code as default_unit_code')
            ->orderBy('p.name')->orderBy('u.code')->get();

        $products = DB::table('products')->where('entity_id',$entity)->where('is_active',1)->orderBy('name')->get(['id','name','unit_id']);
        $units = DB::table('units')->where('entity_id',$entity)->orderBy('name')->get();

        return view('erp.unit-conversions', compact('rows','products','units'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id'=>'required|integer',
            'unit_id'=>'required|integer',
            'conversion_factor'=>'required|numeric|gt:0',
            'is_default'=>'nullable|boolean',
        ]);
        $entity=$this->entityId();
        abort_unless(DB::table('products')->where('entity_id',$entity)->where('id',$data['product_id'])->exists(),422,'Produk tidak valid.');
        abort_unless(DB::table('units')->where('entity_id',$entity)->where('id',$data['unit_id'])->exists(),422,'Satuan tidak valid.');

        DB::table('product_units')->updateOrInsert(
            ['product_id'=>$data['product_id'],'unit_id'=>$data['unit_id']],
            ['entity_id'=>$entity,'conversion_factor'=>$data['conversion_factor'],'is_default'=>(bool)($data['is_default']??false),'updated_at'=>now(),'created_at'=>now()]
        );

        if (($data['is_default']??false)) {
            DB::table('product_units')->where('entity_id',$entity)->where('product_id',$data['product_id'])->where('unit_id','<>',$data['unit_id'])->update(['is_default'=>false,'updated_at'=>now()]);
            DB::table('products')->where('entity_id',$entity)->where('id',$data['product_id'])->update(['unit_id'=>$data['unit_id'],'updated_at'=>now()]);
        }

        return back()->with('success','Konversi satuan berhasil disimpan.');
    }

    public function destroy(int $id)
    {
        $deleted=DB::table('product_units')->where('entity_id',$this->entityId())->where('id',$id)->delete();
        abort_unless($deleted,404);
        return back()->with('success','Konversi satuan berhasil dihapus.');
    }
}