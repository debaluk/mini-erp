<?php

namespace App\Exports;

use App\Models\SalesReturn;
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

    public function query()
    {
        $query = SalesReturn::with(['sale', 'customer', 'warehouse', 'businessUnit', 'user']);

        if (!empty($this->filters['business_unit_id'])) {
            $query->where('business_unit_id', $this->filters['business_unit_id']);
        }

        if (!empty($this->filters['warehouse_id'])) {
            $query->where('warehouse_id', $this->filters['warehouse_id']);
        }

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['start_date']) && !empty($this->filters['end_date'])) {
            $query->whereBetween('return_date', [
                $this->filters['start_date'] . ' 00:00:00',
                $this->filters['end_date'] . ' 23:59:59'
            ]);
        }

        return $query->orderBy('return_date', 'desc');
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
            'Operator'
        ];
    }

    public function map($row): array
    {
        return [
            $row->return_no,
            date('d/m/Y H:i', strtotime($row->return_date)),
            $row->businessUnit ? $row->businessUnit->name : '-',
            $row->sale ? $row->sale->invoice_no : '-',
            $row->customer ? $row->customer->name : 'Pelanggan Umum',
            $row->warehouse ? $row->warehouse->name : '-',
            $row->total,
            $row->reason ?? '-',
            strtoupper($row->status),
            $row->user ? $row->user->name : '-'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'color' => ['rgb' => 'DC3545']]],
        ];
    }
}
