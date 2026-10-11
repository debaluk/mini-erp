<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use App\Models\BusinessUnit;
use App\Models\Supplier;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Exports\ApSubLedgerExport;
use App\Exports\SupplierLedgerExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ApSubLedgerController extends Controller
{
    private function entityId(): int
    {
        $entityId = (int) (auth()->user()->entity_id ?? 0);
        abort_unless($entityId > 0, 403, 'Entitas pengguna tidak valid.');
        return $entityId;
    }

    /**
     * Halaman Utama: List Hutang, Aging, & Rekonsiliasi COA
     */
    public function index(Request $request)
    {
        $entityId       = $this->entityId();
        $startDate      = $request->query('start_date', now()->startOfYear()->format('Y-m-d'));
        $endDate        = $request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId = $request->query('business_unit_id');
        $supplierId     = $request->query('supplier_id');
        $statusFilter   = $request->query('status');
        $search         = $request->query('search');

        $businessUnits = BusinessUnit::where('entity_id', $entityId)->where('is_active', 1)->orderBy('code')->get();
        $suppliers     = Supplier::where('entity_id', $entityId)->where('is_active', 1)->orderBy('name')->get();

        // Query Sub-Ledger Hutang dari Purchases & Payments
        $query = DB::table('purchases as p')
            ->join('suppliers as sup', 'sup.id', '=', 'p.supplier_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'p.business_unit_id')
            ->leftJoinSub(
                DB::table('supplier_payment_allocations')
                    ->select('purchase_id', DB::raw('COALESCE(SUM(amount), 0) as total_paid'))
                    ->where('status', 'posted')
                    ->groupBy('purchase_id'),
                'pay', 'pay.purchase_id', '=', 'p.id'
            )
            ->where('p.entity_id', $entityId)
            ->where('p.status', 'posted')
            ->where('p.payment_method', 'credit')
            ->whereDate('p.purchase_date', '>=', $startDate)
            ->whereDate('p.purchase_date', '<=', $endDate);

        if ($businessUnitId) $query->where('p.business_unit_id', $businessUnitId);
        if ($supplierId)     $query->where('p.supplier_id', $supplierId);
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('p.purchase_no', 'LIKE', "%{$search}%")
                  ->orWhere('sup.name', 'LIKE', "%{$search}%");
            });
        }

        $allPurchases = $query->select(
            'p.id', 'p.purchase_no', 'p.purchase_date', 'p.due_date',
            'p.business_unit_id', 'bu.name as business_unit_name',
            'p.supplier_id', 'sup.name as supplier_name', 'sup.phone as supplier_phone',
            'p.total', 'p.memo',
            DB::raw('COALESCE(pay.total_paid, 0) as paid_amount'),
            DB::raw('GREATEST(p.total - COALESCE(pay.total_paid, 0), 0) as remaining_amount')
        )->orderBy('p.purchase_date', 'asc')->get();

        // Saldo Awal Hutang:
        // Setup Saldo Awal + Hutang sebelum start_date - Pembayaran sebelum start_date
        $openingInitialBalance = DB::table('purchases')
            ->where('entity_id', $entityId)
            ->where('status', 'posted')
            ->where('memo', 'LIKE', '[SALDO AWAL]%')
            ->whereDate('purchase_date', '<', $startDate)
            ->when($businessUnitId, fn($q) => $q->where('business_unit_id', $businessUnitId))
            ->when($supplierId, fn($q) => $q->where('supplier_id', $supplierId))
            ->sum('total');

        $openingPurchases = DB::table('purchases')
            ->where('entity_id', $entityId)
            ->where('status', 'posted')
            ->where('payment_method', 'credit')
            ->where(function ($q) {
                $q->whereNull('memo')
                  ->orWhere('memo', 'NOT LIKE', '[SALDO AWAL]%');
            })
            ->whereDate('purchase_date', '<', $startDate)
            ->when($businessUnitId, fn($q) => $q->where('business_unit_id', $businessUnitId))
            ->when($supplierId, fn($q) => $q->where('supplier_id', $supplierId))
            ->sum('total');

        $openingPayments = DB::table('supplier_payment_allocations as spa')
            ->join('supplier_payments as sp', 'sp.id', '=', 'spa.supplier_payment_id')
            ->join('purchases as pur', 'pur.id', '=', 'spa.purchase_id')
            ->where('pur.entity_id', $entityId)
            ->where('pur.status', 'posted')
            ->where('spa.status', 'posted')
            ->where('sp.status', 'posted')
            ->whereDate('sp.payment_date', '<', $startDate)
            ->when($businessUnitId, fn($q) => $q->where('pur.business_unit_id', $businessUnitId))
            ->when($supplierId, fn($q) => $q->where('pur.supplier_id', $supplierId))
            ->sum('spa.amount');

        $openingBalance = (float) $openingInitialBalance
            + (float) $openingPurchases
            - (float) $openingPayments;

        // Hitung Ringkasan & Aging Hutang
        $totalApAmount = 0; $totalCurrentAp = 0; $totalOverdueAp = 0; $totalPaidThisPeriod = 0;
        $today = Carbon::today();

        $processedPurchases = $allPurchases->map(function ($pur) use ($today, &$totalApAmount, &$totalCurrentAp, &$totalOverdueAp, &$totalPaidThisPeriod) {
            $remaining = (float) $pur->remaining_amount;
            $paid      = (float) $pur->paid_amount;
            $totalApAmount += $remaining;
            $totalPaidThisPeriod += $paid;

            $dueDate = $pur->due_date ? Carbon::parse($pur->due_date) : Carbon::parse($pur->purchase_date);
            $overdueDays = $today->diffInDays($dueDate, false);

            if ($remaining > 0) {
                if ($overdueDays < 0) {
                    $pur->overdue_days = abs($overdueDays);
                    $pur->ap_status    = 'overdue';
                    $totalOverdueAp   += $remaining;
                } else {
                    $pur->overdue_days = 0;
                    $pur->ap_status    = ($paid > 0) ? 'partial' : 'unpaid';
                    $totalCurrentAp   += $remaining;
                }
            } else {
                $pur->overdue_days = 0;
                $pur->ap_status    = 'paid';
            }
            return $pur;
        });

        $filteredList = $statusFilter ? $processedPurchases->filter(fn($i) => $i->ap_status === $statusFilter) : $processedPurchases;

        // Rekonsiliasi Akun COA 2000101 (Hutang Usaha)
        $apCoa = ChartOfAccount::where('entity_id', $entityId)->where('code', '2000101')->first();
        $coaBalance = $apCoa ? (float) JournalEntry::whereHas('journal', function ($q) use ($endDate, $businessUnitId, $entityId) {
            $q->where('entity_id', $entityId)->where('status', 'posted')->whereDate('journal_date', '<=', $endDate);
            if ($businessUnitId) $q->where('business_unit_id', $businessUnitId);
        })->where('account_id', $apCoa->id)->sum(DB::raw('credit - debit')) : 0; // Hutang saldo normal Kredit

        $reconciliationDifference = $totalApAmount - $coaBalance;

        return view('keuangan.akuntansi.buku-hutang.index', compact(
            'businessUnits', 'suppliers', 'startDate', 'endDate',
            'businessUnitId', 'supplierId', 'statusFilter', 'search',
            'filteredList', 'totalApAmount', 'totalCurrentAp', 'totalOverdueAp',
            'totalPaidThisPeriod', 'coaBalance', 'reconciliationDifference'
        ));
    }

    /**
     * AJAX Endpoint: Kartu Hutang Supplier (Ledger)
     */
    public function getSupplierLedger($supplierId, Request $request)
    {
        $entityId       = $this->entityId();
        $startDate      = $request->query('start_date', now()->startOfYear()->format('Y-m-d'));
        $endDate        = $request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId = $request->query('business_unit_id');

        $supplier = Supplier::where('entity_id', $entityId)->findOrFail($supplierId);

        // Saldo Awal sebelum $startDate
        $initialPurchases = DB::table('purchases')->where('entity_id', $entityId)->where('supplier_id', $supplierId)->where('status', 'posted')->where('payment_method', 'credit')
            ->whereDate('purchase_date', '<', $startDate)
            ->when($businessUnitId, fn($q) => $q->where('business_unit_id', $businessUnitId))->sum('total');

        $initialPayments = DB::table('supplier_payment_allocations as spa')
            ->join('supplier_payments as sp', 'sp.id', '=', 'spa.supplier_payment_id')
            ->join('purchases as pur', 'pur.id', '=', 'spa.purchase_id')
            ->where('pur.entity_id', $entityId)->where('pur.supplier_id', $supplierId)->where('pur.status', 'posted')
            ->where('spa.status', 'posted')->where('sp.status', 'posted')
            ->whereDate('sp.payment_date', '<', $startDate)
            ->when($businessUnitId, fn($q) => $q->where('pur.business_unit_id', $businessUnitId))
            ->sum('spa.amount');

        $openingBalance = $initialPurchases - $initialPayments;

        // Mutasi Periode Ini (Hutang bertambah di Kredit / Pembelian, berkurang di Debit / Pelunasan)
        $purchaseEntries = DB::table('purchases')
            ->where('entity_id', $entityId)
            ->where('supplier_id', $supplierId)->where('status', 'posted')->where('payment_method', 'credit')
            ->whereDate('purchase_date', '>=', $startDate)->whereDate('purchase_date', '<=', $endDate)
            ->when($businessUnitId, fn($q) => $q->where('business_unit_id', $businessUnitId))
            ->select('purchase_date as trans_date', 'purchase_no as ref_no', 'memo as description', DB::raw('0 as debit'), 'total as credit')->get();

        $paymentEntries = DB::table('supplier_payment_allocations as spa')
            ->join('supplier_payments as sp', 'sp.id', '=', 'spa.supplier_payment_id')
            ->join('purchases as pur', 'pur.id', '=', 'spa.purchase_id')
            ->where('pur.entity_id', $entityId)->where('pur.supplier_id', $supplierId)->where('pur.status', 'posted')
            ->where('spa.status', 'posted')->where('sp.status', 'posted')
            ->whereDate('sp.payment_date', '>=', $startDate)->whereDate('sp.payment_date', '<=', $endDate)
            ->when($businessUnitId, fn($q) => $q->where('pur.business_unit_id', $businessUnitId))
            ->select('sp.payment_date as trans_date', DB::raw("CONCAT('PAY-', pur.purchase_no) as ref_no"), 'sp.reference as description', 'spa.amount as debit', DB::raw('0 as credit'))->get();

        $mutations = $purchaseEntries->concat($paymentEntries)->sortBy('trans_date');

        $runningBalance = $openingBalance;
        $ledgerRows = [];

        foreach ($mutations as $m) {
            $debit  = (float) $m->debit;
            $credit = (float) $m->credit;
            $runningBalance += ($credit - $debit); // Saldo Hutang = Saldo Awal + Kredit (Pembelian) - Debit (Pelunasan)

            $ledgerRows[] = [
                'date'        => Carbon::parse($m->trans_date)->format('d/m/Y H:i'),
                'ref_no'      => $m->ref_no,
                'description' => $m->description ?? ($credit > 0 ? 'Pembelian Tempo' : 'Pelunasan Hutang'),
                'debit'       => $debit,
                'credit'      => $credit,
                'balance'     => $runningBalance,
            ];
        }

        return response()->json([
            'success'         => true,
            'supplier'        => $supplier,
            'opening_balance' => $openingBalance,
            'ending_balance'  => $runningBalance,
            'ledger'          => $ledgerRows,
        ]);
    }

    /**
     * Action: Simpan Setup Saldo Awal Hutang Supplier
     */
    public function storeInitialBalance(Request $request)
    {
        $request->validate([
            'business_unit_id' => 'required|integer',
            'supplier_id'      => 'required|integer',
            'purchase_no'      => 'required|string|max:100',
            'purchase_date'    => 'required|date',
            'due_date'         => 'required|date|after_or_equal:purchase_date',
            'amount'           => 'required|numeric|min:1',
            'description'      => 'required|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $entityId = $this->entityId();
            $businessUnit = DB::table('business_units')->where('entity_id', $entityId)->where('id', $request->business_unit_id)->where('is_active', 1)->first();
            $supplier = DB::table('suppliers')->where('entity_id', $entityId)->where('id', $request->supplier_id)->where('is_active', 1)->first();
            abort_unless($businessUnit && $supplier, 422, 'Unit Bisnis atau supplier tidak aktif atau bukan milik entitas pengguna.');

            $period = DB::table('accounting_periods')->where('entity_id', $entityId)
                ->where('period_year', Carbon::parse($request->purchase_date)->year)
                ->where('period_month', Carbon::parse($request->purchase_date)->month)->first();
            abort_if($period && in_array($period->status, ['closing', 'closed'], true), 422, 'Periode akuntansi sudah closing atau closed.');

            $mapping = DB::table('business_unit_account_mappings')->where('entity_id', $entityId)
                ->where('business_unit_id', $businessUnit->id)->where('mapping_key', 'payable')->value('account_id');
            $apAccount = $mapping ? ChartOfAccount::where('entity_id', $entityId)->where('id', $mapping)->where('is_active', 1)->where('is_postable', 1)->first() : null;
            $equityAccount = ChartOfAccount::where('entity_id', $entityId)->where('code', '3000101')->where('is_active', 1)->where('is_postable', 1)->first()
                ?? ChartOfAccount::where('entity_id', $entityId)->where('type', 'equity')->where('name', 'LIKE', '%Modal%')->where('is_active', 1)->where('is_postable', 1)->first();
            abort_unless($apAccount && $equityAccount, 422, 'Mapping akun hutang atau akun ekuitas entitas belum tersedia.');

            $purchaseId = DB::table('purchases')->insertGetId([
                'entity_id'        => $entityId,
                'business_unit_id' => $request->business_unit_id,
                'supplier_id'      => $request->supplier_id,
                'user_id'          => auth()->id() ?? 1,
                'purchase_no'      => $request->purchase_no,
                'purchase_date'    => $request->purchase_date,
                'due_date'         => $request->due_date,
                'payment_method'   => 'credit',
                'subtotal'         => $request->amount,
                'total'            => $request->amount,
                'memo'             => '[SALDO AWAL] ' . $request->description,
                'status'           => 'posted',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            $journal = Journal::create([
                'entity_id'        => auth()->user()->entity_id ?? 1,
                'business_unit_id' => $request->business_unit_id,
                'journal_no'       => 'INIT-AP-' . date('YmdHis'),
                'journal_date'     => $request->purchase_date,
                'source_type'      => 'INITIAL_AP',
                'source_id'        => $purchaseId,
                'description'      => 'Saldo Awal Hutang: ' . $request->purchase_no,
                'status'           => 'posted',
            ]);

            // Jurnal Saldo Awal Hutang: Debit Modal/Ekuitas (3000101), Kredit Hutang Usaha (2000101)
            JournalEntry::create(['journal_id' => $journal->id, 'account_id' => $equityAccount->id, 'debit' => $request->amount, 'credit' => 0]);
            JournalEntry::create(['journal_id' => $journal->id, 'account_id' => $apAccount->id, 'debit' => 0, 'credit' => $request->amount]);

            DB::commit();
            return redirect()->back()->with('success', 'Saldo awal hutang berhasil disimpan!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal: ' . $e->getMessage());
        }
    }

    /**
     * Action: Update/Edit Faktur / Saldo Awal Hutang
     */
    public function updateInitialBalance(Request $request, $id)
    {
        $request->validate([
            'business_unit_id' => 'required|integer',
            'supplier_id'      => 'required|integer',
            'purchase_no'      => 'required|string|max:100',
            'purchase_date'    => 'required|date',
            'due_date'         => 'required|date|after_or_equal:purchase_date',
            'amount'           => 'required|numeric|min:1',
            'description'      => 'required|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $entityId = $this->entityId();
            $purchase = DB::table('purchases')->where('entity_id', $entityId)->where('id', $id)
                ->where('status', 'posted')
                ->where(function ($q) {
                    $q->where('memo', 'LIKE', '[SALDO AWAL]%')->orWhere('memo', 'LIKE', '[EDIT HUTANG]%');
                })->lockForUpdate()->first();
            abort_unless($purchase, 404, 'Saldo awal hutang tidak ditemukan pada entitas pengguna.');
            abort_unless((int) $request->business_unit_id === (int) $purchase->business_unit_id, 422, 'Unit Bisnis saldo awal hutang tidak dapat diubah.');
            $businessUnit = DB::table('business_units')->where('entity_id', $entityId)->where('id', $request->business_unit_id)->where('is_active', 1)->first();
            $supplier = DB::table('suppliers')->where('entity_id', $entityId)->where('id', $request->supplier_id)->where('is_active', 1)->first();
            abort_unless($businessUnit && $supplier, 422, 'Unit Bisnis atau supplier tidak aktif atau bukan milik entitas pengguna.');

            $period = DB::table('accounting_periods')->where('entity_id', $entityId)
                ->where('period_year', Carbon::parse($purchase->purchase_date)->year)
                ->where('period_month', Carbon::parse($purchase->purchase_date)->month)->first();
            $targetPeriod = DB::table('accounting_periods')->where('entity_id', $entityId)
                ->where('period_year', Carbon::parse($request->purchase_date)->year)
                ->where('period_month', Carbon::parse($request->purchase_date)->month)->first();
            abort_if(($period && in_array($period->status, ['closing', 'closed'], true)) || ($targetPeriod && in_array($targetPeriod->status, ['closing', 'closed'], true)), 422, 'Periode akuntansi sudah closing atau closed.');

            DB::table('purchases')->where('entity_id', $entityId)->where('id', $id)->update([
                'business_unit_id' => $request->business_unit_id,
                'supplier_id'      => $request->supplier_id,
                'purchase_no'      => $request->purchase_no,
                'purchase_date'    => $request->purchase_date,
                'due_date'         => $request->due_date,
                'subtotal'         => $request->amount,
                'total'            => $request->amount,
                'memo'             => '[SALDO AWAL] ' . $request->description,
                'updated_at'       => now(),
            ]);

            $journal = Journal::where('entity_id', $entityId)->where('source_type', 'INITIAL_AP')->where('source_id', $id)->first();
            if ($journal) {
                $journal->update([
                    'business_unit_id' => $request->business_unit_id,
                    'journal_date'     => $request->purchase_date,
                    'description'      => 'Edit Saldo Awal Hutang: ' . $request->purchase_no,
                ]);

                JournalEntry::where('journal_id', $journal->id)->where('debit', '>', 0)->update(['debit' => $request->amount]);
                JournalEntry::where('journal_id', $journal->id)->where('credit', '>', 0)->update(['credit' => $request->amount]);
            }

            DB::commit();
            return redirect()->back()->with('success', 'Faktur hutang berhasil diperbarui!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal update: ' . $e->getMessage());
        }
    }

    /**
     * Action: Export Excel List Hutang & Aging
     */
    public function exportList(Request $request)
    {
        return Excel::download(new ApSubLedgerExport($request), 'Daftar_Hutang_Supplier_' . date('Ymd_His') . '.xlsx');
    }

    /**
     * Action: Export Excel Kartu Hutang Supplier
     */
    public function exportSupplierLedger($supplierId, Request $request)
    {
        return Excel::download(new SupplierLedgerExport($supplierId, $request), 'Kartu_Hutang_Supplier_' . date('Ymd_His') . '.xlsx');
    }
}