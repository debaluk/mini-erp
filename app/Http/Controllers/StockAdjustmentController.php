<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BusinessUnit;
use App\Models\Warehouse;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StockAdjustmentController extends Controller
{
    /**
     * Halaman Utama List
     */
    public function index(Request $request)
    {
        $startDate      = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate        = $request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId = $request->query('business_unit_id');
        $warehouseId    = $request->query('warehouse_id');
        $statusFilter   = $request->query('status');

        $businessUnits = BusinessUnit::where('is_active', 1)->orderBy('code')->get();
        $warehouses    = Warehouse::where('is_active', 1)->orderBy('name')->get();

        return view('inventori.persediaan.penyesuaian.index', compact(
            'businessUnits', 'warehouses', 'startDate', 'endDate',
            'businessUnitId', 'warehouseId', 'statusFilter'
        ));
    }

    /**
     * Endpoint AJAX untuk DataTables Server-Side Paging & Filter
     */
    public function data(Request $request)
    {
        $startDate      = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate        = $request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId = $request->query('business_unit_id');
        $warehouseId    = $request->query('warehouse_id');
        $statusFilter   = $request->query('status');

        $query = DB::table('stock_adjustments as sa')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'sa.business_unit_id')
            ->join('warehouses as w', 'w.id', '=', 'sa.warehouse_id')
            ->leftJoin('stock_opnames as so', 'so.id', '=', 'sa.stock_opname_id')
            ->join('users as u', 'u.id', '=', 'sa.user_id')
            ->whereNull('sa.deleted_at')
            ->whereDate('sa.adjustment_date', '>=', $startDate)
            ->whereDate('sa.adjustment_date', '<=', $endDate);

        if ($businessUnitId) $query->where('sa.business_unit_id', $businessUnitId);
        if ($warehouseId)    $query->where('sa.warehouse_id', $warehouseId);
        if ($statusFilter)   $query->where('sa.status', $statusFilter);

        $data = $query->select(
            'sa.id',
            'sa.adjustment_date',
            'sa.adjustment_no',
            'sa.status',
            'sa.reason',
            'bu.name as business_unit_name',
            'w.name as warehouse_name',
            'so.opname_no',
            'u.name as creator_name'
        )->orderBy('sa.created_at', 'desc')->get();

        foreach ($data as $item) {
            $item->formatted_date = Carbon::parse($item->adjustment_date)->format('d/m/Y');
            $item->total_amount   = DB::table('stock_adjustment_items')
                ->where('stock_adjustment_id', $item->id)
                ->sum('total_cost') ?? 0;
            $item->formatted_total = 'Rp ' . number_format($item->total_amount, 0, ',', '.');
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Form Buat Adjustment
     */
    public function create(Request $request)
    {
        $businessUnits = BusinessUnit::where('is_active', 1)->orderBy('code')->get();
        $warehouses    = Warehouse::where('is_active', 1)->orderBy('name')->get();
        $autoCode      = $this->generateAdjustmentCode();

        $fromOpnameId   = $request->query('from_opname');
        $selectedOpname = null;
        $varianceItems  = collect();

        if ($fromOpnameId) {
            $selectedOpname = DB::table('stock_opnames')->where('id', $fromOpnameId)->first();
            if ($selectedOpname) {
                $varianceItems = DB::table('stock_opname_items as soi')
                    ->join('products as p', 'p.id', '=', 'soi.product_id')
                    ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
                    ->leftJoin('warehouses_stocks as ws', function ($join) use ($selectedOpname) {
                        $join->on('ws.product_id', '=', 'soi.product_id')
                             ->where('ws.warehouse_id', '=', $selectedOpname->warehouse_id);
                    })
                    ->where('soi.stock_opname_id', $fromOpnameId)
                    ->where('soi.difference', '!=', 0)
                    ->select(
                        'soi.*',
                        'p.code as product_code',
                        'p.name as product_name',
                        'u.name as unit_name',
                        'ws.avg_cost'
                    )->get();
            }
        }

        $postedOpnames = DB::table('stock_opnames as so')
            ->join('warehouses as w', 'w.id', '=', 'so.warehouse_id')
            ->where('so.status', 'posted')
            ->whereNull('so.deleted_at')
            ->select('so.id', 'so.opname_no', 'so.opname_date', 'w.name as warehouse_name')
            ->orderBy('so.id', 'desc')
            ->get();

        return view('inventori.persediaan.penyesuaian.create', compact(
            'businessUnits', 'warehouses', 'autoCode',
            'fromOpnameId', 'selectedOpname', 'varianceItems', 'postedOpnames'
        ));
    }

    /**
     * Simpan Adjustment (Draft / Post Direct)
     */
    public function store(Request $request)
    {
        $request->validate([
            'business_unit_id' => 'required|exists:business_units,id',
            'warehouse_id'     => 'required|exists:warehouses,id',
            'adjustment_date'  => 'required|date',
            'products'         => 'required|array|min:1',
            'adjustment_qty'   => 'required|array|min:1',
        ]);

        DB::beginTransaction();
        try {
            $adjNo = $this->generateAdjustmentCode();

            $adjId = DB::table('stock_adjustments')->insertGetId([
                'entity_id'        => auth()->user()->entity_id ?? 1,
                'business_unit_id' => $request->business_unit_id,
                'warehouse_id'     => $request->warehouse_id,
                'user_id'          => auth()->id() ?? 1,
                'stock_opname_id'  => $request->stock_opname_id ?? null,
                'adjustment_no'    => $adjNo,
                'adjustment_date'  => $request->adjustment_date,
                'status'           => 'draft',
                'reason'           => $request->reason ?? 'Penyesuaian Stok Hasil SO',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            foreach ($request->products as $idx => $prodId) {
                $adjQty = (float) $request->adjustment_qty[$idx];
                if (abs($adjQty) < 0.0001) continue;

                $stock = DB::table('warehouses_stocks')
                    ->where('warehouse_id', $request->warehouse_id)
                    ->where('product_id', $prodId)
                    ->first();

                $systemQty = (float) ($request->system_qty[$idx] ?? ($stock ? $stock->qty : 0));
                $finalQty  = $systemQty + $adjQty;
                $unitCost  = $stock ? (float) $stock->avg_cost : 0.0000;
                $totalCost = abs($adjQty) * $unitCost;

                DB::table('stock_adjustment_items')->insert([
                    'stock_adjustment_id' => $adjId,
                    'product_id'          => $prodId,
                    'system_qty'          => $systemQty,
                    'adjustment_qty'      => $adjQty,
                    'final_qty'           => $finalQty,
                    'unit_cost'          => $unitCost,
                    'total_cost'         => $totalCost,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
            }

            DB::commit();

            if ($request->has('post_now')) {
                return $this->executePosting($adjId);
            }

            return redirect()->route('inventori.penyesuaian.index')
                ->with('swal_success', "Draft Penyesuaian Stok [{$adjNo}] berhasil disimpan!");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('swal_error', 'Gagal menyimpan penyesuaian: ' . $e->getMessage());
        }
    }

    /**
     * Form Edit Adjustment (Khusus Status Draft)
     */
    public function edit($id)
    {
        $adj = DB::table('stock_adjustments')->where('id', $id)->whereNull('deleted_at')->firstOrFail();

        if ($adj->status === 'posted') {
            return redirect()->route('inventori.penyesuaian.show', $id)
                ->with('swal_error', 'Transaksi berstatus POSTED tidak dapat diubah lagi!');
        }

        $businessUnits = BusinessUnit::where('is_active', 1)->orderBy('code')->get();
        $warehouses    = Warehouse::where('is_active', 1)->orderBy('name')->get();

        $items = DB::table('stock_adjustment_items as sai')
            ->join('products as p', 'p.id', '=', 'sai.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->where('sai.stock_adjustment_id', $id)
            ->select('sai.*', 'p.code as product_code', 'p.name as product_name', 'u.name as unit_name')
            ->get();

        return view('inventori.persediaan.penyesuaian.edit', compact('adj', 'businessUnits', 'warehouses', 'items'));
    }

    /**
     * Update Adjustment (Khusus Status Draft)
     */
    public function update(Request $request, $id)
    {
        $adj = DB::table('stock_adjustments')->where('id', $id)->whereNull('deleted_at')->firstOrFail();

        if ($adj->status === 'posted') {
            return redirect()->route('inventori.penyesuaian.index')
                ->with('swal_error', 'Gagal Update: Transaksi berstatus POSTED bersifat permanen!');
        }

        $request->validate([
            'adjustment_date' => 'required|date',
            'adjustment_qty'  => 'required|array',
        ]);

        DB::beginTransaction();
        try {
            DB::table('stock_adjustments')->where('id', $id)->update([
                'adjustment_date' => $request->adjustment_date,
                'reason'          => $request->reason,
                'updated_at'      => now(),
            ]);

            foreach ($request->item_ids as $idx => $itemId) {
                $adjQty = (float) $request->adjustment_qty[$idx];
                $item   = DB::table('stock_adjustment_items')->where('id', $itemId)->first();

                if ($item) {
                    $systemQty = (float) $item->system_qty;
                    $finalQty  = $systemQty + $adjQty;
                    $unitCost  = (float) $item->unit_cost;
                    $totalCost = abs($adjQty) * $unitCost;

                    DB::table('stock_adjustment_items')->where('id', $itemId)->update([
                        'adjustment_qty' => $adjQty,
                        'final_qty'      => $finalQty,
                        'total_cost'     => $totalCost,
                        'updated_at'     => now(),
                    ]);
                }
            }

            DB::commit();

            if ($request->has('post_now')) {
                return $this->executePosting($id);
            }

            return redirect()->route('inventori.penyesuaian.index')
                ->with('swal_success', "Draft Penyesuaian [{$adj->adjustment_no}] berhasil diperbarui!");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('swal_error', 'Gagal memperbarui draft: ' . $e->getMessage());
        }
    }

    /**
     * Detail & Audit Trail
     */
    public function show($id)
    {
        $adj = DB::table('stock_adjustments as sa')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'sa.business_unit_id')
            ->join('warehouses as w', 'w.id', '=', 'sa.warehouse_id')
            ->leftJoin('stock_opnames as so', 'so.id', '=', 'sa.stock_opname_id')
            ->join('users as u', 'u.id', '=', 'sa.user_id')
            ->where('sa.id', $id)
            ->whereNull('sa.deleted_at')
            ->select('sa.*', 'bu.name as business_unit_name', 'w.name as warehouse_name', 'so.opname_no', 'u.name as creator_name')
            ->firstOrFail();

        $items = DB::table('stock_adjustment_items as sai')
            ->join('products as p', 'p.id', '=', 'sai.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->where('sai.stock_adjustment_id', $id)
            ->select('sai.*', 'p.code as product_code', 'p.name as product_name', 'u.name as unit_name')
            ->get();

        $journals = DB::table('journals')
            ->where('source_id', $id)
            ->whereIn('source_type', ['stock_adjustment_loss', 'stock_adjustment_gain'])
            ->get();

        foreach ($journals as $j) {
            $j->entries = DB::table('journal_entries as je')
                ->join('chart_of_accounts as coa', 'coa.id', '=', 'je.account_id')
                ->where('je.journal_id', $j->id)
                ->select('je.*', 'coa.code as account_code', 'coa.name as account_name')
                ->get();
        }

        return view('inventori.persediaan.penyesuaian.show', compact('adj', 'items', 'journals'));
    }

    /**
     * Soft Delete (Draft Only)
     */
    public function destroy($id)
    {
        $adj = DB::table('stock_adjustments')->where('id', $id)->whereNull('deleted_at')->firstOrFail();

        if ($adj->status === 'posted') {
            return redirect()->back()->with('swal_error', 'Gagal Hapus: Transaksi POSTED bersifat permanen untuk audit trail!');
        }

        DB::beginTransaction();
        try {
            DB::table('stock_adjustments')->where('id', $id)->update(['deleted_at' => now()]);
            DB::commit();
            return redirect()->route('inventori.penyesuaian.index')
                ->with('swal_success', "Draft Adjustment [{$adj->adjustment_no}] berhasil dihapus.");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('swal_error', 'Gagal menghapus draft: ' . $e->getMessage());
        }
    }

    /**
     * Action Post
     */
    public function post($id)
    {
        return $this->executePosting($id);
    }

    private function executePosting($id)
    {
        $adj = DB::table('stock_adjustments')->where('id', $id)->whereNull('deleted_at')->first();
        if (!$adj || $adj->status === 'posted') {
            return redirect()->back()->with('swal_error', 'Dokumen penyesuaian sudah berstatus POSTED / tidak ditemukan.');
        }

        DB::beginTransaction();
        try {
            $items = DB::table('stock_adjustment_items')->where('stock_adjustment_id', $id)->get();

            $totalLossAmount = 0;
            $totalGainAmount = 0;

            foreach ($items as $item) {
                $adjQty = (float) $item->adjustment_qty;
                $absQty = abs($adjQty);
                if ($absQty < 0.0001) continue;

                $stock = DB::table('warehouses_stocks')
                    ->where('warehouse_id', $adj->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->first();

                $unitCost  = $stock ? (float) $stock->avg_cost : 0.0000;
                $totalCost = $absQty * $unitCost;

                $movementType = ($adjQty < 0) ? 'ADJUSTMENT_OUT' : 'ADJUSTMENT_IN';

                if ($adjQty < 0) {
                    DB::table('warehouses_stocks')
                        ->where('warehouse_id', $adj->warehouse_id)
                        ->where('product_id', $item->product_id)
                        ->decrement('qty', $absQty);

                    $totalLossAmount += $totalCost;
                } else {
                    DB::table('warehouses_stocks')
                        ->where('warehouse_id', $adj->warehouse_id)
                        ->where('product_id', $item->product_id)
                        ->increment('qty', $absQty);

                    $totalGainAmount += $totalCost;
                }

                $product = DB::table('products')->where('id', $item->product_id)->first();

                DB::table('stock_movements')->insert([
                    'entity_id'         => $adj->entity_id,
                    'business_unit_id'  => $adj->business_unit_id,
                    'warehouse_id'      => $adj->warehouse_id,
                    'product_id'        => $item->product_id,
                    'unit_id'           => $product->base_unit_id ?? null,
                    'transaction_qty'   => $absQty,
                    'conversion_factor' => 1.000000,
                    'movement_type'     => $movementType,
                    'qty'               => $adjQty,
                    'unit_cost'         => $unitCost,
                    'reference_type'    => 'stock_adjustment',
                    'reference_id'      => $adj->id,
                    'occurred_at'       => now(),
                    'created_by'        => auth()->id() ?? 1,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }

            // Auto-Jurnal GL
            $invAccount  = ChartOfAccount::where('code', '1000401')->first() ?? ChartOfAccount::where('type', 'asset')->where('name', 'LIKE', '%Persediaan%')->first();
            $lossAccount = ChartOfAccount::where('code', '6000402')->first() ?? ChartOfAccount::where('type', 'expense')->where('name', 'LIKE', '%Kerusakan%')->first();
            $gainAccount = ChartOfAccount::where('code', '4000303')->first() ?? ChartOfAccount::where('type', 'revenue')->where('name', 'LIKE', '%Lain%')->first();

            if ($totalLossAmount > 0 && $invAccount && $lossAccount) {
                $jLoss = Journal::create([
                    'entity_id'        => $adj->entity_id,
                    'business_unit_id' => $adj->business_unit_id,
                    'journal_no'       => 'JRN-ADJ-LOSS-' . date('YmdHis'),
                    'journal_date'     => $adj->adjustment_date,
                    'source_type'      => 'stock_adjustment_loss',
                    'source_id'        => $adj->id,
                    'description'      => "Beban Kerusakan/Selisih Stok Minus #{$adj->adjustment_no}",
                    'status'           => 'posted',
                ]);

                JournalEntry::create(['journal_id' => $jLoss->id, 'account_id' => $lossAccount->id, 'debit' => $totalLossAmount, 'credit' => 0]);
                JournalEntry::create(['journal_id' => $jLoss->id, 'account_id' => $invAccount->id, 'debit' => 0, 'credit' => $totalLossAmount]);
            }

            if ($totalGainAmount > 0 && $invAccount && $gainAccount) {
                $jGain = Journal::create([
                    'entity_id'        => $adj->entity_id,
                    'business_unit_id' => $adj->business_unit_id,
                    'journal_no'       => 'JRN-ADJ-GAIN-' . date('YmdHis'),
                    'journal_date'     => $adj->adjustment_date,
                    'source_type'      => 'stock_adjustment_gain',
                    'source_id'        => $adj->id,
                    'description'      => "Penyesuaian Selisih Stok Plus #{$adj->adjustment_no}",
                    'status'           => 'posted',
                ]);

                JournalEntry::create(['journal_id' => $jGain->id, 'account_id' => $invAccount->id, 'debit' => $totalGainAmount, 'credit' => 0]);
                JournalEntry::create(['journal_id' => $jGain->id, 'account_id' => $gainAccount->id, 'debit' => 0, 'credit' => $totalGainAmount]);
            }

            DB::table('stock_adjustments')->where('id', $id)->update(['status' => 'posted', 'updated_at' => now()]);

            DB::commit();
            return redirect()->route('inventori.penyesuaian.show', $id)
                ->with('swal_success', "Penyesuaian Stok [{$adj->adjustment_no}] BERHASIL DIPOSTING! Stok & Jurnal GL telah diperbarui.");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('swal_error', 'Gagal memproses posting: ' . $e->getMessage());
        }
    }

    /**
     * Cetak Rekap List Penyesuaian
     */
    public function printList(Request $request)
    {
        $startDate      = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate        = $request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId = $request->query('business_unit_id');
        $warehouseId    = $request->query('warehouse_id');
        $statusFilter   = $request->query('status');

        $query = DB::table('stock_adjustments as sa')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'sa.business_unit_id')
            ->join('warehouses as w', 'w.id', '=', 'sa.warehouse_id')
            ->leftJoin('stock_opnames as so', 'so.id', '=', 'sa.stock_opname_id')
            ->join('users as u', 'u.id', '=', 'sa.user_id')
            ->whereNull('sa.deleted_at')
            ->whereDate('sa.adjustment_date', '>=', $startDate)
            ->whereDate('sa.adjustment_date', '<=', $endDate);

        if ($businessUnitId) $query->where('sa.business_unit_id', $businessUnitId);
        if ($warehouseId)    $query->where('sa.warehouse_id', $warehouseId);
        if ($statusFilter)   $query->where('sa.status', $statusFilter);

        $adjustments = $query->select(
            'sa.*', 'bu.name as business_unit_name', 'w.name as warehouse_name',
            'so.opname_no', 'u.name as creator_name'
        )->orderBy('sa.created_at', 'desc')->get();

        foreach ($adjustments as $adj) {
            $adj->total_amount = DB::table('stock_adjustment_items')
                ->where('stock_adjustment_id', $adj->id)
                ->sum('total_cost') ?? 0;
        }

        return view('inventori.persediaan.penyesuaian.print-list', compact('adjustments', 'startDate', 'endDate'));
    }

    /**
     * Cetak Detail Voucher Penyesuaian
     */
    public function printDetail($id)
    {
        $adj = DB::table('stock_adjustments as sa')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'sa.business_unit_id')
            ->join('warehouses as w', 'w.id', '=', 'sa.warehouse_id')
            ->leftJoin('stock_opnames as so', 'so.id', '=', 'sa.stock_opname_id')
            ->join('users as u', 'u.id', '=', 'sa.user_id')
            ->where('sa.id', $id)
            ->whereNull('sa.deleted_at')
            ->select('sa.*', 'bu.name as business_unit_name', 'w.name as warehouse_name', 'so.opname_no', 'u.name as creator_name')
            ->firstOrFail();

        $items = DB::table('stock_adjustment_items as sai')
            ->join('products as p', 'p.id', '=', 'sai.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->where('sai.stock_adjustment_id', $id)
            ->select('sai.*', 'p.code as product_code', 'p.name as product_name', 'u.name as unit_name')
            ->get();

        return view('inventori.persediaan.penyesuaian.print-detail', compact('adj', 'items'));
    }

    private function generateAdjustmentCode()
    {
        $dateStr = date('Ymd');
        $last    = DB::table('stock_adjustments')
            ->where('adjustment_no', 'LIKE', "ADJ-{$dateStr}-%")
            ->orderBy('id', 'desc')
            ->first();

        $nextSeq = $last ? ((int) substr($last->adjustment_no, -3)) + 1 : 1;
        return 'ADJ-' . $dateStr . '-' . str_pad($nextSeq, 3, '0', STR_PAD_LEFT);
    }
}