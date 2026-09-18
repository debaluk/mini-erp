<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ModuleController extends Controller
{
    private function entityId(): int
    {
        $entity = DB::table('entities')->first();
        if ($entity) return $entity->id;
        return DB::table('entities')->insertGetId([
            'code' => 'ENT-001', 'name' => 'Entitas Utama', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function base(string $module, string $title): array
    {
        $entity = $this->entityId();
        return [
            'module' => $module,
            'title' => $title,
            'products' => DB::table('products as p')
                ->where('p.entity_id', $entity)
                ->where('p.is_active', 1)
                ->leftJoin('product_units as pu', function ($join) {
                    $join->on('pu.product_id', '=', 'p.id')
                        ->where('pu.is_default', 1);
                })
                ->leftJoin('units as u', 'u.id', '=', 'pu.unit_id')
                ->leftJoin(DB::raw('(SELECT product_id, SUM(qty) AS stock_qty FROM warehouses_stocks GROUP BY product_id) AS ws'), 'ws.product_id', '=', 'p.id')
                ->orderBy('p.name')
                ->select(
                    'p.*',
                    'pu.unit_id as selling_unit_id',
                    'u.code as selling_unit_code',
                    'u.name as selling_unit_name',
                    DB::raw('COALESCE(ws.stock_qty, 0) as stock_qty')
                )
                ->get(),
            'warehouses' => DB::table('warehouses')->where('entity_id', $entity)->where('is_active', 1)->orderBy('name')->get(),
            'vehicles' => DB::table('vehicles')->where('entity_id', $entity)->where('status', 'active')->orderBy('code')->get(),
            'drivers' => DB::table('drivers')->where('entity_id', $entity)->where('is_active', 1)->orderBy('name')->get(),
            'suppliers' => DB::table('suppliers')->where('entity_id', $entity)->where('is_active', 1)->orderBy('name')->get(),
            'customers' => DB::table('customers')->where('entity_id', $entity)->where('is_active', 1)->orderBy('name')->get(),
            'accounts' => DB::table('chart_of_accounts')->where('entity_id', $entity)->where('is_active', 1)->orderBy('code')->get(),
            'rows' => collect(),
            'posCart' => session('pos_cart', []),
            'openShift' => DB::table('cash_shifts')->where('entity_id', $entity)->where('user_id', auth()->id())->where('status', 'open')->latest('id')->first(),
        ];
    }

    public function show(string $module)
    {
        $titles = [
            'pos'=>'POS Retail', 'sales'=>'Transaksi Penjualan', 'payments'=>'Pembayaran', 'shifts'=>'Shift Kasir',
            'purchases'=>'Pembelian', 'receipts'=>'Penerimaan Barang', 'payables'=>'Hutang',
            'stock'=>'Stok', 'movements'=>'Mutasi Stok', 'opname'=>'Stock Opname',
            'bom'=>'Formula / BOM', 'production'=>'Produksi Batako', 'production-results'=>'Hasil Produksi',
            'material-usage'=>'Pemakaian Bahan', 'production-cost'=>'HPP Produksi',
            'fleet'=>'Kendaraan', 'deliveries'=>'Pengiriman', 'operations'=>'Operasional Armada', 'fleet-costs'=>'Biaya Armada',
            'journals'=>'Jurnal', 'ledger'=>'Buku Besar', 'receivables'=>'Piutang', 'cashbank'=>'Kas & Bank',
            'cogs'=>'HPP', 'profit-loss'=>'Laba Rugi', 'balance-sheet'=>'Neraca', 'cash-flow'=>'Arus Kas',
        ];
        abort_unless(isset($titles[$module]), 404);
        $data = $this->base($module, $titles[$module]);
        $entity = $this->entityId();

        $queries = [
            'sales' => DB::table('sales as s')
                ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
                ->leftJoin('users as u', 'u.id', '=', 's.user_id')
                ->where('s.entity_id', $entity)
                ->whereBetween('s.sale_date', [
                    request()->input('start_date', now()->startOfMonth()->toDateString()) . ' 00:00:00',
                    request()->input('end_date', now()->endOfMonth()->toDateString()) . ' 23:59:59',
                ])
                ->select(
                    's.id', 's.invoice_no', 's.sale_date', 's.subtotal', 's.discount', 's.total', 's.status',
                    's.shift_id',
                    DB::raw("COALESCE(c.name, 'Umum') as customer_name"),
                    DB::raw("COALESCE(u.name, '-') as cashier_name"),
                    DB::raw("(SELECT GROUP_CONCAT(DISTINCT p.method ORDER BY p.id SEPARATOR ', ') FROM payments p WHERE p.sale_id = s.id) as payment_methods"),
                    DB::raw("(SELECT COALESCE(SUM(p.paid_amount), SUM(p.amount), 0) FROM payments p WHERE p.sale_id = s.id) as paid_amount"),
                    DB::raw("(SELECT COALESCE(SUM(p.change_amount), 0) FROM payments p WHERE p.sale_id = s.id) as change_amount")
                )
                ->orderByDesc('s.id')
                ->paginate(15)
                ->withQueryString(),
            'payments' => DB::table('payments')->where('entity_id',$entity)->latest('id')->paginate(15)->withQueryString(),
            'shifts' => DB::table('cash_shifts')->where('entity_id',$entity)->latest('id')->paginate(15)->withQueryString(),
            'purchases' => DB::table('purchases')->where('entity_id',$entity)->latest('id')->paginate(15)->withQueryString(),
            'receipts' => DB::table('purchases')->where('entity_id',$entity)->where('status','received')->latest('id')->paginate(15)->withQueryString(),
            'payables' => DB::table('purchases')->where('entity_id',$entity)->latest('id')->paginate(15)->withQueryString(),
            'stock' => DB::table('warehouses_stocks')->where('entity_id',$entity)->latest('id')->paginate(20)->withQueryString(),
            'movements' => DB::table('stock_movements')->where('entity_id',$entity)->latest('id')->paginate(20)->withQueryString(),
            'opname' => DB::table('stock_opnames')->where('entity_id',$entity)->latest('id')->paginate(15)->withQueryString(),
            'bom' => DB::table('boms')->where('entity_id',$entity)->latest('id')->paginate(15)->withQueryString(),
            'production' => DB::table('productions')->where('entity_id',$entity)->latest('id')->paginate(15)->withQueryString(),
            'production-results' => DB::table('productions')->where('entity_id',$entity)->latest('id')->paginate(15)->withQueryString(),
            'material-usage' => DB::table('stock_movements')->where('entity_id',$entity)->where('movement_type','production_out')->latest('id')->paginate(20)->withQueryString(),
            'production-cost' => DB::table('productions')->where('entity_id',$entity)->latest('id')->paginate(15)->withQueryString(),
            'fleet' => DB::table('vehicles')->where('entity_id',$entity)->latest('id')->paginate(15)->withQueryString(),
            'deliveries' => DB::table('deliveries')->where('entity_id',$entity)->latest('id')->paginate(15)->withQueryString(),
            'operations' => DB::table('vehicle_operations')->where('entity_id',$entity)->latest('id')->paginate(15)->withQueryString(),
            'fleet-costs' => DB::table('fleet_costs')->where('entity_id',$entity)->latest('id')->paginate(15)->withQueryString(),
            'journals' => DB::table('journals')->where('entity_id',$entity)->latest('id')->paginate(15)->withQueryString(),
        ];
        $data['rows'] = $queries[$module] ?? collect();

        if ($module === 'sales') {
            $saleIds = collect($data['rows']->items())->pluck('id')->all();
            $data['saleDetails'] = [];

            if ($saleIds) {
                $items = DB::table('sale_items as si')
                    ->join('products as p', 'p.id', '=', 'si.product_id')
                    ->whereIn('si.sale_id', $saleIds)
                    ->select('si.sale_id', 'p.sku', 'p.name', 'si.qty', 'si.unit_price', 'si.discount', 'si.total')
                    ->orderBy('si.id')
                    ->get()
                    ->groupBy('sale_id');

                foreach ($saleIds as $saleId) {
                    $data['saleDetails'][$saleId] = $items->get($saleId, collect());
                }
            }
        }

        if ($module === 'bom') {
            $data['boms'] = DB::table('boms')->where('entity_id',$entity)->latest('id')->get();
        }
        if (in_array($module, ['ledger','receivables','cashbank','cogs','profit-loss','balance-sheet','cash-flow'], true)) {
            $data['report'] = $this->report($module, $entity);
        }
        if ($module === 'pos') {
            $data['openShift'] = DB::table('cash_shifts')->where('entity_id',$entity)->where('user_id',auth()->id())->where('status','open')->latest('id')->first();
            $data['posCart'] = request()->session()->get('pos_cart', []);
            $data['posSubtotal'] = 0;
            foreach ($data['posCart'] as $item) {
                $data['posSubtotal'] += (float) $item['price'] * (float) $item['qty'];
            }
            $data['posTotal'] = $data['posSubtotal'];
        }

        if ($module === 'shifts') {
            $data['openShift'] = DB::table('cash_shifts')->where('entity_id',$entity)->where('user_id',auth()->id())->where('status','open')->latest('id')->first();
        }
        return $module === 'pos' ? view('erp.pos-page', $data) : view('erp.module', $data);
    }

    private function report(string $module, int $entity): array
    {
        $result = ['lines'=>[], 'total'=>0];
        if ($module === 'ledger') {
            $result['lines'] = DB::table('journal_entries as e')->join('journals as j','j.id','=','e.journal_id')->join('chart_of_accounts as a','a.id','=','e.account_id')->where('j.entity_id',$entity)->orderBy('j.journal_date')->orderBy('e.id')->select('j.journal_date','j.journal_no','j.description','a.code','a.name','e.debit','e.credit')->get();
        } elseif ($module === 'receivables') {
            $result['lines'] = DB::table('sales')->where('entity_id',$entity)->orderByDesc('sale_date')->get();
            $result['total'] = (float) DB::table('sales')->where('entity_id',$entity)->sum('total');
        } elseif ($module === 'cashbank') {
            $result['lines'] = DB::table('payments')->where('entity_id',$entity)->orderByDesc('payment_date')->get();
            $result['total'] = (float) DB::table('payments')->where('entity_id',$entity)->sum('amount');
        } elseif ($module === 'cogs') {
            $result['lines'] = DB::table('productions')->where('entity_id',$entity)->orderByDesc('production_date')->get();
            $result['total'] = (float) DB::table('productions')->where('entity_id',$entity)->sum('total_cost');
        } elseif ($module === 'profit-loss') {
            $sales = (float) DB::table('sales')->where('entity_id',$entity)->where('status','posted')->sum('total');
            $cogs = (float) DB::table('productions')->where('entity_id',$entity)->sum('total_cost');
            $result['lines'] = [['label'=>'Penjualan','amount'=>$sales],['label'=>'HPP Produksi','amount'=>-$cogs],['label'=>'Laba Kotor','amount'=>$sales-$cogs]];
            $result['total'] = $sales-$cogs;
        } elseif ($module === 'balance-sheet') {
            $stock = (float) DB::table('warehouses_stocks')->where('entity_id',$entity)->selectRaw('COALESCE(SUM(qty * avg_cost),0) v')->value('v');
            $cash = (float) DB::table('payments')->where('entity_id',$entity)->sum('amount');
            $result['lines'] = [['label'=>'Kas & penerimaan','amount'=>$cash],['label'=>'Persediaan','amount'=>$stock]];
            $result['total'] = $cash+$stock;
        } elseif ($module === 'cash-flow') {
            $in = (float) DB::table('payments')->where('entity_id',$entity)->sum('amount');
            $out = (float) DB::table('purchases')->where('entity_id',$entity)->sum('total');
            $result['lines'] = [['label'=>'Penerimaan penjualan','amount'=>$in],['label'=>'Pembelian','amount'=>-$out],['label'=>'Arus kas bersih','amount'=>$in-$out]];
            $result['total'] = $in-$out;
        }
        return $result;
    }


    public function salesDetail(int $id)
    {
        $entity = $this->entityId();

        $sale = DB::table('sales as s')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->where('s.entity_id', $entity)
            ->where('s.id', $id)
            ->select(
                's.id', 's.invoice_no', 's.sale_date', 's.subtotal', 's.discount', 's.total', 's.status', 's.shift_id',
                DB::raw("COALESCE(c.name, 'Umum') as customer_name"),
                DB::raw("COALESCE(u.name, '-') as cashier_name"),
                DB::raw("(SELECT GROUP_CONCAT(DISTINCT p.method ORDER BY p.id SEPARATOR ', ') FROM payments p WHERE p.sale_id = s.id) as payment_methods"),
                DB::raw("(SELECT COALESCE(SUM(p.paid_amount), SUM(p.amount), 0) FROM payments p WHERE p.sale_id = s.id) as paid_amount"),
                DB::raw("(SELECT COALESCE(SUM(p.change_amount), 0) FROM payments p WHERE p.sale_id = s.id) as change_amount")
            )
            ->first();

        abort_unless($sale, 404);

        $items = DB::table('sale_items as si')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->where('si.sale_id', $id)
            ->select('p.sku', 'p.name', 'si.qty', 'si.unit_price', 'si.discount', 'si.total')
            ->orderBy('si.id')
            ->get();

        $entityData = DB::table('entities')->where('id', $entity)->first(['name', 'address', 'phone']);
        return response()->json(['sale' => $sale, 'items' => $items, 'entity' => $entityData]);
    }

    public function exportSalesExcel(Request $request)
    {
        $entity = $this->entityId();
        $start = $request->input('start_date', now()->startOfMonth()->toDateString());
        $end = $request->input('end_date', now()->endOfMonth()->toDateString());

        abort_if($start > $end, 422, 'Periode tanggal tidak valid.');

        $entityData = DB::table('entities')->where('id', $entity)->first(['name', 'address', 'phone']);

        $rows = DB::table('sales as s')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->where('s.entity_id', $entity)
            ->whereBetween('s.sale_date', [$start . ' 00:00:00', $end . ' 23:59:59'])
            ->select(
                's.invoice_no', 's.sale_date', 's.subtotal', 's.discount', 's.total', 's.status',
                's.shift_id',
                DB::raw("COALESCE(c.name, 'Umum') as customer_name"),
                DB::raw("COALESCE(u.name, '-') as cashier_name"),
                DB::raw("(SELECT GROUP_CONCAT(DISTINCT p.method ORDER BY p.id SEPARATOR ', ') FROM payments p WHERE p.sale_id = s.id) as payment_methods"),
                DB::raw("(SELECT COALESCE(SUM(p.paid_amount), SUM(p.amount), 0) FROM payments p WHERE p.sale_id = s.id) as paid_amount"),
                DB::raw("(SELECT COALESCE(SUM(p.change_amount), 0) FROM payments p WHERE p.sale_id = s.id) as change_amount")
            )
            ->orderBy('s.sale_date')
            ->orderBy('s.id')
            ->get();

        $totalPenjualan = (float) $rows->sum(fn ($row) => (float) $row->total);

        $filename = 'penjualan_' . $start . '_' . $end . '.xls';
        $html = '<html><head><meta charset="UTF-8"><style>
            body{font-family:Arial,sans-serif}
            .kop{font-size:16px;font-weight:700}
            .periode{font-size:13px;font-weight:700}
            table{border-collapse:collapse}
            th,td{border:1px solid #000;padding:5px}
            th{font-weight:700}
        </style></head><body>';
        $html .= '<div class="kop">' . e($entityData->name ?? 'MINI ERP') . '</div>';
        if (!empty($entityData->address)) $html .= '<div>' . e($entityData->address) . '</div>';
        if (!empty($entityData->phone)) $html .= '<div>Telp. ' . e($entityData->phone) . '</div>';
        $html .= '<div class="periode">LAPORAN PENJUALAN</div>';
        $html .= '<div>Periode: ' . e(date('d-m-Y', strtotime($start))) . ' s/d ' . e(date('d-m-Y', strtotime($end))) . '</div>';
        $html .= '<br><table><thead><tr>';

        foreach (['No. Invoice','Tanggal','Customer','Kasir','Shift','Pembayaran','Subtotal','Diskon','Total','Dibayar','Kembalian','Status'] as $heading) {
            $html .= '<th>' . e($heading) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ([
                $row->invoice_no,
                $row->sale_date,
                $row->customer_name,
                $row->cashier_name,
                $row->shift_id,
                $row->payment_methods ?: '-',
                $row->subtotal,
                $row->discount,
                $row->total,
                $row->paid_amount,
                $row->change_amount,
                $row->status
            ] as $index => $value) {
                if (in_array($index, [6, 7, 8, 9, 10], true)) {
                    // Nilai angka harus tetap angka mentah agar Excel dapat langsung SUM().
                    // Cast ke float juga mencegah string angka yang membawa format/desimal ganda.
                    $numericValue = (float) $value;
                    $displayValue = (string) $numericValue;
                    $html .= '<td x:num="' . e($displayValue) . '">' . e($displayValue) . '</td>';
                } else {
                    $html .= '<td>' . e((string) $value) . '</td>';
                }
            }
            $html .= '</tr>';
        }

        $html .= '</tbody><tfoot>';
        $html .= '<tr><th colspan="8" style="text-align:right">TOTAL PENJUALAN</th><th x:num="' . e((string) $totalPenjualan) . '">' . e((string) $totalPenjualan) . '</th><th colspan="3"></th></tr>';
        $html .= '</tfoot></table></body></html>';

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function salesData(Request $request)
    {
        $entity = $this->entityId();
        $start = $request->input('start_date', now()->startOfMonth()->toDateString());
        $end = $request->input('end_date', now()->endOfMonth()->toDateString());

        $query = DB::table('sales as s')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->where('s.entity_id', $entity)
            ->whereBetween('s.sale_date', [$start . ' 00:00:00', $end . ' 23:59:59']);

        $recordsTotal = (clone $query)->count('s.id');

        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $like = '%' . $search . '%';
                $q->where('s.invoice_no', 'like', $like)
                    ->orWhere('c.name', 'like', $like)
                    ->orWhere('u.name', 'like', $like)
                    ->orWhere('s.status', 'like', $like);
            });
        }

        $recordsFiltered = (clone $query)->count('s.id');

        $columns = [
            0 => 's.invoice_no',
            1 => 's.sale_date',
            2 => 'c.name',
            3 => 'u.name',
            4 => 's.shift_id',
            5 => 's.total',
            6 => 's.status',
        ];
        $orderColumn = (int) $request->input('order.0.column', 1);
        $orderDir = strtolower((string) $request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($columns[$orderColumn] ?? 's.sale_date', $orderDir);

        $startRow = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 15);
        if ($length < 1) $length = 15;
        if ($length > 100) $length = 100;

        $rows = $query
            ->select(
                's.id', 's.invoice_no', 's.sale_date', 's.subtotal', 's.discount', 's.total', 's.status', 's.shift_id',
                DB::raw("COALESCE(c.name, 'Umum') as customer_name"),
                DB::raw("COALESCE(u.name, '-') as cashier_name"),
                DB::raw("(SELECT GROUP_CONCAT(DISTINCT p.method ORDER BY p.id SEPARATOR ', ') FROM payments p WHERE p.sale_id = s.id) as payment_methods"),
                DB::raw("(SELECT COALESCE(SUM(p.paid_amount), SUM(p.amount), 0) FROM payments p WHERE p.sale_id = s.id) as paid_amount"),
                DB::raw("(SELECT COALESCE(SUM(p.change_amount), 0) FROM payments p WHERE p.sale_id = s.id) as change_amount")
            )
            ->orderByDesc('s.id')
            ->skip($startRow)
            ->take($length)
            ->get();

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ]);
    }

    public function posStore(Request $request)
    {
        $data = $request->validate(['product_id'=>'required|integer','qty'=>'required|numeric|min:0.001','payment_method'=>'required|in:Tunai,Transfer,QRIS']);
        $entity=$this->entityId();
        $product=DB::table('products')->where('entity_id',$entity)->find($data['product_id']); abort_unless($product,404);
        $total=(float)$product->selling_price*(float)$data['qty'];
        $shift=DB::table('cash_shifts')->where('entity_id',$entity)->where('user_id',auth()->id())->where('status','open')->latest('id')->first();
        DB::transaction(function() use($data,$entity,$product,$total,$shift){
            $no='POS-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
            $sale=DB::table('sales')->insertGetId(['entity_id'=>$entity,'user_id'=>auth()->id(),'shift_id'=>$shift?->id,'invoice_no'=>$no,'sale_date'=>now(),'subtotal'=>$total,'total'=>$total,'status'=>'posted','created_at'=>now(),'updated_at'=>now()]);
            DB::table('sale_items')->insert(['sale_id'=>$sale,'product_id'=>$product->id,'qty'=>$data['qty'],'unit_price'=>$product->selling_price,'total'=>$total,'created_at'=>now(),'updated_at'=>now()]);
            DB::table('payments')->insert(['entity_id'=>$entity,'sale_id'=>$sale,'user_id'=>auth()->id(),'payment_date'=>now(),'method'=>$data['payment_method'],'amount'=>$total,'created_at'=>now(),'updated_at'=>now()]);
        });
        return back()->with('success','Transaksi POS berhasil disimpan.');
    }

    public function shiftStore(Request $request)
    {
        $data=$request->validate(['action'=>'required|in:open,close','opening_cash'=>'nullable|numeric|min:0','closing_cash'=>'nullable|numeric|min:0']);
        $entity=$this->entityId();
        $open=DB::table('cash_shifts')->where('entity_id',$entity)->where('user_id',auth()->id())->where('status','open')->latest('id')->first();
        if($data['action']==='open'){ abort_if($open,422,'Shift masih terbuka.'); DB::table('cash_shifts')->insert(['entity_id'=>$entity,'user_id'=>auth()->id(),'opened_at'=>now(),'opening_cash'=>$data['opening_cash']??0,'status'=>'open','created_at'=>now(),'updated_at'=>now()]); return back()->with('success','Shift kasir dibuka.'); }
        abort_unless($open,422,'Tidak ada shift terbuka.');
        DB::table('cash_shifts')->where('id',$open->id)->update(['closed_at'=>now(),'closing_cash'=>$data['closing_cash']??0,'status'=>'closed','updated_at'=>now()]);
        return back()->with('success','Shift kasir ditutup.');
    }

    public function movementStore(Request $request)
    {
        $data=$request->validate(['product_id'=>'required|integer','warehouse_id'=>'required|integer','movement_type'=>'required|in:adjustment_in,adjustment_out','qty'=>'required|numeric|min:0.001','unit_cost'=>'nullable|numeric|min:0']);
        $entity=$this->entityId();
        DB::transaction(function() use($data,$entity){
            $product=DB::table('products')->where('entity_id',$entity)->find($data['product_id']); abort_unless($product,404);
            $warehouse=DB::table('warehouses')->where('entity_id',$entity)->find($data['warehouse_id']); abort_unless($warehouse,404);
            $stock=DB::table('warehouses_stocks')->where(['warehouse_id'=>$warehouse->id,'product_id'=>$product->id])->first();
            $delta=$data['movement_type']==='adjustment_in'?(float)$data['qty']:-((float)$data['qty']);
            abort_if($delta<0 && (!$stock || $stock->qty < abs($delta)),422,'Stok tidak mencukupi.');
            if($stock) DB::table('warehouses_stocks')->where('id',$stock->id)->update(['qty'=>$stock->qty+$delta,'updated_at'=>now()]);
            else DB::table('warehouses_stocks')->insert(['entity_id'=>$entity,'warehouse_id'=>$warehouse->id,'product_id'=>$product->id,'qty'=>$delta,'avg_cost'=>$data['unit_cost']??$product->cost_price,'created_at'=>now(),'updated_at'=>now()]);
            DB::table('stock_movements')->insert(['entity_id'=>$entity,'warehouse_id'=>$warehouse->id,'product_id'=>$product->id,'movement_type'=>$data['movement_type'],'qty'=>$delta,'unit_cost'=>$data['unit_cost']??$product->cost_price,'reference_type'=>'adjustment','occurred_at'=>now(),'created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
        });
        return back()->with('success','Mutasi stok berhasil disimpan.');
    }

    public function opnameStore(Request $request)
    {
        $data=$request->validate(['warehouse_id'=>'required|integer','product_id'=>'required|integer','actual_qty'=>'required|numeric|min:0']);
        $entity=$this->entityId(); $stock=DB::table('warehouses_stocks')->where(['warehouse_id'=>$data['warehouse_id'],'product_id'=>$data['product_id']])->first(); $system=(float)($stock->qty??0); $actual=(float)$data['actual_qty']; $diff=$actual-$system;
        DB::transaction(function() use($data,$entity,$system,$actual,$diff){
            $id=DB::table('stock_opnames')->insertGetId(['entity_id'=>$entity,'warehouse_id'=>$data['warehouse_id'],'user_id'=>auth()->id(),'opname_date'=>today(),'opname_no'=>'OPN-'.now()->format('YmdHis').'-'.Str::upper(Str::random(3)),'status'=>'posted','created_at'=>now(),'updated_at'=>now()]);
            DB::table('stock_opname_items')->insert(['stock_opname_id'=>$id,'product_id'=>$data['product_id'],'system_qty'=>$system,'actual_qty'=>$actual,'difference'=>$diff,'created_at'=>now(),'updated_at'=>now()]);
            $stock=DB::table('warehouses_stocks')->where(['warehouse_id'=>$data['warehouse_id'],'product_id'=>$data['product_id']])->first();
            if($stock) DB::table('warehouses_stocks')->where('id',$stock->id)->update(['qty'=>$actual,'updated_at'=>now()]); else DB::table('warehouses_stocks')->insert(['entity_id'=>$entity,'warehouse_id'=>$data['warehouse_id'],'product_id'=>$data['product_id'],'qty'=>$actual,'avg_cost'=>0,'created_at'=>now(),'updated_at'=>now()]);
            if(abs($diff)>0) DB::table('stock_movements')->insert(['entity_id'=>$entity,'warehouse_id'=>$data['warehouse_id'],'product_id'=>$data['product_id'],'movement_type'=>'opname','qty'=>$diff,'unit_cost'=>$stock->avg_cost??0,'reference_type'=>'stock_opname','reference_id'=>$id,'occurred_at'=>now(),'created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
        });
        return back()->with('success','Stock opname berhasil diposting.');
    }

    public function bomStore(Request $request)
    {
        $data=$request->validate(['product_id'=>'required|integer','code'=>'required|string','name'=>'required|string','output_qty'=>'required|numeric|min:0.001','material_product_id'=>'required|integer','material_qty'=>'required|numeric|min:0.001']);
        $entity=$this->entityId();
        DB::transaction(function() use($data,$entity){
            $product=DB::table('products')->where('entity_id',$entity)->find($data['product_id']); $material=DB::table('products')->where('entity_id',$entity)->find($data['material_product_id']); abort_unless($product && $material,404);
            $bom=DB::table('boms')->insertGetId(['entity_id'=>$entity,'product_id'=>$product->id,'code'=>$data['code'],'name'=>$data['name'],'output_qty'=>$data['output_qty'],'is_active'=>1,'created_at'=>now(),'updated_at'=>now()]);
            DB::table('bom_items')->insert(['bom_id'=>$bom,'product_id'=>$material->id,'qty'=>$data['material_qty'],'created_at'=>now(),'updated_at'=>now()]);
        });
        return back()->with('success','Formula / BOM berhasil disimpan.');
    }

    public function operationStore(Request $request)
    {
        $data=$request->validate(['vehicle_id'=>'required|integer','operation_date'=>'required|date','km_start'=>'required|integer|min:0','km_end'=>'required|integer|gte:km_start','fuel_cost'=>'nullable|numeric|min:0','other_cost'=>'nullable|numeric|min:0','notes'=>'nullable|string']);
        $entity=$this->entityId(); $vehicle=DB::table('vehicles')->where('entity_id',$entity)->find($data['vehicle_id']); abort_unless($vehicle,404);
        DB::table('vehicle_operations')->insert(array_merge($data,['entity_id'=>$entity,'created_at'=>now(),'updated_at'=>now()]));
        DB::table('vehicles')->where('id',$vehicle->id)->update(['current_km'=>$data['km_end'],'updated_at'=>now()]);
        return back()->with('success','Operasional armada berhasil disimpan.');
    }

    public function fleetCostStore(Request $request)
    {
        $data=$request->validate(['vehicle_id'=>'required|integer','cost_date'=>'required|date','cost_type'=>'required|string','amount'=>'required|numeric|min:0','description'=>'nullable|string']);
        $entity=$this->entityId(); abort_unless(DB::table('vehicles')->where('entity_id',$entity)->find($data['vehicle_id']),404);
        DB::table('fleet_costs')->insert(array_merge($data,['entity_id'=>$entity,'created_at'=>now(),'updated_at'=>now()]));
        return back()->with('success','Biaya armada berhasil disimpan.');
    }

    public function deliveryStore(Request $request)
    {
        $data=$request->validate(['vehicle_id'=>'nullable|integer','driver_id'=>'nullable|integer','destination'=>'required|string','distance_km'=>'nullable|numeric|min:0']);
        DB::table('deliveries')->insert(array_merge($data,['entity_id'=>$this->entityId(),'delivery_no'=>'DO-'.now()->format('YmdHis').'-'.Str::upper(Str::random(3)),'delivery_date'=>now(),'status'=>'planned','created_at'=>now(),'updated_at'=>now()]));
        return back()->with('success','Pengiriman berhasil dibuat.');
    }

    public function journalStore(Request $request)
    {
        $data=$request->validate(['description'=>'required|string','debit_account'=>'required|integer','credit_account'=>'required|integer|different:debit_account','amount'=>'required|numeric|min:0.01']);
        $entity=$this->entityId();
        abort_unless(DB::table('chart_of_accounts')->where('entity_id',$entity)->whereIn('id',[$data['debit_account'],$data['credit_account']])->count()===2,422);
        DB::transaction(function() use($data,$entity){
            $j=DB::table('journals')->insertGetId(['entity_id'=>$entity,'journal_no'=>'JRN-'.now()->format('YmdHis').'-'.Str::upper(Str::random(3)),'journal_date'=>today(),'description'=>$data['description'],'status'=>'posted','created_at'=>now(),'updated_at'=>now()]);
            DB::table('journal_entries')->insert([
                ['journal_id'=>$j,'account_id'=>$data['debit_account'],'debit'=>$data['amount'],'credit'=>0,'created_at'=>now(),'updated_at'=>now()],
                ['journal_id'=>$j,'account_id'=>$data['credit_account'],'debit'=>0,'credit'=>$data['amount'],'created_at'=>now(),'updated_at'=>now()],
            ]);
        });
        return back()->with('success','Jurnal berhasil diposting.');
    }
}
