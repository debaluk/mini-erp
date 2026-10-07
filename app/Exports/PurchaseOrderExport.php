<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;

class PurchaseOrderExport implements FromCollection, WithHeadings, WithMapping, WithEvents, WithStyles, ShouldAutoSize
{
    protected $request;
    protected $rowNumber = 0;
    protected $unitBisnisName = 'Semua Unit Bisnis';

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function collection(): \Illuminate\Support\Enumerable
    {
        $startDate      = $this->request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate        = $this->request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId = $this->request->query('business_unit_id');
        $supplierId     = $this->request->query('supplier_id');
        $warehouseId    = $this->request->query('warehouse_id');
        $statusFilter   = $this->request->query('status');

        if ($businessUnitId) {
            $bu = DB::table('business_units')->where('id', $businessUnitId)->first();
            if ($bu) $this->unitBisnisName = "{$bu->code} - {$bu->name}";
        }

        $query = DB::table('purchase_orders as po')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'po.business_unit_id')
            ->join('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->join('warehouses as w', 'w.id', '=', 'po.warehouse_id')
            ->whereNull('po.deleted_at')
            ->whereDate('po.po_date', '>=', $startDate)
            ->whereDate('po.po_date', '<=', $endDate);

        if ($businessUnitId) $query->where('po.business_unit_id', $businessUnitId);
        if ($supplierId)     $query->where('po.supplier_id', $supplierId);
        if ($warehouseId)    $query->where('po.warehouse_id', $warehouseId);
        if ($statusFilter)   $query->where('po.status', $statusFilter);

        $pos = $query->select(
            'po.id', 'po.po_no', 'po.po_date', 'po.status',
            'bu.name as business_unit_name',
            's.name as supplier_name',
            'w.name as warehouse_name'
        )->orderBy('po.created_at', 'desc')->get();

        foreach ($pos as $po) {
            $po->total_amount = DB::table('purchase_order_items')->where('purchase_order_id', $po->id)->sum('total') ?? 0;
        }

        return $pos;
    }

    public function map($po): array
    {
        $this->rowNumber++;
        return [
            $this->rowNumber,
            Carbon::parse($po->po_date)->format('d/m/Y'),
            $po->po_no,
            $po->supplier_name,
            $po->warehouse_name,
            $po->business_unit_name ?? '-',
            (float) $po->total_amount,
            strtoupper($po->status),
        ];
    }

    public function headings(): array
    {
        return [
            'No',
            'Tanggal PO',
            'No. PO',
            'Supplier / Vendor',
            'Gudang Tujuan',
            'Unit Bisnis',
            'Total Nominal (Rp)',
            'Status',
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        return [
            11 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => '1F4E78']
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $totalDataRows = $this->rowNumber;
                $dataStartRow  = 12;
                $lastDataRow   = $dataStartRow + $totalDataRows - 1;
                $totalRow      = $lastDataRow + 1;

                // 1. KOP PERUSAHAAN & HEADER METADATA
                $sheet->mergeCells('A1:H1');
                $sheet->setCellValue('A1', 'MINI ERP ENTERPRISE');
                $sheet->getStyle('A1')->getFont()->setSize(16)->setBold(true)->getColor()->setARGB('1F4E78');

                $sheet->mergeCells('A2:H2');
                $sheet->setCellValue('A2', 'Jl. Industri Utama No. 88, Kawasan Bisnis & Logistik | Telp: (021) 555-8899 | Email: purchasing@minierp.com');
                $sheet->getStyle('A2')->getFont()->setSize(10)->setItalic(true)->getColor()->setARGB('595959');

                // Garis Pembatas Kop
                $sheet->getStyle('A3:H3')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setARGB('1F4E78');

                // Judul Laporan
                $sheet->mergeCells('A5:H5');
                $sheet->setCellValue('A5', 'LAPORAN REKAPITULASI PURCHASE ORDER (PO)');
                $sheet->getStyle('A5')->getFont()->setSize(14)->setBold(true);
                $sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Metadata Block
                $sheet->setCellValue('A7', 'Unit Bisnis');
                $sheet->setCellValue('B7', ': ' . $this->unitBisnisName);
                $sheet->setCellValue('F7', 'Tanggal Cetak');
                $sheet->setCellValue('G7', ': ' . now()->format('d/m/Y H:i'));

                $sheet->setCellValue('A8', 'Periode Laporan');
                $sheet->setCellValue('B8', ': ' . Carbon::parse($this->request->query('start_date', now()->startOfMonth()))->format('d/m/Y') . ' s/d ' . Carbon::parse($this->request->query('end_date', now()->endOfMonth()))->format('d/m/Y'));
                $sheet->setCellValue('F8', 'Dicetak Oleh');
                $sheet->setCellValue('G8', ': ' . (auth()->user()->name ?? 'Administrator'));

                $sheet->getStyle('A7:A8')->getFont()->setBold(true);
                $sheet->getStyle('F7:F8')->getFont()->setBold(true);

                // 2. FORMAT DATA TABLE & ANGKA
                $sheet->getStyle("G{$dataStartRow}:G{$lastDataRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
                $sheet->getStyle("A{$dataStartRow}:B{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$dataStartRow}:C{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->getFont()->setBold(true);
                $sheet->getStyle("F{$dataStartRow}:F{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("H{$dataStartRow}:H{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->getFont()->setBold(true);

                // Border Data
                $sheet->getStyle("A11:H{$lastDataRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('D9D9D9');

                // 3. BARIS GRAND TOTAL
                $sheet->mergeCells("A{$totalRow}:F{$totalRow}");
                $sheet->setCellValue("A{$totalRow}", 'GRAND TOTAL REKAPITULASI PO');
                $sheet->getStyle("A{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $sheet->setCellValue("G{$totalRow}", "=SUM(G{$dataStartRow}:G{$lastDataRow})");
                $sheet->getStyle("G{$totalRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');

                $sheet->getStyle("A{$totalRow}:H{$totalRow}")->getFont()->setBold(true)->getColor()->setARGB('FFFFFF');
                $sheet->getStyle("A{$totalRow}:H{$totalRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('2F5496');
                $sheet->getStyle("A{$totalRow}:H{$totalRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            },
        ];
    }
}