<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use App\Models\BusinessUnit;
use App\Models\JournalEntry;
use App\Exports\ProfitLossExport;
use Maatwebsite\Excel\Facades\Excel;

class ProfitLossController extends Controller
{
    public function index(Request $request)
    {
        $startDate      = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate        = $request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId = $request->query('business_unit_id');

        $businessUnits = BusinessUnit::where('is_active', 1)->orderBy('code')->get();

        // 1. PENDAPATAN PENJUALAN KOTOR (Awal Kepala 4 - Kredit)
        $grossSalesAccounts = $this->getAccountsByCodePattern(['4%'], 'credit', $startDate, $endDate, $businessUnitId, ['4000301']);
        $totalGrossSales    = $grossSalesAccounts->sum('amount'); // Rp 445.000,00

        // 2. RETUR PENJUALAN (Akun 4000301 / Debit)
        $returnSalesAccounts = $this->getAccountsByCodePattern(['4000301'], 'debit', $startDate, $endDate, $businessUnitId);
        $totalSalesReturns   = $returnSalesAccounts->sum('amount'); // Rp 26.000,00

        // PENJUALAN BERSIH
        $netSales = $totalGrossSales - $totalSalesReturns; // Rp 419.000,00

        // 3. BEBAN POKOK PENJUALAN / HPP (Awal Kepala 5)
        $cogsAccounts = $this->getAccountsByCodePattern(['5%'], 'debit', $startDate, $endDate, $businessUnitId);
        $totalCogs    = $cogsAccounts->sum('amount'); // Rp 245.000,00 (Nett setelah pembalikan HPP Retur Good)

        // LABA KOTOR (GROSS PROFIT)
        $grossProfit = $netSales - $totalCogs; // Rp 174.000,00

        // 4. BEBAN OPERASIONAL & KERUGIAN BARANG AFKIR/RUSAK (Awal Kepala 6, 7 & 7000202)
        $expenseAccounts = $this->getAccountsByCodePattern(['6%', '7%'], 'debit', $startDate, $endDate, $businessUnitId);
        $totalExpense    = $expenseAccounts->sum('amount'); // Rp 5.000,00 (Kerugian Kerusakan Persediaan)

        // LABA OPERASIONAL
        $operatingProfit = $grossProfit - $totalExpense; // Rp 169.000,00

        // 5. PENDAPATAN & BEBAN LAIN-LAIN (Awal Kepala 8 & 9)
        $otherIncomeAccounts  = $this->getAccountsByCodePattern(['8%'], 'credit', $startDate, $endDate, $businessUnitId);
        $otherExpenseAccounts = $this->getAccountsByCodePattern(['9%'], 'debit', $startDate, $endDate, $businessUnitId);

        $totalOtherIncome  = $otherIncomeAccounts->sum('amount');
        $totalOtherExpense = $otherExpenseAccounts->sum('amount');
        $netOtherIncome    = $totalOtherIncome - $totalOtherExpense;

        // LABA BERSIH SEBELUM PAJAK
        $netProfit = $operatingProfit + $netOtherIncome; // Rp 169.000,00

        return view('keuangan.laporan.laba-rugi', compact(
            'businessUnits',
            'startDate',
            'endDate',
            'businessUnitId',
            'grossSalesAccounts',
            'returnSalesAccounts',
            'cogsAccounts',
            'expenseAccounts',
            'otherIncomeAccounts',
            'otherExpenseAccounts',
            'totalGrossSales',
            'totalSalesReturns',
            'netSales',
            'totalCogs',
            'grossProfit',
            'totalExpense',
            'operatingProfit',
            'totalOtherIncome',
            'totalOtherExpense',
            'netOtherIncome',
            'netProfit'
        ));
    }

    /**
     * Helper universal kalkulasi saldo bersih COA berdasarkan pola Kode COA
     */
    private function getAccountsByCodePattern(array $codePatterns, string $targetNormalBalance, string $startDate, string $endDate, ?string $businessUnitId, array $excludeCodes = [])
    {
        $queryCOA = ChartOfAccount::where('is_postable', 1)->where('is_active', 1);

        $queryCOA->where(function ($q) use ($codePatterns) {
            foreach ($codePatterns as $pattern) {
                $q->orWhere('code', 'LIKE', $pattern);
            }
        });

        if (!empty($excludeCodes)) {
            $queryCOA->whereNotIn('code', $excludeCodes);
        }

        $accounts = $queryCOA->orderBy('code')->get();
        $result   = collect();

        foreach ($accounts as $account) {
            $query = JournalEntry::where('account_id', $account->id)
                ->whereHas('journal', function ($q) use ($startDate, $endDate, $businessUnitId) {
                    $q->where('status', 'posted')
                      ->whereDate('journal_date', '>=', $startDate)
                      ->whereDate('journal_date', '<=', $endDate);
                    if (!empty($businessUnitId)) {
                        $q->where('business_unit_id', $businessUnitId);
                    }
                });

            $sumDebit  = (float) $query->sum('debit');
            $sumCredit = (float) $query->sum('credit');

            if ($targetNormalBalance === 'credit') {
                $amount = $sumCredit - $sumDebit;
            } else {
                $amount = $sumDebit - $sumCredit;
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

    public function export(Request $request)
    {
        $fileName = 'Laporan_Laba_Rugi_' . date('Ymd_His') . '.xlsx';

        return Excel::download(new ProfitLossExport($request), $fileName);
    }
}