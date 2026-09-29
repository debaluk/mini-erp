<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use App\Models\BusinessUnit;
use App\Models\JournalEntry;
use App\Exports\TrialBalanceExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;

class TrialBalanceController extends Controller
{
    public function index(Request $request)
    {
        $startDate      = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate        = $request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId = $request->query('business_unit_id');

        $businessUnits = BusinessUnit::where('is_active', 1)->orderBy('code')->get();

        // 1. Ambil Seluruh Akun COA Postable
        $accounts = ChartOfAccount::where('is_postable', 1)
            ->where('is_active', 1)
            ->orderBy('code')
            ->get();

        $trialBalanceData = collect();

        $totalInitialDebit  = 0;
        $totalInitialCredit = 0;
        $totalMutationDebit  = 0;
        $totalMutationCredit = 0;
        $totalEndingDebit   = 0;
        $totalEndingCredit  = 0;

        foreach ($accounts as $account) {
            // A. Query Saldo Awal (sebelum start_date)
            $beforeQuery = JournalEntry::where('account_id', $account->id)
                ->whereHas('journal', function ($q) use ($startDate, $businessUnitId) {
                    $q->where('status', 'posted')
                      ->whereDate('journal_date', '<', $startDate);
                    if (!empty($businessUnitId)) {
                        $q->where('business_unit_id', $businessUnitId);
                    }
                });

            $sumDebitBefore  = (float) $beforeQuery->sum('debit');
            $sumCreditBefore = (float) $beforeQuery->sum('credit');

            $initialBalance = 0;
            if ($account->normal_balance === 'debit') {
                $initialBalance = $sumDebitBefore - $sumCreditBefore;
            } else {
                $initialBalance = $sumCreditBefore - $sumDebitBefore;
            }

            // B. Query Mutasi Periode Ini (start_date s/d end_date)
            $mutationQuery = JournalEntry::where('account_id', $account->id)
                ->whereHas('journal', function ($q) use ($startDate, $endDate, $businessUnitId) {
                    $q->where('status', 'posted')
                      ->whereDate('journal_date', '>=', $startDate)
                      ->whereDate('journal_date', '<=', $endDate);
                    if (!empty($businessUnitId)) {
                        $q->where('business_unit_id', $businessUnitId);
                    }
                });

            $mutationDebit  = (float) $mutationQuery->sum('debit');
            $mutationCredit = (float) $mutationQuery->sum('credit');

            // C. Hitung Saldo Akhir
            if ($account->normal_balance === 'debit') {
                $endingNet = $initialBalance + ($mutationDebit - $mutationCredit);
            } else {
                $endingNet = $initialBalance + ($mutationCredit - $mutationDebit);
            }

            // Pecah Saldo Akhir ke Kolom Debit / Kredit Laporan
            $endingDebit  = 0;
            $endingCredit = 0;

            if ($account->normal_balance === 'debit') {
                if ($endingNet >= 0) {
                    $endingDebit = $endingNet;
                } else {
                    $endingCredit = abs($endingNet);
                }
            } else {
                if ($endingNet >= 0) {
                    $endingCredit = $endingNet;
                } else {
                    $endingDebit = abs($endingNet);
                }
            }

            // Tampilkan hanya jika ada saldo awal, mutasi, atau saldo akhir
            if ($initialBalance != 0 || $mutationDebit != 0 || $mutationCredit != 0 || $endingNet != 0) {
                $trialBalanceData->push((object) [
                    'account_code'    => $account->code,
                    'account_name'    => $account->name,
                    'account_type'    => $account->type,
                    'normal_balance'  => $account->normal_balance,
                    'initial_balance' => $initialBalance,
                    'mutation_debit'  => $mutationDebit,
                    'mutation_credit' => $mutationCredit,
                    'ending_debit'    => $endingDebit,
                    'ending_credit'   => $endingCredit,
                ]);

                // Akumulasi Total
                if ($account->normal_balance === 'debit') {
                    if ($initialBalance >= 0) $totalInitialDebit += $initialBalance;
                    else $totalInitialCredit += abs($initialBalance);
                } else {
                    if ($initialBalance >= 0) $totalInitialCredit += $initialBalance;
                    else $totalInitialDebit += abs($initialBalance);
                }

                $totalMutationDebit  += $mutationDebit;
                $totalMutationCredit += $mutationCredit;
                $totalEndingDebit    += $endingDebit;
                $totalEndingCredit   += $endingCredit;
            }
        }

        // Cek Keseimbangan Neraca Saldo
        $isBalanced = (abs($totalEndingDebit - $totalEndingCredit) < 0.01);

        return view('keuangan.laporan.neraca-saldo', compact(
            'businessUnits',
            'startDate',
            'endDate',
            'businessUnitId',
            'trialBalanceData',
            'totalInitialDebit',
            'totalInitialCredit',
            'totalMutationDebit',
            'totalMutationCredit',
            'totalEndingDebit',
            'totalEndingCredit',
            'isBalanced'
        ));
    }

    public function export(Request $request)
    {
        $fileName = 'Neraca_Saldo_' . date('Ymd_His') . '.xlsx';

        return Excel::download(new TrialBalanceExport($request), $fileName);
    }
}