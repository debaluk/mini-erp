<?php

namespace App\Exports;

use Illuminate\Http\Request;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ArSubLedgerExport implements FromCollection, WithHeadings, WithStyles, WithColumnFormatting, ShouldAutoSize
{
    protected Request $request;
    protected float $totalRemaining = 0;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function collection(): Enumerable
    {
        $startDate      = $this->request->query('start_date', now()->startOfYear()->format('Y-m-d'));
        $endDate        = $this->request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId = $this->request->query('business_unit_id');
        $customerId     = $this->request->query('customer_id');

        $query = DB::table('sales as s')
            ->join('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 's.business_unit_id')
            ->leftJoinSub(
                DB::table('payments')
                    ->select('sale_id', DB::raw('COALESCE(SUM(paid_amount), 0) as total_paid'))
                    ->whereNotNull('sale_id')->groupBy('sale_id'),
                'pay', 'pay.sale_id', '=', 's.id'
            )
            ->where('s.status', 'posted')
            ->whereDate('s.sale_date', '>=', $startDate)->whereDate('s.sale_date', '<=', $endDate);

        if (!empty($businessUnitId)) $query->where('s.business_unit_id', $businessUnitId);
        if (!empty($customerId))     $query->where('s.customer_id', $customerId);

        $invoices = $query->select(
            's.sale_date', 's.invoice_no', 'c.name as customer_name', 'bu.name as business_unit_name',
            's.due_date', 's.total', DB::raw('COALESCE(pay.total_paid, 0) as paid_amount'),
            DB::raw('GREATEST(s.total - COALESCE(pay.total_paid, 0), 0) as remaining_amount')
        )->orderBy('s.sale_date', 'asc')->get();

        $rows  = collect();
        $today = Carbon::today();

        foreach ($invoices as $inv) {
            $remaining = (float) $inv->remaining_amount;
            $this->totalRemaining += $remaining;

            $dueDate = $inv->due_date ? Carbon::parse($inv->due_date) : Carbon::parse($inv->sale_date);
            $overdueDays = $today->diffInDays($dueDate, false);

            $statusStr = 'LUNAS';
            if ($remaining > 0) {
                $statusStr = ($overdueDays < 0) ? 'OVERDUE (' . abs($overdueDays) . ' HR)' : (($inv->paid_amount > 0) ? 'CICILAN' : 'BELUM BAYAR');
            }

            $rows->push([
                'sale_date'        => Carbon::parse($inv->sale_date)->format('d/m/Y'),
                'invoice_no'       => $inv->invoice_no,
                'customer_name'    => $inv->customer_name,
                'business_unit'    => $inv->business_unit_name ?? '-',
                'due_date'         => $inv->due_date ? Carbon::parse($inv->due_date)->format('d/m/Y') : '-',
                'status'           => $statusStr,
                'total'            => (float) $inv->total,
                'paid_amount'      => (float) $inv->paid_amount,
                'remaining_amount' => $remaining,
            ]);
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['LAPORAN BUKU BANTU PIUTANG (AR SUB-LEDGER)', '', '', '', '', '', '', '', ''];
    }

    public function columnFormats(): array
    {
        return ['G' => '#,##0.00', 'H' => '#,##0.00', 'I' => '#,##0.00'];
    }

    public function styles(Worksheet $sheet): ?array
    {
        $highestRow = $sheet->getHighestRow();
        $sheet->mergeCells('A1:I1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $headings = ['Tgl. Faktur', 'No. Faktur', 'Pelanggan', 'Unit Bisnis', 'Jatuh Tempo', 'Status', 'Total Faktur (Rp)', 'Terbayar (Rp)', 'Sisa Piutang (Rp)'];
        $sheet->fromArray($headings, null, 'A2');

        $sheet->getStyle('A2:I2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '1F2937']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $totalRow = $highestRow + 1;
        $sheet->setCellValue("A{$totalRow}", 'TOTAL SISA PIUTANG');
        $sheet->mergeCells("A{$totalRow}:H{$totalRow}");
        $sheet->setCellValue("I{$totalRow}", $this->totalRemaining);

        $sheet->getStyle("A{$totalRow}:I{$totalRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN], 'bottom' => ['borderStyle' => Border::BORDER_DOUBLE]],
        ]);

        $sheet->getStyle("A2:I{$totalRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
        ]);

        return [];
    }
}
