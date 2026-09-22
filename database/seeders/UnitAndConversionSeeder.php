<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UnitAndConversionSeeder extends Seeder
{
    public function run(): void
    {
        $entity = DB::table('entities')->orderBy('id')->first();
        $entityId = $entity?->id ?? DB::table('entities')->insertGetId([
            'code'=>'ENT-001','name'=>'Entitas Utama','is_active'=>1,
            'created_at'=>now(),'updated_at'=>now()
        ]);

        DB::transaction(function () use ($entityId) {
            $now = now();

            DB::table('product_unit_conversions')->whereIn('product_id',
                DB::table('products')->where('entity_id',$entityId)->pluck('id')
            )->delete();

            DB::table('products')->where('entity_id',$entityId)->update([
                'base_unit_id'=>null,
                'updated_at'=>$now,
            ]);

            DB::table('units')->where('entity_id',$entityId)->delete();

            $units = [
                ['code'=>'PCS','name'=>'Pieces'], ['code'=>'UNIT','name'=>'Unit'],
                ['code'=>'KG','name'=>'Kilogram'], ['code'=>'GRAM','name'=>'Gram'],
                ['code'=>'TON','name'=>'Ton'], ['code'=>'SAK','name'=>'Sak'],
                ['code'=>'KARUNG','name'=>'Karung'], ['code'=>'DUS','name'=>'Dus'],
                ['code'=>'BOX','name'=>'Box'], ['code'=>'PACK','name'=>'Pack'],
                ['code'=>'LUSIN','name'=>'Lusin'], ['code'=>'M','name'=>'Meter'],
                ['code'=>'CM','name'=>'Centimeter'], ['code'=>'M2','name'=>'Meter Persegi'],
                ['code'=>'M3','name'=>'Meter Kubik'], ['code'=>'LITER','name'=>'Liter'],
                ['code'=>'ML','name'=>'Mililiter'], ['code'=>'TRIP','name'=>'Trip'],
                ['code'=>'JAM','name'=>'Jam'], ['code'=>'HARI','name'=>'Hari'],
            ];

            foreach ($units as &$u) {
                $u['entity_id']=$entityId; $u['created_at']=$now; $u['updated_at']=$now;
            }
            unset($u);
            DB::table('units')->insert($units);

            $uid=[];
            foreach(DB::table('units')->where('entity_id',$entityId)->get(['id','code']) as $u) $uid[$u->code]=$u->id;

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
                    DB::table('products')->where('id',$p->id)->update([
                        'base_unit_id'=>$uid[$base],
                        'updated_at'=>$now,
                    ]);

                    if($extra && isset($uid[$extra[0]])){
                        DB::table('product_unit_conversions')->insert([
                            'product_id'=>$p->id,
                            'unit_id'=>$uid[$extra[0]],
                            'conversion_factor'=>$extra[1],
                            'is_default_purchase'=>true,
                            'is_default_sale'=>true,
                            'is_active'=>true,
                            'created_at'=>$now,
                            'updated_at'=>$now,
                        ]);
                    }
                }
            }
        });
    }
}
