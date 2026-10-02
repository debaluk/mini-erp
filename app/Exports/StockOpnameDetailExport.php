<?php

namespace App\Exports;

use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StockOpnameDetailExport implements FromCollection, WithStyles, ShouldAutoSize, WithCustomStartCell
{
    protected int $opnameId;

    public function __construct(int $opnameId)
    {
        $this->opnameId = $opnameId;
    }

    public function collection(): Enumerable
    {
        return DB::table('stock_opname_items as soi')
            ->join('products as p', 'p.id', '=', 'soi.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->where('soi.stock_opname_id', $this->opnameId)
            ->select(
                'p.code as product_code',
                'p.name as product_name',
                'soi.system_qty',
                'soi.actual_qty',
                'soi.difference',
                'u.name as unit_name'
            )
            ->orderBy('p.name', 'asc')
            ->get()
            ->map(function ($i) {
                return [
                    'product_code' => $i->product_code,
                    'product_name' => $i->product_name,
                    'system_qty'   => (float) $i->system_qty,
                    'actual_qty'   => (float) $i->actual_qty,
                    'difference'   => (float) $i->difference,
                    'unit'         => $i->unit_name ?? '-',
                ];
            });
    }

    public function startCell(): string
    {
        return 'A8';
    }

    public function styles(Worksheet $sheet): ?array
    {
        $op = DB::table('stock_opnames as so')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'so.business_unit_id')
            ->leftJoin('entities as e', 'e.id', '=', 'so.entity_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'so.warehouse_id')
            ->where('so.id', $this->opnameId)
            ->select(
                'so.opname_date',
                'bu.name as business_unit_name',
                'w.name as warehouse_name',
                'e.name as entity_name'
            )
            ->first();

        $entityName = $op->entity_name ?? 'NAMA ENTITAS';
        $unit = $op->business_unit_name ?? '-';
        $warehouse = $op->warehouse_name ?? '-';
        $tglSo = $op ? Carbon::parse($op->opname_date)->format('d/m/Y') : '-';
        $tglCetak = now()->format('d/m/Y H:i');

        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', $entityName);
        $sheet->mergeCells('A2:F2');
        $sheet->setCellValue('A2', 'Laporan Stock Opname');

        $sheet->setCellValue('A3', 'Unit :');
        $sheet->setCellValue('B3', $unit);
        $sheet->setCellValue('A4', 'Gudang :');
        $sheet->setCellValue('B4', $warehouse);
        $sheet->setCellValue('A5', 'Tgl SO :');
        $sheet->setCellValue('B5', $tglSo);
        $sheet->setCellValue('D5', 'Tgl Cetak :');
        $sheet->setCellValue('E5', $tglCetak);

        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1:F2')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle('A3:A5')->getFont()->setBold(true);
        $sheet->getStyle('D5')->getFont()->setBold(true);

        $sheet->fromArray(
            ['Kode Barang', 'Nama Produk', 'Stok Sistem', 'Stok Fisik', 'Selisih (Varian)', 'Satuan'],
            null,
            'A7'
        );

        $highestRow = $sheet->getHighestRow();

        $sheet->getStyle('A7:F7')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->getStyle("A7:F{$highestRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D1D5DB'],
                ],
            ],
        ]);

        return [];
    }
}
