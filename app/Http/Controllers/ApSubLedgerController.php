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
    /**
     * Halaman Utama: List Hutang, Aging, & Rekonsiliasi COA
     */
    public function index(Request $request)
    {
        $startDate      = $request->query('start_date', now()->startOfYear()->format('Y-m-d'));
        $endDate        = $request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId = $request->query('business_unit_id');
        $supplierId     = $request->query('supplier_id');
        $statusFilter   = $request->query('status');
        $search         = $request->query('search');

        $businessUnits = BusinessUnit::where('is_active', 1)->orderBy('code')->get();
        $suppliers     = Supplier::where('is_active', 1)->orderBy('name')->get();

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
            ->where('p.status', 'posted')
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
        $apCoa = ChartOfAccount::where('code', '2000101')->first();
        $coaBalance = $apCoa ? (float) JournalEntry::whereHas('journal', function ($q) use ($endDate, $businessUnitId) {
            $q->where('status', 'posted')->whereDate('journal_date', '<=', $endDate);
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
        $startDate      = $request->query('start_date', now()->startOfYear()->format('Y-m-d'));
        $endDate        = $request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId = $request->query('business_unit_id');

        $supplier = Supplier::findOrFail($supplierId);

        // Saldo Awal sebelum $startDate
        $initialPurchases = DB::table('purchases')->where('supplier_id', $supplierId)->where('status', 'posted')
            ->whereDate('purchase_date', '<', $startDate)
            ->when($businessUnitId, fn($q) => $q->where('business_unit_id', $businessUnitId))->sum('total');

        $initialPayments = DB::table('payments as p')->join('purchases as pur', 'pur.id', '=', 'p.purchase_id')
            ->where('pur.supplier_id', $supplierId)->where('pur.status', 'posted')
            ->whereDate('p.payment_date', '<', $startDate)
            ->when($businessUnitId, fn($q) => $q->where('pur.business_unit_id', $businessUnitId))->sum('p.paid_amount');

        $openingBalance = $initialPurchases - $initialPayments;

        // Mutasi Periode Ini (Hutang bertambah di Kredit / Pembelian, berkurang di Debit / Pelunasan)
        $purchaseEntries = DB::table('purchases')
            ->where('supplier_id', $supplierId)->where('status', 'posted')
            ->whereDate('purchase_date', '>=', $startDate)->whereDate('purchase_date', '<=', $endDate)
            ->when($businessUnitId, fn($q) => $q->where('business_unit_id', $businessUnitId))
            ->select('purchase_date as trans_date', 'purchase_no as ref_no', 'memo as description', DB::raw('0 as debit'), 'total as credit')->get();

        $paymentEntries = DB::table('payments as p')->join('purchases as pur', 'pur.id', '=', 'p.purchase_id')
            ->where('pur.supplier_id', $supplierId)->where('pur.status', 'posted')
            ->whereDate('p.payment_date', '>=', $startDate)->whereDate('p.payment_date', '<=', $endDate)
            ->when($businessUnitId, fn($q) => $q->where('pur.business_unit_id', $businessUnitId))
            ->select('p.payment_date as trans_date', DB::raw("CONCAT('PAY-', pur.purchase_no) as ref_no"), 'p.reference as description', 'p.paid_amount as debit', DB::raw('0 as credit'))->get();

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
            'business_unit_id' => 'required|exists:business_units,id',
            'supplier_id'      => 'required|exists:suppliers,id',
            'purchase_no'      => 'required|string|max:100',
            'purchase_date'    => 'required|date',
            'due_date'         => 'required|date|after_or_equal:purchase_date',
            'amount'           => 'required|numeric|min:1',
            'description'      => 'required|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $apAccount     = ChartOfAccount::where('code', '2000101')->first() ?? ChartOfAccount::where('type', 'liability')->where('name', 'LIKE', '%Hutang%')->first();
            $equityAccount = ChartOfAccount::where('code', '3000101')->first() ?? ChartOfAccount::where('type', 'equity')->where('name', 'LIKE', '%Modal%')->first();

            $purchaseId = DB::table('purchases')->insertGetId([
                'entity_id'        => auth()->user()->entity_id ?? 1,
                'business_unit_id' => $request->business_unit_id,
                'supplier_id'      => $request->supplier_id,
                'user_id'          => auth()->id() ?? 1,
                'purchase_no'      => $request->purchase_no,
                'purchase_date'    => $request->purchase_date,
                'due_date'         => $request->due_date,
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
            'business_unit_id' => 'required|exists:business_units,id',
            'supplier_id'      => 'required|exists:suppliers,id',
            'purchase_no'      => 'required|string|max:100',
            'purchase_date'    => 'required|date',
            'due_date'         => 'required|date|after_or_equal:purchase_date',
            'amount'           => 'required|numeric|min:1',
            'description'      => 'required|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            DB::table('purchases')->where('id', $id)->update([
                'business_unit_id' => $request->business_unit_id,
                'supplier_id'      => $request->supplier_id,
                'purchase_no'      => $request->purchase_no,
                'purchase_date'    => $request->purchase_date,
                'due_date'         => $request->due_date,
                'subtotal'         => $request->amount,
                'total'            => $request->amount,
                'memo'             => '[EDIT HUTANG] ' . $request->description,
                'updated_at'       => now(),
            ]);

            $journal = Journal::where('source_type', 'INITIAL_AP')->where('source_id', $id)->first();
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