<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BusinessUnit;
use App\Models\Warehouse;
use App\Models\Supplier;
use App\Models\Product;
use App\Exports\PurchaseOrderExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $startDate      = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate        = $request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        
        $businessUnits = BusinessUnit::where('is_active', 1)->orderBy('code')->get();
        $warehouses    = Warehouse::where('is_active', 1)->orderBy('name')->get();
        $suppliers     = Supplier::where('is_active', 1)->orderBy('name')->get();
        $products      = Product::query()->leftJoin('units as u', 'u.id', '=', 'products.base_unit_id')->where('products.is_active', 1)->orderBy('products.name')->select('products.*', 'u.code as unit_code', 'u.name as unit_name')->get();
        $autoPoNo      = $this->generatePoCode();

        return view('inventori.pembelian.po.index', compact(
            'businessUnits', 'warehouses', 'suppliers', 'products',
            'startDate', 'endDate', 'autoPoNo'
        ));
    }

    /**
     * DataTables AJAX Endpoint
     */
    public function products(Request $request)
    {
        $businessUnitId = $request->query('business_unit_id');
        $warehouseId = $request->query('warehouse_id');

        if (!$businessUnitId || !$warehouseId) {
            return response()->json([]);
        }

        $products = DB::table('products as p')
            ->join('product_business_units as pbu', function ($join) use ($businessUnitId) {
                $join->on('pbu.product_id', '=', 'p.id')
                    ->where('pbu.business_unit_id', $businessUnitId);
            })
            ->join('warehouses_stocks as ws', function ($join) use ($warehouseId) {
                $join->on('ws.product_id', '=', 'p.id')
                    ->where('ws.warehouse_id', $warehouseId);
            })
            ->leftJoin('product_unit_conversions as puc', function ($join) {
                $join->on('puc.product_id', '=', 'p.id')
                    ->where('puc.is_default_purchase', 1)
                    ->where('puc.is_active', 1);
            })
            ->leftJoin('units as u', 'u.id', '=', 'puc.unit_id')
            ->where('p.is_active', 1)
            ->orderBy('p.name')
            ->select(
                'p.id',
                'p.code',
                'p.name',
                'p.base_unit_id',
                'u.id as unit_id',
                'u.code as unit_code',
                'u.name as unit_name',
                'puc.conversion_factor',
                'ws.qty'
            )
            ->get();

        return response()->json($products);
    }

    public function data(Request $request)
    {
        $startDate      = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate        = $request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId = $request->query('business_unit_id');
        $supplierId     = $request->query('supplier_id');
        $warehouseId    = $request->query('warehouse_id');
        $statusFilter   = $request->query('status');

        $query = DB::table('purchase_orders as po')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'po.business_unit_id')
            ->join('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->join('warehouses as w', 'w.id', '=', 'po.warehouse_id')
            ->join('users as u', 'u.id', '=', 'po.user_id')
            ->whereNull('po.deleted_at')
            ->whereDate('po.po_date', '>=', $startDate)
            ->whereDate('po.po_date', '<=', $endDate);

        if ($businessUnitId) $query->where('po.business_unit_id', $businessUnitId);
        if ($supplierId)     $query->where('po.supplier_id', $supplierId);
        if ($warehouseId)    $query->where('po.warehouse_id', $warehouseId);
        if ($statusFilter)   $query->where('po.status', $statusFilter);

        $data = $query->select(
            'po.id', 'po.po_no', 'po.po_date', 'po.status', 'po.memo',
            'bu.name as business_unit_name',
            's.name as supplier_name',
            'w.name as warehouse_name',
            'u.name as creator_name'
        )->orderBy('po.created_at', 'desc')->get();

        foreach ($data as $item) {
            $item->formatted_date = Carbon::parse($item->po_date)->format('d/m/Y');
            
            // Hitung Total PO & Total Qty Diterima dari Penerimaan/Receipt
            $item->total_amount = DB::table('purchase_order_items')->where('purchase_order_id', $item->id)->sum('total') ?? 0;
            $item->formatted_total = 'Rp ' . number_format($item->total_amount, 0, ',', '.');

            // Progres Penerimaan Barang
            $totalOrdered  = DB::table('purchase_order_items')->where('purchase_order_id', $item->id)->sum('qty') ?? 0;
            $totalReceived = DB::table('receipt_items as ri')
                ->join('purchase_order_items as poi', 'poi.id', '=', 'ri.purchase_order_item_id')
                ->where('poi.purchase_order_id', $item->id)
                ->sum('ri.qty') ?? 0;

            $item->receipt_progress = "{$totalReceived} / {$totalOrdered}";
            $item->is_fully_received = ($totalReceived >= $totalOrdered && $totalOrdered > 0);
        }

        return response()->json(['data' => $data]);
    }

    /**
     * AJAX Supplier Info untuk Auto-Fill Modal Info Vendor
     */
    public function getSupplierInfo($id)
    {
        $supplier = Supplier::find($id);
        if (!$supplier) {
            return response()->json(['success' => false, 'message' => 'Supplier tidak ditemukan'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'name'    => $supplier->name,
                'address' => $supplier->address ?? 'Alamat tidak diisi',
                'phone'   => $supplier->phone ?? 'Telp tidak diisi',
                'npwp'    => $supplier->npwp ?? '-',
            ]
        ]);
    }

    /**
     * Simpan PO Baru (Draft / Direct Approve)
     */
    public function store(Request $request)
    {
        $request->validate([
            'business_unit_id' => 'required|exists:business_units,id',
            'supplier_id'      => 'required|exists:suppliers,id',
            'warehouse_id'     => 'required|exists:warehouses,id',
            'po_date'          => 'required|date',
            'products'         => 'required|array|min:1',
            'qty'              => 'required|array|min:1',
            'unit_price'       => 'required|array|min:1',
        ]);

        DB::beginTransaction();
        try {
            $poNo = $this->generatePoCode();

            $poId = DB::table('purchase_orders')->insertGetId([
                'entity_id'        => auth()->user()->entity_id ?? 1,
                'business_unit_id' => $request->business_unit_id,
                'supplier_id'      => $request->supplier_id,
                'warehouse_id'     => $request->warehouse_id,
                'user_id'          => auth()->id() ?? 1,
                'po_no'            => $poNo,
                'po_date'          => $request->po_date,
                'status'           => $request->status ?? 'draft',
                'memo'             => $request->memo,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            foreach ($request->products as $idx => $prodId) {
                $qty       = (float) $request->qty[$idx];
                $price     = (float) $request->unit_price[$idx];
                $discount  = (float) ($request->discount[$idx] ?? 0);
                $total     = ($qty * $price) - $discount;

                $product = Product::find($prodId);

                DB::table('purchase_order_items')->insert([
                    'purchase_order_id' => $poId,
                    'product_id'        => $prodId,
                    'unit_id'           => $product->base_unit_id ?? null,
                    'qty'               => $qty,
                    'conversion_factor' => 1.000000,
                    'base_qty'          => $qty,
                    'unit_price'        => $price,
                    'discount'          => $discount,
                    'total'             => $total,
                    'memo'              => $request->item_memo[$idx] ?? null,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => "Purchase Order [{$poNo}] berhasil disimpan!"]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan PO: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Load Data Edit untuk Populating Modal
     */
    public function getEditData($id)
    {
        $po = DB::table('purchase_orders')->where('id', $id)->whereNull('deleted_at')->first();
        if (!$po || $po->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'PO tidak dapat diedit / bukan draft'], 400);
        }

        $items = DB::table('purchase_order_items as poi')
            ->join('products as p', 'p.id', '=', 'poi.product_id')
            ->where('poi.purchase_order_id', $id)
            ->select('poi.*', 'p.name as product_name', 'p.code as product_code')
            ->get();

        return response()->json(['success' => true, 'po' => $po, 'items' => $items]);
    }

    /**
     * Update Draft PO
     */
    public function update(Request $request, $id)
    {
        $po = DB::table('purchase_orders')->where('id', $id)->whereNull('deleted_at')->first();
        if (!$po || $po->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'Hanya PO berstatus DRAFT yang dapat diperbarui!'], 400);
        }

        DB::beginTransaction();
        try {
            DB::table('purchase_orders')->where('id', $id)->update([
                'business_unit_id' => $request->business_unit_id,
                'supplier_id'      => $request->supplier_id,
                'warehouse_id'     => $request->warehouse_id,
                'po_date'          => $request->po_date,
                'memo'             => $request->memo,
                'updated_at'       => now(),
            ]);

            // Replace Items
            DB::table('purchase_order_items')->where('purchase_order_id', $id)->delete();

            foreach ($request->products as $idx => $prodId) {
                $qty       = (float) $request->qty[$idx];
                $price     = (float) $request->unit_price[$idx];
                $discount  = (float) ($request->discount[$idx] ?? 0);
                $total     = ($qty * $price) - $discount;

                $product = Product::find($prodId);

                DB::table('purchase_order_items')->insert([
                    'purchase_order_id' => $id,
                    'product_id'        => $prodId,
                    'unit_id'           => $product->base_unit_id ?? null,
                    'qty'               => $qty,
                    'conversion_factor' => 1.000000,
                    'base_qty'          => $qty,
                    'unit_price'        => $price,
                    'discount'          => $discount,
                    'total'             => $total,
                    'memo'              => $request->item_memo[$idx] ?? null,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => "Purchase Order [{$po->po_no}] berhasil diperbarui!"]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui PO: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Show Detail PO (Modal / Full View)
     */
    public function show($id)
    {
        $po = DB::table('purchase_orders as po')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'po.business_unit_id')
            ->join('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->join('warehouses as w', 'w.id', '=', 'po.warehouse_id')
            ->join('users as u', 'u.id', '=', 'po.user_id')
            ->where('po.id', $id)
            ->whereNull('po.deleted_at')
            ->select('po.*', 'bu.name as business_unit_name', 's.name as supplier_name', 's.address as supplier_address', 's.phone as supplier_phone', 'w.name as warehouse_name', 'u.name as creator_name')
            ->firstOrFail();

        $items = DB::table('purchase_order_items as poi')
            ->join('products as p', 'p.id', '=', 'poi.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'poi.unit_id')
            ->where('poi.purchase_order_id', $id)
            ->select('poi.*', 'p.code as product_code', 'p.name as product_name', 'u.name as unit_name')
            ->get();

        return response()->json(['success' => true, 'po' => $po, 'items' => $items]);
    }

    /**
     * Approve Draft PO
     */
    public function approve($id)
    {
        $po = DB::table('purchase_orders')->where('id', $id)->whereNull('deleted_at')->first();

        if (!$po) {
            return response()->json(['success' => false, 'message' => 'PO tidak ditemukan.'], 404);
        }

        if ($po->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'Hanya PO berstatus DRAFT yang dapat di-approve.'], 400);
        }

        DB::table('purchase_orders')->where('id', $id)->update([
            'status' => 'approved',
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => "Purchase Order [{$po->po_no}] berhasil di-approve untuk proses selanjutnya."]);
    }

    /**
     * Force Close PO
     */
    public function closePo(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            DB::table('purchase_orders')->where('id', $id)->update([
                'status'              => 'closed',
                'cancellation_reason' => $request->reason ?? 'Ditutup manual oleh pengguna',
                'cancelled_by'        => auth()->id(),
                'cancelled_at'        => now(),
                'updated_at'          => now(),
            ]);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Status Purchase Order berhasil di-CLOSE!']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menututup PO: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Hapus Draft PO
     */
    public function destroy($id)
    {
        $po = DB::table('purchase_orders')->where('id', $id)->first();
        if ($po && $po->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'Hanya PO berstatus DRAFT yang dapat dihapus!'], 400);
        }

        DB::table('purchase_orders')->where('id', $id)->update(['deleted_at' => now()]);
        return response()->json(['success' => true, 'message' => 'Draft Purchase Order berhasil dihapus.']);
    }

    /**
     * Cetak Nota/Faktur PO dengan Kop Perusahaan
     */
    public function printPo($id)
    {
        $po = DB::table('purchase_orders as po')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'po.business_unit_id')
            ->join('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->join('warehouses as w', 'w.id', '=', 'po.warehouse_id')
            ->join('users as u', 'u.id', '=', 'po.user_id')
            ->where('po.id', $id)
            ->select('po.*', 'bu.name as business_unit_name', 's.name as supplier_name', 's.address as supplier_address', 's.phone as supplier_phone', 'w.name as warehouse_name', 'u.name as creator_name')
            ->firstOrFail();

        $items = DB::table('purchase_order_items as poi')
            ->join('products as p', 'p.id', '=', 'poi.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'poi.unit_id')
            ->where('poi.purchase_order_id', $id)
            ->select('poi.*', 'p.code as product_code', 'p.name as product_name', 'u.name as unit_name')
            ->get();

        return view('inventori.pembelian.po.print-po', compact('po', 'items'));
    }

    /**
     * Cetak Rekap List PO
     */
    public function printList(Request $request)
    {
        $startDate      = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate        = $request->query('end_date', now()->endOfMonth()->format('Y-m-d'));

        $query = DB::table('purchase_orders as po')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'po.business_unit_id')
            ->join('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->join('warehouses as w', 'w.id', '=', 'po.warehouse_id')
            ->whereNull('po.deleted_at')
            ->whereDate('po.po_date', '>=', $startDate)
            ->whereDate('po.po_date', '<=', $endDate);

        $purchaseOrders = $query->select(
            'po.*', 'bu.name as business_unit_name', 's.name as supplier_name', 'w.name as warehouse_name'
        )->orderBy('po.created_at', 'desc')->get();

        foreach ($purchaseOrders as $po) {
            $po->total_amount = DB::table('purchase_order_items')->where('purchase_order_id', $po->id)->sum('total') ?? 0;
        }

        return view('inventori.pembelian.po.print-list', compact('purchaseOrders', 'startDate', 'endDate'));
    }

    private function generatePoCode()
    {
        $dateStr = date('Ymd');
        $last    = DB::table('purchase_orders')
            ->where('po_no', 'LIKE', "PO-{$dateStr}-%")
            ->orderBy('id', 'desc')
            ->first();

        $nextSeq = $last ? ((int) substr($last->po_no, -3)) + 1 : 1;
        return 'PO-' . $dateStr . '-' . str_pad($nextSeq, 3, '0', STR_PAD_LEFT);
    }
	
	public function exportExcel(Request $request)
	{
		$fileName = 'Laporan_Rekapitulasi_PO_' . date('Ymd_His') . '.xlsx';
		return Excel::download(new PurchaseOrderExport($request), $fileName);
	}
}