<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder; use Illuminate\Support\Facades\DB; use Illuminate\Support\Facades\Hash;
class DatabaseSeeder extends Seeder { public function run(): void {
 $eid=DB::table('entities')->insertGetId(['code'=>'ENT001','name'=>'Entitas Utama','is_active'=>1,'created_at'=>now(),'updated_at'=>now()]);
 $uid=DB::table('units')->insertGetId(['entity_id'=>$eid,'code'=>'PCS','name'=>'Pcs','created_at'=>now(),'updated_at'=>now()]);
 $cid=DB::table('product_categories')->insertGetId(['entity_id'=>$eid,'name'=>'Barang Bangunan','created_at'=>now(),'updated_at'=>now()]);
 DB::table('warehouses')->insert(['entity_id'=>$eid,'code'=>'GDG-UTAMA','name'=>'Gudang Utama','type'=>'general','is_active'=>1,'created_at'=>now(),'updated_at'=>now()]);
 DB::table('products')->insert(['entity_id'=>$eid,'category_id'=>$cid,'unit_id'=>$uid,'sku'=>'BAT-10','barcode'=>'899000000001','name'=>'Batako 10 cm','type'=>'finished_goods','cost_price'=>0,'selling_price'=>3500,'created_at'=>now(),'updated_at'=>now()]);
 foreach([['1101','Kas','asset'],['1102','Bank','asset'],['1201','Piutang','asset'],['1301','Persediaan Barang Dagangan','asset'],['1302','Persediaan Bahan Baku','asset'],['1303','WIP','asset'],['1304','Persediaan Barang Jadi','asset'],['2101','Hutang Usaha','liability'],['3101','Modal','equity'],['4101','Penjualan Barang Dagangan','revenue'],['4102','Penjualan Produk Produksi','revenue'],['4301','Pendapatan Jasa Armada','revenue'],['5101','HPP Barang Dagangan','cogs'],['5102','HPP Produk Produksi','cogs'],['5103','HPP Jasa Armada','cogs'],['6101','Beban Operasional','expense']] as $a) DB::table('chart_of_accounts')->insert(['entity_id'=>$eid,'code'=>$a[0],'name'=>$a[1],'type'=>$a[2],'created_at'=>now(),'updated_at'=>now()]);
 }
}
