<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SalesController extends Controller
{
    private function entityId(): int
    {
        return (int) (DB::table('entities')->value('id') ?? 1);
    }

    private function resolveUnit(int $productId, ?int $unitId): object
    {
        $product = DB::table('products')->where('id', $productId)->first(['base_unit_id']);
        abort_unless($product && $product->base_unit_id, 422, 'Item belum memiliki satuan dasar.');

        $unitId = $unitId ?: (int) $product->base_unit_id;
        if ($unitId === (int) $product->base_unit_id) {
            return (object) [
                'unit_id' => $unitId,
                'conversion_factor' => 1.0,
                'base_unit_id' => (int) $product->base_unit_id,
            ];
        }

        $conversion = DB::table('product_unit_conversions')
            ->where('product_id', $productId)
            ->where('unit_id', $unitId)
            ->where('is_active', 1)
            ->first();

        abort_unless($conversion, 422, 'Satuan transaksi belum dikonfigurasi untuk item ini.');

        return (object) [
            'unit_id' => (int) $conversion->unit_id,
            'conversion_factor' => (float) $conversion->conversion_factor,
            'base_unit_id' => (int) $product->base_unit_id,
        ];
    }

    public function index(Request $request)
    {
        $entity = $this->entityId();
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        $rows = DB::table('sales as s')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 's.business_unit_id')
            ->where('s.entity_id', $entity)
            ->whereBetween('s.sale_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->when($request->filled('customer'), fn ($q) => $q->where('c.name', 'like', '%' . $request->customer . '%'))
            ->when($request->filled('business_unit_id'), fn ($q) => $q->where('s.business_unit_id', $request->business_unit_id))
            ->when($request->filled('payment_method'), fn ($q) => $q->whereExists(function ($sub) use ($request) {
                $sub->select(DB::raw(1))->from('payments as fp')
                    ->whereColumn('fp.sale_id', 's.id')
                    ->where('fp.method', $request->payment_method);
            }))
            ->select('s.*', 'c.name as customer_name', 'bu.name as unit_name',
                DB::raw("(SELECT GROUP_CONCAT(DISTINCT p.method ORDER BY p.id SEPARATOR ', ') FROM payments p WHERE p.sale_id = s.id) as payment_methods"))
            ->orderByDesc('s.sale_date')->orderByDesc('s.id')
            ->paginate(10)->withQueryString();

        $units = DB::table('business_units')->where('entity_id', $entity)->where('is_active', 1)->orderBy('name')->get();

        return view('erp.sales.index', compact('rows', 'units'));
    }

    public function export(Request $request)
    {
        $entity = $this->entityId();
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        $rows = DB::table('sales as s')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 's.business_unit_id')
            ->where('s.entity_id', $entity)
            ->whereBetween('s.sale_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->when($request->filled('customer'), fn ($q) => $q->where('c.name', 'like', '%' . $request->customer . '%'))
            ->when($request->filled('business_unit_id'), fn ($q) => $q->where('s.business_unit_id', $request->business_unit_id))
            ->when($request->filled('payment_method'), fn ($q) => $q->whereExists(function ($sub) use ($request) {
                $sub->select(DB::raw(1))->from('payments as fp')
                    ->whereColumn('fp.sale_id', 's.id')
                    ->where('fp.method', $request->payment_method);
            }))
            ->select('s.invoice_no', 's.sale_date', DB::raw("COALESCE(c.name, 'Umum') as customer_name"),
                DB::raw("COALESCE(bu.name, '-') as unit_name"),
                DB::raw("(SELECT GROUP_CONCAT(DISTINCT p.method ORDER BY p.id SEPARATOR ', ') FROM payments p WHERE p.sale_id = s.id) as payment_methods"),
                's.due_date', 's.subtotal', 's.discount', 's.total', 's.status')
            ->orderByDesc('s.sale_date')->orderByDesc('s.id')->get();

        return response()->json([
            'entity_name' => DB::table('entities')->where('id', $entity)->value('name') ?? 'NAMA ENTITAS',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'rows' => $rows,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'nullable|integer',
            'business_unit_id' => 'required|integer',
            'warehouse_id' => 'required|integer',
            'payment_method' => 'required|in:Tunai,Transfer,QRIS,Kredit / Bon',
            'due_date' => 'nullable|date|required_if:payment_method,Kredit / Bon',
            'memo' => 'nullable|string|max:5000',
            'discount' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.unit_id' => 'nullable|integer',
            'items.*.qty' => 'required|numeric|gt:0',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
        ]);

        $entity = $this->entityId();
        $businessUnit = DB::table('business_units')
            ->where('id', $data['business_unit_id'])
            ->where('entity_id', $entity)
            ->where('is_active', 1)->first();
        $warehouse = DB::table('warehouses')
            ->where('id', $data['warehouse_id'])
            ->where('entity_id', $entity)
            ->where('business_unit_id', $data['business_unit_id'])
            ->where('is_active', 1)->first();

        abort_unless($businessUnit, 422, 'Unit Bisnis tidak valid.');
        abort_unless($warehouse, 422, 'Gudang tidak valid untuk Unit Bisnis tersebut.');

        if (!empty($data['customer_id'])) {
            $customer = DB::table('customers')->where('id', $data['customer_id'])
                ->where('entity_id', $entity)->where('is_active', 1)->first();
            abort_unless($customer, 422, 'Customer tidak valid.');
        }

        $invoiceNo = 'INV-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(4));

        $saleId = DB::transaction(function () use ($data, $entity, $invoiceNo) {
            $subtotal = 0;
            $lineItems = [];

            foreach ($data['items'] as $item) {
                $product = DB::table('products')
                    ->where('id', $item['product_id'])
                    ->where('entity_id', $entity)
                    ->where('is_active', 1)->first();

                abort_unless($product, 422, 'Item tidak valid.');
                abort_unless((bool) $product->manage_stock, 422, 'Item ' . $product->name . ' tidak dikelola sebagai stok.');

                $uom = $this->resolveUnit((int) $product->id, isset($item['unit_id']) ? (int) $item['unit_id'] : null);
                $qty = (float) $item['qty'];
                $price = (float) $item['unit_price'];
                $lineDiscount = min((float) ($item['discount'] ?? 0), $qty * $price);
                $lineTotal = round(($qty * $price) - $lineDiscount, 2);
                $baseQty = $qty * $uom->conversion_factor;

                $stock = DB::table('warehouses_stocks')
                    ->where('entity_id', $entity)
                    ->where('warehouse_id', $data['warehouse_id'])
                    ->where('product_id', $product->id)
                    ->lockForUpdate()->first();

                abort_unless($stock && (float) $stock->qty >= $baseQty - 0.0000001,
                    422, 'Stok ' . $product->name . ' tidak mencukupi.');

                $hppUnit = (float) $stock->avg_cost;
                $hppTotal = round($baseQty * $hppUnit, 2);

                $subtotal += $lineTotal;
                $lineItems[] = [
                    'product' => $product,
                    'uom' => $uom,
                    'qty' => $qty,
                    'base_qty' => $baseQty,
                    'price' => $price,
                    'discount' => $lineDiscount,
                    'total' => $lineTotal,
                    'stock' => $stock,
                    'hpp_unit' => $hppUnit,
                    'hpp_total' => $hppTotal,
                ];
            }

            $subtotal = round($subtotal, 2);
            $discount = round(min((float) ($data['discount'] ?? 0), $subtotal), 2);
            $total = round($subtotal - $discount, 2);

            $saleId = DB::table('sales')->insertGetId([
                'entity_id' => $entity,
                'business_unit_id' => $data['business_unit_id'],
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => auth()->id(),
                'shift_id' => null,
                'invoice_no' => $invoiceNo,
                'sale_date' => now(),
                'due_date' => $data['payment_method'] === 'Kredit / Bon' ? $data['due_date'] : null,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'status' => 'posted',
                'memo' => $data['memo'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($lineItems as $line) {
                DB::table('sale_items')->insert([
                    'sale_id' => $saleId,
                    'product_id' => $line['product']->id,
                    'transaction_unit_id' => $line['uom']->unit_id,
                    'conversion_factor' => $line['uom']->conversion_factor,
                    'base_qty' => $line['base_qty'],
                    'base_unit_id' => $line['uom']->base_unit_id,
                    'qty' => $line['qty'],
                    'unit_price' => $line['price'],
                    'discount' => $line['discount'],
                    'total' => $line['total'],
                    'hpp_unit' => $line['hpp_unit'],
                    'hpp_total' => $line['hpp_total'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('warehouses_stocks')->where('id', $line['stock']->id)
                    ->update(['qty' => (float)$line['stock']->qty - $line['base_qty'], 'updated_at' => now()]);

                DB::table('stock_movements')->insert([
                    'entity_id' => $entity,
                    'business_unit_id' => $data['business_unit_id'],
                    'warehouse_id' => $line['stock']->warehouse_id,
                    'product_id' => $line['product']->id,
                    'movement_type' => 'sale_out',
                    'qty' => -$line['base_qty'],
                    'unit_cost' => $line['hpp_unit'],
                    'reference_type' => 'sale',
                    'reference_id' => $saleId,
                    'occurred_at' => now(),
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('payments')->insert([
                'entity_id' => $entity,
                'business_unit_id' => $data['business_unit_id'],
                'sale_id' => $saleId,
                'user_id' => auth()->id(),
                'payment_date' => now(),
                'method' => $data['payment_method'] === 'Kredit / Bon' ? 'credit' : $data['payment_method'],
                'amount' => $total,
                'paid_amount' => $data['payment_method'] === 'Kredit / Bon' ? 0 : $total,
                'change_amount' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $saleId;
        });

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'sale_id' => $saleId, 'redirect' => route('inventori.penjualan.show', $saleId)]);
        }

        return redirect()->route('inventori.penjualan.show', $saleId)->with('success', 'Penjualan berhasil diposting.');
    }

    public function show(int $id)
    {
        $entity = $this->entityId();
        $sale = DB::table('sales as s')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 's.business_unit_id')
            ->where('s.entity_id', $entity)->where('s.id', $id)
            ->select('s.*', 'c.name as customer_name', 'c.phone as customer_phone', 'c.address as customer_address', 'bu.name as unit_name')
            ->first();

        abort_unless($sale, 404);

        $items = DB::table('sale_items as si')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'si.transaction_unit_id')
            ->leftJoin('units as bu', 'bu.id', '=', 'si.base_unit_id')
            ->where('si.sale_id', $sale->id)
            ->select('p.code','p.name','u.code as unit_code','bu.code as base_unit_code','si.qty','si.base_qty','si.conversion_factor','si.unit_price','si.discount','si.total','si.hpp_unit','si.hpp_total')
            ->get();

        $payments = DB::table('payments')->where('sale_id', $sale->id)->orderBy('id')->get();

        return view('erp.sales.show', compact('sale','items','payments'));
    }

    public function print(int $id)
    {
        return $this->show($id);
    }

    public function create()
    {
        $entity = $this->entityId();
        $units = DB::table('business_units')->where('entity_id',$entity)->where('is_active',1)->orderBy('name')->get();
        $warehouses = DB::table('warehouses')->where('entity_id',$entity)->where('is_active',1)->orderBy('name')->get();

        $customers = DB::table('customers as c')
            ->where('c.entity_id',$entity)->where('c.is_active',1)
            ->orderBy('c.name')->get(['c.id','c.name']);

        $products = DB::table('products as p')
            ->leftJoin('units as u','u.id','=','p.base_unit_id')
            ->where('p.entity_id',$entity)->where('p.is_active',1)->where('p.manage_stock',1)
            ->select('p.id','p.code','p.sku','p.name','p.selling_price','p.base_unit_id','u.code as base_unit_code')
            ->orderBy('p.name')->get();

        foreach ($products as $product) {
            $product->transaction_units = DB::table('units as u')
                ->where('u.id',$product->base_unit_id)
                ->get(['u.id','u.code','u.name'])
                ->map(fn($u) => [
                    'id'=>(int)$u->id,
                    'code'=>$u->code,
                    'name'=>$u->name,
                    'factor'=>1,
                    'price'=>(float)$product->selling_price,
                ])->values();

            $conversions = DB::table('product_unit_conversions as puc')
                ->join('units as u','u.id','=','puc.unit_id')
                ->where('puc.product_id',$product->id)
                ->where('puc.is_active',1)
                ->orderBy('u.name')->get(['u.id','u.code','u.name','puc.conversion_factor','puc.is_default_sale']);

            foreach ($conversions as $conversion) {
                $product->transaction_units->push([
                    'id'=>(int)$conversion->id,
                    'code'=>$conversion->code,
                    'name'=>$conversion->name,
                    'factor'=>(float)$conversion->conversion_factor,
                    'price'=>round((float)$product->selling_price*(float)$conversion->conversion_factor,2),
                    'default_sale'=>(bool)$conversion->is_default_sale,
                ]);
            }
        }

        return view('erp.sales.create',compact('units','warehouses','customers','products'));
    }
}
