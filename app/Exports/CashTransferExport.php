<?php

namespace App\Exports;

use App\Models\Journal;
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

class CashTransferExport implements 
    FromCollection, 
    WithHeadings, 
    WithMapping, 
    WithStyles, 
    WithColumnFormatting, 
    ShouldAutoSize
{
    protected Request $request;
    protected float $totalAmount = 0;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function collection(): Enumerable
    {
        $startDate      = $this->request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate        = $this->request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId = $this->request->query('business_unit_id');
        $fromAccountId  = $this->request->query('from_account_id');
        $toAccountId    = $this->request->query('to_account_id');

        $query = Journal::with(['businessUnit', 'entries.account'])
            ->where('source_type', 'CASH_TRANSFER')
            ->whereDate('journal_date', '>=', $startDate)
            ->whereDate('journal_date', '<=', $endDate);

        if (!empty($businessUnitId)) $query->where('business_unit_id', $businessUnitId);

        if (!empty($fromAccountId)) {
            $query->whereHas('entries', function ($q) use ($fromAccountId) {
                $q->where('account_id', $fromAccountId)->where('credit', '>', 0);
            });
        }

        if (!empty($toAccountId)) {
            $query->whereHas('entries', function ($q) use ($toAccountId) {
                $q->where('account_id', $toAccountId)->where('debit', '>', 0);
            });
        }

        $journals = $query->orderBy('journal_date', 'asc')->get();
        $rows     = collect();

        foreach ($journals as $journal) {
            $fromEntry = $journal->entries->where('credit', '>', 0)->first();
            $toEntry   = $journal->entries->where('debit', '>', 0)->first();

            $amount = (float) ($toEntry?->debit ?? 0);
            $this->totalAmount += $amount;

            $rows->push((object)[
                'journal_date'  => $journal->journal_date,
                'journal_no'    => $journal->journal_no,
                'business_unit' => $journal->businessUnit?->name ?? '-',
                'from_account'  => $fromEntry ? "[{$fromEntry->account?->code}] {$fromEntry->account?->name}" : '-',
                'to_account'    => $toEntry ? "[{$toEntry->account?->code}] {$toEntry->account?->name}" : '-',
                'description'   => $journal->description,
                'amount'        => $amount,
            ]);
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['LAPORAN MUTASI & TRANSFER INTERNAL KAS / BANK', '', '', '', '', '', ''];
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
            $row->from_account,
            $row->to_account,
            $row->description,
            (float) $row->amount,
        ];
    }

    public function columnFormats(): array
    {
        return ['G' => '#,##0.00'];
    }

    public function styles(Worksheet $sheet): ?array
    {
        $highestRow = $sheet->getHighestRow();

        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F2937']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $headings = ['Tanggal', 'No. Transaksi', 'Business Unit', 'Akun Sumber (Asal)', 'Akun Tujuan (Penerima)', 'Keterangan / Memo', 'Nominal (Rp)'];
        $sheet->fromArray($headings, null, 'A2');

        $sheet->getStyle('A2:G2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '1F2937']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->getStyle("A3:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("B3:B{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $totalRow = $highestRow + 1;
        $sheet->setCellValue("A{$totalRow}", 'TOTAL MUTASI DANA');
        $sheet->mergeCells("A{$totalRow}:F{$totalRow}");
        $sheet->setCellValue("G{$totalRow}", $this->totalAmount);

        $sheet->getStyle("A{$totalRow}:G{$totalRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0F2FE']],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_THIN],
                'bottom' => ['borderStyle' => Border::BORDER_DOUBLE],
            ],
        ]);

        $sheet->getStyle("A2:G{$totalRow}")->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']],
            ],
        ]);

        return [];
    }
}