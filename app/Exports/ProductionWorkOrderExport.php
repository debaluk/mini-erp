<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductionWorkOrderExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function __construct(
        private readonly int $entityId,
        private readonly string $status = '',
        private readonly string $dateFrom = '',
        private readonly string $dateTo = '',
    ) {}

    public function collection(): Collection
    {
        $rows = DB::table('production_work_orders as wo')
            ->join('boms as b', 'b.id', '=', 'wo.bom_id')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->join('warehouses as w', 'w.id', '=', 'wo.warehouse_id')
            ->where('wo.entity_id', $this->entityId)
            ->select('wo.wo_no', 'wo.wo_date', 'p.code as product_code', 'p.name as product_name', 'b.code as bom_code', 'w.name as warehouse_name', 'wo.batch_qty', 'wo.target_output_qty', 'wo.status')
            ->when($this->status !== '', fn ($q) => $q->where('wo.status', $this->status))
            ->when($this->dateFrom !== '', fn ($q) => $q->whereDate('wo.wo_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($q) => $q->whereDate('wo.wo_date', '<=', $this->dateTo))
            ->orderByDesc('wo.wo_date')
            ->orderByDesc('wo.id')
            ->get();

        return $rows->map(fn ($row) => [
            $row->wo_no,
            $row->wo_date,
            $row->product_code,
            $row->product_name,
            $row->bom_code,
            $row->warehouse_name,
            $row->batch_qty,
            $row->target_output_qty,
            strtoupper($row->status),
        ]);
    }

    public function headings(): array
    {
        return [
            'No. SPK',
            'Tanggal',
            'Kode Produk',
            'Produk',
            'BOM',
            'Gudang',
            'Batch',
            'Target Output',
            'Status',
        ];
    }
}
