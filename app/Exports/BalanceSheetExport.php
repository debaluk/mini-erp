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

class BalanceSheetExport implements 
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
        $asOfDate       = $this->request->query('as_of_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId = $this->request->query('business_unit_id');

        $rows = collect();

        // 1. AKTIVA (ASET)
        $rows->push((object)['code' => '', 'name' => 'ASET (AKTIVA)', 'amount' => '', 'is_header' => true]);
        $assets = $this->getGroupData(['asset', 'current_asset', 'fixed_asset'], $asOfDate, $businessUnitId);
        foreach ($assets as $a) $rows->push($a);
        $totalAssets = $assets->sum('amount');
        $rows->push((object)['code' => '', 'name' => 'TOTAL ASET (AKTIVA)', 'amount' => $totalAssets, 'is_grand_total' => true]);

        // 2. PASIVA: KEWAJIBAN / UTANG
        $rows->push((object)['code' => '', 'name' => 'KEWAJIBAN / UTANG', 'amount' => '', 'is_header' => true]);
        $liabilities = $this->getGroupData(['liability', 'current_liability', 'long_term_liability'], $asOfDate, $businessUnitId);
        foreach ($liabilities as $l) $rows->push($l);
        $totalLiabilities = $liabilities->sum('amount');
        $rows->push((object)['code' => '', 'name' => 'TOTAL KEWAJIBAN', 'amount' => $totalLiabilities, 'is_total' => true]);

        // 3. PASIVA: EKUITAS / MODAL
        $rows->push((object)['code' => '', 'name' => 'EKUITAS / MODAL', 'amount' => '', 'is_header' => true]);
        $equities = $this->getGroupData(['equity', 'capital'], $asOfDate, $businessUnitId);
        foreach ($equities as $eq) $rows->push($eq);

        // Inject Laba Bersih Berjalan
        $currentProfit = $this->calcNetProfit($asOfDate, $businessUnitId);
        $rows->push((object)['code' => '-', 'name' => 'LABA / (RUGI) BERSIH PERIODE BERJALAN', 'amount' => $currentProfit, 'is_header' => false]);

        $grandEquity = $equities->sum('amount') + $currentProfit;
        $rows->push((object)['code' => '', 'name' => 'TOTAL EKUITAS', 'amount' => $grandEquity, 'is_total' => true]);

        // TOTAL PASIVA
        $totalPasiva = $totalLiabilities + $grandEquity;
        $rows->push((object)['code' => '', 'name' => 'TOTAL PASIVA (KEWAJIBAN + EKUITAS)', 'amount' => $totalPasiva, 'is_grand_total' => true]);

        return $rows;
    }

    private function getGroupData(array $types, string $asOfDate, ?string $businessUnitId)
    {
        $accounts = ChartOfAccount::whereIn('type', $types)->where('is_postable', 1)->orderBy('code')->get();
        $result = collect();

        foreach ($accounts as $account) {
            $query = JournalEntry::where('account_id', $account->id)
                ->whereHas('journal', function ($q) use ($asOfDate, $businessUnitId) {
                    $q->where('status', 'posted')->whereDate('journal_date', '<=', $asOfDate);
                    if (!empty($businessUnitId)) $q->where('business_unit_id', $businessUnitId);
                });

            $amount = ($account->normal_balance === 'debit')
                ? ((float)$query->sum('debit') - (float)$query->sum('credit'))
                : ((float)$query->sum('credit') - (float)$query->sum('debit'));

            if ($amount != 0) {
                $result->push((object)[
                    'code'      => $account->code,
                    'name'      => $account->name,
                    'amount'    => $amount,
                    'is_header' => false,
                ]);
            }
        }

        return $result;
    }

    private function calcNetProfit(string $asOfDate, ?string $businessUnitId): float
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
                    $q->where('status', 'posted')->whereDate('journal_date', '<=', $asOfDate);
                    if (!empty($businessUnitId)) $q->where('business_unit_id', $businessUnitId);
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

    public function headings(): array
    {
        return [
            'LAPORAN NERACA KEUANGAN (BALANCE SHEET)',
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

        // Header Title
        $sheet->mergeCells('A1:C1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F2937']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        // Sub Headings
        $sheet->fromArray(['Kode Akun', 'Deskripsi / Akun COA', 'Nominal (Rp)'], null, 'A2');
        $sheet->getStyle('A2:C2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '1F2937']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(24);

        $sheet->getStyle("A3:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Borders
        $sheet->getStyle("A2:C{$highestRow}")->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']],
            ],
        ]);

        return [];
    }
}