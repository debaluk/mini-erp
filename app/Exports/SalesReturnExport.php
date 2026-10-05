<?php

namespace App\Exports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesReturnExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $filters;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    public function query(): Builder
    {
        $query = DB::table('sales_returns as sr')
            ->leftJoin('sales as s', 's.id', '=', 'sr.sale_id')
            ->leftJoin('customers as c', 'c.id', '=', 'sr.customer_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'sr.warehouse_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'sr.business_unit_id')
            ->leftJoin('users as u', 'u.id', '=', 'sr.user_id')
            ->select([
                'sr.return_no',
                'sr.return_date',
                'bu.name as business_unit_name',
                's.invoice_no',
                'c.name as customer_name',
                'w.name as warehouse_name',
                'sr.total',
                'sr.reason',
                'sr.status',
                'u.name as operator_name',
            ]);

        if (!empty($this->filters['business_unit_id'])) {
            $query->where('sr.business_unit_id', $this->filters['business_unit_id']);
        }

        if (!empty($this->filters['warehouse_id'])) {
            $query->where('sr.warehouse_id', $this->filters['warehouse_id']);
        }

        if (!empty($this->filters['status'])) {
            $query->where('sr.status', $this->filters['status']);
        }

        if (!empty($this->filters['start_date']) && !empty($this->filters['end_date'])) {
            $query->whereBetween('sr.return_date', [
                $this->filters['start_date'] . ' 00:00:00',
                $this->filters['end_date'] . ' 23:59:59',
            ]);
        }

        return $query->orderByDesc('sr.return_date');
    }

    public function headings(): array
    {
        return [
            'No. Retur',
            'Tanggal Retur',
            'Unit Bisnis',
            'No. Invoice Asal',
            'Nama Pelanggan',
            'Gudang Penerima',
            'Total Nilai Retur (Rp)',
            'Alasan Retur',
            'Status',
            'Operator',
        ];
    }

    public function map($row): array
    {
        return [
            $row->return_no,
            $row->return_date ? date('d/m/Y H:i', strtotime($row->return_date)) : '-',
            $row->business_unit_name ?? '-',
            $row->invoice_no ?? '-',
            $row->customer_name ?? 'Pelanggan Umum',
            $row->warehouse_name ?? '-',
            $row->total,
            $row->reason ?? '-',
            strtoupper((string) $row->status),
            $row->operator_name ?? '-',
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'color' => ['rgb' => 'DC3545']],
            ],
        ];
    }
}
