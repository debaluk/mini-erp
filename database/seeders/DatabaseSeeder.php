<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name'=>'Owner','email'=>'owner@minierp.local','role'=>'owner'],
            ['name'=>'Admin','email'=>'admin@minierp.local','role'=>'admin'],
            ['name'=>'Kasir','email'=>'kasir@minierp.local','role'=>'kasir'],
            ['name'=>'Inventori','email'=>'inventori@minierp.local','role'=>'inventori'],
            ['name'=>'Akuntansi','email'=>'akuntansi@minierp.local','role'=>'akuntansi'],
        ];
        foreach ($users as $data) DB::table('users')->updateOrInsert(['email'=>$data['email']], ['name'=>$data['name'],'password'=>Hash::make('password'),'role'=>$data['role'],'is_active'=>1,'created_at'=>now(),'updated_at'=>now()]);

        $entity=DB::table('entities')->first();
        if(!$entity){$eid=DB::table('entities')->insertGetId(['code'=>'ENT-001','name'=>'Entitas Utama','is_active'=>1,'created_at'=>now(),'updated_at'=>now()]);} else $eid=$entity->id;
        $unit=DB::table('units')->where(['entity_id'=>$eid,'code'=>'PCS'])->first();
        if(!$unit)$unitId=DB::table('units')->insertGetId(['entity_id'=>$eid,'code'=>'PCS','name'=>'Pieces','created_at'=>now(),'updated_at'=>now()]); else $unitId=$unit->id;
        $warehouse=DB::table('warehouses')->where(['entity_id'=>$eid,'code'=>'GUD-001'])->first();
        if(!$warehouse)$warehouseId=DB::table('warehouses')->insertGetId(['entity_id'=>$eid,'code'=>'GUD-001','name'=>'Gudang Utama','type'=>'general','address'=>'Gudang Utama','is_active'=>1,'created_at'=>now(),'updated_at'=>now()]); else $warehouseId=$warehouse->id;
        $products=[
            ['sku'=>'SEMEN-001','barcode'=>'899000000001','name'=>'Semen','type'=>'raw_material','cost_price'=>65000,'selling_price'=>70000,'minimum_stock'=>10],
            ['sku'=>'PASIR-001','barcode'=>'899000000002','name'=>'Pasir','type'=>'raw_material','cost_price'=>250000,'selling_price'=>300000,'minimum_stock'=>5],
            ['sku'=>'BATAKO-001','barcode'=>'899000000003','name'=>'Batako 10x20x40','type'=>'finished_goods','cost_price'=>2500,'selling_price'=>4000,'minimum_stock'=>100],
        ];
        $productIds=[]; foreach($products as $p){$row=DB::table('products')->where(['entity_id'=>$eid,'sku'=>$p['sku']])->first(); if(!$row)$pid=DB::table('products')->insertGetId(array_merge($p,['entity_id'=>$eid,'unit_id'=>$unitId,'is_active'=>1,'created_at'=>now(),'updated_at'=>now()])); else $pid=$row->id; $productIds[$p['sku']]=$pid;}
        DB::table('vehicles')->updateOrInsert(['entity_id'=>$eid,'code'=>'TRK-001'],['plate_number'=>'DK 1001 XX','model'=>'Truck Bak','vehicle_type'=>'Truck','capacity'=>5,'current_km'=>0,'status'=>'active','created_at'=>now(),'updated_at'=>now()]);
        DB::table('drivers')->updateOrInsert(['entity_id'=>$eid,'code'=>'DRV-001'],['name'=>'Driver Demo','phone'=>'0800000000','license_no'=>'SIM-B1','is_active'=>1,'created_at'=>now(),'updated_at'=>now()]);
        $accounts=[['1000','Kas','asset'],['1100','Bank','asset'],['1200','Persediaan','asset'],['2000','Hutang Usaha','liability'],['3000','Modal','equity'],['4000','Penjualan','revenue'],['5000','HPP','cogs'],['6000','Beban Operasional','expense']];
        foreach($accounts as [$code,$name,$type]) DB::table('chart_of_accounts')->updateOrInsert(['entity_id'=>$eid,'code'=>$code],['name'=>$name,'type'=>$type,'is_active'=>1,'created_at'=>now(),'updated_at'=>now()]);
        $bom=DB::table('boms')->where(['entity_id'=>$eid,'code'=>'BOM-BATAKO'])->first();
        if(!$bom){$bomId=DB::table('boms')->insertGetId(['entity_id'=>$eid,'product_id'=>$productIds['BATAKO-001'],'code'=>'BOM-BATAKO','name'=>'Formula Batako Standar','output_qty'=>10,'is_active'=>1,'created_at'=>now(),'updated_at'=>now()]); DB::table('bom_items')->insert([['bom_id'=>$bomId,'product_id'=>$productIds['SEMEN-001'],'qty'=>1,'created_at'=>now(),'updated_at'=>now()],['bom_id'=>$bomId,'product_id'=>$productIds['PASIR-001'],'qty'=>2,'created_at'=>now(),'updated_at'=>now()]]);}
    }
}
