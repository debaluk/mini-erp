<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UnitAndConversionSeeder extends Seeder
{
    public function run(): void
    {
        $entity = DB::table('entities')->orderBy('id')->first();
        if (!$entity) {
            $entityId = DB::table('entities')->insertGetId([
                'code'=>'ENT-001','name'=>'Entitas Utama','is_active'=>1,
                'created_at'=>now(),'updated_at'=>now()
            ]);
        } else {
            $entityId = $entity->id;
        }

        DB::transaction(function () use ($entityId) {
            // Bersihkan relasi lama sebelum mengganti master satuan.
            if (DB::getSchemaBuilder()->hasTable('product_units')) {
                DB::table('product_units')->where('entity_id',$entityId)->delete();
            }
            DB::table('products')->where('entity_id',$entityId)->update(['unit_id'=>null,'updated_at'=>now()]);
            DB::table('units')->where('entity_id',$entityId)->delete();

            $units = [
                ['code'=>'PCS','name'=>'Pieces'],
                ['code'=>'UNIT','name'=>'Unit'],
                ['code'=>'KG','name'=>'Kilogram'],
                ['code'=>'GRAM','name'=>'Gram'],
                ['code'=>'TON','name'=>'Ton'],
                ['code'=>'SAK','name'=>'Sak'],
                ['code'=>'KARUNG','name'=>'Karung'],
                ['code'=>'DUS','name'=>'Dus'],
                ['code'=>'BOX','name'=>'Box'],
                ['code'=>'PACK','name'=>'Pack'],
                ['code'=>'LUSIN','name'=>'Lusin'],
                ['code'=>'M','name'=>'Meter'],
                ['code'=>'CM','name'=>'Centimeter'],
                ['code'=>'M2','name'=>'Meter Persegi'],
                ['code'=>'M3','name'=>'Meter Kubik'],
                ['code'=>'LITER','name'=>'Liter'],
                ['code'=>'ML','name'=>'Mililiter'],
                ['code'=>'TRIP','name'=>'Trip'],
                ['code'=>'JAM','name'=>'Jam'],
                ['code'=>'HARI','name'=>'Hari'],
            ];

            $now=now();
            foreach($units as &$u){$u['entity_id']=$entityId;$u['created_at']=$now;$u['updated_at']=$now;}
            unset($u);
            DB::table('units')->insert($units);

            $uid=[];
            foreach(DB::table('units')->where('entity_id',$entityId)->get(['id','code']) as $u) $uid[$u->code]=$u->id;

            if (!DB::getSchemaBuilder()->hasTable('product_units')) return;

            $products=DB::table('products')->where('entity_id',$entityId)->get(['id','name']);
            foreach($products as $p){
                $n=strtolower($p->name);
                $base=null; $extra=null;
                if(str_contains($n,'semen')) {$base='KG';$extra=['SAK',50];}
                elseif(str_contains($n,'batako')) {$base='PCS';$extra=['DUS',20];}
                elseif(str_contains($n,'paku')) {$base='PCS';$extra=['BOX',100];}
                elseif(str_contains($n,'keramik')) {$base='PCS';$extra=['DUS',10];}
                elseif(str_contains($n,'cat')) {$base='LITER';$extra=['DUS',12];}
                elseif(str_contains($n,'kabel')) {$base='M';$extra=['BOX',100];}

                if($base && isset($uid[$base])){
                    DB::table('products')->where('id',$p->id)->update(['unit_id'=>$uid[$base],'updated_at'=>$now]);
                    DB::table('product_units')->insert([
                        'entity_id'=>$entityId,'product_id'=>$p->id,'unit_id'=>$uid[$base],
                        'conversion_factor'=>1,'is_default'=>true,'created_at'=>$now,'updated_at'=>$now
                    ]);
                    if($extra && isset($uid[$extra[0]])){
                        DB::table('product_units')->insert([
                            'entity_id'=>$entityId,'product_id'=>$p->id,'unit_id'=>$uid[$extra[0]],
                            'conversion_factor'=>$extra[1],'is_default'=>false,'created_at'=>$now,'updated_at'=>$now
                        ]);
                    }
                }
            }
        });
    }
}