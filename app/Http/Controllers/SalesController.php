<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\SalesJournalService;

class SalesController extends Controller
{
    private function entityId(): int
    {
        return (int) (DB::table('entities')->value('id') ?? 1);
    }

    private function resolveSalePrice(int $productId, int $businessUnitId, int $unitId, int $entity): float
    {
        $price = DB::table('product_prices as pp')
            ->join('products as p', 'p.id', '=', 'pp.product_id')
            ->where('pp.product_id', $productId)
            ->where('pp.business_unit_id', $businessUnitId)
            ->where('pp.unit_id', $unitId)
            ->where('pp.price_type', 'retail')
            ->where('p.entity_id', $entity)
            ->where('p.is_active', 1)
            ->value('pp.selling_price');

        abort_unless($price !== null, 422, 'Harga jual item untuk Business Unit dan satuan tersebut belum tersedia.');

        return (float) $price;
    }

    private function resolveProductUnit(int $productId, ?int $unitId, int $entity): array
    {
        $product = DB::table('products')
            ->where('id', $productId)
            ->where('entity_id', $entity)
            ->first();

        abort_unless($product, 422, 'Item tidak valid.');

        $unitId = $unitId ?: (int) $product->base_unit_id;
        abort_unless($unitId > 0, 422, 'Satuan dasar item belum ditentukan.');

        if ($unitId === (int) $product->base_unit_id) {
            return ['unit_id' => $unitId, 'factor' => 1.0];
        }

        $conversion = DB::table('product_unit_conversions')
            ->where('product_id', $productId)
            ->where('unit_id', $unitId)
            ->where('is_active', 1)
            ->first();

        abort_unless($conversion && (float) $conversion->conversion_factor > 0, 422, 'Konversi satuan item tidak ditemukan atau tidak aktif.');

        return ['unit_id' => $unitId, 'factor' => (float) $conversion->conversion_factor];
    }

    public function index(Request $request)
    {
        $entity = $this->entityId();
        $startDate = $request->filled('start_date') ? $request->input('start_date') : now()->startOfMonth()->toDateString();
        $endDate = $request->filled('end_date') ? $request->input('end_date') : now()->endOfMonth()->toDateString();

        $rows = DB::table('sales as s')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 's.business_unit_id')
            ->where('s.entity_id', $entity)
            ->whereDate('s.sale_date', '>=', $startDate)
            ->whereDate('s.sale_date', '<=', $endDate)
            ->when($request->filled('customer'), fn ($q) => $q->where('c.name', 'like', '%'.$request->customer.'%'))
            ->when($request->filled('unit_id'), fn ($q) => $q->where('s.business_unit_id', $request->unit_id))
            ->when($request->filled('payment_method'), fn ($q) => $q->whereExists(function ($sub) use ($request) {
                $sub->select(DB::raw(1))
                    ->from('payments as fp')
                    ->whereColumn('fp.sale_id', 's.id')
                    ->where('fp.method', $request->payment_method);
            }))
            ->select(
                's.*',
                'c.name as customer_name',
                'bu.name as unit_name',
                DB::raw("(SELECT GROUP_CONCAT(DISTINCT p.method ORDER BY p.id SEPARATOR ', ') FROM payments p WHERE p.sale_id = s.id) as payment_methods")
            )
            ->orderByDesc('s.sale_date')
            ->orderByDesc('s.id')
            ->paginate(10)
            ->withQueryString();

