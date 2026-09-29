<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use App\Models\BusinessUnit;
use App\Models\JournalEntry;
use App\Exports\CashFlowExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;

class CashFlowController extends Controller
{
    public function index(Request $request)
    {
        $startDate      = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate        = $request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId = $request->query('business_unit_id');
        $cashAccountId  = $request->query('cash_account_id');

        $businessUnits = BusinessUnit::where('is_active', 1)->orderBy('code')->get();

        // 1. Ambil Daftar Akun Kas & Bank (is_cash_bank = 1)
        $cashAccounts = ChartOfAccount::where('is_cash_bank', 1)
            ->where('is_postable', 1)
            ->where('is_active', 1)
            ->orderBy('code')
            ->get();

        $cashAccountIds = !empty($cashAccountId) 
            ? [(int) $cashAccountId] 
            : $cashAccounts->pluck('id')->toArray();

        // 2. HITUNG SALDO AWAL KAS & BANK (Eksklusif sebelum tanggal mulai)
        $initialBalance = (float) JournalEntry::whereHas('journal', function ($q) use ($startDate, $businessUnitId) {
                $q->where('status', 'posted')
                  ->whereDate('journal_date', '<', $startDate);
                if (!empty($businessUnitId)) {
                    $q->where('business_unit_id', $businessUnitId);
                }
            })
            ->whereIn('account_id', $cashAccountIds)
            ->sum(DB::raw('debit - credit'));

        // 3. AMBIL HANYA BARIS TRANSAKSI KAS & BANK PERIODE FILTER
        $cashEntries = JournalEntry::with(['journal', 'account'])
            ->whereHas('journal', function ($q) use ($startDate, $endDate, $businessUnitId) {
                $q->where('status', 'posted')
                  ->whereDate('journal_date', '>=', $startDate)
                  ->whereDate('journal_date', '<=', $endDate);
                if (!empty($businessUnitId)) {
                    $q->where('business_unit_id', $businessUnitId);
                }
            })
            ->whereIn('account_id', $cashAccountIds)
            ->get();

        // Pengelompokan Mutasi Kas
        $operatingInFlows   = [];
        $operatingOutFlows  = [];
        $investingInFlows   = [];
        $investingOutFlows  = [];
        $financingInFlows   = [];
        $financingOutFlows  = [];

        $totalOpIn  = 0; $totalOpOut  = 0;
        $totalInvIn = 0; $totalInvOut = 0;
        $totalFinIn = 0; $totalFinOut = 0;

        foreach ($cashEntries as $entry) {
            $journal    = $entry->journal;
            $sourceType = $journal->source_type ?? 'GENERAL';
            $debit      = (float) $entry->debit;
            $credit     = (float) $entry->credit;

            // --- A. PENERIMAAN KAS (DEBIT > 0) ---
            if ($debit > 0) {
                // Cari entri lawan berposisi KREDIT di jurnal yang sama
                $creditCounterparts = JournalEntry::with('account')
                    ->where('journal_id', $journal->id)
                    ->where('credit', '>', 0)
                    ->get();

                // Filter abaikan jika lawan kreditnya juga akun kas (Transfer Antar Kas)
                $nonCashCounterparts = $creditCounterparts->reject(function ($cp) use ($cashAccountIds) {
                    return in_array($cp->account_id, $cashAccountIds);
                });

                if ($nonCashCounterparts->isEmpty()) {
                    continue; // Skip transfer antar kas agar saldo net tetap seimbang
                }

                // Ambil lawan kredit utama (nominal terbesar)
                $primaryCp = $nonCashCounterparts->sortByDesc('credit')->first();
                $cpAccount = $primaryCp?->account;
                $cpType    = strtolower($cpAccount?->type ?? '');
                $cpName    = $cpAccount ? "[{$cpAccount->code}] {$cpAccount->name}" : 'Penerimaan Lainnya';

                if ($sourceType === 'AR_PAYMENT' || str_contains(strtolower($cpName), 'piutang')) {
                    $label = 'Pelunasan Piutang Usaha';
                    $operatingInFlows[$label] = ($operatingInFlows[$label] ?? 0) + $debit;
                    $totalOpIn += $debit;
                } elseif (in_array($cpType, ['equity', 'liability', 'kewajiban']) && (str_contains(strtolower($cpAccount?->name), 'modal') || str_contains(strtolower($cpAccount?->name), 'pinjaman'))) {
                    $financingInFlows[$cpName] = ($financingInFlows[$cpName] ?? 0) + $debit;
                    $totalFinIn += $debit;
                } elseif (str_contains(strtolower($cpAccount?->name), 'aset tetap') || str_contains(strtolower($cpAccount?->name), 'peralatan') || str_contains(strtolower($cpAccount?->name), 'kendaraan')) {
                    $investingInFlows[$cpName] = ($investingInFlows[$cpName] ?? 0) + $debit;
                    $totalInvIn += $debit;
                } else {
                    $operatingInFlows[$cpName] = ($operatingInFlows[$cpName] ?? 0) + $debit;
                    $totalOpIn += $debit;
                }
            }

            // --- B. PENGELUARAN KAS (KREDIT > 0) ---
            if ($credit > 0) {
                // Cari entri lawan berposisi DEBIT di jurnal yang sama
                $debitCounterparts = JournalEntry::with('account')
                    ->where('journal_id', $journal->id)
                    ->where('debit', '>', 0)
                    ->get();

                // Filter abaikan jika lawan debitnya juga akun kas
                $nonCashCounterparts = $debitCounterparts->reject(function ($cp) use ($cashAccountIds) {
                    return in_array($cp->account_id, $cashAccountIds);
                });

                if ($nonCashCounterparts->isEmpty()) {
                    continue;
                }

                // Ambil lawan debit utama (nominal terbesar)
                $primaryCp = $nonCashCounterparts->sortByDesc('debit')->first();
                $cpAccount = $primaryCp?->account;
                $cpType    = strtolower($cpAccount?->type ?? '');
                $cpName    = $cpAccount ? "[{$cpAccount->code}] {$cpAccount->name}" : 'Pengeluaran Lainnya';

                if ($sourceType === 'AP_PAYMENT' || str_contains(strtolower($cpName), 'hutang')) {
                    $label = 'Pembayaran Hutang Vendor (AP)';
                    $operatingOutFlows[$label] = ($operatingOutFlows[$label] ?? 0) + $credit;
                    $totalOpOut += $credit;
                } elseif (in_array($cpType, ['equity', 'liability', 'kewajiban']) && (str_contains(strtolower($cpAccount?->name), 'prive') || str_contains(strtolower($cpAccount?->name), 'dividen') || str_contains(strtolower($cpAccount?->name), 'pinjaman'))) {
                    $financingOutFlows[$cpName] = ($financingOutFlows[$cpName] ?? 0) + $credit;
                    $totalFinOut += $credit;
                } elseif (str_contains(strtolower($cpAccount?->name), 'aset tetap') || str_contains(strtolower($cpAccount?->name), 'peralatan') || str_contains(strtolower($cpAccount?->name), 'kendaraan') || str_contains(strtolower($cpAccount?->name), 'mesin')) {
                    $investingOutFlows[$cpName] = ($investingOutFlows[$cpName] ?? 0) + $credit;
                    $totalInvOut += $credit;
                } else {
                    $operatingOutFlows[$cpName] = ($operatingOutFlows[$cpName] ?? 0) + $credit;
                    $totalOpOut += $credit;
                }
            }
        }

        // Net Cash Flows
        $netOperating = $totalOpIn - $totalOpOut;
        $netInvesting = $totalInvIn - $totalInvOut;
        $netFinancing = $totalFinIn - $totalFinOut;

        $netCashChange = $netOperating + $netInvesting + $netFinancing;
        $endingBalance = $initialBalance + $netCashChange;

        return view('keuangan.laporan.arus-kas', compact(
            'businessUnits',
            'cashAccounts',
            'startDate',
            'endDate',
            'businessUnitId',
            'cashAccountId',
            'initialBalance',
            'operatingInFlows',
            'operatingOutFlows',
            'investingInFlows',
            'investingOutFlows',
            'financingInFlows',
            'financingOutFlows',
            'totalOpIn',
            'totalOpOut',
            'totalInvIn',
            'totalInvOut',
            'totalFinIn',
            'totalFinOut',
            'netOperating',
            'netInvesting',
            'netFinancing',
            'netCashChange',
            'endingBalance'
        ));
    }

    public function export(Request $request)
    {
        $fileName = 'Laporan_Arus_Kas_' . date('Ymd_His') . '.xlsx';
        return Excel::download(new CashFlowExport($request), $fileName);
    }
}
