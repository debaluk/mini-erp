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

class ArAgingExport implements FromCollection, WithHeadings, WithStyles, WithColumnFormatting, ShouldAutoSize
{
    protected Request $request;
    protected float $grandTotal = 0;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function collection(): Enumerable
    {
        $asOfDate       = $this->request->query('as_of_date', date('Y-m-d'));
        $businessUnitId = $this->request->query('business_unit_id');
        $customerId     = $this->request->query('customer_id');

        $query = DB::table('sales as s')
            ->join('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 's.business_unit_id')
            ->leftJoinSub(
                DB::table('payments')
                    ->select('sale_id', DB::raw('COALESCE(SUM(paid_amount), 0) as total_paid'))
                    ->whereDate('payment_date', '<=', $asOfDate)
                    ->groupBy('sale_id'),
                'pay', 'pay.sale_id', '=', 's.id'
            )
            ->where('s.status', 'posted')
            ->whereDate('s.sale_date', '<=', $asOfDate);

        if ($businessUnitId) $query->where('s.business_unit_id', $businessUnitId);
        if ($customerId)     $query->where('s.customer_id', $customerId);

        $invoices = $query->select(
            's.sale_date', 's.invoice_no', 'c.name as customer_name', 'bu.name as business_unit_name',
            's.due_date', 's.total', DB::raw('COALESCE(pay.total_paid, 0) as paid_amount'),
            DB::raw('GREATEST(s.total - COALESCE(pay.total_paid, 0), 0) as remaining_amount')
        )->orderBy('s.sale_date', 'asc')->get();

        $rows  = collect();
        $today = Carbon::parse($asOfDate);

        foreach ($invoices as $inv) {
            $remaining = (float) $inv->remaining_amount;
            if ($remaining <= 0) continue;

            $this->grandTotal += $remaining;
            $dueDate = $inv->due_date ? Carbon::parse($inv->due_date) : Carbon::parse($inv->sale_date);
            $overdueDays = $today->diffInDays($dueDate, false);

            $cVal = 0; $a1 = 0; $a2 = 0; $a3 = 0;
            if ($overdueDays >= 0) {
                $cVal = $remaining;
            } else {
                $days = abs($overdueDays);
                if ($days <= 30)      $a1 = $remaining;
                elseif ($days <= 60)  $a2 = $remaining;
                else                  $a3 = $remaining;
            }

            $rows->push([
                'sale_date'     => Carbon::parse($inv->sale_date)->format('d/m/Y'),
                'invoice_no'    => $inv->invoice_no,
                'customer_name' => $inv->customer_name,
                'business_unit' => $inv->business_unit_name ?? '-',
                'due_date'      => $inv->due_date ? Carbon::parse($inv->due_date)->format('d/m/Y') : '-',
                'val_current'   => $cVal,
                'val_1_30'      => $a1,
                'val_31_60'     => $a2,
                'val_above_60'  => $a3,
                'total_ar'      => $remaining,
            ]);
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['LAPORAN AGING PIUTANG (AR AGING ANALYSIS)', '', '', '', '', '', '', '', '', ''];
    }

    public function columnFormats(): array
    {
        return [
            'F' => '#,##0.00',
            'G' => '#,##0.00',
            'H' => '#,##0.00',
            'I' => '#,##0.00',
            'J' => '#,##0.00',
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        $highestRow = $sheet->getHighestRow();

        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $headings = ['Tgl. Faktur', 'No. Faktur', 'Pelanggan', 'Unit Bisnis', 'Jatuh Tempo', 'Current (Rp)', '1 - 30 Hari (Rp)', '31 - 60 Hari (Rp)', '> 60 Hari (Rp)', 'Total Piutang (Rp)'];
        $sheet->fromArray($headings, null, 'A2');

        $sheet->getStyle('A2:J2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $totalRow = $highestRow + 1;
        $sheet->setCellValue("A{$totalRow}", 'TOTAL PIUTANG');
        $sheet->mergeCells("A{$totalRow}:I{$totalRow}");
        $sheet->setCellValue("J{$totalRow}", $this->grandTotal);

        $sheet->getStyle("A{$totalRow}:J{$totalRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_THIN],
                'bottom' => ['borderStyle' => Border::BORDER_DOUBLE],
            ],
        ]);

        $sheet->getStyle("A2:J{$totalRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
        ]);

        return [];
    }
}