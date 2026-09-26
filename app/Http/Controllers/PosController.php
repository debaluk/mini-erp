<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosController extends ModuleController
{
    private function entityId(): int
    {
        $entity = DB::table('entities')->first();
        abort_unless($entity, 422, 'Entitas belum tersedia.');
        return (int) $entity->id;
    }

public function show(string $module)
    {
        $titles = [
'sales'=>'Transaksi Penjualan', 'payments'=>'Pembayaran',
            'purchases'=>'Pembelian', 'receipts'=>'Penerimaan Barang', 'payables'=>'Hutang',
            'stock'=>'Stok', 'movements'=>'Mutasi Stok', 'opname'=>'Stock Opname',
            'bom'=>'Formula / BOM', 'production'=>'Produksi Batako', 'production-results'=>'Hasil Produksi',
            'material-usage'=>'Pemakaian Bahan', 'production-cost'=>'HPP Produksi',
            'fleet'=>'Kendaraan', 'deliveries'=>'Pengiriman', 'operations'=>'Operasional Armada', 'fleet-costs'=>'Biaya Armada',
            'journals'=>'Jurnal', 'ledger'=>'Buku Besar', 'receivables'=>'Piutang', 'cashbank'=>'Kas & Bank',
            'cogs'=>'HPP', 'profit-loss'=>'Laba Rugi', 'trial-balance'=>'Neraca Saldo', 'balance-sheet'=>'Neraca', 'cash-flow'=>'Arus Kas', 'closing'=>'Closing Periode',
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
        if (in_array($module, ['ledger','receivables','cashbank','cogs','trial-balance','profit-loss','balance-sheet','cash-flow'], true)) {
            $data['report'] = $this->report($module, $entity);
        }
        $inventoryViews = [
            'sales' => 'inventori.penjualan.pos.index',
            'payments' => 'inventori.penjualan.pos.index',
            'purchases' => 'inventori.pembelian.faktur.index',
            'receipts' => 'inventori.pembelian.penerimaan.index',
            'stock' => 'inventori.laporan.persediaan',
            'movements' => 'inventori.persediaan.mutasi.index',
            'opname' => 'inventori.persediaan.opname.index',
            'production' => 'inventori.produksi.work-order.index',
            'production-results' => 'inventori.produksi.hasil-scrap.index',
            'material-usage' => 'inventori.produksi.bahan-baku.index',
            'production-cost' => 'inventori.laporan.hpp',
        ];

        if (isset($inventoryViews[$module])) {
            return view($inventoryViews[$module], $data);
        }

        $accountingViews = [
            'journals' => 'keuangan.akuntansi.jurnal-umum.index',
            'ledger' => 'keuangan.akuntansi.buku-besar.index',
            'cashbank' => 'keuangan.kas-bank.masuk.index',
            'closing' => 'keuangan.akuntansi.closing.index',
            'profit-loss' => 'keuangan.laporan.laba-rugi',
            'trial-balance' => 'keuangan.laporan.neraca-saldo',
            'balance-sheet' => 'keuangan.laporan.neraca',
            'cash-flow' => 'keuangan.laporan.arus-kas',
            'receivables' => 'keuangan.laporan.aging-piutang',
            'payables' => 'keuangan.laporan.aging-hutang',
        ];

        if (isset($accountingViews[$module])) {
            return view($accountingViews[$module], $data);
        }

        abort(404);
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
            4 => 's.total',
            5 => 's.subtotal',
            6 => 's.discount',
            7 => 's.total',
            8 => 's.total',
            9 => 's.total',
            10 => 's.status',
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
                's.id', 's.invoice_no', 's.sale_date', 's.subtotal', 's.discount', 's.total', 's.status',
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

        foreach (['No. Invoice','Tanggal','Customer','Kasir','Pembayaran','Subtotal','Diskon','Total','Dibayar','Kembalian','Status'] as $heading) {
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
                $row->payment_methods ?: '-',
                $row->subtotal,
                $row->discount,
                $row->total,
                $row->paid_amount,
                $row->change_amount,
                $row->status
            ] as $index => $value) {
                if (in_array($index, [5, 6, 7, 8, 9], true)) {
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
        $html .= '<tr><th colspan="7" style="text-align:right">TOTAL PENJUALAN</th><th x:num="' . e((string) $totalPenjualan) . '">' . e((string) $totalPenjualan) . '</th><th colspan="3"></th></tr>';
        $html .= '</tfoot></table></body></html>';

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
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
                's.id', 's.invoice_no', 's.sale_date', 's.subtotal', 's.discount', 's.total', 's.status',
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

public function paymentsData(Request $request)
    {
        $entity = $this->entityId();
        $start = $request->input('start_date', now()->startOfMonth()->toDateString());
        $end = $request->input('end_date', now()->endOfMonth()->toDateString());
        $base = DB::table('payments as p')
            ->leftJoin('sales as s', 's.id', '=', 'p.sale_id')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('users as u', 'u.id', '=', 'p.user_id')
            ->where('p.entity_id', $entity)
            ->whereBetween('p.payment_date', [$start.' 00:00:00', $end.' 23:59:59'])
            ->select('p.id','p.payment_date','p.method','p.amount','p.paid_amount','p.change_amount','p.sale_id','p.user_id',
                DB::raw("CONCAT('PAY-', LPAD(p.id, 6, '0')) as payment_no"),
                DB::raw("CASE WHEN p.sale_id IS NOT NULL THEN 'POS / Penjualan' ELSE 'Lainnya' END as source_name"),
                DB::raw("COALESCE(s.invoice_no, '-') as reference_no"),
                DB::raw("COALESCE(c.name, 'Umum') as customer_name"),
                DB::raw("COALESCE(u.name, '-') as user_name"),
                DB::raw("'posted' as payment_status"));
        $recordsTotal = (clone $base)->count();
        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $base->where(function ($q) use ($search) {
                $like = '%'.$search.'%';
                $q->where('s.invoice_no','like',$like)->orWhere('c.name','like',$like)->orWhere('u.name','like',$like)->orWhere('p.method','like',$like)->orWhereRaw("CONCAT('PAY-', LPAD(p.id, 6, '0')) like ?", [$like]);
            });
        }
        $recordsFiltered = (clone $base)->count();
        $columns = [0=>'p.id',1=>'p.payment_date',2=>'source_name',3=>'s.invoice_no',4=>'c.name',5=>'p.amount',6=>'p.method',7=>'p.method',8=>'u.name',9=>DB::raw("'posted'")];
        $orderCol = (int) $request->input('order.0.column', 1);
        $orderDir = strtolower($request->input('order.0.dir','desc')) === 'asc' ? 'asc' : 'desc';
        $base->orderBy($columns[$orderCol] ?? 'p.id', $orderDir);
        $startRow = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 15);
        if ($length !== -1) $base->skip($startRow)->take($length);
        return response()->json(['draw'=>(int)$request->input('draw'), 'recordsTotal'=>$recordsTotal, 'recordsFiltered'=>$recordsFiltered, 'data'=>$base->get()]);
    }

public function exportPaymentsExcel(Request $request)
    {
        $entity = $this->entityId();
        $start = $request->input('start_date', now()->startOfMonth()->toDateString());
        $end = $request->input('end_date', now()->endOfMonth()->toDateString());
        $rows = DB::table('payments as p')->leftJoin('sales as s','s.id','=','p.sale_id')->leftJoin('customers as c','c.id','=','s.customer_id')->leftJoin('users as u','u.id','=','p.user_id')->where('p.entity_id',$entity)->whereBetween('p.payment_date',[$start.' 00:00:00',$end.' 23:59:59'])->orderBy('p.id')->select('p.*',DB::raw("CONCAT('PAY-', LPAD(p.id, 6, '0')) as payment_no"),DB::raw("CASE WHEN p.sale_id IS NOT NULL THEN 'POS / Penjualan' ELSE 'Lainnya' END as source_name"),DB::raw("COALESCE(s.invoice_no, '-') as reference_no"),DB::raw("COALESCE(c.name, 'Umum') as customer_name"),DB::raw("COALESCE(u.name, '-') as user_name"),DB::raw("COALESCE(p.status, 'posted') as payment_status"))->get();
        $entityRow=DB::table('entities')->where('id',$entity)->first();
        $e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
        $num=fn($v)=>'<span x:num="'.number_format((float)$v,2,'.','').'">'.number_format((float)$v,2,'.','').'</span>';
        $html='<html><head><meta charset="UTF-8"><style>td,th{border:1px solid #999;padding:5px}th{background:#eee}</style></head><body>';
        $html.='<h3>'. $e($entityRow?->name ?? 'MINI ERP') .'</h3><div>'. $e($entityRow?->address ?? '') .'</div><h4>Penerimaan Pembayaran</h4><div>Periode: '.$e($start).' s/d '.$e($end).'</div><br><table><tr><th>No Pembayaran</th><th>Tanggal</th><th>Sumber</th><th>Referensi</th><th>Customer</th><th>Jumlah</th><th>Metode</th><th>Kas/Bank</th><th>User</th><th>Status</th></tr>';
        foreach($rows as $r){$html.='<tr><td>'.$e($r->payment_no).'</td><td>'.$e($r->payment_date).'</td><td>'.$e($r->source_name).'</td><td>'.$e($r->reference_no).'</td><td>'.$e($r->customer_name).'</td><td>'.$num($r->amount).'</td><td>'.$e($r->method).'</td><td>'.$e($r->method).'</td><td>'.$e($r->user_name).'</td><td>'.$e($r->payment_status).'</td></tr>';}
        $html.='</table></body></html>';
        return response($html)->header('Content-Type','application/vnd.ms-excel; charset=UTF-8')->header('Content-Disposition','attachment; filename="penerimaan-pembayaran-'.$start.'-'.$end.'.xls"');
    }

public function paymentDetail(int $id)
    {
        $entity = $this->entityId();
        $row = DB::table('payments as p')->leftJoin('sales as s','s.id','=','p.sale_id')->leftJoin('customers as c','c.id','=','s.customer_id')->leftJoin('users as u','u.id','=','p.user_id')->where('p.entity_id',$entity)->where('p.id',$id)->select('p.*',DB::raw("CONCAT('PAY-', LPAD(p.id, 6, '0')) as payment_no"),'s.invoice_no','s.total as sale_total','c.name as customer_name','u.name as user_name')->first();
        abort_unless($row,404);
        $entityRow = DB::table('entities')->where('id',$entity)->first();
        return response()->json(['payment'=>$row,'entity'=>$entityRow]);
    }
    
    public function create()
    {
        $entity = $this->entityId();
        $user = auth()->user();

        $unit = DB::table('business_units as bu')
            ->join('user_business_units as ubu', 'ubu.business_unit_id', '=', 'bu.id')
            ->where('ubu.user_id', $user->id)
            ->where('bu.entity_id', $entity)
            ->where('bu.code', 'RET')
            ->where('bu.is_active', 1)
            ->select('bu.id', 'bu.code', 'bu.name')
            ->first();

        abort_unless($unit, 403, 'User belum memiliki akses Business Unit Retail.');

        $units = collect([$unit]);
        $defaultUnitId = (int) $unit->id;

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
            ->where('business_unit_id', $unit->id)
            ->where('price_type', 'retail')
            ->get(['product_id', 'business_unit_id', 'unit_id', 'selling_price'])
            ->groupBy('product_id');

        $productCatalog = $products->map(function ($product) use ($productPrices) {
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
                'conversions' => collect(),
            ];
        })->values();

        return view('inventori.penjualan.pos.create', compact(
            'units',
            'defaultUnitId',
            'customers',
            'productCatalog'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'payment_method' => ['required', 'in:Tunai,Transfer,QRIS'],
        ]);

        $entity = $this->entityId();
        $user = auth()->user();

        $retail = DB::table('business_units as bu')
            ->join('user_business_units as ubu', 'ubu.business_unit_id', '=', 'bu.id')
            ->where('ubu.user_id', $user->id)
            ->where('bu.entity_id', $entity)
            ->where('bu.code', 'RET')
            ->where('bu.is_active', 1)
            ->select('bu.id')
            ->first();

        abort_unless($retail, 403, 'User belum memiliki akses Business Unit Retail.');

        foreach ((array) $request->input('items', []) as $item) {
            $product = DB::table('products')
                ->where('id', (int) ($item['product_id'] ?? 0))
                ->where('entity_id', $entity)
                ->where('is_active', 1)
                ->first(['id', 'base_unit_id']);

            abort_unless($product, 422, 'Barang POS tidak valid.');
            abort_unless((int) ($item['unit_id'] ?? 0) === (int) $product->base_unit_id, 422, 'POS hanya menggunakan satuan dasar barang.');
        }

        $request->merge([
            'business_unit_id' => (int) $retail->id,
            'due_date' => null,
        ]);

        return app(SalesController::class)->store($request);
    }

    public function print(int $id)
    {
        $entity = $this->entityId();

        $sale = DB::table('sales as s')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 's.business_unit_id')
            ->leftJoin('entities as e', 'e.id', '=', 's.entity_id')
            ->where('s.entity_id', $entity)
            ->where('s.id', $id)
            ->where('bu.code', 'RET')
            ->select(
                's.*',
                'e.name as entity_name',
                'c.name as customer_name',
                'bu.name as unit_name'
            )
            ->first();

        abort_unless($sale, 404);

        $items = DB::table('sale_items as si')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'si.unit_id')
            ->where('si.sale_id', $sale->id)
            ->select(
                'p.code',
                'p.name',
                'u.code as unit_code',
                'si.qty',
                'si.unit_price',
                'si.discount',
                'si.total'
            )
            ->orderBy('si.id')
            ->get();

        $payments = DB::table('payments')
            ->where('sale_id', $sale->id)
            ->orderBy('id')
            ->get();

        return view('inventori.penjualan.pos.print', compact(
            'sale',
            'items',
            'payments'
        ));
    }

}
