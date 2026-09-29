<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Exports\ArSubLedgerExport;
use App\Exports\CustomerLedgerExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ArSubLedgerController extends Controller
{
    /**
     * Halaman Utama: List Piutang, Aging, & Rekonsiliasi COA
     */
    public function index(Request $request)
    {
        $startDate      = $request->query('start_date', now()->startOfYear()->format('Y-m-d'));
        $endDate        = $request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId = $request->query('business_unit_id');
        $customerId     = $request->query('customer_id');
        $statusFilter   = $request->query('status');
        $search         = $request->query('search');

        $businessUnits = BusinessUnit::where('is_active', 1)->orderBy('code')->get();
        $customers     = Customer::where('is_active', 1)->orderBy('name')->get();

        // Query Sub-Ledger Piutang dari Sales & Payments
        $query = DB::table('sales as s')
            ->join('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 's.business_unit_id')
            ->leftJoinSub(
                DB::table('payments')
                    ->select('sale_id', DB::raw('COALESCE(SUM(paid_amount), 0) as total_paid'))
                    ->groupBy('sale_id'),
                'pay', 'pay.sale_id', '=', 's.id'
            )
            ->where('s.status', 'posted')
            ->whereDate('s.sale_date', '>=', $startDate)
            ->whereDate('s.sale_date', '<=', $endDate);

        if ($businessUnitId) $query->where('s.business_unit_id', $businessUnitId);
        if ($customerId)     $query->where('s.customer_id', $customerId);
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('s.invoice_no', 'LIKE', "%{$search}%")
                  ->orWhere('c.name', 'LIKE', "%{$search}%");
            });
        }

        $allInvoices = $query->select(
            's.id', 's.invoice_no', 's.sale_date', 's.due_date',
            's.business_unit_id', 'bu.name as business_unit_name',
            's.customer_id', 'c.name as customer_name', 'c.phone as customer_phone',
            's.total', 's.memo',
            DB::raw('COALESCE(pay.total_paid, 0) as paid_amount'),
            DB::raw('GREATEST(s.total - COALESCE(pay.total_paid, 0), 0) as remaining_amount')
        )->orderBy('s.sale_date', 'asc')->get();

        // Hitung Ringkasan & Aging
        $totalArAmount = 0; $totalCurrentAr = 0; $totalOverdueAr = 0; $totalPaidThisPeriod = 0;
        $today = Carbon::today();

        $processedInvoices = $allInvoices->map(function ($inv) use ($today, &$totalArAmount, &$totalCurrentAr, &$totalOverdueAr, &$totalPaidThisPeriod) {
            $remaining = (float) $inv->remaining_amount;
            $paid      = (float) $inv->paid_amount;
            $totalArAmount += $remaining;
            $totalPaidThisPeriod += $paid;

            $dueDate = $inv->due_date ? Carbon::parse($inv->due_date) : Carbon::parse($inv->sale_date);
            $overdueDays = $today->diffInDays($dueDate, false);

            if ($remaining > 0) {
                if ($overdueDays < 0) {
                    $inv->overdue_days = abs($overdueDays);
                    $inv->ar_status    = 'overdue';
                    $totalOverdueAr   += $remaining;
                } else {
                    $inv->overdue_days = 0;
                    $inv->ar_status    = ($paid > 0) ? 'partial' : 'unpaid';
                    $totalCurrentAr   += $remaining;
                }
            } else {
                $inv->overdue_days = 0;
                $inv->ar_status    = 'paid';
            }
            return $inv;
        });

        $filteredList = $statusFilter ? $processedInvoices->filter(fn($i) => $i->ar_status === $statusFilter) : $processedInvoices;

        // Rekonsiliasi Akun COA 1000301 (Piutang Usaha)
        $arCoa = ChartOfAccount::where('code', '1000301')->first();
        $coaBalance = $arCoa ? (float) JournalEntry::whereHas('journal', function ($q) use ($endDate, $businessUnitId) {
            $q->where('status', 'posted')->whereDate('journal_date', '<=', $endDate);
            if ($businessUnitId) $q->where('business_unit_id', $businessUnitId);
        })->where('account_id', $arCoa->id)->sum(DB::raw('debit - credit')) : 0;

        $reconciliationDifference = $totalArAmount - $coaBalance;

        return view('keuangan.akuntansi.buku-piutang.index', compact(
            'businessUnits', 'customers', 'startDate', 'endDate',
            'businessUnitId', 'customerId', 'statusFilter', 'search',
            'filteredList', 'totalArAmount', 'totalCurrentAr', 'totalOverdueAr',
            'totalPaidThisPeriod', 'coaBalance', 'reconciliationDifference'
        ));
    }

    /**
     * AJAX Endpoint: Kartu Piutang Pelanggan (Ledger)
     */
    public function getCustomerLedger($customerId, Request $request)
    {
        $startDate      = $request->query('start_date', now()->startOfYear()->format('Y-m-d'));
        $endDate        = $request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId = $request->query('business_unit_id');

        $customer = Customer::findOrFail($customerId);

        // Saldo Awal sebelum $startDate
        $initialSales = DB::table('sales')->where('customer_id', $customerId)->where('status', 'posted')
            ->whereDate('sale_date', '<', $startDate)
            ->when($businessUnitId, fn($q) => $q->where('business_unit_id', $businessUnitId))->sum('total');

        $initialPayments = DB::table('payments as p')->join('sales as s', 's.id', '=', 'p.sale_id')
            ->where('s.customer_id', $customerId)->where('s.status', 'posted')
            ->whereDate('p.payment_date', '<', $startDate)
            ->when($businessUnitId, fn($q) => $q->where('s.business_unit_id', $businessUnitId))->sum('p.paid_amount');

        $openingBalance = $initialSales - $initialPayments;

        // Mutasi Periode Ini
        $salesEntries = DB::table('sales')
            ->where('customer_id', $customerId)->where('status', 'posted')
            ->whereDate('sale_date', '>=', $startDate)->whereDate('sale_date', '<=', $endDate)
            ->when($businessUnitId, fn($q) => $q->where('business_unit_id', $businessUnitId))
            ->select('sale_date as trans_date', 'invoice_no as ref_no', 'memo as description', 'total as debit', DB::raw('0 as credit'))->get();

        $paymentEntries = DB::table('payments as p')->join('sales as s', 's.id', '=', 'p.sale_id')
            ->where('s.customer_id', $customerId)->where('s.status', 'posted')
            ->whereDate('p.payment_date', '>=', $startDate)->whereDate('p.payment_date', '<=', $endDate)
            ->when($businessUnitId, fn($q) => $q->where('s.business_unit_id', $businessUnitId))
            ->select('p.payment_date as trans_date', DB::raw("CONCAT('PAY-', s.invoice_no) as ref_no"), 'p.reference as description', DB::raw('0 as debit'), 'p.paid_amount as credit')->get();

        $mutations = $salesEntries->concat($paymentEntries)->sortBy('trans_date');

        $runningBalance = $openingBalance;
        $ledgerRows = [];

        foreach ($mutations as $m) {
            $debit  = (float) $m->debit;
            $credit = (float) $m->credit;
            $runningBalance += ($debit - $credit);

            $ledgerRows[] = [
                'date'        => Carbon::parse($m->trans_date)->format('d/m/Y H:i'),
                'ref_no'      => $m->ref_no,
                'description' => $m->description ?? ($debit > 0 ? 'Penjualan Kredit' : 'Pelunasan Piutang'),
                'debit'       => $debit,
                'credit'      => $credit,
                'balance'     => $runningBalance,
            ];
        }

        return response()->json([
            'success'         => true,
            'customer'        => $customer,
            'opening_balance' => $openingBalance,
            'ending_balance'  => $runningBalance,
            'ledger'          => $ledgerRows,
        ]);
    }

    /**
     * Action: Simpan Setup Saldo Awal Piutang
     */
    public function storeInitialBalance(Request $request)
    {
        $request->validate([
            'business_unit_id' => 'required|exists:business_units,id',
            'customer_id'      => 'required|exists:customers,id',
            'invoice_no'       => 'required|string|max:100',
            'sale_date'        => 'required|date',
            'due_date'         => 'required|date|after_or_equal:sale_date',
            'amount'           => 'required|numeric|min:1',
            'description'      => 'required|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $arAccount     = ChartOfAccount::where('code', '1000301')->first() ?? ChartOfAccount::where('type', 'asset')->where('name', 'LIKE', '%Piutang%')->first();
            $equityAccount = ChartOfAccount::where('code', '3000101')->first() ?? ChartOfAccount::where('type', 'equity')->where('name', 'LIKE', '%Modal%')->first();

            $saleId = DB::table('sales')->insertGetId([
                'entity_id'        => auth()->user()->entity_id ?? 1,
                'business_unit_id' => $request->business_unit_id,
                'customer_id'      => $request->customer_id,
                'user_id'          => auth()->id() ?? 1,
                'invoice_no'       => $request->invoice_no,
                'sale_date'        => $request->sale_date,
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
                'journal_no'       => 'INIT-AR-' . date('YmdHis'),
                'journal_date'     => $request->sale_date,
                'source_type'      => 'INITIAL_AR',
                'source_id'        => $saleId,
                'description'      => 'Saldo Awal Piutang: ' . $request->invoice_no,
                'status'           => 'posted',
            ]);

            JournalEntry::create(['journal_id' => $journal->id, 'account_id' => $arAccount->id, 'debit' => $request->amount, 'credit' => 0]);
            JournalEntry::create(['journal_id' => $journal->id, 'account_id' => $equityAccount->id, 'debit' => 0, 'credit' => $request->amount]);

            DB::commit();
            return redirect()->back()->with('success', 'Saldo awal piutang berhasil disimpan!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal: ' . $e->getMessage());
        }
    }

    /**
     * Action: Update/Edit Faktur / Saldo Awal Piutang
     */
    public function updateInitialBalance(Request $request, $id)
    {
        $request->validate([
            'business_unit_id' => 'required|exists:business_units,id',
            'customer_id'      => 'required|exists:customers,id',
            'invoice_no'       => 'required|string|max:100',
            'sale_date'        => 'required|date',
            'due_date'         => 'required|date|after_or_equal:sale_date',
            'amount'           => 'required|numeric|min:1',
            'description'      => 'required|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            DB::table('sales')->where('id', $id)->update([
                'business_unit_id' => $request->business_unit_id,
                'customer_id'      => $request->customer_id,
                'invoice_no'       => $request->invoice_no,
                'sale_date'        => $request->sale_date,
                'due_date'         => $request->due_date,
                'subtotal'         => $request->amount,
                'total'            => $request->amount,
                'memo'             => '[EDIT PIUTANG] ' . $request->description,
                'updated_at'       => now(),
            ]);

            $journal = Journal::where('source_type', 'INITIAL_AR')->where('source_id', $id)->first();
            if ($journal) {
                $journal->update([
                    'business_unit_id' => $request->business_unit_id,
                    'journal_date'     => $request->sale_date,
                    'description'      => 'Edit Saldo Awal Piutang: ' . $request->invoice_no,
                ]);

                JournalEntry::where('journal_id', $journal->id)->where('debit', '>', 0)->update(['debit' => $request->amount]);
                JournalEntry::where('journal_id', $journal->id)->where('credit', '>', 0)->update(['credit' => $request->amount]);
            }

            DB::commit();
            return redirect()->back()->with('success', 'Faktur piutang berhasil diperbarui!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal update: ' . $e->getMessage());
        }
    }

    /**
     * Action: Export Excel List Piutang & Aging
     */
    public function exportList(Request $request)
    {
        return Excel::download(new ArSubLedgerExport($request), 'Daftar_Piutang_Usaha_' . date('Ymd_His') . '.xlsx');
    }

    /**
     * Action: Export Excel Kartu Piutang Pelanggan
     */
    public function exportCustomerLedger($customerId, Request $request)
    {
        return Excel::download(new CustomerLedgerExport($customerId, $request), 'Kartu_Piutang_Pelanggan_' . date('Ymd_His') . '.xlsx');
    }
}