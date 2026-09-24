<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InitialSetupController extends Controller
{
    private function entityId(): int
    {
        $entity = DB::table('entities')->first();
        abort_unless($entity, 500, 'Entitas belum tersedia.');
        return (int) $entity->id;
    }

    public function index(Request $request)
    {
        $entity = $this->entityId();
        $entityName = DB::table('entities')->where('id', $entity)->value('name') ?? 'NAMA ENTITAS';

        $rows = DB::table('products as p')
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->leftJoin('item_initial_setups as s', function ($join) use ($entity) {
                $join->on('s.product_id', '=', 'p.id')
                    ->where('s.entity_id', $entity);
            })
            ->where('p.entity_id', $entity)
            ->where('p.is_active', 1)
            ->select(
                'p.id',
                'p.code',
                'p.name',
                'p.item_type',
                's.selling_price',
                'u.code as unit_code',
                'u.name as unit_name',
                's.setup_date',
                's.purchase_price as initial_purchase_price',
                's.initial_stock',
                's.markup_percent'
            )
            ->orderBy('p.name')
            ->distinct()
            ->get();

        $retailUnitId = DB::table('business_units')
            ->where('entity_id', $entity)
            ->where('code', 'RET')
            ->where('is_active', 1)
            ->value('id');

        $products = DB::table('products as p')
            ->join('product_business_units as pu', 'pu.product_id', '=', 'p.id')
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->where('p.entity_id', $entity)
            ->where('p.is_active', 1)
            ->where('p.item_type', 'barang')
            ->whereNotExists(function ($q) use ($entity) {
                $q->select(DB::raw(1))
                    ->from('item_initial_setups as existing')
                    ->whereColumn('existing.product_id', 'p.id')
                    ->where('existing.entity_id', $entity);
            })
            ->when($retailUnitId, fn ($q) => $q->where('pu.business_unit_id', $retailUnitId))
            ->select('p.id', 'p.code', 'p.barcode', 'p.name', 'u.code as unit_code', 'u.name as unit_name')
            ->orderBy('p.name')
            ->distinct()
            ->get();

        return view('inventori.persediaan.initial-setup.index', compact('rows', 'products', 'entityName'));
    }

    public function storeInitial(Request $request)
    {
        $entity = $this->entityId();

        $data = $request->validate([
            'setup_date' => ['required', 'date'],
            'product_id' => ['required', 'integer'],
            'purchase_price' => ['required', 'numeric', 'gt:0'],
            'initial_stock' => ['required', 'numeric', 'gt:0'],
            'markup_percent' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'gt:0'],
        ]);

        $product = DB::table('products')->where('entity_id', $entity)->where('id', $data['product_id'])->where('is_active', 1)->first();
        abort_unless($product, 422, 'Item tidak valid.');

        $retailUnitId = DB::table('business_units')
            ->where('entity_id', $entity)
            ->where('code', 'RET')
            ->where('is_active', 1)
            ->value('id');

        abort_unless(
            $retailUnitId && DB::table('product_business_units')->where('product_id', $product->id)->where('business_unit_id', $retailUnitId)->exists(),
            422,
            'Item belum dipilih untuk Unit Retail.'
        );

        abort_if(
            DB::table('item_initial_setups')->where('entity_id', $entity)->where('product_id', $product->id)->exists(),
            422,
            'Setup awal item ini sudah ada. Setup awal tidak dapat diulang atau ditimpa.'
        );

        $warehouse = DB::table('warehouses')
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->orderBy('id')
            ->first();

        abort_unless($warehouse, 422, 'Belum ada gudang aktif untuk menerima stok awal.');

        DB::transaction(function () use ($entity, $data, $product, $warehouse): void {
            DB::table('item_initial_setups')->insert([
                'entity_id' => $entity,
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'setup_date' => $data['setup_date'],
                'purchase_price' => $data['purchase_price'],
                'initial_stock' => $data['initial_stock'],
                'markup_percent' => $data['markup_percent'] ?? 0,
                'selling_price' => $data['selling_price'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $stock = DB::table('warehouses_stocks')
                ->where('entity_id', $entity)
                ->where('warehouse_id', $warehouse->id)
                ->where('product_id', $product->id)
                ->first();

            if ($stock) {
                abort_unless((float) $stock->qty == 0.0, 422, 'Item sudah memiliki stok. Setup awal tidak dapat menambah stok ke stok yang sudah ada.');
                DB::table('warehouses_stocks')->where('id', $stock->id)->update([
                    'qty' => $data['initial_stock'],
                    'avg_cost' => $data['purchase_price'],
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('warehouses_stocks')->insert([
                    'entity_id' => $entity,
                    'warehouse_id' => $warehouse->id,
                    'product_id' => $product->id,
                    'qty' => $data['initial_stock'],
                    'avg_cost' => $data['purchase_price'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('stock_movements')->insert([
                'entity_id' => $entity,
                'business_unit_id' => $warehouse->business_unit_id,
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'unit_id' => $product->base_unit_id,
                'transaction_qty' => $data['initial_stock'],
                'conversion_factor' => 1,
                'movement_type' => 'opening',
                'qty' => $data['initial_stock'],
                'unit_cost' => $data['purchase_price'],
                'reference_type' => 'item_initial_setup',
                'reference_id' => $product->id,
                'occurred_at' => $data['setup_date'].' 00:00:00',
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Setup awal item berhasil disimpan.']);
    }
    public function update(Request $request, int $product)
    {
        $entity = $this->entityId();
        $data = $request->validate([
            'setup_date' => ['required','date'],
            'purchase_price' => ['required','numeric','gt:0'],
            'initial_stock' => ['required','numeric','min:0'],
            'markup_percent' => ['required','numeric','min:0'],
            'selling_price' => ['required','numeric','gt:0'],
        ]);
        $setup = DB::table('item_initial_setups')->where('entity_id',$entity)->where('product_id',$product)->first();
        abort_unless($setup, 404, 'Setup awal item belum ada.');
        $hasTransaction = DB::table('sale_items')->where('product_id',$product)->exists() || DB::table('purchase_items')->where('product_id',$product)->exists();
        abort_if($hasTransaction, 422, 'Data tidak dapat diedit karena item sudah memiliki transaksi Pembelian atau Penjualan.');
        DB::transaction(function () use ($entity,$product,$data,$setup): void {
            DB::table('item_initial_setups')->where('entity_id',$entity)->where('product_id',$product)->update(['setup_date'=>$data['setup_date'],'purchase_price'=>$data['purchase_price'],'initial_stock'=>$data['initial_stock'],'markup_percent'=>$data['markup_percent'],'selling_price'=>$data['selling_price'],'updated_at'=>now()]);
            $stock=DB::table('warehouses_stocks')->where('entity_id',$entity)->where('warehouse_id',$setup->warehouse_id)->where('product_id',$product)->first();
            if($stock){ $delta=(float)$data['initial_stock']-(float)$setup->initial_stock; DB::table('warehouses_stocks')->where('id',$stock->id)->update(['qty'=>(float)$stock->qty+$delta,'avg_cost'=>$data['purchase_price'],'updated_at'=>now()]); }
            DB::table('stock_movements')->where('entity_id',$entity)->where('product_id',$product)->where('reference_type','item_initial_setup')->where('reference_id',$product)->update(['qty'=>$data['initial_stock'],'unit_cost'=>$data['purchase_price'],'occurred_at'=>$data['setup_date'].' 00:00:00','updated_at'=>now()]);
        });
        return response()->json(['message'=>'Harga jual item berhasil diperbarui.']);
    }
}
