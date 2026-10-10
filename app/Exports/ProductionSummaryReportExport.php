<?php

namespace App\\Exports;

use Carbon\\Carbon;
use Illuminate\\Support\\Collection;
use Maatwebsite\\Excel\\Concerns\\FromCollection;
use Maatwebsite\\Excel\\Concerns\\ShouldAutoSize;
use Maatwebsite\\Excel\\Concerns\\WithColumnFormatting;
use Maatwebsite\\Excel\\Concerns\\WithHeadings;
use Maatwebsite\\Excel\\Concerns\\WithMapping;
use Maatwebsite\\Excel\\Concerns\\WithStyles;
use PhpOffice\\PhpSpreadsheet\\Shared\\Date;
use PhpOffice\\PhpSpreadsheet\\Style\\NumberFormat;
use PhpOffice\\PhpSpreadsheet\\Worksheet\\Worksheet;

class ProductionSummaryReportExport implements FromCollection, WithHeadings, ShouldAutoSize, WithMapping, WithColumnFormatting, WithStyles
{
    public function __construct(private readonly Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function map($r): array
    {
        return [
            Date::dateTimeToExcel(Carbon::parse($r->date)),
            $r->wo_no,
            (float) $r->target,
            (float) $r->estimated_cost,
            (float) $r->material_cost,
            (float) $r->labor_cost,
            (float) $r->production_qty,
            $r->hpp_unit !== null ? (float) $r->hpp_unit : null,
            (float) $r->reject_qty,
        ];
    }

    public function headings(): array
    {
        return ['Tanggal', 'SPK', 'Target', 'Estimasi Biaya', 'Bahan Baku', 'Upah', 'Produksi', 'HPP per Unit', 'Reject'];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_DATE_DDMMYYYY,
            'C' => '#,##0',
            'D' => '#,##0.00',
            'E' => '#,##0.00',
            'F' => '#,##0.00',
            'G' => '#,##0',
            'H' => '#,##0.0000',
            'I' => '#,##0',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
