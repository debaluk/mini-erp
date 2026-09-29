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

class CashDisbursementExport implements 
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
        $startDate        = $this->request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate          = $this->request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId   = $this->request->query('business_unit_id');
        $cashAccountId    = $this->request->query('cash_account_id');
        $disbursementType = $this->request->query('disbursement_type');

        $query = Journal::with(['businessUnit', 'entries.account'])
            ->whereIn('source_type', ['CASH_OUT', 'AP_PAYMENT'])
            ->whereDate('journal_date', '>=', $startDate)
            ->whereDate('journal_date', '<=', $endDate);

        if (!empty($businessUnitId)) $query->where('business_unit_id', $businessUnitId);
        if (!empty($disbursementType)) $query->where('source_type', $disbursementType);

        if (!empty($cashAccountId)) {
            $query->whereHas('entries', function ($q) use ($cashAccountId) {
                $q->where('account_id', $cashAccountId)->where('credit', '>', 0);
            });
        }

        $journals = $query->orderBy('journal_date', 'asc')->get();
        $rows     = collect();

        foreach ($journals as $journal) {
            $cashEntry        = $journal->entries->where('credit', '>', 0)->first();
            $counterpartEntry = $journal->entries->where('debit', '>', 0)->first();

            $amount = (float) ($cashEntry?->credit ?? 0);
            $this->totalAmount += $amount;

            $typeLabel = ($journal->source_type === 'AP_PAYMENT') ? 'Pembayaran Hutang' : 'Pengeluaran Umum';

            $rows->push((object)[
                'journal_date'    => $journal->journal_date,
                'journal_no'      => $journal->journal_no,
                'business_unit'   => $journal->businessUnit?->name ?? '-',
                'type'            => $typeLabel,
                'cash_account'    => $cashEntry ? "[{$cashEntry->account?->code}] {$cashEntry->account?->name}" : '-',
                'counterpart_acc' => $counterpartEntry ? "[{$counterpartEntry->account?->code}] {$counterpartEntry->account?->name}" : '-',
                'description'     => $journal->description,
                'amount'          => $amount,
            ]);
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['LAPORAN PENGELUARAN KAS & BANK', '', '', '', '', '', '', ''];
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
            $row->type,
            $row->cash_account,
            $row->counterpart_acc,
            $row->description,
            (float) $row->amount,
        ];
    }

    public function columnFormats(): array
    {
        return ['H' => '#,##0.00'];
    }

    public function styles(Worksheet $sheet): ?array
    {
        $highestRow = $sheet->getHighestRow();

        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '991B1B']], // Merah Tua
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $headings = ['Tanggal', 'No. Transaksi', 'Business Unit', 'Kategori', 'Akun Kas/Bank', 'Akun Tujuan', 'Keterangan', 'Nominal (Rp)'];
        $sheet->fromArray($headings, null, 'A2');

        $sheet->getStyle('A2:H2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '1F2937']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEE2E2']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->getStyle("A3:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("B3:B{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D3:D{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $totalRow = $highestRow + 1;
        $sheet->setCellValue("A{$totalRow}", 'TOTAL PENGELUARAN');
        $sheet->mergeCells("A{$totalRow}:G{$totalRow}");
        $sheet->setCellValue("H{$totalRow}", $this->totalAmount);

        $sheet->getStyle("A{$totalRow}:H{$totalRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_THIN],
                'bottom' => ['borderStyle' => Border::BORDER_DOUBLE],
            ],
        ]);

        $sheet->getStyle("A2:H{$totalRow}")->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']],
            ],
        ]);

        return [];
    }
}