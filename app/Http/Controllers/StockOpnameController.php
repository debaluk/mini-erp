<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BusinessUnit;
use App\Models\Warehouse;
use App\Models\Product;
use App\Exports\StockOpnameListExport;
use App\Exports\StockOpnameDetailExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StockOpnameController extends Controller
{
    /**
     * Halaman List & Summary Stock Opname
     */
    public function index(Request $request)
    {
        $startDate      = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate        = $request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId = $request->query('business_unit_id');
        $warehouseId    = $request->query('warehouse_id');
        $statusFilter   = $request->query('status');
        $search         = $request->query('search');

        $businessUnits = BusinessUnit::where('is_active', 1)->orderBy('code')->get();
        $warehouses    = Warehouse::where('is_active', 1)->orderBy('name')->get();

        $query = DB::table('stock_opnames as so')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'so.business_unit_id')
            ->join('warehouses as w', 'w.id', '=', 'so.warehouse_id')
            ->join('users as u', 'u.id', '=', 'so.user_id')
            ->whereNull('so.deleted_at')
            ->whereDate('so.opname_date', '>=', $startDate)
            ->whereDate('so.opname_date', '<=', $endDate);

        if ($businessUnitId) $query->where('so.business_unit_id', $businessUnitId);
        if ($warehouseId)    $query->where('so.warehouse_id', $warehouseId);
        if ($statusFilter)   $query->where('so.status', $statusFilter);
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('so.opname_no', 'LIKE', "%{$search}%")
                  ->orWhere('w.name', 'LIKE', "%{$search}%");
            });
        }

        $opnames = $query->select(
            'so.*',
            'bu.name as business_unit_name',
            'w.name as warehouse_name',
            'u.name as creator_name'
        )->orderBy('so.created_at', 'desc')->get();

        foreach ($opnames as $op) {
            $stats = DB::table('stock_opname_items')
                ->where('stock_opname_id', $op->id)
                ->select(
                    DB::raw('COUNT(*) as total_items'),
                    DB::raw('SUM(CASE WHEN difference != 0 THEN 1 ELSE 0 END) as total_variance_items'),
                    DB::raw('SUM(difference) as total_difference_qty')
                )->first();

            $op->total_items          = $stats->total_items ?? 0;
            $op->total_variance_items = $stats->total_variance_items ?? 0;
            $op->total_difference_qty = $stats->total_difference_qty ?? 0;
            $op->has_physical_count = DB::table('stock_opname_items')
                ->where('stock_opname_id', $op->id)
                ->whereColumn('updated_at', '>', 'created_at')
                ->exists();
        }

        return view('inventori.persediaan.opname.index', compact(
            'businessUnits', 'warehouses', 'opnames', 'startDate',
            'endDate', 'businessUnitId', 'warehouseId', 'statusFilter', 'search'
        ));
    }

    /**
     * TAHAP 1A: Form Halaman Penuh Buat Snapshot SO Baru
     */
    public function create()
    {
        $businessUnits = BusinessUnit::where('is_active', 1)->orderBy('code')->get();
        $warehouses    = Warehouse::where('is_active', 1)->orderBy('name')->get();
        $autoCode      = $this->generateOpnameCode();

        return view('inventori.persediaan.opname.create', compact('businessUnits', 'warehouses', 'autoCode'));
    }

    /**
     * Edit Header Snapshot Stock Opname
     */
    public function edit($id)
    {
        $opname = DB::table('stock_opnames')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->where('status', 'draft')
            ->firstOrFail();

        $businessUnits = BusinessUnit::where('is_active', 1)->orderBy('code')->get();
        $warehouses    = Warehouse::where('is_active', 1)->orderBy('name')->get();
        $autoCode      = $opname->opname_no;

        return view('inventori.persediaan.opname.create', compact(
            'businessUnits',
            'warehouses',
            'autoCode',
            'opname'
        ));
    }

    /**
     * TAHAP 1B: Simpan Snapshot Stok Sistem (Status: DRAFT)
     */
    public function storeSnapshot(Request $request)
    {
        $request->validate([
            'business_unit_id' => 'required|exists:business_units,id',
            'warehouse_id'     => 'required|exists:warehouses,id',
            'opname_date'      => 'required|date',
        ]);

        DB::beginTransaction();
        try {
            // Ambil daftar produk yang aktif mengelola stok di gudang terpilih
            $stocks = DB::table('warehouses_stocks as ws')
                ->join('products as p', 'p.id', '=', 'ws.product_id')
                ->where('ws.warehouse_id', $request->warehouse_id)
                ->where('p.is_active', 1)
                ->where('p.manage_stock', 1)
                ->select('p.id as product_id', 'ws.qty as system_qty')
                ->get();

            if ($stocks->isEmpty()) {
                return redirect()->back()->with('error', 'Gagal: Tidak ada item barang terdaftar di gudang terpilih!');
            }

            $opnameNo = $this->generateOpnameCode();

            $opnameId = DB::table('stock_opnames')->insertGetId([
                'entity_id'        => auth()->user()->entity_id ?? 1,
                'business_unit_id' => $request->business_unit_id,
                'warehouse_id'     => $request->warehouse_id,
                'user_id'          => auth()->id() ?? 1,
                'opname_date'      => $request->opname_date,
                'opname_no'        => $opnameNo,
                'status'           => 'draft', // Draft Snapshot
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            foreach ($stocks as $st) {
                DB::table('stock_opname_items')->insert([
                    'stock_opname_id' => $opnameId,
                    'product_id'      => $st->product_id,
                    'system_qty'      => (float) $st->system_qty,
                    'actual_qty'      => (float) $st->system_qty, // Inisialisasi default
                    'difference'      => 0.000,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }

            DB::commit();
            return redirect()->route('inventori.stock-opname.input-count', $opnameId)
                ->with('success', "Snapshot Stock Opname [{$opnameNo}] berhasil dibuat! Silakan lanjutkan ke Tahap Input Hasil Fisik.");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal membuat snapshot: ' . $e->getMessage());
        }
    }

    /**
     * TAHAP 2A: Form Halaman Penuh Input Hasil Fisik per No. SO
     */
    public function update(Request $request, $id)
    {
        $opname = DB::table('stock_opnames')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->where('status', 'draft')
            ->firstOrFail();

        $request->validate([
            'business_unit_id' => 'required|exists:business_units,id',
            'warehouse_id'     => 'required|exists:warehouses,id',
            'opname_date'      => 'required|date',
        ]);

        DB::table('stock_opnames')
            ->where('id', $id)
            ->update([
                'business_unit_id' => $request->business_unit_id,
                'warehouse_id'     => $request->warehouse_id,
                'opname_date'      => $request->opname_date,
                'status'           => $request->boolean('approve_post') ? 'posted' : 'draft',
                'updated_at'       => now(),
            ]);

        return redirect()->route('inventori.stock-opname.index')
            ->with('success', "Stock Opname [{$opname->opname_no}] berhasil diperbarui.");
    }

    public function inputCount($id)
    {
        $opname = DB::table('stock_opnames as so')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'so.business_unit_id')
            ->join('warehouses as w', 'w.id', '=', 'so.warehouse_id')
            ->join('users as u', 'u.id', '=', 'so.user_id')
            ->where('so.id', $id)
            ->whereNull('so.deleted_at')
            ->select('so.*', 'bu.name as business_unit_name', 'w.name as warehouse_name', 'u.name as creator_name')
            ->firstOrFail();

        $items = DB::table('stock_opname_items as soi')
            ->join('products as p', 'p.id', '=', 'soi.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->where('soi.stock_opname_id', $id)
            ->select('soi.*', 'p.code as product_code', 'p.name as product_name', 'u.name as unit_name')
            ->orderBy('p.name', 'asc')
            ->get();

        return view('inventori.persediaan.opname.input-count', compact('opname', 'items'));
    }

    /**
     * TAHAP 2B: Simpan Hasil Fisik & Hitung Selisih (Status: POSTED)
     */
    public function storeCount(Request $request, $id)
    {
        $opname = DB::table('stock_opnames')->where('id', $id)->whereNull('deleted_at')->firstOrFail();

        $request->validate([
            'item_ids'   => 'required|array',
            'actual_qty' => 'required|array',
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->item_ids as $idx => $itemId) {
                $actualQty = (float) $request->actual_qty[$idx];

                $item = DB::table('stock_opname_items')->where('id', $itemId)->first();
                if ($item) {
                    $systemQty = (float) $item->system_qty;
                    $difference = $actualQty - $systemQty;

                    DB::table('stock_opname_items')->where('id', $itemId)->update([
                        'actual_qty' => $actualQty,
                        'difference' => $difference,
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::commit();
            return redirect()->route('inventori.stock-opname.index')
                ->with('success', "Hasil Perhitungan Fisik SO [{$opname->opname_no}] berhasil disimpan!");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menyimpan hasil SO: ' . $e->getMessage());
        }
    }

    /**
     * Halaman Detail Laporan Hasil Opname
     */
    public function show($id)
    {
        $opname = DB::table('stock_opnames as so')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'so.business_unit_id')
            ->join('warehouses as w', 'w.id', '=', 'so.warehouse_id')
            ->join('users as u', 'u.id', '=', 'so.user_id')
            ->where('so.id', $id)
            ->whereNull('so.deleted_at')
            ->select('so.*', 'bu.name as business_unit_name', 'w.name as warehouse_name', 'u.name as creator_name')
            ->firstOrFail();

        $items = DB::table('stock_opname_items as soi')
            ->join('products as p', 'p.id', '=', 'soi.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->where('soi.stock_opname_id', $id)
            ->select('soi.*', 'p.code as product_code', 'p.name as product_name', 'u.name as unit_name')
            ->orderBy('p.name', 'asc')
            ->get();

        return view('inventori.persediaan.opname.show', compact('opname', 'items'));
    }

    /**
     * Soft Delete Dokumen Opname
     */
    public function destroy($id)
    {
        $opname = DB::table('stock_opnames')->where('id', $id)->whereNull('deleted_at')->firstOrFail();

        DB::beginTransaction();
        try {
            DB::table('stock_opnames')->where('id', $id)->update(['deleted_at' => now()]);
            DB::commit();
            return redirect()->route('inventori.stock-opname.index')
                ->with('success', "Dokumen Stock Opname [{$opname->opname_no}] berhasil dihapus.");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /**
     * Cetak Lembar Hitung Fisik (Blind Count Sheet)
     */
    public function printSheet($id)
    {
        $opname = DB::table('stock_opnames as so')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'so.business_unit_id')
            ->join('warehouses as w', 'w.id', '=', 'so.warehouse_id')
            ->where('so.id', $id)
            ->whereNull('so.deleted_at')
            ->select('so.*', 'bu.name as business_unit_name', 'w.name as warehouse_name')
            ->firstOrFail();

        $items = DB::table('stock_opname_items as soi')
            ->join('products as p', 'p.id', '=', 'soi.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->where('soi.stock_opname_id', $id)
            ->select('soi.*', 'p.code as product_code', 'p.name as product_name', 'u.name as unit_name')
            ->orderBy('p.name', 'asc')
            ->get();

        return view('inventori.persediaan.opname.print-sheet', compact('opname', 'items'));
    }

    /**
     * Cetak Laporan Varian Hasil SO per No. SO
     */
    public function printReport($id)
    {
        $opname = DB::table('stock_opnames as so')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'so.business_unit_id')
            ->join('warehouses as w', 'w.id', '=', 'so.warehouse_id')
            ->join('users as u', 'u.id', '=', 'so.user_id')
            ->where('so.id', $id)
            ->whereNull('so.deleted_at')
            ->select('so.*', 'bu.name as business_unit_name', 'w.name as warehouse_name', 'u.name as creator_name')
            ->firstOrFail();

        $items = DB::table('stock_opname_items as soi')
            ->join('products as p', 'p.id', '=', 'soi.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->where('soi.stock_opname_id', $id)
            ->select('soi.*', 'p.code as product_code', 'p.name as product_name', 'u.name as unit_name')
            ->orderBy('p.name', 'asc')
            ->get();

        return view('inventori.persediaan.opname.print-report', compact('opname', 'items'));
    }

    /**
     * Export Excel 1: List Seluruh Dokumen Stock Opname
     */
    public function exportList(Request $request)
    {
        return Excel::download(new StockOpnameListExport($request), 'Laporan_List_Stock_Opname_' . date('Ymd_His') . '.xlsx');
    }

    /**
     * Export Excel 2: Detail Hasil SO per Nomor SO Spesifik
     */
    public function exportDetail($id)
    {
        $opname = DB::table('stock_opnames')->where('id', $id)->firstOrFail();
        return Excel::download(new StockOpnameDetailExport($id), 'Stock_Opname_' . $opname->opname_no . '.xlsx');
    }

    private function generateOpnameCode()
    {
        $dateStr = date('Ymd');
        $last    = DB::table('stock_opnames')
            ->where('opname_no', 'LIKE', "OPN-{$dateStr}-%")
            ->orderBy('id', 'desc')
            ->first();

        $nextSeq = $last ? ((int) substr($last->opname_no, -3)) + 1 : 1;
        return 'OPN-' . $dateStr . '-' . str_pad($nextSeq, 3, '0', STR_PAD_LEFT);
    }
}