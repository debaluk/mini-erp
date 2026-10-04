<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\SalesReturnService;
use App\Exports\SalesReturnExport;
use App\Models\Warehouse;
use App\Models\BusinessUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class SalesReturnController extends Controller
{
    protected $salesReturnService;

    public function __construct(SalesReturnService $salesReturnService)
    {
        $this->salesReturnService = $salesReturnService;
    }

    public function index()
    {
        $businessUnits = BusinessUnit::where('is_active', 1)->get();
        $warehouses = Warehouse::where('is_active', 1)->get();

        return view('inventori.penjualan.retur.index', compact('businessUnits', 'warehouses'));
    }

    public function data(Request $request)
    {
        $entity = (int) (DB::table('entities')->value('id') ?? 1);
        $query = DB::table('sales_returns as sr')
            ->leftJoin('sales as s', 's.id', '=', 'sr.sale_id')
            ->leftJoin('customers as c', 'c.id', '=', 'sr.customer_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'sr.warehouse_id')
            ->where('sr.entity_id', $entity);

        if ($request->filled('business_unit_id')) $query->where('sr.business_unit_id', $request->business_unit_id);
        if ($request->filled('warehouse_id')) $query->where('sr.warehouse_id', $request->warehouse_id);
        if ($request->filled('status')) $query->where('sr.status', $request->status);
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('sr.return_date', [$request->start_date . ' 00:00:00', $request->end_date . ' 23:59:59']);
        }

        $recordsTotal = (clone $query)->count('sr.id');
        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('sr.return_no', 'like', "%{$search}%")
                    ->orWhere('sr.status', 'like', "%{$search}%")
                    ->orWhere('s.invoice_no', 'like', "%{$search}%")
                    ->orWhere('c.name', 'like', "%{$search}%")
                    ->orWhere('w.name', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $query)->count('sr.id');
        $columns = [0 => 'sr.return_no', 1 => 'sr.return_date', 5 => 'sr.total', 6 => 'sr.status'];
        $orderColumn = (int) $request->input('order.0.column', 1);
        $orderDirection = strtolower((string) $request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($columns[$orderColumn] ?? 'sr.return_date', $orderDirection);

        $startRow = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);
        if ($length !== -1) $query->offset($startRow)->limit(max(1, $length));

        $rows = $query->select([
            'sr.id', 'sr.return_no', 'sr.return_date', 'sr.total', 'sr.status',
            's.invoice_no', 'c.name as customer_name', 'w.name as warehouse_name',
        ])->get();

        $data = $rows->map(fn ($row) => [
            'return_no' => $row->return_no,
            'return_date_formatted' => $row->return_date ? date('d/m/Y H:i', strtotime($row->return_date)) : '-',
            'invoice_no' => $row->invoice_no ?? '-',
            'customer_name' => $row->customer_name ?? 'Pelanggan Umum',
            'warehouse_name' => $row->warehouse_name ?? '-',
            'total_formatted' => 'Rp ' . number_format((float) $row->total, 2, ',', '.'),
            'status' => $row->status,
            'actions' => '<div class="btn-group btn-group-sm">'
                . '<button type="button" class="btn btn-outline-info btn-view-return" data-id="' . $row->id . '" title="Detail & Jurnal"><i class="bi bi-eye"></i></button>'
                . '<button type="button" class="btn btn-outline-warning btn-edit-return" data-id="' . $row->id . '" title="Edit & Koreksi Jurnal"><i class="bi bi-pencil"></i></button>'
                . '<button type="button" class="btn btn-outline-secondary btn-print-return" data-id="' . $row->id . '" title="Cetak Nota"><i class="bi bi-printer"></i></button>'
                . '</div>',
        ])->values();

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function lookupInvoices(Request $request)
    {
        $entity = (int) (DB::table('entities')->value('id') ?? 1);
        $query = DB::table('sales as s')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->where('s.entity_id', $entity)
            ->where('s.status', 'posted');

        if ($request->filled('business_unit_id')) $query->where('s.business_unit_id', $request->business_unit_id);
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('s.invoice_no', 'like', "%{$search}%")
                    ->orWhere('c.name', 'like', "%{$search}%");
            });
        }

        $invoices = $query->select('s.*', 'c.name as customer_name')
            ->orderByDesc('s.sale_date')->limit(20)->get();

        return response()->json(['success' => true, 'data' => $invoices]);
    }

    public function saleItems($saleId)
    {
        $entity = (int) (DB::table('entities')->value('id') ?? 1);
        $sale = DB::table('sales as s')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->where('s.entity_id', $entity)->where('s.id', $saleId)->where('s.status', 'posted')
            ->select('s.*', 'c.name as customer_name')->first();

        abort_unless($sale, 404);

        $items = DB::table('sale_items as si')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'si.unit_id')
            ->where('si.sale_id', $saleId)
            ->select('si.*', 'p.code as product_code', 'p.name as product_name', 'u.name as unit_name')
            ->get()
            ->map(function ($item) {
                $returnedQty = (float) DB::table('sales_return_items')->where('sale_item_id', $item->id)->sum('qty');
                return [
                    'sale_item_id' => $item->id, 'product_id' => $item->product_id,
                    'product_code' => $item->product_code ?? '-', 'product_name' => $item->product_name ?? '-',
                    'unit_id' => $item->unit_id, 'unit_name' => $item->unit_name ?? 'Pcs',
                    'qty_sold' => (float) $item->qty, 'qty_returned_before' => $returnedQty,
                    'qty_remaining' => max(0, (float) $item->qty - $returnedQty),
                    'unit_price' => (float) $item->unit_price, 'hpp_unit' => (float) $item->hpp_unit,
                    'conversion_factor' => (float) ($item->conversion_factor ?? 1),
                    'base_qty' => (float) ($item->base_qty ?? $item->qty),
                ];
            });

        return response()->json(['success' => true, 'sale' => $sale, 'items' => $items]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'business_unit_id' => 'required|exists:business_units,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'sale_id' => 'required|exists:sales,id',
            'return_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.sale_item_id' => 'required|exists:sale_items,id',
            'items.*.qty' => 'required|numeric|min:0.001',
            'items.*.condition' => 'required|in:good,damaged',
        ]);

        try {
            $return = $this->salesReturnService->processReturn($request->all(), auth()->id());

            return response()->json([
                'success' => true,
                'message' => 'Retur penjualan berhasil disimpan dan jurnal berhasil diposting!',
                'data' => $return
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses retur: ' . $e->getMessage()
            ], 500);
        }
    }

    public function edit($id)
    {
        $entity = (int) (DB::table('entities')->value('id') ?? 1);
        $return = DB::table('sales_returns as sr')
            ->leftJoin('sales as s', 's.id', '=', 'sr.sale_id')
            ->leftJoin('customers as c', 'c.id', '=', 'sr.customer_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'sr.warehouse_id')
            ->where('sr.entity_id', $entity)->where('sr.id', $id)
            ->select('sr.*', 's.invoice_no', 'c.name as customer_name', 'w.name as warehouse_name')->first();

        abort_unless($return, 404);
        $items = DB::table('sales_return_items as sri')
            ->join('products as p', 'p.id', '=', 'sri.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'sri.unit_id')
            ->where('sri.sales_return_id', $id)
            ->select('sri.*', 'p.code as product_code', 'p.name as product_name', 'u.name as unit_name')->get();

        return response()->json(['success' => true, 'data' => $return, 'items' => $items]);
    }

    public function printData($id)
    {
        return $this->edit($id);
    }

    public function export(Request $request)
    {
        $filename = 'Laporan_Retur_Penjualan_' . date('Ymd_His') . '.xlsx';
        return Excel::download(new SalesReturnExport($request->all()), $filename);
    }
}
