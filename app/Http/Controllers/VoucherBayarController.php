<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VoucherBayarController extends Controller
{
    private function entityId(): int { return (int) (auth()->user()?->entity_id ?? 0); }

    private function availableWagesQuery()
    {
        return DB::table('production_costs as pc')
            ->join('productions as p', 'p.id', '=', 'pc.production_id')
            ->join('production_work_orders as wo', 'wo.id', '=', 'p.production_work_order_id')
            ->join('production_work_order_workers as wow', 'wow.id', '=', 'pc.reference_id')
            ->join('workers as w', 'w.id', '=', 'wow.worker_id')
            ->join('business_units as bu', 'bu.id', '=', 'p.business_unit_id')
            ->leftJoin('voucher_bayar_items as vbi', 'vbi.production_cost_id', '=', 'pc.id')
            ->where('p.entity_id', $this->entityId())->where('p.status', 'posted')
            ->where('pc.cost_group', 'U')->where('pc.source', 'work_order_result')
            ->where('pc.reference_type', 'production_work_order_worker')->whereNull('vbi.id')
            ->select(['pc.id as wage_id','pc.amount','pc.description as wage_description','p.id as production_id',
                'p.production_no','p.production_date','p.business_unit_id','bu.name as business_unit_name',
                'wo.id as work_order_id','wo.wo_no','wow.worker_id','w.name as worker_name']);
    }

    public function postingUpah()
    {
        $wages = $this->availableWagesQuery()->orderBy('w.name')->orderBy('p.production_date')->orderBy('p.id')->get();
        $workerGroups = $wages->groupBy(fn($r) => $r->worker_id.'-'.$r->business_unit_id);
        return view('inventori.produksi.posting-upah.index', compact('workerGroups'));
    }

    public function storeUpahVoucher(Request $request)
    {
        $data = $request->validate([
            'worker_id'=>['required','integer','min:1'], 'business_unit_id'=>['required','integer','min:1'],
            'wage_ids'=>['required','array','min:1'], 'wage_ids.*'=>['required','integer','distinct'],
            'scheduled_payment_date'=>['required','date'], 'description'=>['nullable','string','max:255'],
        ]);
        try {
            $voucherId = DB::transaction(function () use ($data) {
                $ids = array_map('intval', $data['wage_ids']);
                $rows = $this->availableWagesQuery()->whereIn('pc.id',$ids)
                    ->where('wow.worker_id',(int)$data['worker_id'])
                    ->where('p.business_unit_id',(int)$data['business_unit_id'])
                    ->lockForUpdate()->get();
                abort_unless($rows->count() === count(array_unique($ids)), 422, 'Sebagian upah sudah masuk voucher atau bukan milik entitas ini.');
                abort_unless($rows->isNotEmpty(), 422, 'Tidak ada upah yang bisa diajukan.');
                $total = round((float)$rows->sum('amount'),2);
                abort_unless($total > 0, 422, 'Total upah harus lebih dari nol.');
                $now = now();
                $id = DB::table('voucher_bayar')->insertGetId([
                    'entity_id'=>$this->entityId(),'business_unit_id'=>(int)$data['business_unit_id'],
                    'voucher_no'=>'TMP-'.Str::upper(Str::random(16)),'category'=>'UPAH_PRODUKSI',
                    'worker_id'=>(int)$data['worker_id'],'request_date'=>$now->toDateString(),
                    'scheduled_payment_date'=>$data['scheduled_payment_date'],
                    'description'=>trim((string)($data['description']??'')) ?: 'Permohonan pembayaran upah produksi',
                    'total_amount'=>$total,'paid_amount'=>0,'status'=>'MENUNGGU_PEMBAYARAN',
                    'created_by'=>auth()->id(),'created_at'=>$now,'updated_at'=>$now,
                ]);
                DB::table('voucher_bayar')->where('id',$id)->update([
                    'voucher_no'=>'VU-'.$now->format('Ymd').'-'.str_pad((string)$id,6,'0',STR_PAD_LEFT),'updated_at'=>$now
                ]);
                $items = [];
                foreach ($rows as $r) $items[] = [
                    'voucher_bayar_id'=>$id,'source_type'=>'PRODUCTION_COST','source_id'=>(int)$r->wage_id,
                    'production_cost_id'=>(int)$r->wage_id,'production_id'=>(int)$r->production_id,
                    'work_order_id'=>(int)$r->work_order_id,'worker_id'=>(int)$r->worker_id,
                    'description'=>$r->wo_no.' • '.$r->production_no.' • '.$r->production_date,
                    'amount'=>round((float)$r->amount,2),'created_at'=>$now,'updated_at'=>$now
                ];
                DB::table('voucher_bayar_items')->insert($items);
                return $id;
            });
            $number = DB::table('voucher_bayar')->where('id',$voucherId)->value('voucher_no');
            return redirect()->route('produksi.posting-upah.index')->with('success','Permohonan pembayaran berhasil dibuat: '.$number);
        } catch (\Throwable $e) {
            report($e);
            return redirect()->back()->withInput()->with('error','Gagal membuat voucher upah: '.$e->getMessage());
        }
    }

    public function index(Request $request)
    {
        $category = $request->query('category'); $status = $request->query('status');
        $q = DB::table('voucher_bayar as v')->leftJoin('workers as w','w.id','=','v.worker_id')
            ->join('business_units as bu','bu.id','=','v.business_unit_id')->where('v.entity_id',$this->entityId())
            ->select(['v.*','w.name as worker_name','bu.name as business_unit_name',
                DB::raw('(SELECT COUNT(*) FROM voucher_bayar_items i WHERE i.voucher_bayar_id = v.id) as item_count'),
                DB::raw("(SELECT GROUP_CONCAT(DISTINCT wo.wo_no ORDER BY wo.wo_no SEPARATOR ', ') FROM voucher_bayar_items i JOIN production_work_orders wo ON wo.id = i.work_order_id WHERE i.voucher_bayar_id = v.id) as spk_list")]);
        if (in_array($category,['UPAH_PRODUKSI','HUTANG_SUPPLIER','OPERASIONAL','LAINNYA'],true)) $q->where('v.category',$category);
        if (in_array($status,['MENUNGGU_PEMBAYARAN','DIBAYAR_SEBAGIAN','LUNAS'],true)) $q->where('v.status',$status);
        $vouchers = $q->orderByRaw("CASE WHEN v.status = 'LUNAS' THEN 1 ELSE 0 END")
            ->orderBy('v.scheduled_payment_date')->orderByDesc('v.id')->paginate(20)->withQueryString();
        $cashAccounts = DB::table('chart_of_accounts')->where('entity_id',$this->entityId())
            ->where('is_cash_bank',1)->where('is_postable',1)->where('is_active',1)->orderBy('code')->get(['id','code','name']);
        return view('keuangan.voucher.index',compact('vouchers','cashAccounts','category','status'));
    }

    public function pay(Request $request, int $id)
    {
        $data = $request->validate([
            'payment_date'=>['required','date'],'cash_account_id'=>['required','integer','min:1'],'amount'=>['required','numeric','gt:0']
        ]);
        try {
            DB::transaction(function () use ($data,$id) {
                $entityId=$this->entityId();
                $v=DB::table('voucher_bayar')->where('entity_id',$entityId)->where('id',$id)->lockForUpdate()->first();
                abort_unless($v,404,'Voucher tidak ditemukan.');
                abort_unless($v->category==='UPAH_PRODUKSI',422,'Pembayaran kategori ini belum diaktifkan.');
                abort_unless($v->status!=='LUNAS',422,'Voucher sudah lunas.');
                $remaining=round((float)$v->total_amount-(float)$v->paid_amount,2);
                $amount=round((float)$data['amount'],2);
                abort_unless($amount>0 && $amount<=$remaining,422,'Nominal melebihi sisa voucher.');
                $cash=DB::table('chart_of_accounts')->where('entity_id',$entityId)->where('id',(int)$data['cash_account_id'])
                    ->where('is_cash_bank',1)->where('is_postable',1)->where('is_active',1)->first();
                abort_unless($cash,422,'Akun kas/bank tidak valid untuk entitas ini.');
                $payableId=DB::table('business_unit_account_mappings')->where('entity_id',$entityId)
                    ->where('business_unit_id',$v->business_unit_id)->where('mapping_key','salary_payable')->value('account_id');
                $payable=DB::table('chart_of_accounts')->where('entity_id',$entityId)->where('id',$payableId)
                    ->where('is_active',1)->where('is_postable',1)->first();
                abort_unless($payable,422,'Mapping akun Hutang Gaji belum tersedia atau tidak valid.');
                $now=now();
                $paymentId=DB::table('voucher_bayar_payments')->insertGetId([
                    'voucher_bayar_id'=>$v->id,'payment_date'=>$data['payment_date'],'cash_account_id'=>$cash->id,
                    'amount'=>$amount,'created_by'=>auth()->id(),'created_at'=>$now,'updated_at'=>$now
                ]);
                $journalId=DB::table('journals')->insertGetId([
                    'entity_id'=>$entityId,'business_unit_id'=>$v->business_unit_id,
                    'journal_no'=>'VBP-'.$now->format('YmdHis').'-'.Str::upper(Str::random(4)),
                    'journal_date'=>$data['payment_date'],'source_type'=>'VOUCHER_PAYMENT','source_id'=>$paymentId,
                    'description'=>'Pembayaran '.$v->voucher_no.' - '.$v->description,'status'=>'posted',
                    'created_at'=>$now,'updated_at'=>$now
                ]);
                DB::table('journal_entries')->insert([
                    ['journal_id'=>$journalId,'account_id'=>$payable->id,'debit'=>$amount,'credit'=>0,'created_at'=>$now,'updated_at'=>$now],
                    ['journal_id'=>$journalId,'account_id'=>$cash->id,'debit'=>0,'credit'=>$amount,'created_at'=>$now,'updated_at'=>$now]
                ]);
                DB::table('voucher_bayar_payments')->where('id',$paymentId)->update(['journal_id'=>$journalId,'updated_at'=>$now]);
                $paid=round((float)$v->paid_amount+$amount,2);
                DB::table('voucher_bayar')->where('id',$v->id)->update([
                    'paid_amount'=>$paid,'status'=>$paid >= (float)$v->total_amount ? 'LUNAS' : 'DIBAYAR_SEBAGIAN','updated_at'=>$now
                ]);
            });
            return redirect()->route('keuangan.voucher.index')->with('success','Pembayaran berhasil disimpan dan jurnal pelunasan hutang gaji diposting.');
        } catch (\Throwable $e) {
            report($e);
            return redirect()->back()->withInput()->with('error','Pembayaran voucher gagal: '.$e->getMessage());
        }
    }
}