        $units = DB::table('business_units')
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view('inventori.penjualan.tempo.index', compact('rows', 'units'));
    }

    public function report(Request $request)
    {
        $entity = $this->entityId();
        $startDate = $request->filled('start_date') ? $request->input('start_date') : now()->startOfMonth()->toDateString();
        $endDate = $request->filled('end_date') ? $request->input('end_date') : now()->endOfMonth()->toDateString();

        $rows = DB::table('sales as s')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 's.business_unit_id')
            ->where('s.entity_id', $entity)
            ->whereDate('s.sale_date', '>=', $startDate)
            ->whereDate('s.sale_date', '<=', $endDate)
            ->when($request->filled('customer'), fn ($q) => $q->where('c.name', 'like', '%'.$request->customer.'%'))
            ->when($request->filled('unit_id'), fn ($q) => $q->where('s.business_unit_id', $request->unit_id))
            ->when($request->filled('payment_method'), fn ($q) => $q->whereExists(function ($sub) use ($request) {
                $sub->select(DB::raw(1))->from('payments as fp')->whereColumn('fp.sale_id', 's.id')->where('fp.method', $request->payment_method);
            }))
            ->select('s.*', 'c.name as customer_name', 'bu.name as unit_name',
                DB::raw("(SELECT GROUP_CONCAT(DISTINCT p.method ORDER BY p.id SEPARATOR ', ') FROM payments p WHERE p.sale_id = s.id) as payment_methods"),
                DB::raw("(SELECT j.id FROM journals j WHERE j.entity_id = s.entity_id AND j.source_type = 'sale' AND j.source_id = s.id LIMIT 1) as journal_id")
            )
            ->orderByDesc('s.sale_date')->orderByDesc('s.id')->paginate(10)->withQueryString();

        $units = DB::table('business_units')->where('entity_id', $entity)->where('is_active', 1)->orderBy('name')->get();

        return view('inventori.laporan.penjualan', compact('rows', 'units', 'startDate', 'endDate'));
    }

    public function export(Request $request)
    {
        $entity = $this->entityId();
        $startDate = $request->filled('start_date') ? $request->input('start_date') : now()->startOfMonth()->toDateString();
        $endDate = $request->filled('end_date') ? $request->input('end_date') : now()->endOfMonth()->toDateString();

        $rows = DB::table('sales as s')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 's.business_unit_id')
            ->where('s.entity_id', $entity)
            ->whereDate('s.sale_date', '>=', $startDate)
            ->whereDate('s.sale_date', '<=', $endDate)
            ->when($request->filled('customer'), fn ($q) => $q->where('c.name', 'like', '%'.$request->customer.'%'))
            ->when($request->filled('unit_id'), fn ($q) => $q->where('s.business_unit_id', $request->unit_id))
            ->when($request->filled('payment_method'), fn ($q) => $q->whereExists(function ($sub) use ($request) {
                $sub->select(DB::raw(1))
                    ->from('payments as fp')
                    ->whereColumn('fp.sale_id', 's.id')
                    ->where('fp.method', $request->payment_method);
            }))
            ->select(
                's.invoice_no',
                's.sale_date',
                DB::raw("COALESCE(c.name, 'Umum') as customer_name"),
                DB::raw("COALESCE(bu.name, '-') as unit_name"),
                DB::raw("(SELECT GROUP_CONCAT(DISTINCT p.method ORDER BY p.id SEPARATOR ', ') FROM payments p WHERE p.sale_id = s.id) as payment_methods"),
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

    public function exportExcel(Request $request)
    {
        $entity = $this->entityId();
        $start = $request->input('start_date', now()->startOfMonth()->toDateString());
        $end = $request->input('end_date', now()->endOfMonth()->toDateString());

        abort_if($start > $end, 422, 'Periode tanggal tidak valid.');

        $rows = DB::table('sales as s')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 's.business_unit_id')
            ->where('s.entity_id', $entity)
            ->whereDate('s.sale_date', '>=', $start)
            ->whereDate('s.sale_date', '<=', $end)
            ->when($request->filled('customer'), fn ($q) => $q->where('c.name', 'like', '%'.$request->customer.'%'))
            ->when($request->filled('unit_id'), fn ($q) => $q->where('s.business_unit_id', $request->unit_id))
            ->when($request->filled('payment_method'), fn ($q) => $q->whereExists(function ($sub) use ($request) {
                $sub->select(DB::raw(1))->from('payments as fp')->whereColumn('fp.sale_id', 's.id')->where('fp.method', $request->payment_method);
            }))
            ->select(
                's.invoice_no', 's.sale_date', 's.due_date', 's.subtotal', 's.discount', 's.total', 's.status',
                DB::raw("COALESCE(c.name, 'Umum') as customer_name"),
                DB::raw("COALESCE(bu.name, '-') as unit_name"),
                DB::raw("(SELECT GROUP_CONCAT(DISTINCT p.method ORDER BY p.id SEPARATOR ', ') FROM payments p WHERE p.sale_id = s.id) as payment_methods")
            )
            ->orderBy('s.sale_date')->orderBy('s.id')->get();

        $entityName = DB::table('entities')->where('id', $entity)->value('name') ?? 'MINI ERP';
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $num = fn ($v) => (string) (float) $v;
        $methodLabel = fn ($v) => match ($v) {
            'cash' => 'Tunai', 'credit' => 'Kredit / Bon', 'transfer' => 'Transfer', 'qris' => 'QRIS', default => $v ?: '-',
        };

        $html = '<html><head><meta charset="UTF-8"><style>
            body{font-family:Arial,sans-serif}.kop{font-size:16px;font-weight:700}.title{font-size:14px;font-weight:700}
            table{border-collapse:collapse;margin-top:14px}th,td{border:1px solid #000;padding:5px}th{font-weight:700}.right{text-align:right}
        </style></head><body>';
        $html .= '<div class="kop">' . $e($entityName) . '</div>';
        $html .= '<div class="title">LAPORAN PENJUALAN</div>';
        $html .= '<div>Periode : ' . $e(date('d/m/Y', strtotime($start))) . ' s/d ' . $e(date('d/m/Y', strtotime($end))) . '</div>';
        $html .= '<div>Cetak Tanggal : ' . $e(now()->format('d/m/Y')) . '</div>';
        $html .= '<br><table><thead><tr>';
        foreach (['No. Penjualan','Tanggal','Customer','Unit','Cara Bayar','Jatuh Tempo','Subtotal','Diskon','Total','Status'] as $heading) {
            $html .= '<th>' . $e($heading) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $r) {
            $html .= '<tr><td>'.$e($r->invoice_no).'</td><td>'.$e(date('d/m/Y', strtotime($r->sale_date))).'</td><td>'.$e($r->customer_name).'</td><td>'.$e($r->unit_name).'</td><td>'.$e($methodLabel($r->payment_methods)).'</td><td>'.(!empty($r->due_date) ? $e(date('d/m/Y', strtotime($r->due_date))) : '-').'</td>';
            foreach ([$r->subtotal, $r->discount, $r->total] as $value) { $v=$num($value); $html .= '<td class="right" x:num="'.$e($v).'">'.$e($v).'</td>'; }
            $html .= '<td>'.$e($r->status ?? '-').'</td></tr>';
        }
        $html .= '</tbody></table></body></html>';

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laporan-penjualan_'.$start.'_'.$end.'.xls"',
        ]);
    }

    public function postJournal(int $id)
    {
        $journalId = app(SalesJournalService::class)->post($id, $this->entityId());

        return back()->with('success', 'Jurnal penjualan berhasil diposting.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'business_unit_id' => ['required', 'integer'],
            'payment_method' => ['required', 'in:Tunai,Transfer,QRIS,Kredit / Bon'],
            'due_date' => ['nullable', 'date', 'required_if:payment_method,Kredit / Bon'],
            'memo' => ['nullable', 'string', 'max:5000'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.unit_id' => ['nullable', 'integer'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.selling_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $entity = $this->entityId();

        $unit = DB::table('business_units')
            ->where('id', $data['business_unit_id'])
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->first();
        abort_unless($unit, 422, 'Business Unit tidak valid.');

        if (!empty($data['customer_id'])) {
            $customer = DB::table('customers')
                ->where('id', $data['customer_id'])
                ->where('entity_id', $entity)
                ->where('is_active', 1)
                ->first();
            abort_unless($customer, 422, 'Customer tidak valid.');
        }

        $invoiceNo = 'INV-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));

        $saleId = DB::transaction(function () use ($data, $entity, $unit, $invoiceNo): int {
            $subtotal = 0.0;
            $lineItems = [];

            foreach ($data['items'] as $item) {
                $product = DB::table('products')
                    ->where('id', $item['product_id'])
                    ->where('entity_id', $entity)
                    ->where('is_active', 1)
                    ->first();
                abort_unless($product, 422, 'Item tidak valid.');

                $uom = $this->resolveProductUnit(
                    $product->id,
                    isset($item['unit_id']) ? (int) $item['unit_id'] : null,
                    $entity
                );

                $transactionQty = (float) $item['qty'];
                $transactionPrice = $this->resolveSalePrice(
                    $product->id,
                    (int) $unit->id,
                    $uom['unit_id'],
                    $entity
                );
                $baseQty = round($transactionQty * $uom['factor'], 9);
                $lineDiscount = min((float) ($item['discount'] ?? 0), $transactionQty * $transactionPrice);
                $lineTotal = round(($transactionQty * $transactionPrice) - $lineDiscount, 2);

                $stock = DB::table('warehouses_stocks as ws')
                    ->join('warehouses as w', 'w.id', '=', 'ws.warehouse_id')
                    ->where('ws.entity_id', $entity)
                    ->where('ws.product_id', $product->id)
                    ->where('w.business_unit_id', $unit->id)
                    ->lockForUpdate()
                    ->select('ws.*')
                    ->first();

                abort_unless($stock && (float) $stock->qty >= $baseQty, 422, 'Stok '.$product->name.' tidak mencukupi.');

                $hppUnit = (float) $stock->avg_cost;
                $hppTotal = round($baseQty * $hppUnit, 2);

                $subtotal += $lineTotal;

                $lineItems[] = [
                    'product' => $product,
                    'uom' => $uom,
                    'qty' => $transactionQty,
                    'base_qty' => $baseQty,
                    'price' => $transactionPrice,
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
                'business_unit_id' => $unit->id,
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => auth()->id(),
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
                    'unit_id' => $line['uom']['unit_id'],
                    'qty' => $line['qty'],
                    'conversion_factor' => $line['uom']['factor'],
                    'base_qty' => $line['base_qty'],
                    'unit_price' => $line['price'],
                    'base_unit_cost' => $line['hpp_unit'],
                    'discount' => $line['discount'],
                    'total' => $line['total'],
                    'hpp_unit' => $line['hpp_unit'],
                    'hpp_total' => $line['hpp_total'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('warehouses_stocks')->where('id', $line['stock']->id)->update([
                    'qty' => (float) $line['stock']->qty - $line['base_qty'],
                    'updated_at' => now(),
                ]);

                DB::table('stock_movements')->insert([
                    'entity_id' => $entity,
                    'business_unit_id' => $unit->id,
                    'warehouse_id' => $line['stock']->warehouse_id,
                    'product_id' => $line['product']->id,
                    'unit_id' => $line['uom']['unit_id'],
                    'transaction_qty' => $line['qty'],
                    'conversion_factor' => $line['uom']['factor'],
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

            if ($data['payment_method'] === 'Kredit / Bon') {
                DB::table('payments')->insert([
                    'entity_id' => $entity,
                    'business_unit_id' => $unit->id,
                    'sale_id' => $saleId,
                    'user_id' => auth()->id(),
                    'payment_date' => now(),
                    'method' => 'credit',
                    'amount' => 0,
                    'paid_amount' => 0,
                    'change_amount' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('payments')->insert([
                    'entity_id' => $entity,
                    'business_unit_id' => $unit->id,
                    'sale_id' => $saleId,
                    'user_id' => auth()->id(),
                    'payment_date' => now(),
                    'method' => match ($data['payment_method']) {
                        'Tunai' => 'cash',
                        'Transfer' => 'transfer',
                        'QRIS' => 'qris',
                        default => throw new \RuntimeException('Metode pembayaran tidak valid.'),
                    },
                    'amount' => $total,
                    'paid_amount' => $total,
                    'change_amount' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            app(SalesJournalService::class)->post($saleId, $entity);

            return $saleId;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'sale_id' => $saleId,
                'redirect' => route('inventori.penjualan.show', $saleId),
            ]);
        }

        return redirect()->route('inventori.penjualan.show', $saleId)->with('success', 'Penjualan berhasil diposting.');
    }

    public function show($id)
    {
        $id = (int) $id;
        $entity = $this->entityId();

        $sale = DB::table('sales as s')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 's.business_unit_id')
            ->where('s.entity_id', $entity)
            ->where('s.id', $id)
            ->select('s.*', 'c.name as customer_name', 'c.phone as customer_phone', 'c.address as customer_address', 'bu.name as unit_name')
            ->first();

        abort_unless($sale, 404);

        $items = DB::table('sale_items as si')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'si.unit_id')
            ->leftJoin('units as bu', 'bu.id', '=', 'p.base_unit_id')
            ->where('si.sale_id', $sale->id)
            ->select(
                'p.code',
                'p.name',
                'u.code as transaction_unit_code',
                'bu.code as base_unit_code',
                'si.qty',
                'si.conversion_factor',
                'si.base_qty',
                'si.unit_price',
                'si.discount',
                'si.total',
                'si.hpp_unit',
                'si.hpp_total'
            )
            ->get();

        $payments = DB::table('payments')->where('sale_id', $sale->id)->orderBy('id')->get();

        $previousReceivable = 0;
        if ($sale->customer_id) {
            $previousReceivable = (float) (
                DB::table('sales as ps')
                    ->where('ps.entity_id', $entity)
                    ->where('ps.customer_id', $sale->customer_id)
                    ->where('ps.id', '<', $sale->id)
                    ->whereExists(function ($q) {
                        $q->select(DB::raw(1))
                            ->from('payments as cp')
                            ->whereColumn('cp.sale_id', 'ps.id')
                            ->where('cp.method', 'credit');
                    })
                    ->leftJoin(DB::raw('(SELECT sale_id, SUM(COALESCE(paid_amount,0)) paid_amount FROM payments GROUP BY sale_id) pp'), 'pp.sale_id', '=', 'ps.id')
                    ->sum(DB::raw('GREATEST(ps.total - COALESCE(pp.paid_amount,0),0)'))
            );
        }

        return view('inventori.penjualan.tempo.show', compact('sale', 'items', 'payments', 'previousReceivable'));
    }

    public function print(int $id)
    {
        return $this->show($id);
    }

    public function create()
    {
        $entity = $this->entityId();
        $user = auth()->user();

        $units = DB::table('business_units as bu')
            ->join('user_business_units as ubu', 'ubu.business_unit_id', '=', 'bu.id')
            ->where('ubu.user_id', $user->id)
            ->where('bu.entity_id', $entity)
            ->where('bu.is_active', 1)
            ->orderBy('bu.name')
            ->get(['bu.id', 'bu.code', 'bu.name']);

        $defaultUnitId = $user->default_business_unit_id;
        if ($defaultUnitId && !$units->contains('id', (int) $defaultUnitId)) {
            $defaultUnitId = $units->first()->id ?? null;
        }

        $customers = DB::table('customers as c')
            ->where('c.entity_id', $entity)
            ->where('c.is_active', 1)
            ->leftJoin(DB::raw('(SELECT s.customer_id, SUM(GREATEST(s.total - COALESCE(p.paid_amount,0),0)) AS outstanding
                FROM sales s
                LEFT JOIN (SELECT sale_id, SUM(COALESCE(paid_amount,0)) paid_amount FROM payments GROUP BY sale_id) p ON p.sale_id=s.id
                WHERE s.entity_id='.$entity.' AND s.customer_id IS NOT NULL
                AND EXISTS (SELECT 1 FROM payments cp WHERE cp.sale_id=s.id AND cp.method="credit")
                GROUP BY s.customer_id) ob'), 'ob.customer_id', '=', 'c.id')
            ->orderBy('c.name')
            ->get(['c.id', 'c.name', DB::raw('COALESCE(ob.outstanding,0) as outstanding')]);

        $products = DB::table('products as p')
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->where('p.entity_id', $entity)
            ->where('p.is_active', 1)
            ->select('p.id', 'p.code', 'p.sku', 'p.barcode', 'p.name', 'p.base_unit_id', 'u.code as base_unit_code', 'u.name as base_unit_name')
            ->orderBy('p.name')
            ->get();

        $productPrices = DB::table('product_prices')
            ->whereIn('product_id', $products->pluck('id'))
            ->whereIn('business_unit_id', $units->pluck('id'))
            ->where('price_type', 'retail')
            ->get(['product_id', 'business_unit_id', 'unit_id', 'selling_price'])
            ->groupBy('product_id');

        $productConversions = DB::table('product_unit_conversions as puc')
            ->join('units as u', 'u.id', '=', 'puc.unit_id')
            ->whereIn('puc.product_id', $products->pluck('id'))
            ->where('puc.is_active', 1)
            ->orderBy('u.name')
            ->get(['puc.product_id', 'puc.unit_id', 'puc.conversion_factor', 'puc.is_default_sale', 'u.code', 'u.name'])
            ->groupBy('product_id');

        $productCatalog = $products->map(function ($product) use ($productPrices, $productConversions) {
            return [
                'id' => (int) $product->id,
                'code' => $product->code,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'base_unit_id' => (int) $product->base_unit_id,
                'base_unit_code' => $product->base_unit_code,
                'base_unit_name' => $product->base_unit_name,
                'prices' => ($productPrices[$product->id] ?? collect())->values(),
                'conversions' => ($productConversions[$product->id] ?? collect())->values(),
            ];
        })->values();

        return view('inventori.penjualan.tempo.create', compact(
            'units',
            'defaultUnitId',
            'customers',
            'productCatalog'
        ));
    }
}
