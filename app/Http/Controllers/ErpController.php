<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ErpController extends Controller
{
    private function entityId(): int
    {
        $entity = DB::table('entities')->first();
        if (!$entity) {
            return DB::table('entities')->insertGetId(['code'=>'ENT-001','name'=>'Entitas Utama','is_active'=>1,'created_at'=>now(),'updated_at'=>now()]);
        }
        return $entity->id;
    }

    private function masterConfig(string $type): array
    {
        return [
            'products'=>['title'=>'Produk','table'=>'products','columns'=>['sku','barcode','name','type','cost_price','selling_price','minimum_stock'],'fields'=>[
                'sku'=>['label'=>'SKU','type'=>'text'],'barcode'=>['label'=>'Barcode','type'=>'text'],'name'=>['label'=>'Nama Produk','type'=>'text','required'=>true],
                'type'=>['label'=>'Tipe','type'=>'select','options'=>['raw_material'=>'Bahan Baku','merchandise'=>'Barang Dagang','wip'=>'WIP','finished_goods'=>'Barang Jadi']],
                'cost_price'=>['label'=>'Harga Pokok','type'=>'number','step'=>'0.01'],'selling_price'=>['label'=>'Harga Jual','type'=>'number','step'=>'0.01'],'minimum_stock'=>['label'=>'Minimum Stok','type'=>'number','step'=>'0.001']]],
            'customers'=>['title'=>'Customer','table'=>'customers','columns'=>['code','name','phone','address','credit_limit'],'fields'=>['code'=>['label'=>'Kode','type'=>'text','required'=>true],'name'=>['label'=>'Nama','type'=>'text','required'=>true],'phone'=>['label'=>'Telepon','type'=>'text'],'address'=>['label'=>'Alamat','type'=>'textarea'],'credit_limit'=>['label'=>'Limit Kredit','type'=>'number','step'=>'0.01']]],
            'suppliers'=>['title'=>'Supplier','table'=>'suppliers','columns'=>['code','name','phone','address','credit_limit'],'fields'=>['code'=>['label'=>'Kode','type'=>'text','required'=>true],'name'=>['label'=>'Nama','type'=>'text','required'=>true],'phone'=>['label'=>'Telepon','type'=>'text'],'address'=>['label'=>'Alamat','type'=>'textarea'],'credit_limit'=>['label'=>'Limit Kredit','type'=>'number','step'=>'0.01']]],
            'warehouses'=>['title'=>'Gudang','table'=>'warehouses','columns'=>['code','name','type','address'],'fields'=>['code'=>['label'=>'Kode','type'=>'text','required'=>true],'name'=>['label'=>'Nama Gudang','type'=>'text','required'=>true],'type'=>['label'=>'Tipe','type'=>'text'],'address'=>['label'=>'Alamat','type'=>'textarea']]],
            'units'=>['title'=>'Satuan','table'=>'units','columns'=>['code','name'],'fields'=>['code'=>['label'=>'Kode','type'=>'text','required'=>true],'name'=>['label'=>'Nama Satuan','type'=>'text','required'=>true]]],
            'tariffs'=>['title'=>'Tarif','table'=>'tariffs','columns'=>['code','name','tariff_type','base_price','price_per_km','price_per_hour','minimum_charge'],'fields'=>['code'=>['label'=>'Kode','type'=>'text','required'=>true],'name'=>['label'=>'Nama Tarif','type'=>'text','required'=>true],'tariff_type'=>['label'=>'Jenis','type'=>'text','required'=>true],'base_price'=>['label'=>'Harga Dasar','type'=>'number','step'=>'0.01'],'price_per_km'=>['label'=>'Harga/KM','type'=>'number','step'=>'0.01'],'price_per_hour'=>['label'=>'Harga/Jam','type'=>'number','step'=>'0.01'],'minimum_charge'=>['label'=>'Minimum Charge','type'=>'number','step'=>'0.01']]],
            'vehicles'=>['title'=>'Kendaraan','table'=>'vehicles','columns'=>['code','plate_number','model','vehicle_type','capacity','current_km','status'],'fields'=>['code'=>['label'=>'Kode','type'=>'text','required'=>true],'plate_number'=>['label'=>'No. Polisi','type'=>'text','required'=>true],'model'=>['label'=>'Model','type'=>'text'],'vehicle_type'=>['label'=>'Jenis','type'=>'text'],'capacity'=>['label'=>'Kapasitas','type'=>'number','step'=>'0.001'],'current_km'=>['label'=>'KM Saat Ini','type'=>'number','step'=>'1'],'status'=>['label'=>'Status','type'=>'text']]],
            'drivers'=>['title'=>'Driver','table'=>'drivers','columns'=>['code','name','phone','license_no','license_expiry'],'fields'=>['code'=>['label'=>'Kode','type'=>'text','required'=>true],'name'=>['label'=>'Nama','type'=>'text','required'=>true],'phone'=>['label'=>'Telepon','type'=>'text'],'license_no'=>['label'=>'No. SIM','type'=>'text'],'license_expiry'=>['label'=>'Masa Berlaku SIM','type'=>'date']]],
        ][$type] ?? abort(404);
    }

    public function master(string $type)
    {
        $config = $this->masterConfig($type);
        $rows = DB::table($config['table'])->where('entity_id',$this->entityId())->latest('id')->paginate(15)->withQueryString();
        return view('erp.master', compact('config','rows','type'));
    }

    public function masterStore(Request $request, string $type)
    {
        $config = $this->masterConfig($type);
        $rules=[];
        foreach ($config['fields'] as $key=>$field) if (($field['required'] ?? false)) $rules[$key]=['required'];
        $data=$request->validate($rules);
        foreach ($config['fields'] as $key=>$field) if (!array_key_exists($key,$data)) $data[$key]=$request->input($key);
        $data['entity_id']=$this->entityId();
        if (Schema::hasColumn($config['table'], 'is_active')) {
            $data['is_active']=1;
        }
        $data['created_at']=now();
        $data['updated_at']=now();
        if ($type==='products') { $data['unit_id']=$data['unit_id']??null; $data['category_id']=$data['category_id']??null; }
        DB::table($config['table'])->insert($data);
        return back()->with('success',$config['title'].' berhasil disimpan.');
    }

    public function module(string $module)
    {
        $titles=['pos'=>'POS Retail','sales'=>'Transaksi Penjualan','payments'=>'Pembayaran','shifts'=>'Shift Kasir','purchases'=>'Pembelian','receipts'=>'Penerimaan Barang','payables'=>'Hutang','stock'=>'Stok','movements'=>'Mutasi Stok','opname'=>'Stock Opname','bom'=>'Formula / BOM','production'=>'Produksi Batako','production-results'=>'Hasil Produksi','material-usage'=>'Pemakaian Bahan','production-cost'=>'HPP Produksi','fleet'=>'Armada & Jasa','deliveries'=>'Pengiriman','operations'=>'Operasional Armada','fleet-costs'=>'Biaya Armada','journals'=>'Jurnal','ledger'=>'Buku Besar','receivables'=>'Piutang','cashbank'=>'Kas & Bank','cogs'=>'HPP','profit-loss'=>'Laba Rugi','balance-sheet'=>'Neraca','cash-flow'=>'Arus Kas'];
        abort_unless(isset($titles[$module]),404);
        $data=['module'=>$module,'title'=>$titles[$module]];
        $entity=$this->entityId();
        $map=['sales'=>'sales','payments'=>'payments','shifts'=>'cash_shifts','purchases'=>'purchases','receipts'=>'purchases','payables'=>'purchases','stock'=>'warehouses_stocks','movements'=>'stock_movements','opname'=>'stock_opnames','bom'=>'boms','production'=>'productions','production-results'=>'productions','material-usage'=>'productions','production-cost'=>'productions','fleet'=>'vehicles','deliveries'=>'deliveries','operations'=>'vehicle_operations','fleet-costs'=>'fleet_costs','journals'=>'journals'];
        $data['rows']=isset($map[$module]) ? DB::table($map[$module])->where('entity_id',$entity)->latest('id')->paginate(15)->withQueryString() : collect();
        $data['products']=DB::table('products')->where('entity_id',$entity)->where('is_active',1)->orderBy('name')->get();
        $data['warehouses']=DB::table('warehouses')->where('entity_id',$entity)->where('is_active',1)->orderBy('name')->get();
        $data['suppliers']=DB::table('suppliers')->where('entity_id',$entity)->where('is_active',1)->orderBy('name')->get();
        $data['vehicles']=DB::table('vehicles')->where('entity_id',$entity)->where('status','active')->orderBy('code')->get();
        $data['drivers']=DB::table('drivers')->where('entity_id',$entity)->where('is_active',1)->orderBy('name')->get();
        return view('erp.module',$data);
    }

    public function posStore(Request $request)
    {
        $data=$request->validate(['product_id'=>'required|integer','qty'=>'required|numeric|min:0.001','payment_method'=>'required|string']);
        $entity=$this->entityId(); $product=DB::table('products')->where('entity_id',$entity)->find($data['product_id']); abort_unless($product,404);
        $total=(float)$product->selling_price*(float)$data['qty'];
        DB::transaction(function() use($entity,$data,$product,$total){
            $no='POS-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
            $sale=DB::table('sales')->insertGetId(['entity_id'=>$entity,'user_id'=>auth()->id(),'invoice_no'=>$no,'sale_date'=>now(),'subtotal'=>$total,'total'=>$total,'status'=>'posted','created_at'=>now(),'updated_at'=>now()]);
            DB::table('sale_items')->insert(['sale_id'=>$sale,'product_id'=>$product->id,'qty'=>$data['qty'],'unit_price'=>$product->selling_price,'total'=>$total,'created_at'=>now(),'updated_at'=>now()]);
            DB::table('payments')->insert(['entity_id'=>$entity,'sale_id'=>$sale,'user_id'=>auth()->id(),'payment_date'=>now(),'method'=>$data['payment_method'],'amount'=>$total,'created_at'=>now(),'updated_at'=>now()]);
        });
        return back()->with('success','Transaksi POS berhasil disimpan.');
    }

    public function purchaseStore(Request $request)
    {
        $data=$request->validate(['supplier_id'=>'required|integer','product_id'=>'required|integer','warehouse_id'=>'required|integer','qty'=>'required|numeric|min:0.001','unit_cost'=>'required|numeric|min:0']); $entity=$this->entityId();
        abort_unless(DB::table('suppliers')->where('entity_id',$entity)->where('is_active',1)->where('id',$data['supplier_id'])->exists(),422,'Supplier tidak valid.');
        abort_unless(DB::table('products')->where('entity_id',$entity)->where('is_active',1)->where('id',$data['product_id'])->exists(),422,'Produk tidak valid.');
        abort_unless(DB::table('warehouses')->where('entity_id',$entity)->where('is_active',1)->where('id',$data['warehouse_id'])->exists(),422,'Gudang tidak valid.');
        $total=$data['qty']*$data['unit_cost'];
        DB::transaction(function() use($data,$entity,$total){
            $no='PO-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)); $id=DB::table('purchases')->insertGetId(['entity_id'=>$entity,'supplier_id'=>$data['supplier_id'],'user_id'=>auth()->id(),'purchase_no'=>$no,'purchase_date'=>now(),'subtotal'=>$total,'total'=>$total,'status'=>'received','created_at'=>now(),'updated_at'=>now()]);
            DB::table('purchase_items')->insert(['purchase_id'=>$id,'product_id'=>$data['product_id'],'qty'=>$data['qty'],'unit_cost'=>$data['unit_cost'],'total'=>$total,'created_at'=>now(),'updated_at'=>now()]);
            $stock=DB::table('warehouses_stocks')->where(['warehouse_id'=>$data['warehouse_id'],'product_id'=>$data['product_id']])->first();
            if($stock) DB::table('warehouses_stocks')->where('id',$stock->id)->update(['qty'=>$stock->qty+$data['qty'],'avg_cost'=>$data['unit_cost'],'updated_at'=>now()]); else DB::table('warehouses_stocks')->insert(['entity_id'=>$entity,'warehouse_id'=>$data['warehouse_id'],'product_id'=>$data['product_id'],'qty'=>$data['qty'],'avg_cost'=>$data['unit_cost'],'created_at'=>now(),'updated_at'=>now()]);
            DB::table('stock_movements')->insert(['entity_id'=>$entity,'warehouse_id'=>$data['warehouse_id'],'product_id'=>$data['product_id'],'movement_type'=>'purchase_in','qty'=>$data['qty'],'unit_cost'=>$data['unit_cost'],'reference_type'=>'purchase','reference_id'=>$id,'occurred_at'=>now(),'created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
        }); return back()->with('success','Pembelian dan penerimaan stok berhasil.');
    }

    public function productionStore(Request $request)
    {
        $data=$request->validate(['bom_id'=>'required|integer','warehouse_id'=>'required|integer','qty'=>'required|numeric|min:0.001']); $entity=$this->entityId();
        DB::transaction(function() use($data,$entity){
            $bom=DB::table('boms')->where('entity_id',$entity)->find($data['bom_id']); abort_unless($bom,404);
            $id=DB::table('productions')->insertGetId(['entity_id'=>$entity,'warehouse_id'=>$data['warehouse_id'],'bom_id'=>$bom->id,'user_id'=>auth()->id(),'production_no'=>'PROD-'.now()->format('YmdHis'),'production_date'=>now(),'qty'=>$data['qty'],'status'=>'posted','created_at'=>now(),'updated_at'=>now()]);
            $items=DB::table('bom_items')->where('bom_id',$bom->id)->get(); $cost=0;
            foreach($items as $item){ $stock=DB::table('warehouses_stocks')->where(['warehouse_id'=>$data['warehouse_id'],'product_id'=>$item->product_id])->first(); $need=$item->qty*$data['qty']; abort_unless($stock && $stock->qty >= $need,422,'Stok bahan baku tidak cukup.'); $cost += $need*$stock->avg_cost; DB::table('warehouses_stocks')->where('id',$stock->id)->update(['qty'=>$stock->qty-$need,'updated_at'=>now()]); DB::table('stock_movements')->insert(['entity_id'=>$entity,'warehouse_id'=>$data['warehouse_id'],'product_id'=>$item->product_id,'movement_type'=>'production_out','qty'=>-$need,'unit_cost'=>$stock->avg_cost,'reference_type'=>'production','reference_id'=>$id,'occurred_at'=>now(),'created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]); }
            $output=$bom->output_qty*$data['qty']; $stock=DB::table('warehouses_stocks')->where(['warehouse_id'=>$data['warehouse_id'],'product_id'=>$bom->product_id])->first(); if($stock) DB::table('warehouses_stocks')->where('id',$stock->id)->update(['qty'=>$stock->qty+$output,'avg_cost'=>$output?($cost/$output):0,'updated_at'=>now()]); else DB::table('warehouses_stocks')->insert(['entity_id'=>$entity,'warehouse_id'=>$data['warehouse_id'],'product_id'=>$bom->product_id,'qty'=>$output,'avg_cost'=>$output?($cost/$output):0,'created_at'=>now(),'updated_at'=>now()]); DB::table('stock_movements')->insert(['entity_id'=>$entity,'warehouse_id'=>$data['warehouse_id'],'product_id'=>$bom->product_id,'movement_type'=>'production_in','qty'=>$output,'unit_cost'=>$output?($cost/$output):0,'reference_type'=>'production','reference_id'=>$id,'occurred_at'=>now(),'created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]); DB::table('productions')->where('id',$id)->update(['total_cost'=>$cost,'updated_at'=>now()]);
        }); return back()->with('success','Produksi berhasil diposting dan stok diperbarui.');
    }

    public function deliveryStore(Request $request){$data=$request->validate(['vehicle_id'=>'nullable|integer','driver_id'=>'nullable|integer','destination'=>'required|string','distance_km'=>'nullable|numeric|min:0']); DB::table('deliveries')->insert(['entity_id'=>$this->entityId(),'vehicle_id'=>$data['vehicle_id']??null,'driver_id'=>$data['driver_id']??null,'delivery_no'=>'DO-'.now()->format('YmdHis').'-'.Str::upper(Str::random(3)),'delivery_date'=>now(),'destination'=>$data['destination'],'distance_km'=>$data['distance_km']??0,'status'=>'planned','created_at'=>now(),'updated_at'=>now()]); return back()->with('success','Pengiriman berhasil dibuat.');}
    public function journalStore(Request $request){$data=$request->validate(['description'=>'required|string','debit_account'=>'required|integer','credit_account'=>'required|integer','amount'=>'required|numeric|min:0.01']); DB::transaction(function()use($data){$j=DB::table('journals')->insertGetId(['entity_id'=>$this->entityId(),'journal_no'=>'JRN-'.now()->format('YmdHis').'-'.Str::upper(Str::random(3)),'journal_date'=>now()->toDateString(),'description'=>$data['description'],'status'=>'posted','created_at'=>now(),'updated_at'=>now()]); DB::table('journal_entries')->insert([['journal_id'=>$j,'account_id'=>$data['debit_account'],'debit'=>$data['amount'],'credit'=>0,'created_at'=>now(),'updated_at'=>now()],['journal_id'=>$j,'account_id'=>$data['credit_account'],'debit'=>0,'credit'=>$data['amount'],'created_at'=>now(),'updated_at'=>now()]]);}); return back()->with('success','Jurnal berhasil diposting.');}
}