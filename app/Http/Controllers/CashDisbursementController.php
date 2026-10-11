<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use App\Models\BusinessUnit;
use App\Models\Supplier;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Exports\CashDisbursementExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;

class CashDisbursementController extends Controller
{
    private function entityId(): int
    {
        $entityId = (int) (auth()->user()->entity_id ?? 0);
        abort_unless($entityId > 0, 403, 'Entitas pengguna tidak valid.');
        return $entityId;
    }

    public function index(Request $request)
    {
        $startDate      = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate        = $request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId = $request->query('business_unit_id');
        $cashAccountId  = $request->query('cash_account_id');
        $disbursementType = $request->query('disbursement_type'); // Kategori: AP_PAYMENT / CASH_OUT
        $search         = $request->query('search');

        $entityId = $this->entityId();
        $businessUnits = BusinessUnit::where('entity_id', $entityId)->where('is_active', 1)->orderBy('code')->get();
        $suppliers     = Supplier::where('entity_id', $entityId)->where('is_active', 1)->orderBy('name')->get();

        // 1. Dropdown Akun Kas & Bank (is_cash_bank = 1)
        $cashAccounts = ChartOfAccount::where('entity_id', $entityId)->where('is_cash_bank', 1)
            ->where('is_postable', 1)
            ->where('is_active', 1)
            ->orderBy('code')
            ->get();

        // 2. Dropdown COA Hierarki Level 2 (Header Group) & Level 3 (Postable Child)
        $coaLevel2 = ChartOfAccount::where('entity_id', $entityId)->where('level', 2)
            ->where('is_active', 1)
            ->with(['children' => function ($query) {
                $query->where('level', 3)
                      ->where('is_postable', 1)
                      ->where('is_active', 1)
                      ->orderBy('code');
            }])
            ->orderBy('code')
            ->get();

        // Base Query Filter
        $baseQuery = Journal::with(['businessUnit', 'entries.account'])
            ->where('entity_id', $entityId)
            ->whereIn('source_type', ['CASH_OUT', 'AP_PAYMENT', 'sales_return'])
            ->whereDate('journal_date', '>=', $startDate)
            ->whereDate('journal_date', '<=', $endDate);

        if (!empty($businessUnitId)) {
            $baseQuery->where('business_unit_id', $businessUnitId);
        }

        if (!empty($disbursementType)) {
            $baseQuery->where('source_type', $disbursementType);
        }

        if (!empty($cashAccountId)) {
            $baseQuery->whereHas('entries', function ($q) use ($cashAccountId) {
                $q->where('account_id', $cashAccountId)->where('credit', '>', 0);
            });
        }

        if (!empty($search)) {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('journal_no', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Hitung Ringkasan KPI Seluruh Data Terfilter
        $allFilteredData        = (clone $baseQuery)->get();
        $totalDisbursementAmount = 0;
        $totalApPayment         = 0;
        $totalOtherDisbursement = 0;

        foreach ($allFilteredData as $journal) {
            $cashEntry = $journal->entries->where('credit', '>', 0)->first();
            $amount    = (float) ($cashEntry?->credit ?? 0);
            $totalDisbursementAmount += $amount;

            if ($journal->source_type === 'AP_PAYMENT') {
                $totalApPayment += $amount;
            } else {
                $totalOtherDisbursement += $amount;
            }
        }

        // Server-Side Pagination
        $disbursements = $baseQuery->orderBy('journal_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('keuangan.kas-bank.keluar.index', compact(
            'businessUnits',
            'suppliers',
            'cashAccounts',
            'coaLevel2',
            'startDate',
            'endDate',
            'businessUnitId',
            'cashAccountId',
            'disbursementType',
            'search',
            'disbursements',
            'totalDisbursementAmount',
            'totalApPayment',
            'totalOtherDisbursement'
        ));
    }

    /**
     * AJAX Endpoint: Ambil Faktur Pembelian Belum Lunas Milik Supplier
     */
    public function getUnpaidBills($supplierId)
    {
        $entityId = $this->entityId();
        abort_unless(DB::table('suppliers')->where('entity_id', $entityId)->where('id', $supplierId)->where('is_active', 1)->exists(), 404, 'Supplier tidak ditemukan.');
        $bills = DB::table('purchases as p')
            ->leftJoinSub(
                DB::table('payments')
                    ->select('purchase_id', DB::raw('COALESCE(SUM(paid_amount), 0) as paid_amount'))
                    ->whereNotNull('purchase_id')
                    ->groupBy('purchase_id'),
                'pay',
                'pay.purchase_id',
                '=',
                'p.id'
            )
            ->where('p.entity_id', $entityId)
            ->where('p.supplier_id', $supplierId)
            ->where('p.payment_method', 'credit')
            ->where('p.status', 'posted')
            ->select(
                'p.id',
                'p.purchase_no',
                'p.purchase_date',
                'p.total',
                DB::raw('COALESCE(pay.paid_amount, 0) as paid_amount'),
                DB::raw('GREATEST(p.total - COALESCE(pay.paid_amount, 0), 0) as remaining_amount')
            )
            ->whereRaw('GREATEST(p.total - COALESCE(pay.paid_amount, 0), 0) > 0')
            ->orderBy('p.purchase_date', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'bills'   => $bills
        ]);
    }

