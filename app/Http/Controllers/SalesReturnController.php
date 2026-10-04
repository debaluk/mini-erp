<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\SalesReturnService;
use App\Exports\SalesReturnExport;
use App\Models\SalesReturn;
use App\Models\Sale;
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

    /**
     * Halaman Utama Retur Penjualan
     */
    public function index()
    {
        $businessUnits = BusinessUnit::where('is_active', 1)->get();
        $warehouses = Warehouse::where('is_active', 1)->get();

        return view('inventori.penjualan.retur.index', compact('businessUnits', 'warehouses'));
    }

    /**
     * DataTables Server-Side AJAX Endpoint
     */
    public function data(Request $request)
    {
        $query = SalesReturn::with(['sale', 'customer', 'warehouse', 'businessUnit', 'user'])
            ->select('sales_returns.*');

        // Multi-Tenant Isolation
        if ($request->filled('business_unit_id')) {
            $query->where('business_unit_id', $request->business_unit_id);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('return_date', [
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59'
            ]);
        }

        // DataTables server-side response dibuat native agar controller tidak
        // bergantung pada package/helper datatables() yang tidak terpasang.
        $recordsTotal = (clone $query)->count();

        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('return_no', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhereHas('sale', function ($sq) use ($search) {
                        $sq->where('invoice_no', 'like', "%{$search}%");
                    })
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('warehouse', function ($wq) use ($search) {
                        $wq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $recordsFiltered = (clone $query)->count();

        $columns = [
            0 => 'return_no',
            1 => 'return_date',
            5 => 'total',
            6 => 'status',
        ];

        $orderColumn = (int) $request->input('order.0.column', 1);
        $orderDirection = strtolower((string) $request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($columns[$orderColumn] ?? 'return_date', $orderDirection);

        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);

        if ($length !== -1) {
            $query->skip($start)->take(max(1, $length));
        }

        $rows = $query->get();

        $data = $rows->map(function ($row) {
            return [
                'return_no' => $row->return_no,
                'return_date_formatted' => $row->return_date ? date('d/m/Y H:i', strtotime($row->return_date)) : '-',
                'invoice_no' => $row->sale?->invoice_no ?? '-',
                'customer_name' => $row->customer?->name ?? 'Pelanggan Umum',
                'warehouse_name' => $row->warehouse?->name ?? '-',
                'total_formatted' => 'Rp ' . number_format((float) $row->total, 2, ',', '.'),
                'status' => $row->status,
                'actions' => '<div class="btn-group btn-group-sm">'
                    . '<button type="button" class="btn btn-outline-info btn-view-return" data-id="' . $row->id . '" title="Detail & Jurnal"><i class="bi bi-eye"></i></button>'
                    . '<button type="button" class="btn btn-outline-warning btn-edit-return" data-id="' . $row->id . '" title="Edit & Koreksi Jurnal"><i class="bi bi-pencil"></i></button>'
                    . '<button type="button" class="btn btn-outline-secondary btn-print-return" data-id="' . $row->id . '" title="Cetak Nota"><i class="bi bi-printer"></i></button>'
                    . '</div>',
            ];
        })->values();

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    /**
     * AJAX Lookup Modal Invoice Penjualan
     */
    public function lookupInvoices(Request $request)
    {
        $query = Sale::with('customer')
            ->where('status', 'posted');

        if ($request->filled('business_unit_id')) {
            $query->where('business_unit_id', $request->business_unit_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_no', 'LIKE', "%{$search}%")
                  ->orWhereHas('customer', function ($qc) use ($search) {
                      $qc->where('name', 'LIKE', "%{$search}%");
                  });
            });
        }

        $invoices = $query->orderBy('sale_date', 'desc')->limit(20)->get();

        return response()->json([
            'success' => true,
            'data' => $invoices
        ]);
    }

    /**
     * Get Items dari Invoice Asal (Lengkap dengan Sisa Qty Belum Diretur)
     */
    public function saleItems($saleId)
    {
        $sale = Sale::with(['saleItems.product', 'saleItems.unit', 'customer'])->findOrFail($saleId);

        $items = $sale->saleItems->map(function ($item) {
            // Hitung Qty yang sudah pernah diretur sebelumnya
            $returnedQty = DB::table('sales_return_items')
                ->where('sale_item_id', $item->id)
                ->sum('qty');

            $remainingQty = max(0, $item->qty - $returnedQty);

            return [
                'sale_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_code' => $item->product->code ?? '-',
                'product_name' => $item->product->name ?? '-',
                'unit_id' => $item->unit_id,
                'unit_name' => $item->unit->name ?? 'Pcs',
                'qty_sold' => (float)$item->qty,
                'qty_returned_before' => (float)$returnedQty,
                'qty_remaining' => (float)$remainingQty,
                'unit_price' => (float)$item->unit_price,
                'hpp_unit' => (float)$item->hpp_unit,
                'conversion_factor' => (float)($item->conversion_factor ?? 1.0),
                'base_qty' => (float)($item->base_qty ?? $item->qty)
            ];
        });

        return response()->json([
            'success' => true,
            'sale' => $sale,
            'items' => $items
        ]);
    }

    /**
     * Store / Update Transaksi Retur Penjualan & Otomatis Koreksi Jurnal
     */
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

    /**
     * Get Data Detail Retur untuk Modal Edit
     */
    public function edit($id)
    {
        $return = SalesReturn::with([
            'salesReturnItems.product',
            'salesReturnItems.unit',
            'salesReturnItems.saleItem',
            'sale.customer',
            'warehouse',
            'businessUnit'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $return
        ]);
    }

    /**
     * Cetak Nota Kredit / Proof Retur
     */
    public function printData($id)
    {
        $return = SalesReturn::with([
            'salesReturnItems.product',
            'salesReturnItems.unit',
            'sale',
            'customer',
            'warehouse',
            'businessUnit',
            'user'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $return
        ]);
    }

    /**
     * Export Excel List Retur Penjualan
     */
    public function export(Request $request)
    {
        $filename = 'Laporan_Retur_Penjualan_' . date('Ymd_His') . '.xlsx';
        return Excel::download(new SalesReturnExport($request->all()), $filename);
    }
}
