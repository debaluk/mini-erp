<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class InventoryReportController extends Controller
{
    private function entityId(Request $request): int
    {
        return (int) ($request->user()->entity_id ?? 1);
    }

    private function stockQuery(Request $request, int $entityId)
    {
        $buMap = DB::table('warehouse_business_units as wbu')
            ->join('business_units as bu', function ($join) {
                $join->on('bu.id', '=', 'wbu.business_unit_id')
                    ->on('bu.entity_id', '=', 'wbu.entity_id');
            })
            ->where('wbu.entity_id', $entityId)
            ->select('wbu.warehouse_id', DB::raw("GROUP_CONCAT(bu.name ORDER BY bu.code SEPARATOR ', ') as business_unit_names"))
            ->groupBy('wbu.warehouse_id');

        return DB::table('warehouses_stocks as ws')
            ->join('products as p', function ($join) {
                $join->on('p.id', '=', 'ws.product_id')->on('p.entity_id', '=', 'ws.entity_id');
            })
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->join('warehouses as w', function ($join) {
                $join->on('w.id', '=', 'ws.warehouse_id')->on('w.entity_id', '=', 'ws.entity_id');
            })
            ->leftJoinSub($buMap, 'bm', fn ($join) => $join->on('bm.warehouse_id', '=', 'w.id'))
            ->where('ws.entity_id', $entityId)
            ->when($request->filled('business_unit_id'), function ($query) use ($request, $entityId) {
                $query->whereExists(function ($sub) use ($request, $entityId) {
                    $sub->select(DB::raw(1))->from('warehouse_business_units as wbu')
                        ->whereColumn('wbu.warehouse_id', 'ws.warehouse_id')
                        ->where('wbu.entity_id', $entityId)
                        ->where('wbu.business_unit_id', $request->integer('business_unit_id'));
                });
            })
            ->when($request->filled('warehouse_id'), fn ($query) => $query->where('ws.warehouse_id', $request->integer('warehouse_id')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%' . trim((string) $request->query('search')) . '%';
                $query->where(fn ($sub) => $sub->where('p.name', 'like', $term)
                    ->orWhere('p.code', 'like', $term)->orWhere('p.sku', 'like', $term));
            })
            ->select(
                'p.id as product_id', 'p.code', 'p.sku', 'p.name as product_name',
                'u.code as unit_code', 'u.name as unit_name',
                'w.id as warehouse_id', 'w.name as warehouse_name',
                'bm.business_unit_names', 'ws.qty', 'ws.avg_cost',
                DB::raw('(ws.qty * ws.avg_cost) as stock_value')
            );
    }

    private function movementSummary(Request $request, int $entityId): array
    {
        $incomingTypes = ['opening', 'purchase_in', 'receipt_in', 'production_in', 'transfer_in', 'adjustment_in', 'return_in'];
        $outgoingTypes = ['sale_out', 'purchase_return', 'production_out', 'transfer_out', 'adjustment_out', 'reject_out', 'return_out'];

        $query = DB::table('stock_movements as sm')
            ->join('products as p', function ($join) {
                $join->on('p.id', '=', 'sm.product_id')->on('p.entity_id', '=', 'sm.entity_id');
            })
            ->where('sm.entity_id', $entityId)
            ->whereDate('sm.occurred_at', '>=', $request->query('start_date'))
            ->whereDate('sm.occurred_at', '<=', $request->query('end_date'))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('sm.warehouse_id', $request->integer('warehouse_id')))
            ->when($request->filled('business_unit_id'), function ($q) use ($request, $entityId) {
                $q->whereExists(function ($sub) use ($request, $entityId) {
                    $sub->select(DB::raw(1))->from('warehouse_business_units as wbu')
                        ->whereColumn('wbu.warehouse_id', 'sm.warehouse_id')
                        ->where('wbu.entity_id', $entityId)
                        ->where('wbu.business_unit_id', $request->integer('business_unit_id'));
                });
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . trim((string) $request->query('search')) . '%';
                $q->where(fn ($sub) => $sub->where('p.name', 'like', $term)
                    ->orWhere('p.code', 'like', $term)->orWhere('p.sku', 'like', $term));
            });

        $inPlaceholders = "'" . implode("','", $incomingTypes) . "'";
        $outPlaceholders = "'" . implode("','", $outgoingTypes) . "'";
        $row = $query->selectRaw(
            "COALESCE(SUM(CASE WHEN sm.movement_type IN ({$inPlaceholders}) OR (sm.movement_type NOT IN ({$outPlaceholders}) AND sm.qty > 0) THEN 1 ELSE 0 END), 0) as rows_in,
             COALESCE(SUM(CASE WHEN sm.movement_type IN ({$outPlaceholders}) OR (sm.movement_type NOT IN ({$inPlaceholders}) AND sm.qty < 0) THEN 1 ELSE 0 END), 0) as rows_out"
        )->first();

        return [
            'rowsIn' => (int) ($row->rows_in ?? 0),
            'rowsOut' => (int) ($row->rows_out ?? 0),
        ];
    }

    private function filters(Request $request): void
    {
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'business_unit_id' => ['nullable', 'integer', 'min:1'],
            'warehouse_id' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
    }

    public function index(Request $request)
    {
        $startDate = $request->query('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', now()->toDateString());
        $request->merge(['start_date' => $startDate, 'end_date' => $endDate]);
        $this->filters($request);
        $entityId = $this->entityId($request);

        $businessUnits = DB::table('business_units')->where('entity_id', $entityId)
            ->where('is_active', 1)->orderBy('code')->get(['id', 'name']);
        $warehouses = DB::table('warehouses')->where('entity_id', $entityId)
            ->where('is_active', 1)->orderBy('name')->get(['id', 'name']);
        $entityName = DB::table('entities')->where('id', $entityId)->value('name') ?? 'MINI ERP';
        $selectedUnitName = $request->filled('business_unit_id')
            ? ($businessUnits->firstWhere('id', $request->integer('business_unit_id'))->name ?? 'Unit Bisnis')
            : 'Semua Unit Bisnis';
        $selectedWarehouseName = $request->filled('warehouse_id')
            ? ($warehouses->firstWhere('id', $request->integer('warehouse_id'))->name ?? 'Gudang')
            : 'Semua Gudang';

        $summary = (clone $this->stockQuery($request, $entityId))->reorder()->select([])
            ->selectRaw('COUNT(*) as stock_lines, COUNT(DISTINCT ws.product_id) as item_count,
                COALESCE(SUM(ws.qty * ws.avg_cost), 0) as stock_value,
                COALESCE(SUM(CASE WHEN ws.qty <= 0 THEN 1 ELSE 0 END), 0) as zero_or_negative')
            ->first();

        $rows = $this->stockQuery($request, $entityId)
            ->orderBy('p.name')->orderBy('w.name')->paginate(20)->withQueryString();

        $topStock = $this->stockQuery($request, $entityId)
            ->orderByDesc('stock_value')->limit(10)->get();
        $byWarehouse = (clone $this->stockQuery($request, $entityId))->reorder()->select([])
            ->select('w.id', 'w.name as warehouse_name')
            ->selectRaw('COUNT(*) as stock_lines, COALESCE(SUM(ws.qty * ws.avg_cost), 0) as stock_value')
            ->groupBy('w.id', 'w.name')->orderByDesc('stock_value')->get();
        $movement = $this->movementSummary($request, $entityId);

        return view('inventori.laporan.persediaan-eksekutif', compact(
            'businessUnits', 'warehouses', 'entityName', 'selectedUnitName',
            'selectedWarehouseName', 'startDate', 'endDate', 'summary', 'rows',
            'topStock', 'byWarehouse', 'movement'
        ));
    }

    public function export(Request $request)
    {
        $startDate = $request->query('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', now()->toDateString());
        $request->merge(['start_date' => $startDate, 'end_date' => $endDate]);
        $this->filters($request);
        $entityId = $this->entityId($request);
        abort_if($startDate > $endDate, 422, 'Periode tanggal tidak valid.');

        $entity = DB::table('entities')->where('id', $entityId)->first();
        abort_unless($entity, 404, 'Data entitas tidak ditemukan.');
        $rows = $this->stockQuery($request, $entityId)->orderBy('p.name')->orderBy('w.name')->get();
        $unitName = $request->filled('business_unit_id')
            ? (DB::table('business_units')->where('entity_id', $entityId)->where('id', $request->integer('business_unit_id'))->value('name') ?? 'Unit Bisnis')
            : 'Semua Unit Bisnis';
        $warehouseName = $request->filled('warehouse_id')
            ? (DB::table('warehouses')->where('entity_id', $entityId)->where('id', $request->integer('warehouse_id'))->value('name') ?? 'Gudang')
            : 'Semua Gudang';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Persediaan');
        $sheet->mergeCells('A1:J1');
        $sheet->setCellValue('A1', $entity->name ?? 'MINI ERP');
        $sheet->mergeCells('A2:J2');
        $sheet->setCellValue('A2', 'LAPORAN PERSEDIAAN EKSEKUTIF');
        $sheet->setCellValue('A4', 'Periode Mutasi');
        $sheet->setCellValue('B4', date('d/m/Y', strtotime($startDate)) . ' s/d ' . date('d/m/Y', strtotime($endDate)));
        $sheet->setCellValue('A5', 'Unit Bisnis');
        $sheet->setCellValue('B5', $unitName);
        $sheet->setCellValue('A6', 'Gudang');
        $sheet->setCellValue('B6', $warehouseName);
        $sheet->setCellValue('A7', 'Tanggal Cetak');
        $sheet->setCellValue('B7', now()->format('d/m/Y H:i'));
        $sheet->fromArray([['No.', 'Kode Item', 'SKU', 'Nama Item', 'Satuan Dasar', 'Unit Bisnis', 'Gudang', 'Qty Saat Ini', 'HPP Rata-rata', 'Nilai Stok']], null, 'A9');

        $rowNumber = 10;
        foreach ($rows as $index => $row) {
            $sheet->fromArray([[$index + 1, $row->code ?: '-', $row->sku ?: '-', $row->product_name,
                $row->unit_name ?: $row->unit_code ?: '-', $row->business_unit_names ?: '-',
                $row->warehouse_name, (float) $row->qty, (float) $row->avg_cost, (float) $row->stock_value]], null, 'A' . $rowNumber);
            $rowNumber++;
        }
        $lastRow = max(9, $rowNumber - 1);
        $sheet->getStyle('A1:J1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1:J2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A9:J9')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A9:J9')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('343A40');
        $sheet->getStyle('A9:J' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        if ($rowNumber > 10) {
            $sheet->getStyle('H10:H' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.###');
            $sheet->getStyle('I10:J' . $lastRow)->getNumberFormat()->setFormatCode('#,##0');
        }
        foreach (range('A', 'J') as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
        $sheet->getColumnDimension('D')->setWidth(32);
        $sheet->getColumnDimension('F')->setWidth(24);
        $sheet->freezePane('A10');
        $sheet->setAutoFilter('A9:J' . $lastRow);

        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'laporan-persediaan-eksekutif-' . now()->format('Ymd-His') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
