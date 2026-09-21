<?php

namespace App\Http\Controllers;

use App\Services\Inventory\InventoryCostEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class SalesController extends Controller
{
    public function __construct(
        protected InventoryCostEngine $inventoryCost,
    ) {}

    private function entityId(): int
    {
        return (int) (DB::table('entities')->value('id') ?? 0);
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
            ->whereDate('s.sale_date', '>=', $startDate)
            ->whereDate('s.sale_date', '<=', $endDate)
            ->when($request->filled('customer'), fn ($q) => $q->where('c.name', 'like', '%'.$request->customer.'%'))
            ->when($request->filled('business_unit_id'), fn ($q) => $q->where('s.business_unit_id', $request->business_unit_id))
            ->when($request->filled('unit_id'), fn ($q) => $q->where('s.business_unit_id', $request->unit_id))
            ->when($request->filled('payment_method'), fn ($q) => $q->whereExists(function ($sub) use ($request) {
                $sub->select(DB::raw(1))->from('payments as fp')->whereColumn('fp.sale_id', 's.id')->where('fp.method', $request->payment_method);
            }))
            ->select('s.*', 'c.name as customer_name', 'bu.name as unit_name')
            ->orderByDesc('s.sale_date')
            ->orderByDesc('s.id')
            ->paginate(10)
            ->withQueryString();

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
            ->whereDate('s.sale_date', '>=', $startDate)
            ->whereDate('s.sale_date', '<=', $endDate)
            ->when($request->filled('customer'), fn ($q) => $q->where('c.name', 'like', '%'.$request->customer.'%'))
            ->when($request->filled('business_unit_id'), fn ($q) => $q->where('s.business_unit_id', $request->business_unit_id))
            ->when($request->filled('unit_id'), fn ($q) => $q->where('s.business_unit_id', $request->unit_id))
            ->when($request->filled('payment_method'), fn ($q) => $q->whereExists(function ($sub) use ($request) {
                $sub->select(DB::raw(1))->from('payments as fp')->whereColumn('fp.sale_id', 's.id')->where('fp.method', $request->payment_method);
            }))
            ->select(
                's.invoice_no',
                's.sale_date',
                DB::raw("COALESCE(c.name, 'Umum') as customer_name"),
                DB::raw("COALESCE(bu.name, '-') as unit_name"),
                's.due_date',
                's.subtotal',
                's.discount',
                's.total',
                's.status'
            )
            ->orderByDesc('s.sale_date')
            ->orderByDesc('s.id')
            ->get();

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
            'customer_id' => ['nullable','integer'],
            'business_unit_id' => ['nullable','integer'],
            'unit_id' => ['nullable','integer'],
            'warehouse_id' => ['nullable','integer'],
            'payment_method' => ['required','in:Tunai,Transfer,QRIS,Kredit / Bon'],
            'due_date' => ['nullable','date','required_if:payment_method,Kredit / Bon'],
            'memo' => ['nullable','string','max:5000'],
            'discount' => ['nullable','numeric','min:0'],
            'items' => ['required','array','min:1'],
            'items.*.product_id' => ['required','integer'],
            'items.*.qty' => ['required','numeric','gt:0'],
            'items.*.unit_price' => ['required','numeric','min:0'],
            'items.*.discount' => ['nullable','numeric','min:0'],
        ]);

        $entity = $this->entityId();
        $businessUnitId = (int) ($data['business_unit_id'] ?? $data['unit_id'] ?? 0);
        abort_if($businessUnitId <= 0, 422, 'Unit Bisnis wajib dipilih.');

        $unit = DB::table('business_units')
            ->where('id', $businessUnitId)->where('entity_id', $entity)->where('is_active', 1)->first();
        abort_unless($unit, 422, 'Unit Bisnis tidak valid.');

        $warehouse = DB::table('warehouses')
            ->where('entity_id', $entity)
            ->where('business_unit_id', $businessUnitId)
            ->where('is_active', 1)
            ->when(!empty($data['warehouse_id']), fn ($q) => $q->where('id', $data['warehouse_id']))
            ->orderBy('id')
            ->first();
        abort_unless($warehouse, 422, 'Gudang aktif untuk Unit Bisnis belum tersedia.');

        if (!empty($data['customer_id'])) {
            abort_unless(
                DB::table('customers')->where('id', $data['customer_id'])->where('entity_id', $entity)->where('is_active', 1)->exists(),
                422,
                'Customer tidak valid.'
            );
        }

        $invoiceNo = 'INV-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));

        try {
            $saleId = DB::transaction(function () use ($data, $entity, $businessUnitId, $warehouse, $invoiceNo) {
                $subtotal = 0;
                $lineItems = [];

                foreach ($data['items'] as $item) {
                    $product = DB::table('products')
                        ->where('id', $item['product_id'])
                        ->where('entity_id', $entity)
                        ->where('is_active', 1)
                        ->first();

                    abort_unless($product, 422, 'Item tidak valid.');

                    $qty = (float) $item['qty'];
                    $price = (float) $item['unit_price'];
                    $lineDiscount = min((float) ($item['discount'] ?? 0), $qty * $price);
                    $lineTotal = round(($qty * $price) - $lineDiscount, 2);
                    $subtotal += $lineTotal;

                    $lineItems[] = [
                        'product' => $product,
                        'qty' => $qty,
                        'price' => $price,
                        'discount' => $lineDiscount,
                        'total' => $lineTotal,
                    ];
                }

                $subtotal = round($subtotal, 2);
                $discount = round(min((float) ($data['discount'] ?? 0), $subtotal), 2);
                $total = round($subtotal - $discount, 2);

                $saleId = DB::table('sales')->insertGetId([
                    'entity_id' => $entity,
                    'business_unit_id' => $businessUnitId,
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
                    $cost = $this->inventoryCost->issue(
                        $entity,
                        $businessUnitId,
                        (int) $warehouse->id,
                        (int) $line['product']->id,
                        $line['qty'],
                        'sale_out',
                        'sale',
                        $saleId,
                        auth()->id(),
                    );

                    DB::table('sale_items')->insert([
                        'sale_id' => $saleId,
                        'product_id' => $line['product']->id,
                        'qty' => $line['qty'],
                        'unit_price' => $line['price'],
                        'discount' => $line['discount'],
                        'total' => $line['total'],
                        'hpp_unit' => $cost['unit_cost'],
                        'hpp_total' => $cost['total_cost'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('payments')->insert([
                    'entity_id' => $entity,
                    'business_unit_id' => $businessUnitId,
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
            }, 3);
        } catch (Throwable $e) {
            abort(422, $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json(['ok'=>true,'sale_id'=>$saleId,'redirect'=>route('inventori.penjualan.show',$saleId)]);
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
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->where('si.sale_id', $sale->id)
            ->select('p.code','p.name','u.code as unit_code','si.qty','si.unit_price','si.discount','si.total','si.hpp_unit','si.hpp_total')
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
        $customers = DB::table('customers')->where('entity_id',$entity)->where('is_active',1)->orderBy('name')->get(['id','name']);
        $products = DB::table('products as p')
            ->leftJoin('units as u','u.id','=','p.base_unit_id')
            ->where('p.entity_id',$entity)->where('p.is_active',1)
            ->select('p.id','p.code','p.sku','p.name','p.selling_price','u.code as selling_unit_code')
            ->orderBy('p.name')->get();

        return view('erp.sales.create', compact('units','warehouses','customers','products'));
    }
}
