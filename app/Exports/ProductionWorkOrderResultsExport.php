<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductionWorkOrderResultsExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function __construct(
        private readonly int $entityId,
        private readonly string $dateFrom,
        private readonly string $dateTo,
    ) {}

    public function collection(): Collection
    {
        return DB::table('production_work_order_result_lines as l')
            ->join('production_work_order_results as r', 'r.id', '=', 'l.production_work_order_result_id')
            ->join('production_work_orders as wo', 'wo.id', '=', 'r.production_work_order_id')
            ->join('boms as b', 'b.id', '=', 'wo.bom_id')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->join('workers as w', 'w.id', '=', 'l.worker_id')
            ->where('r.entity_id', $this->entityId)
            ->whereDate('r.production_date', '>=', $this->dateFrom)
            ->whereDate('r.production_date', '<=', $this->dateTo)
            ->orderByDesc('r.production_date')
            ->orderBy('wo.wo_no')
            ->orderBy('w.name')
            ->get([
                'r.production_date',
                'wo.wo_no',
                'p.code as product_code',
                'p.name as product_name',
                'w.name as worker_name',
                'l.pay_type',
                'l.unit_rate',
                'l.good_qty',
                'l.reject_qty',
                'r.status',
            ])
            ->map(fn ($row) => [
                $row->production_date,
                $row->wo_no,
                $row->product_code,
                $row->product_name,
                $row->worker_name,
                $row->pay_type === 'satuan' ? 'Satuan' : 'Borongan',
                (float) $row->unit_rate,
                (float) $row->good_qty,
                $row->pay_type === 'satuan' ? (float) $row->good_qty * (float) $row->unit_rate : 0,
                (float) $row->reject_qty,
                $row->pay_type === 'satuan' ? (float) $row->reject_qty * (float) $row->unit_rate : 0,
                $row->status === 'posted' ? 'Sudah Closing' : 'Draft',
            ]);
    }

    public function headings(): array
    {
        return [
            'Tanggal QC',
            'No. SPK',
            'Kode Produk',
            'Produk',
            'Pekerja',
            'Dasar Upah',
            'Tarif',
            'Hasil Bagus',
            'Biaya Bagus',
            'Reject',
            'Biaya Reject',
            'Status',
        ];
    }
}
