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

class ApSubLedgerExport implements FromCollection, WithHeadings, WithStyles, WithColumnFormatting, ShouldAutoSize
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
        $supplierId     = $this->request->query('supplier_id');

        $query = DB::table('purchases as p')
            ->join('suppliers as sup', 'sup.id', '=', 'p.supplier_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'p.business_unit_id')
            ->leftJoinSub(
                DB::table('payments')
                    ->select('purchase_id', DB::raw('COALESCE(SUM(paid_amount), 0) as total_paid'))
                    ->whereNotNull('purchase_id')->groupBy('purchase_id'),
                'pay', 'pay.purchase_id', '=', 'p.id'
            )
            ->where('p.status', 'posted')
            ->whereDate('p.purchase_date', '>=', $startDate)->whereDate('p.purchase_date', '<=', $endDate);

        if ($businessUnitId) $query->where('p.business_unit_id', $businessUnitId);
        if ($supplierId)     $query->where('p.supplier_id', $supplierId);

        $purchases = $query->select(
            'p.purchase_date', 'p.purchase_no', 'sup.name as supplier_name', 'bu.name as business_unit_name',
            'p.due_date', 'p.total', DB::raw('COALESCE(pay.total_paid, 0) as paid_amount'),
            DB::raw('GREATEST(p.total - COALESCE(pay.total_paid, 0), 0) as remaining_amount')
        )->orderBy('p.purchase_date', 'asc')->get();

        $rows  = collect();
        $today = Carbon::today();

        foreach ($purchases as $pur) {
            $remaining = (float) $pur->remaining_amount;
            $this->totalRemaining += $remaining;

            $dueDate = $pur->due_date ? Carbon::parse($pur->due_date) : Carbon::parse($pur->purchase_date);
            $overdueDays = $today->diffInDays($dueDate, false);

            $statusStr = 'LUNAS';
            if ($remaining > 0) {
                $statusStr = ($overdueDays < 0) ? 'OVERDUE (' . abs($overdueDays) . ' HR)' : (($pur->paid_amount > 0) ? 'CICILAN' : 'BELUM BAYAR');
            }

            $rows->push([
                'purchase_date'    => Carbon::parse($pur->purchase_date)->format('d/m/Y'),
                'purchase_no'      => $pur->purchase_no,
                'supplier_name'    => $pur->supplier_name,
                'business_unit'    => $pur->business_unit_name ?? '-',
                'due_date'         => $pur->due_date ? Carbon::parse($pur->due_date)->format('d/m/Y') : '-',
                'status'           => $statusStr,
                'total'            => (float) $pur->total,
                'paid_amount'      => (float) $pur->paid_amount,
                'remaining_amount' => $remaining,
            ]);
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['LAPORAN BUKU BANTU HUTANG SUPPLIER (AP SUB-LEDGER)', '', '', '', '', '', '', '', ''];
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
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '9333EA']], // Ungu
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $headings = ['Tgl. Faktur', 'No. Faktur', 'Supplier', 'Unit Bisnis', 'Jatuh Tempo', 'Status', 'Total Faktur (Rp)', 'Terbayar (Rp)', 'Sisa Hutang (Rp)'];
        $sheet->fromArray($headings, null, 'A2');

        $sheet->getStyle('A2:I2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '1F2937']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3E8FF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $totalRow = $highestRow + 1;
        $sheet->setCellValue("A{$totalRow}", 'TOTAL SISA HUTANG');
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