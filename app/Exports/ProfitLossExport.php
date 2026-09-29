<?php

namespace App\Exports;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ProfitLossExport implements 
    FromCollection, 
    WithHeadings, 
    WithMapping, 
    WithStyles, 
    WithColumnFormatting, 
    ShouldAutoSize
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function collection(): Enumerable
    {
        $startDate      = $this->request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate        = $this->request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId = $this->request->query('business_unit_id');

        $rows = collect();

        // 1. PENDAPATAN PENJUALAN KOTOR
        $rows->push((object)['code' => '', 'name' => 'I. PENDAPATAN PENJUALAN KOTOR', 'amount' => '', 'is_header' => true]);
        $grossSales = $this->getPatternData(['4%'], 'credit', $startDate, $endDate, $businessUnitId, ['4000301']);
        foreach ($grossSales as $item) $rows->push($item);
        $totalGrossSales = $grossSales->sum('amount');
        $rows->push((object)['code' => '', 'name' => 'TOTAL PENJUALAN KOTOR', 'amount' => $totalGrossSales, 'is_total' => true]);

        // 2. RETUR PENJUALAN
        $rows->push((object)['code' => '', 'name' => 'RETUR PENJUALAN', 'amount' => '', 'is_header' => true]);
        $returns = $this->getPatternData(['4000301'], 'debit', $startDate, $endDate, $businessUnitId);
        foreach ($returns as $item) $rows->push($item);
        $totalReturns = $returns->sum('amount');
        $rows->push((object)['code' => '', 'name' => 'TOTAL RETUR PENJUALAN', 'amount' => $totalReturns, 'is_total' => true]);

        // PENJUALAN BERSIH
        $netSales = $totalGrossSales - $totalReturns;
        $rows->push((object)['code' => '', 'name' => 'PENJUALAN BERSIH (NET SALES)', 'amount' => $netSales, 'is_total' => true]);

        // 3. BEBAN POKOK PENJUALAN (HPP)
        $rows->push((object)['code' => '', 'name' => 'II. BEBAN POKOK PENJUALAN (HPP)', 'amount' => '', 'is_header' => true]);
        $cogs = $this->getPatternData(['5%'], 'debit', $startDate, $endDate, $businessUnitId);
        foreach ($cogs as $item) $rows->push($item);
        $totalCogs = $cogs->sum('amount');
        $rows->push((object)['code' => '', 'name' => 'TOTAL BEBAN POKOK PENJUALAN', 'amount' => $totalCogs, 'is_total' => true]);

        // LABA KOTOR
        $grossProfit = $netSales - $totalCogs;
        $rows->push((object)['code' => '', 'name' => 'LABA KOTOR (GROSS PROFIT)', 'amount' => $grossProfit, 'is_grand_total' => true]);

        // 4. BEBAN OPERASIONAL & KERUGIAN AFKIR
        $rows->push((object)['code' => '', 'name' => 'III. BEBAN OPERASIONAL & KERUGIAN PERSEDIAAN', 'amount' => '', 'is_header' => true]);
        $expenses = $this->getPatternData(['6%', '7%'], 'debit', $startDate, $endDate, $businessUnitId);
        foreach ($expenses as $item) $rows->push($item);
        $totalExpense = $expenses->sum('amount');
        $rows->push((object)['code' => '', 'name' => 'TOTAL BEBAN OPERASIONAL', 'amount' => $totalExpense, 'is_total' => true]);

        // LABA OPERASIONAL
        $operatingProfit = $grossProfit - $totalExpense;
        $rows->push((object)['code' => '', 'name' => 'LABA OPERASIONAL (OPERATING PROFIT)', 'amount' => $operatingProfit, 'is_grand_total' => true]);

        // 5. PENDAPATAN / BEBAN LAIN-LAIN
        $otherIncomes  = $this->getPatternData(['8%'], 'credit', $startDate, $endDate, $businessUnitId);
        $otherExpenses = $this->getPatternData(['9%'], 'debit', $startDate, $endDate, $businessUnitId);
        $netOther = $otherIncomes->sum('amount') - $otherExpenses->sum('amount');

        if ($otherIncomes->count() > 0 || $otherExpenses->count() > 0) {
            $rows->push((object)['code' => '', 'name' => 'IV. PENDAPATAN & BEBAN LAIN-LAIN', 'amount' => '', 'is_header' => true]);
            foreach ($otherIncomes as $item) $rows->push($item);
            foreach ($otherExpenses as $item) $rows->push($item);
            $rows->push((object)['code' => '', 'name' => 'TOTAL PENDAPATAN / (BEBAN) LAIN-LAIN', 'amount' => $netOther, 'is_total' => true]);
        }

        // LABA BERSIH SEBELUM PAJAK
        $netProfit = $operatingProfit + $netOther;
        $rows->push((object)['code' => '', 'name' => 'LABA BERSIH SEBELUM PAJAK (NET PROFIT)', 'amount' => $netProfit, 'is_net_profit' => true]);

        return $rows;
    }

    private function getPatternData(array $codePatterns, string $targetNormalBalance, string $startDate, string $endDate, ?string $businessUnitId, array $excludeCodes = [])
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

            $amount = ($targetNormalBalance === 'credit') ? ($sumCredit - $sumDebit) : ($sumDebit - $sumCredit);

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

    public function headings(): array
    {
        return [
            'LAPORAN LABA RUGI (PROFIT & LOSS STATEMENT)',
            '',
            '',
        ];
    }

    public function map($row): array
    {
        return [
            $row->code,
            $row->name,
            is_numeric($row->amount) ? (float) $row->amount : '',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'C' => '#,##0.00',
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        $highestRow = $sheet->getHighestRow();

        $sheet->mergeCells('A1:C1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F2937']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $sheet->fromArray(['Kode Akun', 'Deskripsi / Akun COA', 'Nominal (Rp)'], null, 'A2');
        $sheet->getStyle('A2:C2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '1F2937']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(24);

        $sheet->getStyle("A3:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("A2:C{$highestRow}")->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']],
            ],
        ]);

        return [];
    }
}