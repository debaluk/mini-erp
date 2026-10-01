<?php

namespace App\Exports;

use Illuminate\Support\Collection;
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

class PurchaseInvoiceExport implements FromCollection, WithHeadings, WithMapping, WithEvents, WithStyles, ShouldAutoSize
{
    protected $request;
    protected $rowNumber = 0;
    protected $unitBisnisName = 'Semua Unit Bisnis';
    protected $entityName = 'MINI ERP SYSTEM';

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function collection(): Collection
    {
        $startDate      = $this->request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate        = $this->request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId = $this->request->query('business_unit_id');

        $entityId = (int) (auth()->user()->entity_id ?? 1);
        $entity = DB::table('entities')->where('id', $entityId)->first();
        if ($entity) {
            $this->entityName = $entity->name;
        }

        if ($businessUnitId) {
            $bu = DB::table('business_units')->where('id', $businessUnitId)->first();
            if ($bu) {
                $this->unitBisnisName = "{$bu->code} - {$bu->name}";
            }
        }

        $query = DB::table('purchases as p')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'p.business_unit_id')
            ->join('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->leftJoinSub(
                DB::table('receipts as r')
                    ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
                    ->select('r.purchase_id', 'w.name as warehouse_name')
                    ->whereRaw('r.id = (
                        SELECT MIN(r2.id)
                        FROM receipts r2
                        WHERE r2.purchase_id = r.purchase_id
                    )'),
                'rw',
                'rw.purchase_id',
                '=',
                'p.id'
            )
            ->whereNull('p.deleted_at')
            ->whereDate('p.purchase_date', '>=', $startDate)
            ->whereDate('p.purchase_date', '<=', $endDate);

        if ($businessUnitId) {
            $query->where('p.business_unit_id', $businessUnitId);
        }

        return $query->select(
            'p.*',
            'bu.name as business_unit_name',
            's.name as supplier_name',
            'rw.warehouse_name'
        )->orderBy('p.created_at', 'desc')->get();
    }

    public function map($p): array
    {
        $this->rowNumber++;

        return [
            $this->rowNumber,
            Carbon::parse($p->purchase_date)->format('d/m/Y'),
            $p->purchase_no,
            $p->supplier_name,
            $p->warehouse_name ?? '-',
            $p->business_unit_name ?? '-',
            strtoupper($p->payment_method ?? '-')
                . ($p->due_date ? " (JT: " . Carbon::parse($p->due_date)->format('d/m/Y') . ")" : ''),
            ((int) $p->goods_received === 1) ? 'Ya (Stok +)' : 'Tidak (Pengakuan)',
            (float) $p->total,
            strtoupper($p->status),
        ];
    }

    public function headings(): array
    {
        return [
            'No',
            'Tanggal Faktur',
            'No. Faktur',
            'Supplier / Vendor',
            'Gudang Tujuan',
            'Unit Bisnis',
            'Cara Bayar',
            'Barang Diterima',
            'Total Nominal (Rp)',
            'Status',
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        return [
            11 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => '1F4E78']],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $totalDataRows = $this->rowNumber;
                $dataStartRow  = 12;
                $lastDataRow   = $dataStartRow + $totalDataRows - 1;
                $totalRow      = $lastDataRow + 1;

                $sheet->mergeCells('A1:J1');
                $sheet->setCellValue('A1', $this->entityName);
                $sheet->getStyle('A1')->getFont()->setSize(16)->setBold(true)->getColor()->setARGB('1F4E78');

                $sheet->mergeCells('A2:J2');
                $sheet->setCellValue('A2', 'Laporan Pembelian');
                $sheet->getStyle('A2')->getFont()->setSize(14)->setBold(true);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getStyle('A3:J3')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setARGB('1F4E78');

                $sheet->mergeCells('A5:J5');
                $sheet->setCellValue('A5', 'Laporan Pembelian');
                $sheet->getStyle('A5')->getFont()->setSize(14)->setBold(true);
                $sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->setCellValue('A7', 'Unit Bisnis');
                $sheet->setCellValue('B7', ': ' . $this->unitBisnisName);
                $sheet->setCellValue('H7', 'Tgl Cetak');
                $sheet->setCellValue('I7', ': ' . now()->format('d/m/Y H:i'));

                $sheet->setCellValue('A8', 'Periode');
                $sheet->setCellValue(
                    'B8',
                    ': ' .
                    Carbon::parse($this->request->query('start_date', now()->startOfMonth()))->format('d/m/Y') .
                    ' s/d ' .
                    Carbon::parse($this->request->query('end_date', now()->endOfMonth()))->format('d/m/Y')
                );

                $sheet->getStyle('A7:A8')->getFont()->setBold(true);
                $sheet->getStyle('H7')->getFont()->setBold(true);

                if ($lastDataRow >= $dataStartRow) {
                    $sheet->getStyle("I{$dataStartRow}:I{$lastDataRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
                    $sheet->getStyle("A{$dataStartRow}:B{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("C{$dataStartRow}:C{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->getFont()->setBold(true);
                    $sheet->getStyle("G{$dataStartRow}:H{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("J{$dataStartRow}:J{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->getFont()->setBold(true);
                    $sheet->getStyle("A11:J{$lastDataRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('D9D9D9');
                }

                $sheet->mergeCells("A{$totalRow}:H{$totalRow}");
                $sheet->setCellValue("A{$totalRow}", 'GRAND TOTAL PEMBELIAN');
                $sheet->getStyle("A{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                if ($lastDataRow >= $dataStartRow) {
                    $sheet->setCellValue("I{$totalRow}", "=SUM(I{$dataStartRow}:I{$lastDataRow})");
                } else {
                    $sheet->setCellValue("I{$totalRow}", 0);
                }

                $sheet->getStyle("I{$totalRow}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
                $sheet->getStyle("A{$totalRow}:J{$totalRow}")->getFont()->setBold(true)->getColor()->setARGB('FFFFFF');
                $sheet->getStyle("A{$totalRow}:J{$totalRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('2F5496');
            },
        ];
    }
}
