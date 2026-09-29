<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use App\Models\BusinessUnit;
use App\Models\JournalEntry;
use App\Exports\BalanceSheetExport;
use Maatwebsite\Excel\Facades\Excel;

class BalanceSheetController extends Controller
{
    public function index(Request $request)
    {
        $asOfDate       = $request->query('as_of_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId = $request->query('business_unit_id');

        $businessUnits = BusinessUnit::where('is_active', 1)->orderBy('code')->get();

        // 1. ASET (AKTIVA)
        $assetAccounts = $this->getAccountBalanceGroup(['asset', 'current_asset', 'fixed_asset'], $asOfDate, $businessUnitId);
        $totalAssets   = $assetAccounts->sum('amount');

        // 2. KEWAJIBAN / UTANG (LIABILITIES)
        $liabilityAccounts = $this->getAccountBalanceGroup(['liability', 'current_liability', 'long_term_liability'], $asOfDate, $businessUnitId);
        $totalLiabilities   = $liabilityAccounts->sum('amount');

        // 3. EKUITAS / MODAL (EQUITY)
        $equityAccounts = $this->getAccountBalanceGroup(['equity', 'capital'], $asOfDate, $businessUnitId);
        $totalEquity    = $equityAccounts->sum('amount');

        // 4. HITUNG LABA BERSIH PERIODE BERJALAN (AUTOMATIC RETAINED EARNINGS INJECTION)
        $currentNetProfit = $this->calculateCurrentNetProfit($asOfDate, $businessUnitId);

        // Total Ekuitas Akhir = Ekuitas Terdaftar + Laba Bersih Berjalan
        $grandTotalEquity = $totalEquity + $currentNetProfit;

        // Total Pasiva = Kewajiban + Total Ekuitas
        $totalPasiva = $totalLiabilities + $grandTotalEquity;

        // Cek Keseimbangan Persamaan Dasar Akuntansi (Aset = Pasiva)
        $isBalanced = (abs($totalAssets - $totalPasiva) < 0.01);

        return view('keuangan.laporan.neraca', compact(
            'businessUnits',
            'asOfDate',
            'businessUnitId',
            'assetAccounts',
            'liabilityAccounts',
            'equityAccounts',
            'totalAssets',
            'totalLiabilities',
            'totalEquity',
            'currentNetProfit',
            'grandTotalEquity',
            'totalPasiva',
            'isBalanced'
        ));
    }

    /**
     * Helper kalkulasi akumulasi saldo akun Neraca (Aset, Kewajiban, Ekuitas) s/d Tanggal
     */
    private function getAccountBalanceGroup(array $types, string $asOfDate, ?string $businessUnitId)
    {
        $accounts = ChartOfAccount::whereIn('type', $types)
            ->where('is_postable', 1)
            ->where('is_active', 1)
            ->orderBy('code')
            ->get();

        $result = collect();

        foreach ($accounts as $account) {
            $query = JournalEntry::where('account_id', $account->id)
                ->whereHas('journal', function ($q) use ($asOfDate, $businessUnitId) {
                    $q->where('status', 'posted')
                      ->whereDate('journal_date', '<=', $asOfDate);
                    if (!empty($businessUnitId)) {
                        $q->where('business_unit_id', $businessUnitId);
                    }
                });

            $sumDebit  = (float) $query->sum('debit');
            $sumCredit = (float) $query->sum('credit');

            // Kalkulasi nominal akumulasi sesuai posisi normal
            if ($account->normal_balance === 'debit') {
                $amount = $sumDebit - $sumCredit;
            } else {
                $amount = $sumCredit - $sumDebit;
            }

            if ($amount != 0) {
                $result->push((object) [
                    'code'   => $account->code,
                    'name'   => $account->name,
                    'amount' => $amount,
                ]);
            }
        }

        return $result;
    }

    /**
     * Helper kalkulasi Laba Bersih Berjalan (Revenue - COGS - Expenses) s/d Tanggal
     */
    private function calculateCurrentNetProfit(string $asOfDate, ?string $businessUnitId): float
    {
        $plAccounts = ChartOfAccount::whereIn('type', [
            'revenue', 'income', 'cogs', 'hpp', 
            'expense', 'operational_expense', 
            'other_income', 'other_revenue', 'other_expense'
        ])->where('is_postable', 1)->get();

        $netProfit = 0;

        foreach ($plAccounts as $account) {
            $query = JournalEntry::where('account_id', $account->id)
                ->whereHas('journal', function ($q) use ($asOfDate, $businessUnitId) {
                    $q->where('status', 'posted')
                      ->whereDate('journal_date', '<=', $asOfDate);
                    if (!empty($businessUnitId)) {
                        $q->where('business_unit_id', $businessUnitId);
                    }
                });

            $sumDebit  = (float) $query->sum('debit');
            $sumCredit = (float) $query->sum('credit');

            if (in_array($account->type, ['revenue', 'income', 'other_income', 'other_revenue'])) {
                $netProfit += ($sumCredit - $sumDebit);
            } else {
                $netProfit -= ($sumDebit - $sumCredit);
            }
        }

        return $netProfit;
    }

    public function export(Request $request)
    {
        $fileName = 'Laporan_Neraca_Keuangan_' . date('Ymd_His') . '.xlsx';

        return Excel::download(new BalanceSheetExport($request), $fileName);
    }
}
