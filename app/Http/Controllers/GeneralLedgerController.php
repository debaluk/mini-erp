<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use App\Models\BusinessUnit;
use App\Models\JournalEntry;
use App\Exports\GeneralLedgerExport;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class GeneralLedgerController extends Controller
{
    public function index(Request $request)
    {
        $startDate      = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate        = $request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $accountId      = $request->query('account_id');
        $businessUnitId = $request->query('business_unit_id');

        $accounts      = ChartOfAccount::where('is_postable', 1)->orderBy('code')->get();
        $businessUnits = BusinessUnit::where('is_active', 1)->orderBy('code')->get();

        $selectedAccount = $accountId ? ChartOfAccount::find($accountId) : null;
        $ledgerData      = collect();
        $initialBalance  = 0;
        $endingBalance   = 0;

        if ($selectedAccount) {
            // 1. Hitung Saldo Awal (sebelum start_date)
            $initialQuery = JournalEntry::where('account_id', $accountId)
                ->whereHas('journal', function ($q) use ($startDate, $businessUnitId) {
                    $q->where('status', 'posted')
                      ->whereDate('journal_date', '<', $startDate);
                    if (!empty($businessUnitId)) {
                        $q->where('business_unit_id', $businessUnitId);
                    }
                });

            $sumDebitBefore  = (float) $initialQuery->sum('debit');
            $sumCreditBefore = (float) $initialQuery->sum('credit');

            if ($selectedAccount->normal_balance === 'debit') {
                $initialBalance = $sumDebitBefore - $sumCreditBefore;
            } else {
                $initialBalance = $sumCreditBefore - $sumDebitBefore;
            }

            // 2. Ambil Transaksi Mutasi Jurnal Periode Terpilih
            $entries = JournalEntry::with(['journal.businessUnit'])
                ->where('account_id', $accountId)
                ->whereHas('journal', function ($q) use ($startDate, $endDate, $businessUnitId) {
                    $q->where('status', 'posted')
                      ->whereDate('journal_date', '>=', $startDate)
                      ->whereDate('journal_date', '<=', $endDate);
                    if (!empty($businessUnitId)) {
                        $q->where('business_unit_id', $businessUnitId);
                    }
                })
                ->join('journals', 'journal_entries.journal_id', '=', 'journals.id')
                ->orderBy('journals.journal_date', 'asc')
                ->orderBy('journals.id', 'asc')
                ->orderBy('journal_entries.id', 'asc')
                ->select('journal_entries.*')
                ->get();

            // 3. Hitung Running Balance per Baris
            $runningBalance = $initialBalance;
            foreach ($entries as $entry) {
                $debit  = (float) $entry->debit;
                $credit = (float) $entry->credit;

                if ($selectedAccount->normal_balance === 'debit') {
                    $runningBalance += ($debit - $credit);
                } else {
                    $runningBalance += ($credit - $debit);
                }

                $entry->running_balance = $runningBalance;
                $ledgerData->push($entry);
            }

            $endingBalance = $runningBalance;
        }

        return view('keuangan.akuntansi.buku-besar.index', compact(
            'accounts',
            'businessUnits',
            'selectedAccount',
            'startDate',
            'endDate',
            'accountId',
            'businessUnitId',
            'initialBalance',
            'ledgerData',
            'endingBalance'
        ));
    }

    public function export(Request $request)
    {
        $accountId = $request->query('account_id');
        if (!$accountId) {
            return redirect()->back()->with('error', 'Silakan pilih Akun COA terlebih dahulu.');
        }

        $account = ChartOfAccount::find($accountId);
        $accountName = $account ? str_replace(['/', '\\'], '_', $account->code . '_' . $account->name) : 'COA';
        $fileName = 'Buku_Besar_' . $accountName . '_' . date('Ymd_His') . '.xlsx';

        return Excel::download(new GeneralLedgerExport($request), $fileName);
    }
}
