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
            'entity' => DB::table('entities')->where('id', $entity)->first(),
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
            'cogs'=>'HPP', 'profit-loss'=>'Laba Rugi', 'trial-balance'=>'Neraca Saldo', 'balance-sheet'=>'Neraca', 'cash-flow'=>'Arus Kas',
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
        if (in_array($module, ['ledger','receivables','cashbank','cogs','trial-balance','profit-loss','balance-sheet','cash-flow'], true)) {
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

    public function paymentDetail(int $id)
    {
        $entity = $this->entityId();
        $row = DB::table('payments as p')->leftJoin('sales as s','s.id','=','p.sale_id')->leftJoin('customers as c','c.id','=','s.customer_id')->leftJoin('users as u','u.id','=','p.user_id')->where('p.entity_id',$entity)->where('p.id',$id)->select('p.*',DB::raw("CONCAT('PAY-', LPAD(p.id, 6, '0')) as payment_no"),'s.invoice_no','s.total as sale_total','c.name as customer_name','u.name as user_name')->first();
        abort_unless($row,404);
        $entityRow = DB::table('entities')->where('id',$entity)->first();
        return response()->json(['payment'=>$row,'entity'=>$entityRow]);
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

    public function exportTrialBalanceExcel(Request $request)
    {
        $entity = $this->entityId();
        $start = $request->input('start_date', now()->startOfMonth()->toDateString());
        $end = $request->input('end_date', now()->endOfMonth()->toDateString());
        abort_if($start > $end, 422, 'Periode tanggal tidak valid.');

        $accounts = DB::table('chart_of_accounts')->where('entity_id',$entity)->where('is_active',1)->where('level',3)->orderBy('code')->get(['id','code','name']);
        $base = DB::table('journal_entries as e')->join('journals as j','j.id','=','e.journal_id')->where('j.entity_id',$entity)->where('j.status','posted');
        $opening = (clone $base)->where('j.journal_date','<',$start)->groupBy('e.account_id')->select('e.account_id',DB::raw('SUM(e.debit) debit'),DB::raw('SUM(e.credit) credit'))->get()->keyBy('account_id');
        $period = (clone $base)->whereBetween('j.journal_date',[$start,$end])->groupBy('e.account_id')->select('e.account_id',DB::raw('SUM(e.debit) debit'),DB::raw('SUM(e.credit) credit'))->get()->keyBy('account_id');

        $entityRow=DB::table('entities')->where('id',$entity)->first();
        $e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
        $num=fn($v)=>number_format((float)$v,2,'.','');
        $html='<html><head><meta charset="UTF-8"><style>body{font-family:Arial}table{border-collapse:collapse}th,td{border:1px solid #000;padding:5px}.right{text-align:right}.bold{font-weight:700}</style></head><body>';
        $html.='<div><b>'.$e($entityRow?->name ?? 'MINI ERP').'</b></div>';
        if(!empty($entityRow?->address)) $html.='<div>'.$e($entityRow->address).'</div>';
        $html.='<h4>NERACA SALDO</h4><div>Periode: '.$e(date('d-m-Y',strtotime($start))).' s/d '.$e(date('d-m-Y',strtotime($end))).'</div><br>';
        $html.='<table><tr><th>Kode</th><th>Nama Akun</th><th>Saldo Awal</th><th>Debit</th><th>Kredit</th><th>Saldo Akhir</th></tr>';
        $openTotal=0;$debitTotal=0;$creditTotal=0;$balanceTotal=0;
        foreach($accounts as $a){$op=$opening->get($a->id);$pr=$period->get($a->id);$o=(float)($op->debit??0)-(float)($op->credit??0);$d=(float)($pr->debit??0);$cr=(float)($pr->credit??0);$b=$o+$d-$cr;$openTotal+=$o;$debitTotal+=$d;$creditTotal+=$cr;$balanceTotal+=$b;$html.='<tr><td>'.$e($a->code).'</td><td>'.$e($a->name).'</td><td class="right">'.$num(abs($o)).' '.($o>=0?'D':'K').'</td><td class="right">'.$num($d).'</td><td class="right">'.$num($cr).'</td><td class="right">'.$num(abs($b)).' '.($b>=0?'D':'K').'</td></tr>';}
        $html.='<tr class="bold"><td colspan="2">TOTAL</td><td class="right">'.$num(abs($openTotal)).' '.($openTotal>=0?'D':'K').'</td><td class="right">'.$num($debitTotal).'</td><td class="right">'.$num($creditTotal).'</td><td class="right">'.$num(abs($balanceTotal)).' '.($balanceTotal>=0?'D':'K').'</td></tr></table></body></html>';
        return response($html)->header('Content-Type','application/vnd.ms-excel; charset=UTF-8')->header('Content-Disposition','attachment; filename="neraca-saldo_'.$start.'_'.$end.'.xls"');
    }

    public function exportBalanceSheetExcel(Request $request)
    {
        $entity = $this->entityId();
        $asOf = $request->input('end_date', now()->endOfMonth()->toDateString());

        $totals = DB::table('journal_entries as e')
            ->join('journals as j','j.id','=','e.journal_id')
            ->where('j.entity_id',$entity)->where('j.status','posted')
            ->where('j.journal_date','<=',$asOf)
            ->groupBy('e.account_id')
            ->select('e.account_id',DB::raw('SUM(e.debit) as debit'),DB::raw('SUM(e.credit) as credit'));

        $accounts = DB::table('chart_of_accounts as a')
            ->leftJoinSub($totals,'jt',fn($join)=>$join->on('jt.account_id','=','a.id'))
            ->where('a.entity_id',$entity)->where('a.is_active',1)->where('a.level',3)
            ->whereIn('a.type',['asset','liability','equity'])->orderBy('a.code')
            ->select('a.code','a.name','a.type',DB::raw('COALESCE(jt.debit,0) as debit'),DB::raw('COALESCE(jt.credit,0) as credit'))->get();

        $groups=['asset'=>[],'liability'=>[],'equity'=>[]];
        $tot=['asset'=>0,'liability'=>0,'equity'=>0];
        foreach($accounts as $a){
            $amount=$a->type==='asset'?(float)$a->debit-(float)$a->credit:(float)$a->credit-(float)$a->debit;
            $groups[$a->type][]=[$a->code,$a->name,$amount];
            $tot[$a->type]+=$amount;
        }

        $profitRows=DB::table('journal_entries as e')
            ->join('journals as j','j.id','=','e.journal_id')
            ->join('chart_of_accounts as a','a.id','=','e.account_id')
            ->where('j.entity_id',$entity)->where('j.status','posted')->where('j.journal_date','<=',$asOf)
            ->whereIn('a.type',['revenue','cogs','expense'])
            ->select('a.type',DB::raw('SUM(e.debit) as debit'),DB::raw('SUM(e.credit) as credit'))
            ->groupBy('a.type')->get();
        $profit=0;
        foreach($profitRows as $p) $profit += $p->type==='revenue'?(float)$p->credit-(float)$p->debit:(float)$p->debit-(float)$p->credit;
        if(abs($profit)>0.00001){$groups['equity'][]=['','Laba Tahun Berjalan',$profit];$tot['equity']+=$profit;}

        $entityRow=DB::table('entities')->where('id',$entity)->first();
        $e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
        $num=fn($v)=>number_format((float)$v,2,'.','');
        $html='<html><head><meta charset="UTF-8"><style>body{font-family:Arial}table{border-collapse:collapse}th,td{border:1px solid #000;padding:5px}.right{text-align:right}.bold{font-weight:700}</style></head><body>';
        $html.='<div><b>'.$e($entityRow?->name??'MINI ERP').'</b></div>';
        if(!empty($entityRow?->address))$html.='<div>'.$e($entityRow->address).'</div>';
        $html.='<h4>NERACA</h4><div>Per tanggal: '.$e(date('d-m-Y',strtotime($asOf))).'</div><br><table><tr><th colspan="2">AKTIVA</th><th>Jumlah</th></tr><tr><th colspan="2">Asset</th><th>Jumlah</th></tr>';
        foreach($groups['asset'] as $x)$html.='<tr><td>'.$e($x[0]).'</td><td>'.$e($x[1]).'</td><td class="right">'.$num($x[2]).'</td></tr>';
        $html.='<tr class="bold"><td colspan="2">TOTAL AKTIVA</td><td class="right">'.$num($tot['asset']).'</td></tr><tr><th colspan="2">PASSIVA</th><th>Jumlah</th></tr><tr><th colspan="2">Hutang</th><th>Jumlah</th></tr>';
        foreach($groups['liability'] as $x)$html.='<tr><td>'.$e($x[0]).'</td><td>'.$e($x[1]).'</td><td class="right">'.$num($x[2]).'</td></tr>';
        $html.='<tr class="bold"><td colspan="2">TOTAL HUTANG</td><td class="right">'.$num($tot['liability']).'</td></tr><tr><th colspan="2">Modal</th><th>Jumlah</th></tr>';
        foreach($groups['equity'] as $x)$html.='<tr><td>'.$e($x[0]).'</td><td>'.$e($x[1]).'</td><td class="right">'.$num($x[2]).'</td></tr>';
        $html.='<tr class="bold"><td colspan="2">TOTAL MODAL</td><td class="right">'.$num($tot['equity']).'</td></tr><tr class="bold"><td colspan="2">TOTAL PASSIVA</td><td class="right">'.$num($tot['liability']+$tot['equity']).'</td></tr>';
        $html.='</table></body></html>';
        return response($html)->header('Content-Type','application/vnd.ms-excel; charset=UTF-8')->header('Content-Disposition','attachment; filename="neraca_'.$asOf.'.xls"');
    }

    public function exportProfitLossExcel(Request $request)
    {
        $entity = $this->entityId();
        $start = $request->input('start_date', now()->startOfMonth()->toDateString());
        $end = $request->input('end_date', now()->endOfMonth()->toDateString());

        abort_if($start > $end, 422, 'Periode tanggal tidak valid.');

        $journalTotals = DB::table('journal_entries as e')
            ->join('journals as j','j.id','=','e.journal_id')
            ->where('j.entity_id',$entity)
            ->where('j.status','posted')
            ->whereBetween('j.journal_date',[$start,$end])
            ->groupBy('e.account_id')
            ->select('e.account_id',
                DB::raw('SUM(e.debit) as debit'),
                DB::raw('SUM(e.credit) as credit'));

        $accounts = DB::table('chart_of_accounts as a')
            ->leftJoinSub($journalTotals, 'jt', function ($join) {
                $join->on('jt.account_id','=','a.id');
            })
            ->where('a.entity_id',$entity)
            ->where('a.is_active',1)
            ->whereIn('a.type',['revenue','cogs','expense'])
            ->orderBy('a.code')
            ->select('a.id','a.parent_id','a.code','a.name','a.type','a.level',
                DB::raw('COALESCE(jt.debit,0) as debit'),
                DB::raw('COALESCE(jt.credit,0) as credit'))
            ->get();

        $lines = [];
        $totals = ['revenue'=>0, 'cogs'=>0, 'expense'=>0];

        foreach ($accounts as $a) {
            $amount = $a->type === 'revenue'
                ? (float)$a->credit - (float)$a->debit
                : (float)$a->debit - (float)$a->credit;

            if ((int)$a->level === 3) {
                $totals[$a->type] += $amount;
                $lines[$a->code] = $amount;
            }
        }

        $entityRow = DB::table('entities')->where('id',$entity)->first();
        $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $num = fn($v) => number_format((float)$v, 2, '.', '');

        $html = '<html><head><meta charset="UTF-8"><style>
            body{font-family:Arial,sans-serif}
            .kop{font-size:16px;font-weight:700}
            .judul{font-size:14px;font-weight:700}
            .periode{font-size:12px}
            table{border-collapse:collapse}
            th,td{border:1px solid #000;padding:5px}
            th{font-weight:700}
            .right{text-align:right}
            .bold{font-weight:700}
        </style></head><body>';
        $html .= '<div class="kop">'.$e($entityRow?->name ?? 'MINI ERP').'</div>';
        if (!empty($entityRow?->address)) $html .= '<div>'.$e($entityRow->address).'</div>';
        if (!empty($entityRow?->phone)) $html .= '<div>Telp. '.$e($entityRow->phone).'</div>';
        $html .= '<br><div class="judul">LAPORAN LABA RUGI</div>';
        $html .= '<div class="periode">Periode: '.$e(date('d-m-Y', strtotime($start))).' s/d '.$e(date('d-m-Y', strtotime($end))).'</div>';
        $html .= '<br><table><thead><tr><th>Kode</th><th>Nama Akun</th><th>Jumlah</th></tr></thead><tbody>';

        $groups = [
            'revenue' => 'PENDAPATAN',
            'cogs' => 'HPP',
            'expense' => 'BIAYA',
        ];

        foreach ($groups as $type => $label) {
            $html .= '<tr class="bold"><td colspan="2">'.$e($label).'</td><td></td></tr>';
            foreach ($accounts as $a) {
                if ($a->type !== $type) continue;
                $level = (int)$a->level;
                $amount = $level === 3 ? ($lines[$a->code] ?? 0) : null;
                $indent = max(0, $level - 1) * 20;
                $html .= '<tr>';
                $html .= '<td style="padding-left:'.(5 + $indent).'px">'.$e($a->code).'</td>';
                $html .= '<td class="'.($level < 3 ? 'bold' : '').'" style="padding-left:'.(5 + $indent).'px">'.$e($a->name).'</td>';
                $html .= '<td class="right">'.($amount === null ? '' : $num($amount)).'</td>';
                $html .= '</tr>';
            }
            $total = $totals[$type];
            $html .= '<tr class="bold"><td colspan="2">TOTAL '.$e($label).'</td><td class="right">'.$num($total).'</td></tr>';
            if ($type === 'cogs') {
                $gross = $totals['revenue'] - $totals['cogs'];
                $html .= '<tr class="bold"><td colspan="2">LABA KOTOR</td><td class="right">'.$num($gross).'</td></tr>';
            }
        }

        $net = $totals['revenue'] - $totals['cogs'] - $totals['expense'];
        $html .= '<tr class="bold"><td colspan="2">LABA / (RUGI) BERSIH</td><td class="right">'.$num($net).'</td></tr>';
        $html .= '</tbody></table></body></html>';

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laba-rugi_'.$start.'_'.$end.'.xls"',
        ]);
    }

    private function report(string $module, int $entity): array
    {
        $result = ['lines'=>[], 'total'=>0];
        $startDate = request()->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = request()->input('end_date', now()->endOfMonth()->toDateString());

        if ($module === 'ledger') {
            $result['lines'] = DB::table('journal_entries as e')
                ->join('journals as j','j.id','=','e.journal_id')
                ->join('chart_of_accounts as a','a.id','=','e.account_id')
                ->where('j.entity_id',$entity)->where('j.status','posted')
                ->whereBetween('j.journal_date',[$startDate,$endDate])
                ->orderBy('j.journal_date')->orderBy('e.id')
                ->select('j.journal_date','j.journal_no','j.description','a.code','a.name','e.debit','e.credit')->get();
        } elseif ($module === 'receivables') {
            $result['lines'] = DB::table('sales')->where('entity_id',$entity)->orderByDesc('sale_date')->get();
            $result['total'] = (float) DB::table('sales')->where('entity_id',$entity)->sum('total');
        } elseif ($module === 'cashbank') {
            $result['lines'] = DB::table('payments')->where('entity_id',$entity)->orderByDesc('payment_date')->get();
            $result['total'] = (float) DB::table('payments')->where('entity_id',$entity)->sum('amount');
        } elseif ($module === 'cogs') {
            $result['lines'] = DB::table('productions')->where('entity_id',$entity)->orderByDesc('production_date')->paginate(15)->withQueryString();
            $result['total'] = (float) DB::table('productions')->where('entity_id',$entity)->sum('total_cost');
        } elseif ($module === 'profit-loss') {
            // Ambil seluruh hirarki COA Laba Rugi. Akun detail tetap menampilkan nilai 0
            // jika belum ada jurnal pada periode yang dipilih.
            $journalTotals = DB::table('journal_entries as e')
                ->join('journals as j','j.id','=','e.journal_id')
                ->where('j.entity_id',$entity)
                ->where('j.status','posted')
                ->whereBetween('j.journal_date',[$startDate,$endDate])
                ->groupBy('e.account_id')
                ->select('e.account_id',
                    DB::raw('SUM(e.debit) as debit'),
                    DB::raw('SUM(e.credit) as credit'));

            $accounts = DB::table('chart_of_accounts as a')
                ->leftJoinSub($journalTotals, 'jt', function ($join) {
                    $join->on('jt.account_id','=','a.id');
                })
                ->where('a.entity_id',$entity)
                ->where('a.is_active',1)
                ->whereIn('a.type',['revenue','cogs','expense'])
                ->orderBy('a.code')
                ->select('a.id','a.parent_id','a.code','a.name','a.type','a.level',
                    DB::raw('COALESCE(jt.debit,0) as debit'),
                    DB::raw('COALESCE(jt.credit,0) as credit'))
                ->get();

            $revenueTotal = 0;
            $cogsTotal = 0;
            $expenseTotal = 0;

            foreach ($accounts as $a) {
                $amount = $a->type === 'revenue'
                    ? (float)$a->credit - (float)$a->debit
                    : (float)$a->debit - (float)$a->credit;

                if ((int)$a->level === 3) {
                    if ($a->type === 'revenue') $revenueTotal += $amount;
                    elseif ($a->type === 'cogs') $cogsTotal += $amount;
                    else $expenseTotal += $amount;

                    $result['lines'][] = [
                        'code'=>$a->code,
                        'label'=>$a->name,
                        'type'=>$a->type,
                        'amount'=>$amount,
                        'level'=>(int)$a->level,
                        'parent_id'=>$a->parent_id,
                    ];
                }
            }

            // Hirarki untuk tampilan laporan: hanya kelompok yang memang memiliki
            // turunan Laba Rugi aktif.
            $plAccounts = $accounts->filter(function ($a) {
                return in_array($a->type,['revenue','cogs','expense'],true);
            })->values();

            $result['coa_hierarchy'] = $plAccounts->map(function ($a) use ($accounts) {
                return [
                    'id'=>$a->id,
                    'parent_id'=>$a->parent_id,
                    'code'=>$a->code,
                    'name'=>$a->name,
                    'type'=>$a->type,
                    'level'=>(int)$a->level,
                ];
            })->all();

            $grossProfit = $revenueTotal - $cogsTotal;
            $netProfit = $grossProfit - $expenseTotal;

            $result['revenue'] = $revenueTotal;
            $result['cogs'] = $cogsTotal;
            $result['expense'] = $expenseTotal;
            $result['gross_profit'] = $grossProfit;
            $result['net_profit'] = $netProfit;
            $result['total'] = $netProfit;
        } elseif ($module === 'trial-balance') {
            // Neraca saldo: saldo awal sebelum periode + mutasi selama periode.
            $accounts = DB::table('chart_of_accounts as a')
                ->where('a.entity_id',$entity)->where('a.is_active',1)->where('a.level',3)
                ->orderBy('a.code')->get(['a.id','a.code','a.name']);

            $baseQuery = DB::table('journal_entries as e')
                ->join('journals as j','j.id','=','e.journal_id')
                ->where('j.entity_id',$entity)->where('j.status','posted');

            $opening = (clone $baseQuery)->where('j.journal_date','<',$startDate)
                ->groupBy('e.account_id')->select('e.account_id',DB::raw('SUM(e.debit) debit'),DB::raw('SUM(e.credit) credit'))->get()->keyBy('account_id');

            $period = (clone $baseQuery)->whereBetween('j.journal_date',[$startDate,$endDate])
                ->groupBy('e.account_id')->select('e.account_id',DB::raw('SUM(e.debit) debit'),DB::raw('SUM(e.credit) credit'))->get()->keyBy('account_id');

            $openingTotal = 0; $debitTotal = 0; $creditTotal = 0; $balanceTotal = 0;
            foreach ($accounts as $a) {
                $op = $opening->get($a->id);
                $pr = $period->get($a->id);
                $saldoAwal = (float)($op->debit ?? 0) - (float)($op->credit ?? 0);
                $debit = (float)($pr->debit ?? 0);
                $credit = (float)($pr->credit ?? 0);
                $saldoAkhir = $saldoAwal + $debit - $credit;
                $openingTotal += $saldoAwal; $debitTotal += $debit; $creditTotal += $credit; $balanceTotal += $saldoAkhir;
                $result['lines'][] = ['code'=>$a->code,'label'=>$a->name,'opening'=>$saldoAwal,'debit'=>$debit,'credit'=>$credit,'balance'=>$saldoAkhir];
            }
            $result['opening_total']=$openingTotal; $result['debit_total']=$debitTotal; $result['credit_total']=$creditTotal; $result['balance_total']=$balanceTotal;
            $result['total']=$balanceTotal;
        } elseif ($module === 'balance-sheet') {
            // Neraca: posisi akun neraca per tanggal yang dipilih.
            $asOf = $endDate;

            $totals = DB::table('journal_entries as e')
                ->join('journals as j','j.id','=','e.journal_id')
                ->where('j.entity_id',$entity)
                ->where('j.status','posted')
                ->where('j.journal_date','<=',$asOf)
                ->groupBy('e.account_id')
                ->select('e.account_id',DB::raw('SUM(e.debit) as debit'),DB::raw('SUM(e.credit) as credit'));

            $accounts = DB::table('chart_of_accounts as a')
                ->leftJoinSub($totals,'jt',fn($join)=>$join->on('jt.account_id','=','a.id'))
                ->where('a.entity_id',$entity)
                ->where('a.is_active',1)
                ->where('a.level',3)
                ->whereIn('a.type',['asset','liability','equity'])
                ->orderBy('a.code')
                ->select('a.id','a.code','a.name','a.type',
                    DB::raw('COALESCE(jt.debit,0) as debit'),
                    DB::raw('COALESCE(jt.credit,0) as credit'))
                ->get();

            $lines=['asset'=>[],'liability'=>[],'equity'=>[]];
            $totalsByType=['asset'=>0,'liability'=>0,'equity'=>0];

            foreach($accounts as $a){
                $amount = $a->type === 'asset'
                    ? (float)$a->debit - (float)$a->credit
                    : (float)$a->credit - (float)$a->debit;
                $lines[$a->type][]=[
                    'code'=>$a->code,'label'=>$a->name,'amount'=>$amount
                ];
                $totalsByType[$a->type]+=$amount;
            }

            // Pendapatan, HPP, dan biaya yang belum ditutup ke laba ditahan
            // menjadi "Laba Tahun Berjalan" pada sisi ekuitas.
            $profitRows = DB::table('journal_entries as e')
                ->join('journals as j','j.id','=','e.journal_id')
                ->join('chart_of_accounts as a','a.id','=','e.account_id')
                ->where('j.entity_id',$entity)
                ->where('j.status','posted')
                ->where('j.journal_date','<=',$asOf)
                ->whereIn('a.type',['revenue','cogs','expense'])
                ->select('a.type',DB::raw('SUM(e.debit) as debit'),DB::raw('SUM(e.credit) as credit'))
                ->groupBy('a.type')
                ->get();

            $profit=0;
            foreach($profitRows as $p){
                $profit += $p->type === 'revenue'
                    ? (float)$p->credit-(float)$p->debit
                    : (float)$p->debit-(float)$p->credit;
            }
            if(abs($profit)>0.00001){
                $lines['equity'][]=['code'=>'','label'=>'Laba Tahun Berjalan','amount'=>$profit];
                $totalsByType['equity'] += $profit;
            }

            $result['balance_sheet_lines']=$lines;
            $result['balance_sheet_totals']=$totalsByType;
            $result['total_assets']=$totalsByType['asset'];
            $result['total_liabilities_equity']=$totalsByType['liability']+$totalsByType['equity'];
            $result['balance_difference']=$totalsByType['asset']-$result['total_liabilities_equity'];
            $result['total']=$totalsByType['asset'];
        } elseif ($module === 'cash-flow') {
            // Format Arus Kas dikunci terlebih dahulu. Logika dinamis akan diisi
            // setelah jurnal, COA, dan mapping akun kas/bank selesai.
            $result['cash_flow'] = [
                'operating' => [
                    'Penerimaan dari Penjualan' => 0,
                    'Penerimaan dari Customer' => 0,
                    'Pembayaran kepada Supplier' => 0,
                    'Pembayaran Beban Operasional' => 0,
                    'Pembayaran Biaya Operasional Lainnya' => 0,
                ],
                'investing' => [
                    'Pembelian Kendaraan' => 0,
                    'Pembelian Peralatan' => 0,
                    'Pembelian Aset Tetap' => 0,
                    'Penjualan Aset Tetap' => 0,
                ],
                'financing' => [
                    'Setoran Modal' => 0,
                    'Pengambilan Modal' => 0,
                    'Penerimaan Pinjaman' => 0,
                    'Pembayaran Pinjaman' => 0,
                ],
                'operating_total' => 0,
                'investing_total' => 0,
                'financing_total' => 0,
                'net_change' => 0,
                'opening_cash' => 0,
                'closing_cash' => 0,
            ];
            $result['total'] = 0;
        }
        return $result;
    }



    public function exportCashFlowExcel(Request $request)
    {
        $entity = $this->entityId();
        $entityRow = DB::table('entities')->where('id',$entity)->first();
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        $rows = [
            ['ARUS KAS DARI AKTIVITAS OPERASI', ''],
            ['Penerimaan dari Penjualan', 0],
            ['Penerimaan dari Customer', 0],
            ['Pembayaran kepada Supplier', 0],
            ['Pembayaran Beban Operasional', 0],
            ['Pembayaran Biaya Operasional Lainnya', 0],
            ['TOTAL ARUS KAS OPERASI', 0],
            ['', ''],
            ['ARUS KAS DARI AKTIVITAS INVESTASI', ''],
            ['Pembelian Kendaraan', 0],
            ['Pembelian Peralatan', 0],
            ['Pembelian Aset Tetap', 0],
            ['Penjualan Aset Tetap', 0],
            ['TOTAL ARUS KAS INVESTASI', 0],
            ['', ''],
            ['ARUS KAS DARI AKTIVITAS PENDANAAN', ''],
            ['Setoran Modal', 0],
            ['Pengambilan Modal', 0],
            ['Penerimaan Pinjaman', 0],
            ['Pembayaran Pinjaman', 0],
            ['TOTAL ARUS KAS PENDANAAN', 0],
            ['KENAIKAN / (PENURUNAN) KAS', 0],
            ['SALDO KAS AWAL', 0],
            ['SALDO KAS AKHIR', 0],
        ];

        $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $html = '<html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;font-size:11pt}table{border-collapse:collapse;width:100%}th,td{border:1px solid #000;padding:6px}th{font-weight:700}.right{text-align:right}.bold{font-weight:700}.section{font-weight:700;background:#eee}</style></head><body>';
        $html .= '<div style="text-align:center;font-weight:700;font-size:14pt;">'.$e($entityRow?->name ?? 'MINI ERP').'</div>';
        $html .= '<div style="text-align:center;font-weight:700;font-size:13pt;">LAPORAN ARUS KAS</div>';
        $html .= '<div style="text-align:center;">Periode '.$e($startDate).' s/d '.$e($endDate).'</div><br>';
        $html .= '<table><thead><tr><th>Uraian</th><th>Jumlah (Rp)</th></tr></thead><tbody>';
        foreach($rows as $row){
            $label=$row[0]; $value=$row[1];
            $isSection=in_array($label,['ARUS KAS DARI AKTIVITAS OPERASI','ARUS KAS DARI AKTIVITAS INVESTASI','ARUS KAS DARI AKTIVITAS PENDANAAN'],true);
            $isTotal=str_starts_with($label,'TOTAL ') || in_array($label,['KENAIKAN / (PENURUNAN) KAS','SALDO KAS AWAL','SALDO KAS AKHIR'],true);
            $cls=($isSection?'section ':'').($isTotal?'bold':'');
            $html.='<tr class="'.$cls.'"><td>'.$e($label).'</td><td class="right">'.($label!==''?'Rp '.number_format((float)$value,0,',','.'):'').'</td></tr>';
        }
        $html .= '</tbody></table></body></html>';
        return response($html,200,['Content-Type'=>'application/vnd.ms-excel; charset=UTF-8','Content-Disposition'=>'attachment; filename="arus-kas-'.$startDate.'-'.$endDate.'.xls"']);
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