    /**
     * Action 1: Simpan Pembayaran Hutang Vendor (AP Payment)
     */
    public function storeApPayment(Request $request)
    {
        $request->validate([
            'business_unit_id' => 'required|integer',
            'supplier_id'      => 'required|integer',
            'journal_date'     => 'required|date',
            'cash_account_id'  => 'required|integer',
            'amount'           => 'required|numeric|min:1',
            'purchase_ids'     => 'required|array|min:1',
            'purchase_ids.*'   => 'required|integer',
            'pay_amounts'      => 'required|array',
            'description'      => 'required|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $entityId = $this->entityId();
            $businessUnit = DB::table('business_units')
                ->where('entity_id', $entityId)->where('id', $request->business_unit_id)->where('is_active', 1)->first();
            $supplier = DB::table('suppliers')
                ->where('entity_id', $entityId)->where('id', $request->supplier_id)->where('is_active', 1)->first();
            abort_unless($businessUnit && $supplier, 422, 'Unit Bisnis atau supplier tidak aktif atau bukan milik entitas pengguna.');

            $period = DB::table('accounting_periods')
                ->where('entity_id', $entityId)
                ->where('period_year', \Carbon\Carbon::parse($request->journal_date)->year)
                ->where('period_month', \Carbon\Carbon::parse($request->journal_date)->month)
                ->first();
            abort_if($period && in_array($period->status, ['closing', 'closed'], true), 422, 'Periode akuntansi sudah closing atau closed.');

            $cashAccount = ChartOfAccount::where('entity_id', $entityId)
                ->where('id', $request->cash_account_id)
                ->where('is_cash_bank', 1)->where('is_postable', 1)->where('is_active', 1)->first();
            abort_unless($cashAccount, 422, 'Akun kas/bank tidak aktif atau bukan milik entitas pengguna.');

            $mappings = DB::table('business_unit_account_mappings')
                ->where('entity_id', $entityId)
                ->where('business_unit_id', $businessUnit->id)
                ->whereIn('mapping_key', ['payable', 'cash', 'bank'])
                ->pluck('account_id', 'mapping_key');
            abort_unless(isset($mappings['payable']), 422, 'Mapping akun hutang Unit Bisnis belum tersedia.');
            abort_unless(
                ((isset($mappings['cash']) && (int) $mappings['cash'] === (int) $cashAccount->id) ||
                 (isset($mappings['bank']) && (int) $mappings['bank'] === (int) $cashAccount->id)),
                422,
                'Akun kas/bank harus sesuai mapping Unit Bisnis aktif.'
            );
            $apAccount = ChartOfAccount::where('entity_id', $entityId)
                ->where('id', $mappings['payable'])->where('is_active', 1)->where('is_postable', 1)->first();
            abort_unless($apAccount, 422, 'Akun hutang pada mapping Unit Bisnis tidak valid.');

            $totalPaidActual = 0;

            foreach ($request->purchase_ids as $purchaseId) {
                $payAmount = (float) ($request->pay_amounts[$purchaseId] ?? 0);
                if ($payAmount <= 0) continue;

                $purchase = DB::table('purchases')
                    ->where('entity_id', $entityId)
                    ->where('id', $purchaseId)
                    ->where('business_unit_id', $businessUnit->id)
                    ->where('supplier_id', $supplier->id)
                    ->where('payment_method', 'credit')
                    ->where('status', 'posted')
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();
                abort_unless($purchase, 422, 'Faktur yang dibayar tidak valid atau berbeda entitas, supplier, atau Unit Bisnis.');

                $paidBefore = (float) DB::table('payments')->where('purchase_id', $purchaseId)->sum('paid_amount');
                $remaining = max(0, (float) $purchase->total - $paidBefore);
                abort_if($payAmount > $remaining + 0.000001, 422, 'Nominal pembayaran melebihi sisa hutang faktur '.$purchase->purchase_no.'.');

                $totalPaidActual += $payAmount;
                DB::table('payments')->insert([
                    'purchase_id'    => $purchaseId,
                    'payment_date'   => $request->journal_date,
                    'paid_amount'    => $payAmount,
                    'payment_method' => 'TRANSFER/CASH',
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);

                $totalPaidForPurchase = $paidBefore + $payAmount;
                $status = ($totalPaidForPurchase >= (float) $purchase->total - 0.000001) ? 'paid' : 'partial';
                DB::table('purchases')->where('entity_id', $entityId)->where('id', $purchaseId)->update([
                    'paid_amount'    => $totalPaidForPurchase,
                    'payment_status' => $status,
                    'updated_at'     => now()
                ]);
            }

            if ($totalPaidActual <= 0) {
                throw new \Exception('Nominal pembayaran pada faktur yang dicentang tidak boleh 0.');
            }
            abort_if(abs($totalPaidActual - (float) $request->amount) > 0.01, 422, 'Total pembayaran tidak sesuai dengan rincian faktur yang dipilih.');

            // 3. Simpan Header Jurnal Pengeluaran
            $journalNo = 'AP-' . date('YmdHis') . '-' . strtoupper(substr(uniqid(), -3));

            $journal = Journal::create([
                'entity_id'        => $entityId,
                'business_unit_id' => $businessUnit->id,
                'journal_no'       => $journalNo,
                'journal_date'     => $request->journal_date,
                'source_type'      => 'AP_PAYMENT',
                'source_id'        => null,
                'description'      => $request->description,
                'status'           => 'posted',
            ]);

            // Debit: Hutang Usaha
            JournalEntry::create([
                'journal_id' => $journal->id,
                'account_id' => $apAccount->id,
                'debit'      => $totalPaidActual,
                'credit'     => 0,
            ]);

            // Kredit: Kas/Bank Sumber Uang
            JournalEntry::create([
                'journal_id' => $journal->id,
                'account_id' => $cashAccount->id,
                'debit'      => 0,
                'credit'     => $totalPaidActual,
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Pembayaran Hutang Vendor berhasil disimpan & diposting ke Jurnal!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal memproses pembayaran hutang: ' . $e->getMessage());
        }
    }

    /**
     * Action 2: Simpan Pengeluaran Kas/Bank Umum
     */
    public function storeOtherDisbursement(Request $request)
    {
        $request->validate([
            'business_unit_id'       => 'required|integer',
            'journal_date'           => 'required|date',
            'cash_account_id'        => 'required|integer',
            'counterpart_account_id' => 'required|integer',
            'amount'                 => 'required|numeric|min:1',
            'description'            => 'required|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $entityId = $this->entityId();
            $businessUnit = DB::table('business_units')->where('entity_id', $entityId)->where('id', $request->business_unit_id)->where('is_active', 1)->first();
            abort_unless($businessUnit, 422, 'Unit Bisnis tidak aktif atau bukan milik entitas pengguna.');
            $cashAccount = ChartOfAccount::where('entity_id', $entityId)->where('id', $request->cash_account_id)
                ->where('is_cash_bank', 1)->where('is_postable', 1)->where('is_active', 1)->first();
            abort_unless($cashAccount, 422, 'Akun kas/bank tidak aktif atau bukan milik entitas pengguna.');
            $counterpartAccount = ChartOfAccount::where('entity_id', $entityId)->where('id', $request->counterpart_account_id)
                ->where('is_postable', 1)->where('is_active', 1)->first();
            abort_unless($counterpartAccount, 422, 'Akun lawan tidak aktif atau bukan milik entitas pengguna.');

            $mappings = DB::table('business_unit_account_mappings')->where('entity_id', $entityId)
                ->where('business_unit_id', $businessUnit->id)->whereIn('mapping_key', ['cash', 'bank'])->pluck('account_id', 'mapping_key');
            abort_unless(
                ((isset($mappings['cash']) && (int) $mappings['cash'] === (int) $cashAccount->id) ||
                 (isset($mappings['bank']) && (int) $mappings['bank'] === (int) $cashAccount->id)),
                422,
                'Akun kas/bank harus sesuai mapping Unit Bisnis aktif.'
            );

            $period = DB::table('accounting_periods')->where('entity_id', $entityId)
                ->where('period_year', \Carbon\Carbon::parse($request->journal_date)->year)
                ->where('period_month', \Carbon\Carbon::parse($request->journal_date)->month)->first();
            abort_if($period && in_array($period->status, ['closing', 'closed'], true), 422, 'Periode akuntansi sudah closing atau closed.');

            $journalNo = 'KK-' . date('YmdHis') . '-' . strtoupper(substr(uniqid(), -3));

            $journal = Journal::create([
                'entity_id'        => $entityId,
                'business_unit_id' => $businessUnit->id,
                'journal_no'       => $journalNo,
                'journal_date'     => $request->journal_date,
                'source_type'      => 'CASH_OUT',
                'source_id'        => null,
                'description'      => $request->description,
                'status'           => 'posted',
            ]);

            // Debit: Akun Tujuan / Beban / Operasional
            JournalEntry::create([
                'journal_id' => $journal->id,
                'account_id' => $counterpartAccount->id,
                'debit'      => $request->amount,
                'credit'     => 0,
            ]);

            // Kredit: Kas/Bank Pembayar
            JournalEntry::create([
                'journal_id' => $journal->id,
                'account_id' => $cashAccount->id,
                'debit'      => 0,
                'credit'     => $request->amount,
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Pengeluaran Kas/Bank berhasil disimpan!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal memproses pengeluaran: ' . $e->getMessage());
        }
    }

    public function export(Request $request)
    {
        $fileName = 'Laporan_Pengeluaran_Kas_Bank_' . date('Ymd_His') . '.xlsx';
        return Excel::download(new CashDisbursementExport($request), $fileName);
    }
}