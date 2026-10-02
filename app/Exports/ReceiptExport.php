<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Carbon\Carbon;

class ReceiptExport implements FromCollection, WithHeadings, WithMapping
{
    protected $request;
    protected int $rowNumber = 0;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function collection(): \Illuminate\Support\Enumerable
    {
        $startDate = $this->request->query('start_date', now()->startOfMonth()->toDateString());
        $endDate = $this->request->query('end_date', now()->endOfMonth()->toDateString());

        $query = DB::table('receipts as r')
            ->leftJoin('purchases as p', 'p.id', '=', 'r.purchase_id')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'p.purchase_order_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'r.supplier_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'r.business_unit_id')
            ->leftJoin(DB::raw('(SELECT receipt_id, COUNT(*) as total_items FROM receipt_items GROUP BY receipt_id) ri'), 'ri.receipt_id', '=', 'r.id')
            ->where('r.entity_id', DB::table('entities')->value('id') ?? 1)
            ->whereDate('r.receipt_date', '>=', $startDate)
            ->whereDate('r.receipt_date', '<=', $endDate);

        if ($this->request->filled('business_unit_id')) $query->where('r.business_unit_id', $this->request->business_unit_id);
        if ($this->request->filled('warehouse_id')) $query->where('r.warehouse_id', $this->request->warehouse_id);
        if ($this->request->filled('status')) $query->where('r.status', $this->request->status);

        return $query->select(
            'r.receipt_no', 'r.receipt_date', 'po.po_no',
            's.name as supplier_name', 'w.name as warehouse_name',
            'bu.name as business_unit_name',
            DB::raw('COALESCE(ri.total_items, 0) as total_items'),
            'r.status'
        )->orderByDesc('r.receipt_date')->orderByDesc('r.id')->get();
    }

    public function map($row): array
    {
        return [
            ++$this->rowNumber,
            Carbon::parse($row->receipt_date)->format('d/m/Y H:i'),
            $row->receipt_no,
            $row->po_no ?? '-',
            $row->supplier_name ?? '-',
            $row->warehouse_name ?? '-',
            $row->business_unit_name ?? '-',
            (int) $row->total_items,
            strtoupper($row->status ?? '-'),
        ];
    }

    public function headings(): array
    {
        return ['No', 'Tanggal', 'No. Penerimaan', 'No. PO', 'Supplier / Vendor', 'Gudang', 'Unit Bisnis', 'Total Item', 'Status'];
    }
}
