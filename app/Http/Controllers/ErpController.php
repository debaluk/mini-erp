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
            'customers'=>[
                'title'=>'Customer','table'=>'customers',
                'columns'=>['code','name','customer_type','phone','address','is_active'],
                'column_labels'=>['code'=>'Kode Customer','name'=>'Nama Customer','customer_type'=>'Jenis Customer','phone'=>'No. Telepon','address'=>'Alamat','is_active'=>'Status'],
                'fields'=>[
                    'code'=>['label'=>'Kode Customer','type'=>'text','readonly'=>true],
                    'name'=>['label'=>'Nama Customer','type'=>'text','required'=>true],
                    'customer_type'=>['label'=>'Jenis Customer','type'=>'select','required'=>true,'options'=>['umum'=>'Umum','proyek'=>'Proyek','perusahaan'=>'Perusahaan']],
                    'phone'=>['label'=>'No. Telepon','type'=>'text'],
                    'address'=>['label'=>'Alamat','type'=>'textarea'],
                    'is_active'=>['label'=>'Status','type'=>'select','options'=>['1'=>'Aktif','0'=>'Nonaktif']]
                ]
            ],
            'suppliers'=>[
                'title'=>'Supplier','table'=>'suppliers',
                'columns'=>['code','name','category','phone','whatsapp','email','website','address','country','is_active'],
                'column_labels'=>['code'=>'Kode Supplier','name'=>'Nama Supplier','category'=>'Kategori','phone'=>'No. Telepon','whatsapp'=>'WhatsApp','email'=>'Email','website'=>'Website','address'=>'Alamat','country'=>'Negara','is_active'=>'Status'],
                'fields'=>[
                    'code'=>['label'=>'Kode Supplier','type'=>'text','readonly'=>true],
                    'name'=>['label'=>'Nama Supplier','type'=>'text','required'=>true],
                    'category'=>['label'=>'Kategori','type'=>'text'],
                    'phone'=>['label'=>'No. Telepon','type'=>'text'],
                    'whatsapp'=>['label'=>'WhatsApp','type'=>'text'],
                    'email'=>['label'=>'Email','type'=>'email'],
                    'website'=>['label'=>'Website','type'=>'url'],
                    'address'=>['label'=>'Alamat','type'=>'textarea'],
                    'country'=>['label'=>'Negara','type'=>'text'],
                    'is_active'=>['label'=>'Status','type'=>'select','options'=>['1'=>'Aktif','0'=>'Nonaktif']]
                ]
            ],
            'warehouses'=>['title'=>'Gudang','table'=>'warehouses','columns'=>['code','name','type','address'],'fields'=>['code'=>['label'=>'Kode','type'=>'text','required'=>true],'name'=>['label'=>'Nama Gudang','type'=>'text','required'=>true],'type'=>['label'=>'Tipe','type'=>'text'],'address'=>['label'=>'Alamat','type'=>'textarea']]],
            'units'=>['title'=>'Satuan','table'=>'units','columns'=>['code','name'],'fields'=>['code'=>['label'=>'Kode','type'=>'text','required'=>true],'name'=>['label'=>'Nama Satuan','type'=>'text','required'=>true]]],
            'tariffs'=>['title'=>'Tarif','table'=>'tariffs','columns'=>['code','name','tariff_type','base_price','price_per_km','price_per_hour','minimum_charge'],'fields'=>['code'=>['label'=>'Kode','type'=>'text','required'=>true],'name'=>['label'=>'Nama Tarif','type'=>'text','required'=>true],'tariff_type'=>['label'=>'Jenis','type'=>'text','required'=>true],'base_price'=>['label'=>'Harga Dasar','type'=>'number','step'=>'0.01'],'price_per_km'=>['label'=>'Harga/KM','type'=>'number','step'=>'0.01'],'price_per_hour'=>['label'=>'Harga/Jam','type'=>'number','step'=>'0.01'],'minimum_charge'=>['label'=>'Minimum Charge','type'=>'number','step'=>'0.01']]],
            'vehicles'=>['title'=>'Kendaraan','table'=>'vehicles','columns'=>['code','plate_number','model','vehicle_type','capacity','current_km','status'],'fields'=>['code'=>['label'=>'Kode','type'=>'text','required'=>true],'plate_number'=>['label'=>'No. Polisi','type'=>'text','required'=>true],'model'=>['label'=>'Model','type'=>'text'],'vehicle_type'=>['label'=>'Jenis','type'=>'text'],'capacity'=>['label'=>'Kapasitas','type'=>'number','step'=>'0.001'],'current_km'=>['label'=>'KM Saat Ini','type'=>'number','step'=>'1'],'status'=>['label'=>'Status','type'=>'text']]],
            'drivers'=>['title'=>'Driver','table'=>'drivers','columns'=>['code','name','phone','license_no','license_expiry'],'fields'=>['code'=>['label'=>'Kode','type'=>'text','required'=>true],'name'=>['label'=>'Nama','type'=>'text','required'=>true],'phone'=>['label'=>'Telepon','type'=>'text'],'license_no'=>['label'=>'No. SIM','type'=>'text'],'license_expiry'=>['label'=>'Masa Berlaku SIM','type'=>'date']]],
        ][$type] ?? abort(404);
    }

    public function itemMaster(Request $request)
    {
        $entity = $this->entityId();

        $units = DB::table('units')
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $businessUnits = DB::table('business_units')
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->orderBy('id')
            ->get();

        $nextCodes = [];
        foreach (['barang' => 'BRG', 'jasa' => 'JSA', 'aset' => 'AST'] as $itemType => $prefix) {
            $lastNumber = DB::table('products')
                ->where('entity_id', $entity)
                ->where('code', 'like', $prefix.'-%')
                ->get(['code'])
                ->map(function ($row) use ($prefix) {
                    return preg_match('/^'.preg_quote($prefix, '/').'-(\\d+)$/', $row->code, $m) ? (int) $m[1] : 0;
                })
                ->max() ?? 0;
            $nextCodes[$itemType] = $prefix.'-'.str_pad((string) ($lastNumber + 1), 5, '0', STR_PAD_LEFT);
        }

        $items = DB::table('products')
            ->leftJoin('units as base_units', 'base_units.id', '=', 'products.base_unit_id')
            ->when($request->filled('business_unit_id'), function ($query) use ($request) {
                $query->join('product_units', function ($join) {
                    $join->on('product_units.product_id', '=', 'products.id');
                })->where('product_units.business_unit_id', (int) $request->business_unit_id);
            })
            ->where('products.entity_id', $entity)
            ->select(
                'products.id',
                'products.code',
                'products.barcode',
                'products.name',
                'products.item_type',
                'products.minimum_stock',
                'products.manage_stock',
                'products.is_active',
                'base_units.name as base_unit_name'
            )
            ->orderByDesc('products.id')
            ->distinct()
            ->get();

        if ($request->ajax()) {
            return response()->json([
                'data' => $items->map(fn ($item) => [
                    'id' => $item->id,
                    'code' => $item->code,
                    'barcode' => $item->barcode,
                    'name' => $item->name,
                    'item_type' => $item->item_type,
                    'base_unit_name' => $item->base_unit_name,
                    'minimum_stock' => $item->minimum_stock,
                    'manage_stock' => (bool) $item->manage_stock,
                    'is_active' => (bool) $item->is_active,
                ]),
            ]);
        }

        return view('erp.master-item', compact('items', 'units', 'businessUnits'));
    }

    public function itemCreate()
    {
        $entity = $this->entityId();
        $units = DB::table('units')
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();
        $businessUnits = DB::table('business_units')
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->orderBy('id')
            ->get();

        return view('erp.master-item-create', compact('units', 'businessUnits'));
    }

    public function itemInlineUomStore(Request $request)
    {
        $entity = $this->entityId();
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100'],
        ]);
        $code = trim($data['code']);
        $name = trim($data['name']);
        abort_if(DB::table('units')->where('entity_id', $entity)->where('code', $code)->exists(), 422, 'Kode satuan sudah digunakan.');
        $id = DB::table('units')->insertGetId([
            'entity_id' => $entity,
            'code' => $code,
            'name' => $name,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json(['message' => 'Satuan berhasil ditambahkan.', 'unit' => ['id' => $id, 'code' => $code, 'name' => $name]]);
    }

    public function itemInlineBusinessUnitStore(Request $request)
    {
        $entity = $this->entityId();
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100'],
        ]);
        $code = trim($data['code']);
        $name = trim($data['name']);
        abort_if(DB::table('business_units')->where('entity_id', $entity)->where('code', $code)->exists(), 422, 'Kode Unit sudah digunakan.');
        $id = DB::table('business_units')->insertGetId([
            'entity_id' => $entity,
            'code' => $code,
            'name' => $name,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json(['message' => 'Unit berhasil ditambahkan.', 'business_unit' => ['id' => $id, 'code' => $code, 'name' => $name]]);
    }

    public function itemStore(Request $request)
    {
        $entity = $this->entityId();

        $data = $request->validate([
            'item_type' => ['required', 'in:barang,jasa,aset'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'business_unit_ids' => ['required', 'array', 'min:1'],
            'business_unit_ids.*' => ['integer'],
            'base_unit_id' => ['required', 'integer'],
            'manage_stock' => ['required', 'boolean'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'boolean'],
            'conversion_unit_id' => ['nullable', 'array'],
            'conversion_unit_id.*' => ['nullable', 'integer'],
            'conversion_factor' => ['nullable', 'array'],
            'conversion_factor.*' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $unitIds = array_values(array_unique(array_map('intval', $data['business_unit_ids'])));
        $validBusinessUnits = DB::table('business_units')
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->whereIn('id', $unitIds)
            ->count();
        abort_unless($validBusinessUnits === count($unitIds), 422, 'Unit tidak valid.');

        abort_unless(
            DB::table('units')->where('entity_id', $entity)->where('is_active', 1)->where('id', $data['base_unit_id'])->exists(),
            422,
            'Satuan dasar tidak valid.'
        );

        $barcode = trim((string) ($data['barcode'] ?? ''));
        if ($barcode !== '') {
            abort_if(
                DB::table('products')->where('entity_id', $entity)->where('barcode', $barcode)->exists(),
                422,
                'Barcode sudah digunakan oleh item lain.'
            );
        } else {
            $barcode = null;
        }

        $prefix = match ($data['item_type']) {
            'barang' => 'BRG',
            'jasa' => 'JSA',
            'aset' => 'AST',
        };
        $last = DB::table('products')
            ->where('entity_id', $entity)
            ->where('code', 'like', $prefix.'-%')
            ->orderByDesc('id')
            ->pluck('code')
            ->first();
        $number = 1;
        if ($last && preg_match('/-(\\d+)$/', $last, $match)) {
            $number = ((int) $match[1]) + 1;
        }
        do {
            $code = $prefix.'-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
            $number++;
        } while (DB::table('products')->where('entity_id', $entity)->where('code', $code)->exists());

        $legacyType = match ($data['item_type']) {
            'barang' => 'merchandise',
            'jasa' => 'service',
            'aset' => 'asset',
        };
        $minimumStock = (float) ($data['minimum_stock'] ?? 0);
        if (!(bool) $data['manage_stock']) {
            $minimumStock = 0;
        }

        $conversionUnits = $data['conversion_unit_id'] ?? [];
        $conversionFactors = $data['conversion_factor'] ?? [];

        DB::transaction(function () use ($entity, $data, $unitIds, $barcode, $code, $legacyType, $minimumStock, $conversionUnits, $conversionFactors): void {
            $productId = DB::table('products')->insertGetId([
                'entity_id' => $entity,
                'code' => $code,
                'sku' => $code,
                'barcode' => $barcode,
                'name' => $data['name'],
                'item_type' => $data['item_type'],
                'type' => $legacyType,
                'unit_id' => $data['base_unit_id'],
                'base_unit_id' => $data['base_unit_id'],
                'minimum_stock' => $minimumStock,
                'manage_stock' => (bool) $data['manage_stock'],
                'is_active' => (bool) $data['status'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($unitIds as $businessUnitId) {
                DB::table('product_units')->insert([
                    'product_id' => $productId,
                    'business_unit_id' => $businessUnitId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($conversionUnits as $index => $conversionUnitId) {
                if (!$conversionUnitId || empty($conversionFactors[$index])) {
                    continue;
                }
                if ((int) $conversionUnitId === (int) $data['base_unit_id']) {
                    continue;
                }
                DB::table('unit_conversions')->insert([
                    'product_id' => $productId,
                    'unit_id' => $conversionUnitId,
                    'conversion_factor' => $conversionFactors[$index],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Item berhasil disimpan.']);
        }

        return redirect()->route('master.menu.produk')->with('success', 'Item berhasil disimpan.');
    }

    public function itemDelete(Request $request, int $id)
    {
        $entity = $this->entityId();
        $item = DB::table('products')->where('entity_id', $entity)->find($id);
        abort_unless($item, 404);

        $hasTransactions = DB::table('stock_movements')->where('product_id', $id)->exists()
            || DB::table('purchase_items')->where('product_id', $id)->exists()
            || DB::table('sale_items')->where('product_id', $id)->exists();
        abort_if($hasTransactions, 422, 'Item sudah digunakan dalam transaksi dan tidak dapat dihapus. Nonaktifkan item jika tidak digunakan lagi.');

        DB::transaction(function () use ($id, $entity): void {
            DB::table('unit_conversions')->where('product_id', $id)->delete();
            DB::table('product_units')->where('product_id', $id)->delete();
            DB::table('products')->where('entity_id', $entity)->where('id', $id)->delete();
        });

        return response()->json(['message' => 'Item berhasil dihapus.']);
    }

    public function itemEdit(Request $request, int $id)
    {
        $entity = $this->entityId();
        $item = DB::table('products')->where('entity_id', $entity)->find($id);
        abort_unless($item, 404);
        $units = DB::table('units')->where('entity_id', $entity)->where('is_active', 1)->orderBy('name')->get();
        $businessUnits = DB::table('business_units')->where('entity_id', $entity)->where('is_active', 1)->orderBy('id')->get();
        $selectedBusinessUnits = DB::table('product_units')->where('product_id', $id)->pluck('business_unit_id')->all();
        $conversions = DB::table('unit_conversions')->where('product_id', $id)->get();

        if ($request->ajax()) {
            return response()->json([
                'item' => $item,
                'selected_business_units' => $selectedBusinessUnits,
                'conversions' => $conversions,
            ]);
        }

        return view('erp.master-item-edit', compact('item', 'units', 'businessUnits', 'selectedBusinessUnits', 'conversions'));
    }

    public function itemUpdate(Request $request, int $id)
    {
        $entity = $this->entityId();
        $item = DB::table('products')->where('entity_id', $entity)->find($id);
        abort_unless($item, 404);
        $data = $request->validate([
            'barcode' => ['nullable', 'string', 'max:100'], 'name' => ['required', 'string', 'max:255'],
            'business_unit_ids' => ['required', 'array', 'min:1'], 'business_unit_ids.*' => ['integer'],
            'base_unit_id' => ['required', 'integer'], 'manage_stock' => ['required', 'boolean'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0'], 'status' => ['required', 'boolean'],
            'conversion_unit_id' => ['nullable', 'array'], 'conversion_unit_id.*' => ['nullable', 'integer'],
            'conversion_factor' => ['nullable', 'array'], 'conversion_factor.*' => ['nullable', 'numeric', 'gt:0'],
        ]);
        $unitIds = array_values(array_unique(array_map('intval', $data['business_unit_ids'])));
        abort_unless(DB::table('business_units')->where('entity_id',$entity)->where('is_active',1)->whereIn('id',$unitIds)->count() === count($unitIds),422,'Unit tidak valid.');
        abort_unless(DB::table('units')->where('entity_id',$entity)->where('is_active',1)->where('id',$data['base_unit_id'])->exists(),422,'Satuan dasar tidak valid.');
        $barcode = trim((string)($data['barcode'] ?? '')) ?: null;
        if ($barcode !== null) abort_if(DB::table('products')->where('entity_id',$entity)->where('barcode',$barcode)->where('id','<>',$id)->exists(),422,'Barcode sudah digunakan oleh item lain.');
        $minimumStock = (float)($data['minimum_stock'] ?? 0);
        if (!(bool)$data['manage_stock']) $minimumStock=0;
        $conversionUnits=$data['conversion_unit_id']??[]; $conversionFactors=$data['conversion_factor']??[];
        DB::transaction(function() use($entity,$id,$data,$unitIds,$barcode,$minimumStock,$conversionUnits,$conversionFactors){
            DB::table('products')->where('entity_id',$entity)->where('id',$id)->update(['barcode'=>$barcode,'name'=>$data['name'],'unit_id'=>$data['base_unit_id'],'base_unit_id'=>$data['base_unit_id'],'minimum_stock'=>$minimumStock,'manage_stock'=>(bool)$data['manage_stock'],'is_active'=>(bool)$data['status'],'updated_at'=>now()]);
            DB::table('product_units')->where('product_id',$id)->delete();
            foreach($unitIds as $businessUnitId) DB::table('product_units')->insert(['product_id'=>$id,'business_unit_id'=>$businessUnitId,'created_at'=>now(),'updated_at'=>now()]);
            DB::table('unit_conversions')->where('product_id',$id)->delete();
            foreach($conversionUnits as $index=>$conversionUnitId){ if(!$conversionUnitId || empty($conversionFactors[$index]) || (int)$conversionUnitId===(int)$data['base_unit_id']) continue; DB::table('unit_conversions')->insert(['product_id'=>$id,'unit_id'=>$conversionUnitId,'conversion_factor'=>$conversionFactors[$index],'created_at'=>now(),'updated_at'=>now()]); }
        });
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Item berhasil diperbarui.']);
        }

        return redirect()->route('master.menu.produk')->with('success','Item berhasil diperbarui.');
    }



    public function unitMaster(Request $request)
    {
        $entity = $this->entityId();

        if ($request->ajax() && $request->has('draw')) {
            $query = DB::table('units')
                ->where('entity_id', $entity);

            $search = trim((string) $request->input('search.value', ''));
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%');
                });
            }

            $total = DB::table('units')->where('entity_id', $entity)->count();
            $filtered = $query->count();

            $rows = $query
                ->orderByDesc('id')
                ->offset(max(0, (int) $request->input('start', 0)))
                ->limit((int) $request->input('length', 15) > 0 ? (int) $request->input('length', 15) : 15)
                ->get();

            return response()->json([
                'draw' => (int) $request->input('draw'),
                'recordsTotal' => $total,
                'recordsFiltered' => $filtered,
                'data' => $rows,
            ]);
        }

        return view('erp.master-unit');
    }

    public function unitStore(Request $request)
    {
        $entity = $this->entityId();
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ]);

        $code = trim($data['code']);
        $name = trim($data['name']);

        abort_if(
            DB::table('units')->where('entity_id', $entity)->where('code', $code)->exists(),
            422,
            'Kode satuan sudah digunakan.'
        );

        $id = DB::table('units')->insertGetId([
            'entity_id' => $entity,
            'code' => $code,
            'name' => $name,
            'is_active' => (int) $data['is_active'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Satuan berhasil disimpan.',
            'unit' => ['id' => $id, 'code' => $code, 'name' => $name, 'is_active' => (int) $data['is_active']],
        ]);
    }

    public function unitUpdate(Request $request, int $id)
    {
        $entity = $this->entityId();
        $unit = DB::table('units')->where('entity_id', $entity)->where('id', $id)->first();
        abort_unless($unit, 404, 'Satuan tidak ditemukan.');

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ]);

        $code = trim($data['code']);
        $name = trim($data['name']);

        abort_if(
            DB::table('units')
                ->where('entity_id', $entity)
                ->where('code', $code)
                ->where('id', '<>', $id)
                ->exists(),
            422,
            'Kode satuan sudah digunakan.'
        );

        DB::table('units')
            ->where('entity_id', $entity)
            ->where('id', $id)
            ->update([
                'code' => $code,
                'name' => $name,
                'is_active' => (int) $data['is_active'],
                'updated_at' => now(),
            ]);

        return response()->json(['message' => 'Satuan berhasil diperbarui.']);
    }

    public function unitDelete(Request $request, int $id)
    {
        $entity = $this->entityId();
        $unit = DB::table('units')->where('entity_id', $entity)->where('id', $id)->first();
        abort_unless($unit, 404, 'Satuan tidak ditemukan.');

        $inUse = DB::table('products')->where('entity_id', $entity)->where(function ($q) use ($id) {
            $q->where('base_unit_id', $id)->orWhere('unit_id', $id);
        })->exists()
            || DB::table('unit_conversions')->where('unit_id', $id)->exists();

        abort_if($inUse, 422, 'Satuan sudah digunakan oleh Item atau konversi dan tidak dapat dihapus. Nonaktifkan satuan jika tidak digunakan lagi.');

        DB::table('units')->where('entity_id', $entity)->where('id', $id)->delete();

        return response()->json(['message' => 'Satuan berhasil dihapus.']);
    }

    public function master(Request $request, string $type)
    {
        $config = $this->masterConfig($type);
        $entity = $this->entityId();

        if ($request->ajax() && $request->has('draw')) {
            $columns = $config['columns'];
            $query = DB::table($config['table'])->where('entity_id', $entity);

            $search = trim((string) $request->input('search.value', ''));
            if ($search !== '') {
                $query->where(function ($q) use ($columns, $search) {
                    foreach ($columns as $column) {
                        $q->orWhere($column, 'like', '%'.$search.'%');
                    }
                });
            }

            $total = DB::table($config['table'])->where('entity_id', $entity)->count();
            $filtered = $query->count();

            $orderIndex = (int) $request->input('order.0.column', 0);
            $orderDir = strtolower((string) $request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';
            $orderColumn = $columns[$orderIndex] ?? 'id';

            $length = (int) $request->input('length', 15);
            $start = max(0, (int) $request->input('start', 0));

            $rows = $query->orderBy($orderColumn, $orderDir)
                ->orderBy('id', 'desc')
                ->offset($start)
                ->limit($length > 0 ? $length : 15)
                ->get();

            return response()->json([
                'draw' => (int) $request->input('draw'),
                'recordsTotal' => $total,
                'recordsFiltered' => $filtered,
                'data' => $rows,
            ]);
        }

        $rows = DB::table($config['table'])->where('entity_id',$entity)->latest('id')->paginate(15)->withQueryString();
        return view('erp.master', compact('config','rows','type'));
    }

    public function masterStore(Request $request, string $type)
    {
        $config = $this->masterConfig($type);
        $rules=[];
        foreach ($config['fields'] as $key=>$field) if (($field['required'] ?? false)) $rules[$key]=['required'];
        $data=$request->validate($rules);
        foreach ($config['fields'] as $key=>$field) {
            if (array_key_exists($key, $data)) continue;
            $value = $request->input($key);
            if ($value !== null && $value !== '') $data[$key] = $value;
        }
        $entity = $this->entityId();
        if (in_array($type, ['customers','suppliers'], true)) {
            $prefix = $type === 'customers' ? 'CUS' : 'SUP';
            $lastNumber = DB::table($config['table'])->where('entity_id', $entity)->where('code', 'like', $prefix.'-%')->get(['code'])
                ->map(fn ($row) => preg_match('/^'.preg_quote($prefix, '/').'-(\\d+)$/', $row->code, $m) ? (int) $m[1] : 0)->max() ?? 0;
            $data['code'] = $prefix.'-'.str_pad((string) ($lastNumber + 1), 5, '0', STR_PAD_LEFT);
            $data['is_active'] = array_key_exists('is_active', $data) ? (int) $data['is_active'] : 1;
            if ($type === 'customers') $data['credit_limit'] = 0;
        }
        $data['entity_id']=$entity;
        if (Schema::hasColumn($config['table'], 'is_active') && !array_key_exists('is_active', $data)) $data['is_active']=1;
        $data['created_at']=now();
        $data['updated_at']=now();
        if ($type==='products') { $data['unit_id']=$data['unit_id']??null; $data['category_id']=$data['category_id']??null; }
        DB::table($config['table'])->insert($data);
        return $request->expectsJson()
            ? response()->json(['message'=>$config['title'].' berhasil disimpan.'])
            : back()->with('success',$config['title'].' berhasil disimpan.');
    }

    public function masterUpdate(Request $request, string $type, int $id)
    {
        $config = $this->masterConfig($type);
        $rules=[];
        foreach ($config['fields'] as $key=>$field) if (($field['required'] ?? false)) $rules[$key]=['required'];
        $data=$request->validate($rules);
        foreach ($config['fields'] as $key=>$field) {
            if (array_key_exists($key, $data)) continue;
            $value = $request->input($key);
            if ($value !== null && $value !== '') $data[$key] = $value;
        }
        unset($data['entity_id']);
        if (in_array($type, ['customers','suppliers'], true)) {
            unset($data['code']);
            $data['is_active'] = array_key_exists('is_active', $data) ? (int) $data['is_active'] : 1;
            if ($type === 'customers') $data['credit_limit'] = 0;
        }
        $data['updated_at']=now();
        DB::table($config['table'])->where('entity_id',$this->entityId())->where('id',$id)->update($data);
        return $request->expectsJson()
            ? response()->json(['message'=>$config['title'].' berhasil diperbarui.'])
            : back()->with('success',$config['title'].' berhasil diperbarui.');
    }

    public function masterDelete(Request $request, string $type, int $id)
    {
        $config = $this->masterConfig($type);
        try {
            $deleted=DB::table($config['table'])
                ->where('entity_id',$this->entityId())
                ->where('id',$id)
                ->delete();
            abort_unless($deleted,404,'Data tidak ditemukan.');
        } catch (\Throwable $e) {
            return $request->expectsJson()
                ? response()->json(['message'=>'Data tidak dapat dihapus karena sudah digunakan oleh transaksi/data lain.'],422)
                : back()->withErrors(['delete'=>'Data tidak dapat dihapus karena sudah digunakan oleh transaksi/data lain.']);
        }
        return $request->expectsJson()
            ? response()->json(['message'=>$config['title'].' berhasil dihapus.'])
            : back()->with('success',$config['title'].' berhasil dihapus.');
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