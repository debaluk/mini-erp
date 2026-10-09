<?php

namespace App\\Exports;

use Illuminate\\Support\\Collection;
use Maatwebsite\\Excel\\Concerns\\FromCollection;
use Maatwebsite\\Excel\\Concerns\\ShouldAutoSize;
use Maatwebsite\\Excel\\Concerns\\WithHeadings;

class ProductionSummaryReportExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function __construct(private readonly Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows->map(fn ($r) => [
            $r->date, $r->wo_no, $r->target, $r->estimated_cost, $r->material_cost,
            $r->labor_cost, $r->production_qty, $r->hpp_unit, $r->reject_qty,
        ]);
    }

    public function headings(): array
    {
        return ['Tanggal', 'SPK', 'Target', 'Estimasi Biaya', 'Bahan Baku', 'Upah', 'Produksi', 'HPP per Unit', 'Reject'];
    }
}
