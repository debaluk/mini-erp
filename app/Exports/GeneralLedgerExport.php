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
use Carbon\Carbon;

class GeneralLedgerExport implements 
    FromCollection, 
    WithHeadings, 
    WithMapping, 
    WithStyles, 
    WithColumnFormatting, 
    ShouldAutoSize
{
    protected Request $request;
    protected ?ChartOfAccount $account = null;
    protected float $initialBalance = 0;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function collection(): Enumerable
    {
        $startDate      = $this->request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate        = $this->request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $accountId      = $this->request->query('account_id');
        $businessUnitId = $this->request->query('business_unit_id');

        $this->account = ChartOfAccount::find($accountId);
        $rows = collect();

        if (!$this->account) {
            return $rows;
        }

        // 1. Saldo Awal
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

        if ($this->account->normal_balance === 'debit') {
            $this->initialBalance = $sumDebitBefore - $sumCreditBefore;
        } else {
            $this->initialBalance = $sumCreditBefore - $sumDebitBefore;
        }

        // Row Saldo Awal
        $rows->push((object)[
            'journal_date'    => $startDate,
            'journal_no'      => '-',
            'business_unit'   => '-',
            'source_type'     => 'INITIAL',
            'description'     => 'SALDO AWAL PERIODE',
            'debit'           => 0,
            'credit'          => 0,
            'running_balance' => $this->initialBalance,
        ]);

        // 2. Transaksi Mutasi Jurnal
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

        $runningBalance = $this->initialBalance;

        foreach ($entries as $entry) {
            $debit  = (float) $entry->debit;
            $credit = (float) $entry->credit;

            if ($this->account->normal_balance === 'debit') {
                $runningBalance += ($debit - $credit);
            } else {
                $runningBalance += ($credit - $debit);
            }

            $journal = $entry->journal;

            $rows->push((object)[
                'journal_date'    => $journal?->journal_date,
                'journal_no'      => $journal?->journal_no ?? '-',
                'business_unit'   => $journal?->businessUnit?->name ?? '-',
                'source_type'     => strtoupper($journal?->source_type ?? 'MANUAL'),
                'description'     => $journal?->description ?? '-',
                'debit'           => $debit,
                'credit'          => $credit,
                'running_balance' => $runningBalance,
            ]);
        }

        return $rows;
    }

    public function headings(): array
    {
        $code = $this->account?->code ?? '-';
        $name = $this->account?->name ?? '-';

        return [
            "Buku Besar Akun: [{$code}] {$name}",
            '',
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
        $formattedDate = '-';
        if ($row->journal_date) {
            try {
                $formattedDate = Carbon::parse($row->journal_date)->format('d/m/Y');
            } catch (\Exception $e) {
                $formattedDate = (string) $row->journal_date;
            }
        }

        return [
            $formattedDate,
            $row->journal_no,
            $row->business_unit,
            $row->source_type,
            $row->description,
            (float) $row->debit,
            (float) $row->credit,
            (float) $row->running_balance,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'F' => '#,##0.00',
            'G' => '#,##0.00',
            'H' => '#,##0.00',
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        $highestRow = $sheet->getHighestRow();

        // 1. Header Judul
        $sheet->mergeCells('A1:H1');
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

        // 2. Header Kolom
        $headings = [
            'Tanggal',
            'No. Jurnal',
            'Business Unit',
            'Tipe Transaksi',
            'Keterangan / Memo',
            'Debit (Rp)',
            'Kredit (Rp)',
            'Saldo Running (Rp)',
        ];
        $sheet->fromArray($headings, null, 'A2');

        $sheet->getStyle('A2:H2')->applyFromArray([
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
        $sheet->getRowDimension(2)->setRowHeight(24);

        // 3. Alignment Data
        $sheet->getStyle("A3:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("B3:B{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D3:D{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Highlight Row Saldo Awal
        if ($highestRow >= 3) {
            $sheet->getStyle('A3:H3')->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FEF3C7'],
                ],
            ]);
        }

        // Borders
        if ($highestRow >= 2) {
            $sheet->getStyle("A2:H{$highestRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color'       => ['rgb' => 'D1D5DB'],
                    ],
                ],
            ]);
        }

        return [];
    }
}