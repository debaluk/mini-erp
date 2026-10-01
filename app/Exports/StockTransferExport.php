<?php

namespace App\Exports;

use Illuminate\Http\Request;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StockTransferExport implements FromCollection, WithHeadings, WithCustomStartCell, WithStyles, ShouldAutoSize
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function collection(): Enumerable
    {
        $startDate      = $this->request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate        = $this->request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId = $this->request->query('business_unit_id');
        $fromWhId       = $this->request->query('from_warehouse_id');
        $toWhId         = $this->request->query('to_warehouse_id');

        $query = DB::table('stock_transfers as st')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'st.business_unit_id')
            ->join('warehouses as w_from', 'w_from.id', '=', 'st.from_warehouse_id')
            ->join('warehouses as w_to', 'w_to.id', '=', 'st.to_warehouse_id')
            ->join('stock_transfer_items as sti', 'sti.stock_transfer_id', '=', 'st.id')
            ->join('products as p', 'p.id', '=', 'sti.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->whereDate('st.transfer_date', '>=', $startDate)
            ->whereDate('st.transfer_date', '<=', $endDate);

        if ($businessUnitId) $query->where('st.business_unit_id', $businessUnitId);
        if ($fromWhId) $query->where('st.from_warehouse_id', $fromWhId);
        if ($toWhId) $query->where('st.to_warehouse_id', $toWhId);

        $rows = $query->select(
            'st.transfer_date',
            'st.transfer_no',
            'bu.name as business_unit_name',
            'w_from.name as from_warehouse',
            'w_to.name as to_warehouse',
            'p.code as product_code',
            'p.name as product_name',
            'sti.quantity',
            'u.name as unit_name',
            'st.status',
            'st.memo'
        )->orderBy('st.created_at', 'desc')->get();

        return $rows->map(function ($r) {
            return [
                'date'          => Carbon::parse($r->transfer_date)->format('d/m/Y'),
                'transfer_no'   => $r->transfer_no,
                'business_unit' => $r->business_unit_name ?? '-',
                'from'          => $r->from_warehouse,
                'to'            => $r->to_warehouse,
                'product_code'  => $r->product_code,
                'product_name'  => $r->product_name,
                'quantity'      => (float) $r->quantity,
                'unit'          => $r->unit_name,
                'status'        => strtoupper($r->status),
                'memo'          => $r->memo ?? '-',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Tgl. Mutasi',
            'No. Mutasi',
            'Unit Bisnis',
            'Gudang Pengirim',
            'Gudang Penerima',
            'Kode Barang',
            'Nama Produk',
            'Qty',
            'Satuan',
            'Status',
            'Memo'
        ];
    }

    public function startCell(): string
    {
        return 'A7';
    }

    public function styles(Worksheet $sheet): ?array
    {
        $entity = DB::table('entities')->where('id', 1)->first();

        $startDate = $this->request->query(
            'start_date',
            now()->startOfMonth()->format('Y-m-d')
        );
        $endDate = $this->request->query(
            'end_date',
            now()->endOfMonth()->format('Y-m-d')
        );

        $sheet->fromArray([
            [$entity->name ?? 'Entitas Utama'],
            [$entity->address ?? ''],
            ['LAPORAN MUTASI ANTAR GUDANG'],
            ['Periode: ' . Carbon::parse($startDate)->format('d/m/Y') . ' s/d ' . Carbon::parse($endDate)->format('d/m/Y')],
            ['Tanggal Cetak: ' . now()->format('d/m/Y H:i')],
        ], null, 'A1');

        $sheet->mergeCells('A1:K1');
        $sheet->mergeCells('A2:K2');
        $sheet->mergeCells('A3:K3');
        $sheet->mergeCells('A4:K4');
        $sheet->mergeCells('A5:K5');

        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT
            ],
        ]);

        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['size' => 10],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT
            ],
        ]);

        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT
            ],
        ]);

        $sheet->getStyle('A4:A5')->applyFromArray([
            'font' => ['size' => 9],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT
            ],
        ]);

        $sheet->getStyle('A7:K7')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563EB']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D1D5DB']
                ]
            ],
        ]);

        $highestRow = $sheet->getHighestRow();

        if ($highestRow >= 8) {
            $sheet->getStyle("A8:K{$highestRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'D1D5DB']
                    ]
                ],
            ]);
        }

        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->getRowDimension(3)->setRowHeight(20);
        $sheet->getRowDimension(7)->setRowHeight(22);

        return [];
    }
}
