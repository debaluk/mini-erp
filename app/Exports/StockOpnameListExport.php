<?php

namespace App\Exports;

use Illuminate\Http\Request;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StockOpnameListExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
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
        $warehouseId    = $this->request->query('warehouse_id');

        $query = DB::table('stock_opnames as so')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'so.business_unit_id')
            ->join('warehouses as w', 'w.id', '=', 'so.warehouse_id')
            ->join('users as u', 'u.id', '=', 'so.user_id')
            ->whereNull('so.deleted_at')
            ->whereDate('so.opname_date', '>=', $startDate)
            ->whereDate('so.opname_date', '<=', $endDate);

        if ($businessUnitId) $query->where('so.business_unit_id', $businessUnitId);
        if ($warehouseId)    $query->where('so.warehouse_id', $warehouseId);

        $opnames = $query->select(
            'so.id', 'so.opname_date', 'so.opname_no', 'bu.name as business_unit_name',
            'w.name as warehouse_name', 'u.name as creator_name', 'so.status'
        )->orderBy('so.created_at', 'desc')->get();

        return $opnames->map(function ($r) {
            $stats = DB::table('stock_opname_items')->where('stock_opname_id', $r->id)
                ->select(DB::raw('COUNT(*) as total_items'), DB::raw('SUM(CASE WHEN difference != 0 THEN 1 ELSE 0 END) as total_variance'))
                ->first();

            return [
                'date'          => Carbon::parse($r->opname_date)->format('d/m/Y'),
                'opname_no'     => $r->opname_no,
                'business_unit' => $r->business_unit_name ?? '-',
                'warehouse'     => $r->warehouse_name,
                'creator'       => $r->creator_name,
                'total_items'   => $stats->total_items ?? 0,
                'total_var'     => $stats->total_variance ?? 0,
                'status'        => strtoupper($r->status),
            ];
        });
    }

    public function headings(): array
    {
        return ['LAPORAN DAFTAR STOCK OPNAME GUDANG', '', '', '', '', '', '', ''];
    }

    public function styles(Worksheet $sheet): ?array
    {
        $highestRow = $sheet->getHighestRow();
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $headings = ['Tgl. Opname', 'No. Opname', 'Unit Bisnis', 'Lokasi Gudang', 'Petugas', 'Total Item', 'Item Selisih', 'Status'];
        $sheet->fromArray($headings, null, 'A2');

        $sheet->getStyle('A2:H2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0284C7']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->getStyle("A2:H{$highestRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
        ]);

        return [];
    }
}