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

class TrialBalanceExport implements 
    FromCollection, 
    WithHeadings, 
    WithMapping, 
    WithStyles, 
    WithColumnFormatting, 
    ShouldAutoSize
{
    protected Request $request;
    protected float $totalMutationDebit = 0;
    protected float $totalMutationCredit = 0;
    protected float $totalEndingDebit = 0;
    protected float $totalEndingCredit = 0;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function collection(): Enumerable
    {
        $startDate      = $this->request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate        = $this->request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId = $this->request->query('business_unit_id');

        $accounts = ChartOfAccount::where('is_postable', 1)
            ->where('is_active', 1)
            ->orderBy('code')
            ->get();

        $rows = collect();

        foreach ($accounts as $account) {
            // 1. Saldo Awal
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

            // 2. Mutasi Periode
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

            // 3. Saldo Akhir
            if ($account->normal_balance === 'debit') {
                $endingNet = $initialBalance + ($mutationDebit - $mutationCredit);
            } else {
                $endingNet = $initialBalance + ($mutationCredit - $mutationDebit);
            }

            $endingDebit  = 0;
            $endingCredit = 0;

            if ($account->normal_balance === 'debit') {
                if ($endingNet >= 0) $endingDebit = $endingNet;
                else $endingCredit = abs($endingNet);
            } else {
                if ($endingNet >= 0) $endingCredit = $endingNet;
                else $endingDebit = abs($endingNet);
            }

            if ($initialBalance != 0 || $mutationDebit != 0 || $mutationCredit != 0 || $endingNet != 0) {
                $rows->push((object)[
                    'account_code'    => $account->code,
                    'account_name'    => $account->name,
                    'normal_balance'  => strtoupper($account->normal_balance),
                    'mutation_debit'  => $mutationDebit,
                    'mutation_credit' => $mutationCredit,
                    'ending_debit'    => $endingDebit,
                    'ending_credit'   => $endingCredit,
                ]);

                $this->totalMutationDebit  += $mutationDebit;
                $this->totalMutationCredit += $mutationCredit;
                $this->totalEndingDebit    += $endingDebit;
                $this->totalEndingCredit   += $endingCredit;
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'LAPORAN NERACA SALDO (TRIAL BALANCE)',
            '',
            '',
            '',
            '',
            '',
            '',
        ];
    }

    public function map($row): array
    {
        return [
            $row->account_code,
            $row->account_name,
            $row->normal_balance,
            (float) $row->mutation_debit,
            (float) $row->mutation_credit,
            (float) $row->ending_debit,
            (float) $row->ending_credit,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'D' => '#,##0.00',
            'E' => '#,##0.00',
            'F' => '#,##0.00',
            'G' => '#,##0.00',
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        $highestRow = $sheet->getHighestRow();

        // 1. Title Header
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold'  => true,
                'size'  => 12,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F2937'],
            ],
            'alignment' => [
                'vertical'   => Alignment::VERTICAL_CENTER,
                'horizontal' => Alignment::HORIZONTAL_LEFT,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        // 2. Headings Row
        $headings = [
            'Kode Akun',
            'Nama Akun COA',
            'Posisi Normal',
            'Mutasi Debit (Rp)',
            'Mutasi Kredit (Rp)',
            'Saldo Akhir Debit (Rp)',
            'Saldo Akhir Kredit (Rp)',
        ];
        $sheet->fromArray($headings, null, 'A2');

        $sheet->getStyle('A2:G2')->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => '1F2937'],
                'size'  => 10,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E5E7EB'],
            ],
            'alignment' => [
                'vertical'   => Alignment::VERTICAL_CENTER,
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(25);

        // 3. Alignment Data
        $sheet->getStyle("A3:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("C3:C{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // 4. Baris Total Paling Bawah
        $totalRow = $highestRow + 1;
        $sheet->setCellValue("A{$totalRow}", 'TOTAL NERACA SALDO');
        $sheet->mergeCells("A{$totalRow}:C{$totalRow}");
        $sheet->setCellValue("D{$totalRow}", $this->totalMutationDebit);
        $sheet->setCellValue("E{$totalRow}", $this->totalMutationCredit);
        $sheet->setCellValue("F{$totalRow}", $this->totalEndingDebit);
        $sheet->setCellValue("G{$totalRow}", $this->totalEndingCredit);

        $sheet->getStyle("A{$totalRow}:G{$totalRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FEF3C7'],
            ],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_THIN],
                'bottom' => ['borderStyle' => Border::BORDER_DOUBLE],
            ],
        ]);

        // Borders All Cells
        $sheet->getStyle("A2:G{$totalRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => 'D1D5DB'],
                ],
            ],
        ]);

        return [];
    }
}