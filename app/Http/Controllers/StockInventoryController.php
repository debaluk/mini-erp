<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class StockInventoryController extends Controller
{
    private function entityId(Request $request): int
    {
        return (int) ($request->user()->entity_id ?? 1);
    }

    private function movementDelta(object $movement): float
    {
        $qty = (float) $movement->qty;
        $incoming = ['opening', 'purchase_in', 'receipt_in', 'production_in', 'transfer_in', 'adjustment_in', 'return_in'];
        $outgoing = ['sale_out', 'purchase_return', 'production_out', 'transfer_out', 'adjustment_out', 'reject_out', 'return_out'];

        if (in_array($movement->movement_type, $incoming, true)) return abs($qty);
        if (in_array($movement->movement_type, $outgoing, true)) return -abs($qty);
        return $qty;
    }

    public function index(Request $request)
    {
        $entityId = $this->entityId($request);
        $businessUnits = DB::table('business_units')
            ->where('entity_id', $entityId)->where('is_active', 1)->orderBy('code')->get();
        if ($request->filled('business_unit_id')) {
            abort_unless($businessUnits->contains('id', $request->integer('business_unit_id')), 422, 'Unit bisnis tidak aktif atau tidak valid.');
        }
        $warehouses = DB::table('warehouses as w')
            ->join('warehouse_business_units as wbu', function ($join) { $join->on('wbu.warehouse_id', '=', 'w.id')->on('wbu.entity_id', '=', 'w.entity_id'); })
            ->join('business_units as bu', function ($join) { $join->on('bu.id', '=', 'wbu.business_unit_id')->on('bu.entity_id', '=', 'wbu.entity_id'); })
            ->where('w.entity_id', $entityId)->where('w.is_active', 1)->where('bu.is_active', 1)
            ->orderBy('w.name')->distinct()->get(['w.id', 'w.name']);

        $buMap = DB::table('warehouse_business_units as wbu')
            ->join('business_units as bu', function ($join) {
                $join->on('bu.id', '=', 'wbu.business_unit_id')
                    ->on('bu.entity_id', '=', 'wbu.entity_id');
            })
            ->where('wbu.entity_id', $entityId)->where('bu.is_active', 1)
            ->select('wbu.warehouse_id', DB::raw("GROUP_CONCAT(bu.name ORDER BY bu.code SEPARATOR ', ') as business_unit_names"))
            ->groupBy('wbu.warehouse_id');

        $query = DB::table('warehouses_stocks as ws')
            ->join('products as p', function ($join) {
                $join->on('p.id', '=', 'ws.product_id')->on('p.entity_id', '=', 'ws.entity_id');
            })
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->join('warehouses as w', function ($join) {
                $join->on('w.id', '=', 'ws.warehouse_id')->on('w.entity_id', '=', 'ws.entity_id');
            })
            ->leftJoinSub($buMap, 'bm', fn ($join) => $join->on('bm.warehouse_id', '=', 'w.id'))
            ->where('ws.entity_id', $entityId)->where('w.is_active', 1)
            ->whereExists(function ($sub) use ($entityId) {
                $sub->select(DB::raw(1))->from('warehouse_business_units as active_wbu')
                    ->join('business_units as active_bu', function ($join) { $join->on('active_bu.id', '=', 'active_wbu.business_unit_id')->on('active_bu.entity_id', '=', 'active_wbu.entity_id'); })
                    ->whereColumn('active_wbu.warehouse_id', 'ws.warehouse_id')->where('active_wbu.entity_id', $entityId)->where('active_bu.is_active', 1);
            })
            ->select(
                'p.id as product_id', 'p.code', 'p.sku', 'p.name as product_name',
                'u.code as unit_code', 'u.name as unit_name', 'w.id as warehouse_id',
                'w.name as warehouse_name', 'bm.business_unit_names', 'ws.qty', 'p.minimum_stock as reorder_point', 'p.manage_stock'
            )
            ->orderBy('p.name')->orderBy('w.name');

        if ($request->filled('business_unit_id')) {
            $query->whereExists(function ($sub) use ($request, $entityId) {
                $sub->select(DB::raw(1))->from('warehouse_business_units as wbu')
                    ->whereColumn('wbu.warehouse_id', 'ws.warehouse_id')
                    ->where('wbu.entity_id', $entityId)
                    ->where('wbu.business_unit_id', $request->integer('business_unit_id'));
            });
        }
        if ($request->filled('warehouse_id')) $query->where('ws.warehouse_id', $request->integer('warehouse_id'));
        if ($request->filled('search')) {
            $search = '%' . trim($request->string('search')->toString()) . '%';
            $query->where(fn ($sub) => $sub->where('p.name', 'like', $search)
                ->orWhere('p.code', 'like', $search)->orWhere('p.sku', 'like', $search));
        }

        $rows = $query->paginate(15)->withQueryString();

        return view('inventori.persediaan.stok.index', compact('businessUnits', 'warehouses', 'rows'));
    }


    public function export(Request $request)
    {
        $entityId = $this->entityId($request);
        $businessUnitId = $request->filled('business_unit_id') ? $request->integer('business_unit_id') : null;
        $warehouseId = $request->filled('warehouse_id') ? $request->integer('warehouse_id') : null;
        $search = trim((string) $request->query('search', ''));

        $businessUnitName = 'Semua Unit Bisnis';
        if ($businessUnitId) {
            $businessUnitName = DB::table('business_units')
                ->where('entity_id', $entityId)->where('is_active', 1)->where('id', $businessUnitId)->value('name');
            abort_unless($businessUnitName, 422, 'Unit bisnis tidak valid.');
        }

        $entity = DB::table('entities')->where('id', $entityId)->first();
        abort_unless($entity, 404, 'Data entitas tidak ditemukan.');

        $buMap = DB::table('warehouse_business_units as wbu')
            ->join('business_units as bu', function ($join) {
                $join->on('bu.id', '=', 'wbu.business_unit_id')->on('bu.entity_id', '=', 'wbu.entity_id');
            })
            ->where('wbu.entity_id', $entityId)->where('bu.is_active', 1)
            ->select('wbu.warehouse_id', DB::raw("GROUP_CONCAT(bu.name ORDER BY bu.code SEPARATOR ', ') as business_unit_names"))
            ->groupBy('wbu.warehouse_id');

        $rows = DB::table('warehouses_stocks as ws')
            ->join('products as p', function ($join) {
                $join->on('p.id', '=', 'ws.product_id')->on('p.entity_id', '=', 'ws.entity_id');
            })
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->join('warehouses as w', function ($join) {
                $join->on('w.id', '=', 'ws.warehouse_id')->on('w.entity_id', '=', 'ws.entity_id');
            })
            ->leftJoinSub($buMap, 'bm', fn ($join) => $join->on('bm.warehouse_id', '=', 'w.id'))
            ->where('ws.entity_id', $entityId)->where('w.is_active', 1)
            ->whereExists(function ($sub) use ($entityId) {
                $sub->select(DB::raw(1))->from('warehouse_business_units as active_wbu')
                    ->join('business_units as active_bu', function ($join) { $join->on('active_bu.id', '=', 'active_wbu.business_unit_id')->on('active_bu.entity_id', '=', 'active_wbu.entity_id'); })
                    ->whereColumn('active_wbu.warehouse_id', 'ws.warehouse_id')->where('active_wbu.entity_id', $entityId)->where('active_bu.is_active', 1);
            })
            ->when($businessUnitId, function ($query) use ($businessUnitId, $entityId) {
                $query->whereExists(function ($sub) use ($businessUnitId, $entityId) {
                    $sub->select(DB::raw(1))->from('warehouse_business_units as wbu')
                        ->whereColumn('wbu.warehouse_id', 'ws.warehouse_id')
                        ->where('wbu.entity_id', $entityId)
                        ->where('wbu.business_unit_id', $businessUnitId);
                });
            })
            ->when($warehouseId, fn ($query) => $query->where('ws.warehouse_id', $warehouseId))
            ->when($search !== '', function ($query) use ($search) {
                $term = '%' . $search . '%';
                $query->where(fn ($sub) => $sub->where('p.name', 'like', $term)
                    ->orWhere('p.code', 'like', $term)->orWhere('p.sku', 'like', $term));
            })
            ->select('p.code', 'p.sku', 'p.name as product_name', 'u.name as unit_name',
                'u.code as unit_code', 'bm.business_unit_names', 'w.name as warehouse_name', 'ws.qty', 'p.minimum_stock as reorder_point', 'p.manage_stock')
            ->orderBy('p.name')->orderBy('w.name')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Stok');

        $sheet->mergeCells('A1:J1');
        $sheet->setCellValue('A1', $entity->name ?? 'Entitas');
        $sheet->mergeCells('A2:J2');
        $sheet->setCellValue('A2', $entity->address ?? '');
        $contact = collect([
            !empty($entity->phone) ? 'Telp: ' . $entity->phone : null,
            !empty($entity->email) ? 'Email: ' . $entity->email : null,
            !empty($entity->npwp) ? 'NPWP: ' . $entity->npwp : null,
            !empty($entity->nib) ? 'NIB: ' . $entity->nib : null,
        ])->filter()->implode(' | ');
        $sheet->mergeCells('A3:J3');
        $sheet->setCellValue('A3', $contact);
        $sheet->mergeCells('A5:J5');
        $sheet->setCellValue('A5', 'LAPORAN STOK BARANG');
        $sheet->setCellValue('A6', 'Unit Bisnis');
        $sheet->mergeCells('B6:J6');
        $sheet->setCellValue('B6', $businessUnitName);
        $sheet->setCellValue('A7', 'Tgl Cetak');
        $sheet->mergeCells('B7:J7');
        $sheet->setCellValue('B7', now()->format('d/m/Y H:i'));
        $sheet->fromArray([['No.', 'Kode Barang', 'SKU', 'Nama Barang', 'Satuan', 'Unit Bisnis', 'Gudang', 'Stok Tersedia', 'Minimal Stok / ROP', 'Status ROP']], null, 'A9');

        $rowNumber = 10;
        foreach ($rows as $index => $row) {
            $ropStatus = $row->manage_stock && $row->reorder_point !== null ? ((float) $row->qty <= (float) $row->reorder_point ? 'Perlu Reorder' : 'Aman') : '-';
            $sheet->fromArray([[$index + 1, $row->code ?: '-', $row->sku ?: '-', $row->product_name,
                $row->unit_name ?: $row->unit_code ?: '-', $row->business_unit_names ?: '-', $row->warehouse_name, (float) $row->qty,
                $row->manage_stock && $row->reorder_point !== null ? (float) $row->reorder_point : null, $ropStatus]], null, 'A' . $rowNumber);
            $rowNumber++;
        }

        $lastRow = max(9, $rowNumber - 1);
        $sheet->getStyle('A1:J1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1:J3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A5:J5')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A5:H5')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A9:J9')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A9:H9')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('343A40');
        $sheet->getStyle('A9:J' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        if ($rowNumber > 10) {
            $sheet->getStyle('H10:I' . $lastRow)->getNumberFormat()->setFormatCode('[$-id-ID]#,##0.###');
            $sheet->getStyle('H10:H' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        }
        $sheet->getStyle('A3:H3')->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        foreach (range('A', 'J') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->getColumnDimension('D')->setWidth(32);
        $sheet->getColumnDimension('F')->setWidth(24);
        $sheet->getColumnDimension('G')->setWidth(22);
        $sheet->freezePane('A10');
        $sheet->setAutoFilter('A9:J' . $lastRow);

        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'laporan-stok-barang-' . now()->format('Ymd-His') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function history(Request $request, int $product, int $warehouse)
    {
        $entityId = $this->entityId($request);
        $stock = DB::table('warehouses_stocks as ws')
            ->join('products as p', function ($join) {
                $join->on('p.id', '=', 'ws.product_id')->on('p.entity_id', '=', 'ws.entity_id');
            })
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->join('warehouses as w', function ($join) {
                $join->on('w.id', '=', 'ws.warehouse_id')->on('w.entity_id', '=', 'ws.entity_id');
            })
            ->where('ws.entity_id', $entityId)->where('ws.product_id', $product)->where('ws.warehouse_id', $warehouse)
            ->select('p.id as product_id', 'p.code', 'p.sku', 'p.name as product_name', 'u.code as unit_code',
                'u.name as unit_name', 'w.id as warehouse_id', 'w.name as warehouse_name', 'ws.qty')
            ->first();
        abort_unless($stock, 404);

        $businessUnitNames = DB::table('warehouse_business_units as wbu')
            ->join('business_units as bu', function ($join) {
                $join->on('bu.id', '=', 'wbu.business_unit_id')->on('bu.entity_id', '=', 'wbu.entity_id');
            })
            ->where('wbu.entity_id', $entityId)->where('wbu.warehouse_id', $warehouse)
            ->orderBy('bu.code')->pluck('bu.name')->implode(', ');
        $stock->business_unit_names = $businessUnitNames ?: '-';

        $startDate = $request->query('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', now()->toDateString());
        $request->validate(['start_date' => ['nullable', 'date'], 'end_date' => ['nullable', 'date', 'after_or_equal:start_date']]);

        $movements = DB::table('stock_movements')
            ->where('entity_id', $entityId)->where('product_id', $product)->where('warehouse_id', $warehouse)
            ->whereDate('occurred_at', '<=', $endDate)
            ->orderBy('occurred_at')->orderBy('id')->get();

        $openingBalance = 0.0;
        $history = collect();
        $balance = 0.0;
        foreach ($movements as $movement) {
            $delta = $this->movementDelta($movement);
            if (substr((string) $movement->occurred_at, 0, 10) < $startDate) {
                $openingBalance += $delta;
                continue;
            }
            if (substr((string) $movement->occurred_at, 0, 10) > $endDate) continue;
            $balance += $delta;
            $history->push((object) [
                'date' => $movement->occurred_at,
                'reference' => $movement->reference_type && $movement->reference_id
                    ? strtoupper(str_replace('_', ' ', $movement->reference_type)) . ' #' . $movement->reference_id : '-',
                'description' => ucwords(str_replace('_', ' ', (string) $movement->movement_type)),
                'in' => $delta > 0 ? $delta : 0,
                'out' => $delta < 0 ? abs($delta) : 0,
                'balance' => $openingBalance + $balance,
                'unit_cost' => $movement->unit_cost,
            ]);
        }

        return view('inventori.persediaan.stok.history', compact('stock', 'startDate', 'endDate', 'openingBalance', 'history'));
    }
}
