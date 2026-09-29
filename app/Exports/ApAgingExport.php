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

class ApAgingExport implements FromCollection, WithHeadings, WithStyles, WithColumnFormatting, ShouldAutoSize
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
        $supplierId     = $this->request->query('supplier_id');

        $query = DB::table('purchases as p')
            ->join('suppliers as sup', 'sup.id', '=', 'p.supplier_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'p.business_unit_id')
            ->leftJoinSub(
                DB::table('supplier_payment_allocations as spa')
                    ->join('supplier_payments as sp', 'sp.id', '=', 'spa.supplier_payment_id')
                    ->select('spa.purchase_id', DB::raw('COALESCE(SUM(spa.amount), 0) as total_paid'))
                    ->where('spa.status', 'posted')
                    ->where('sp.status', 'posted')
                    ->whereDate('sp.payment_date', '<=', $asOfDate)
                    ->groupBy('spa.purchase_id'),
                'pay', 'pay.purchase_id', '=', 'p.id'
            )
            ->where('p.status', 'posted')
            ->whereDate('p.purchase_date', '<=', $asOfDate);

        if ($businessUnitId) $query->where('p.business_unit_id', $businessUnitId);
        if ($supplierId)     $query->where('p.supplier_id', $supplierId);

        $purchases = $query->select(
            'p.purchase_date', 'p.purchase_no', 'sup.name as supplier_name', 'bu.name as business_unit_name',
            'p.due_date', 'p.total', DB::raw('COALESCE(pay.total_paid, 0) as paid_amount'),
            DB::raw('GREATEST(p.total - COALESCE(pay.total_paid, 0), 0) as remaining_amount')
        )->orderBy('p.purchase_date', 'asc')->get();

        $rows  = collect();
        $today = Carbon::parse($asOfDate);

        foreach ($purchases as $pur) {
            $remaining = (float) $pur->remaining_amount;
            if ($remaining <= 0) continue;

            $this->grandTotal += $remaining;
            $dueDate = $pur->due_date ? Carbon::parse($pur->due_date) : Carbon::parse($pur->purchase_date);
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
                'purchase_date' => Carbon::parse($pur->purchase_date)->format('d/m/Y'),
                'purchase_no'   => $pur->purchase_no,
                'supplier_name' => $pur->supplier_name,
                'business_unit' => $pur->business_unit_name ?? '-',
                'due_date'      => $pur->due_date ? Carbon::parse($pur->due_date)->format('d/m/Y') : '-',
                'val_current'   => $cVal,
                'val_1_30'      => $a1,
                'val_31_60'     => $a2,
                'val_above_60'  => $a3,
                'total_ap'      => $remaining,
            ]);
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['LAPORAN AGING HUTANG SUPPLIER (AP AGING ANALYSIS)', '', '', '', '', '', '', '', '', ''];
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
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '6B21A8']], // Ungu AP
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $headings = ['Tgl. Faktur', 'No. Pembelian', 'Supplier', 'Unit Bisnis', 'Jatuh Tempo', 'Current (Rp)', '1 - 30 Hari (Rp)', '31 - 60 Hari (Rp)', '> 60 Hari (Rp)', 'Total Hutang (Rp)'];
        $sheet->fromArray($headings, null, 'A2');

        $sheet->getStyle('A2:J2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7E22CE']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $totalRow = $highestRow + 1;
        $sheet->setCellValue("A{$totalRow}", 'TOTAL HUTANG');
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