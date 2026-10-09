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
     * Form Buat Adjustment. Satu opname hanya boleh menjadi sumber satu adjustment aktif.
     */
    public function create(Request $request)
    {
        $businessUnits = BusinessUnit::where('is_active', 1)->orderBy('code')->get();
        $warehouses = Warehouse::where('is_active', 1)->orderBy('name')->get();
        $autoCode = $this->generateAdjustmentCode();
        $fromOpnameId = $request->query('from_opname');
        $selectedOpname = null;
        $varianceItems = collect();

        if ($fromOpnameId) {
            $selectedOpname = DB::table('stock_opnames')
                ->where('id', $fromOpnameId)
                ->where('status', 'posted')
                ->whereNull('deleted_at')
                ->first();

            if (!$selectedOpname) {
                return redirect()->route('inventori.penyesuaian.create')
                    ->with('swal_error', 'Stock Opname belum difinalisasi atau tidak ditemukan.');
            }

            if (DB::table('stock_adjustments')->where('stock_opname_id', $selectedOpname->id)->whereNull('deleted_at')->exists()) {
                return redirect()->route('inventori.penyesuaian.index')
                    ->with('swal_error', 'Stock Opname ini sudah memiliki dokumen Penyesuaian Stok. Selisih tidak boleh diproses dua kali.');
            }

            $this->validateAdjustmentMapping(
                (int) $selectedOpname->entity_id,
                (int) $selectedOpname->business_unit_id,
                (int) $selectedOpname->warehouse_id
            );

            $varianceItems = DB::table('stock_opname_items as soi')
                ->join('products as p', 'p.id', '=', 'soi.product_id')
                ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
                ->leftJoin('warehouses_stocks as ws', function ($join) use ($selectedOpname) {
                    $join->on('ws.product_id', '=', 'soi.product_id')
                        ->where('ws.warehouse_id', '=', $selectedOpname->warehouse_id)
                        ->where('ws.entity_id', '=', $selectedOpname->entity_id);
                })
                ->where('soi.stock_opname_id', $selectedOpname->id)
                ->where('soi.difference', '!=', 0)
                ->select('soi.*', 'p.code as product_code', 'p.name as product_name', 'u.name as unit_name', 'ws.avg_cost')
                ->orderBy('p.name')
                ->get();

            if ($varianceItems->isEmpty()) {
                return redirect()->route('inventori.stock-opname.show', $selectedOpname->id)
                    ->with('success', 'Tidak ada selisih fisik. Penyesuaian Stok tidak diperlukan.');
            }
        }

        $postedOpnames = DB::table('stock_opnames as so')
            ->join('warehouses as w', 'w.id', '=', 'so.warehouse_id')
            ->where('so.status', 'posted')
            ->whereNull('so.deleted_at')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('stock_adjustments as sa')
                    ->whereColumn('sa.stock_opname_id', 'so.id')
                    ->whereNull('sa.deleted_at');
            })
            ->select('so.id', 'so.opname_no', 'so.opname_date', 'w.name as warehouse_name')
            ->orderBy('so.id', 'desc')
            ->get();

        return view('inventori.persediaan.penyesuaian.create', compact(
            'businessUnits', 'warehouses', 'autoCode',
            'fromOpnameId', 'selectedOpname', 'varianceItems', 'postedOpnames'
        ));
    }

    /**
     * Simpan Adjustment. Kuantitas dari opname selalu divalidasi terhadap data sumber di database.
     */
    public function store(Request $request)
    {
        $request->validate([
            'business_unit_id' => 'required|exists:business_units,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'adjustment_date' => 'required|date',
            'products' => 'required|array|min:1',
            'products.*' => 'required|integer|distinct|exists:products,id',
            'adjustment_qty' => 'required|array|min:1',
            'adjustment_qty.*' => 'required|numeric',
            'stock_opname_id' => 'nullable|integer|exists:stock_opnames,id',
        ]);

        if (array_keys($request->input('products', [])) !== array_keys($request->input('adjustment_qty', []))) {
            return redirect()->back()->withInput()->with('swal_error', 'Daftar barang dan kuantitas penyesuaian tidak sesuai.');
        }

        DB::beginTransaction();
        try {
            $entityId = (int) (auth()->user()->entity_id ?? 1);
            $businessUnitId = (int) $request->business_unit_id;
            $warehouseId = (int) $request->warehouse_id;
            $opname = null;
            $sourceItems = collect();

            if ($request->filled('stock_opname_id')) {
                $opname = DB::table('stock_opnames')
                    ->where('id', $request->stock_opname_id)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if (!$opname || $opname->status !== 'posted') {
                    throw new \Exception('Stock Opname harus sudah difinalisasi sebelum dibuatkan penyesuaian.');
                }

                if ((int) $opname->entity_id !== $entityId
                    || (int) $opname->business_unit_id !== $businessUnitId
                    || (int) $opname->warehouse_id !== $warehouseId) {
                    throw new \Exception('Unit Bisnis dan gudang Penyesuaian harus sama dengan dokumen Stock Opname.');
                }

                $this->validateAdjustmentMapping($entityId, $businessUnitId, $warehouseId);

                if (DB::table('stock_adjustments')
                    ->where('stock_opname_id', $opname->id)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->exists()) {
                    throw new \Exception('Stock Opname ini sudah memiliki Penyesuaian Stok. Selisih tidak boleh diposting dua kali.');
                }

                $sourceItems = DB::table('stock_opname_items')
                    ->where('stock_opname_id', $opname->id)
                    ->where('difference', '!=', 0)
                    ->lockForUpdate()
                    ->get();

                $expectedProducts = $sourceItems->pluck('product_id')->map(fn ($v) => (int) $v)->sort()->values()->all();
                $submittedProducts = collect($request->input('products'))->map(fn ($v) => (int) $v)->sort()->values()->all();

                if ($sourceItems->isEmpty() || $expectedProducts !== $submittedProducts) {
                    throw new \Exception('Barang harus persis sama dengan item berselisih pada Stock Opname. Muat ulang form dan coba lagi.');
                }
            } else {
                $this->validateAdjustmentMapping($entityId, $businessUnitId, $warehouseId);
            }

            $adjNo = $this->generateAdjustmentCode();
            $adjId = DB::table('stock_adjustments')->insertGetId([
                'entity_id' => $entityId,
                'business_unit_id' => $businessUnitId,
                'warehouse_id' => $warehouseId,
                'user_id' => auth()->id() ?? 1,
                'stock_opname_id' => $opname?->id,
                'adjustment_no' => $adjNo,
                'adjustment_date' => $request->adjustment_date,
                'status' => 'draft',
                'reason' => $request->reason ?? 'Penyesuaian Stok',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($request->input('products') as $idx => $prodId) {
                $sourceItem = $opname ? $sourceItems->firstWhere('product_id', (int) $prodId) : null;
                $adjQty = $sourceItem
                    ? (float) $sourceItem->difference
                    : (float) $request->input('adjustment_qty')[$idx];

                if ($sourceItem && abs($adjQty - (float) $request->input('adjustment_qty')[$idx]) > 0.000001) {
                    throw new \Exception('Kuantitas penyesuaian tidak boleh berbeda dari selisih Stock Opname.');
                }

                if (abs($adjQty) < 0.000001) continue;

                $stock = DB::table('warehouses_stocks')
                    ->where('entity_id', $entityId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('product_id', $prodId)
                    ->lockForUpdate()
                    ->first();

                $systemQty = $sourceItem ? (float) $sourceItem->system_qty : (float) ($stock->qty ?? 0);
                $unitCost = (float) ($stock->avg_cost ?? 0);
                DB::table('stock_adjustment_items')->insert([
                    'stock_adjustment_id' => $adjId,
                    'product_id' => $prodId,
                    'system_qty' => $systemQty,
                    'adjustment_qty' => $adjQty,
                    'final_qty' => $systemQty + $adjQty,
                    'unit_cost' => $unitCost,
                    'total_cost' => abs($adjQty) * $unitCost,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (!DB::table('stock_adjustment_items')->where('stock_adjustment_id', $adjId)->exists()) {
                throw new \Exception('Tidak ada item dengan kuantitas penyesuaian yang valid.');
            }

            DB::commit();

            if ($request->has('post_now')) return $this->executePosting($adjId);

            return redirect()->route('inventori.penyesuaian.index')
                ->with('swal_success', "Draft Penyesuaian Stok [{$adjNo}] berhasil disimpan.");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('swal_error', 'Gagal menyimpan penyesuaian: ' . $e->getMessage());
        }
    }

    /**

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
        DB::beginTransaction();
        try {
            $adj = DB::table('stock_adjustments')
                ->where('id', $id)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if (!$adj || $adj->status === 'posted') {
                throw new \Exception('Dokumen penyesuaian sudah berstatus POSTED atau tidak ditemukan.');
            }

            if ($adj->stock_opname_id) {
                DB::table('stock_opnames')
                    ->where('id', $adj->stock_opname_id)
                    ->lockForUpdate()
                    ->first();

                $otherAdjustment = DB::table('stock_adjustments')
                    ->where('stock_opname_id', $adj->stock_opname_id)
                    ->where('id', '!=', $adj->id)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($otherAdjustment) {
                    throw new \Exception('Opname ini terhubung ke penyesuaian lain. Posting dibatalkan untuk mencegah posting ganda.');
                }
            }

            $items = DB::table('stock_adjustment_items')
                ->where('stock_adjustment_id', $id)
                ->lockForUpdate()
                ->get();

            $totalLossAmount = 0;
            $totalGainAmount = 0;

            foreach ($items as $item) {
                $adjQty = (float) $item->adjustment_qty;
                $absQty = abs($adjQty);
                if ($absQty < 0.0001) continue;

                $stockQuery = DB::table('warehouses_stocks')
                    ->where('entity_id', $adj->entity_id)
                    ->where('warehouse_id', $adj->warehouse_id)
                    ->where('product_id', $item->product_id);

                $stock = (clone $stockQuery)->lockForUpdate()->first();
                if (!$stock) {
                    DB::table('warehouses_stocks')->insertOrIgnore([
                        'entity_id' => $adj->entity_id,
                        'warehouse_id' => $adj->warehouse_id,
                        'product_id' => $item->product_id,
                        'qty' => 0,
                        'avg_cost' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $stock = (clone $stockQuery)->lockForUpdate()->first();
                }

                if (!$stock) {
                    throw new \Exception("Saldo stok produk ID {$item->product_id} tidak dapat dibuat.");
                }

                $unitCost = (float) $stock->avg_cost;
                $totalCost = $absQty * $unitCost;
                $movementType = $adjQty < 0 ? 'ADJUSTMENT_OUT' : 'ADJUSTMENT_IN';

                DB::table('warehouses_stocks')
                    ->where('id', $stock->id)
                    ->update(['qty' => (float) $stock->qty + $adjQty, 'updated_at' => now()]);

                DB::table('stock_adjustment_items')->where('id', $item->id)->update([
                    'unit_cost' => $unitCost,
                    'total_cost' => $totalCost,
                    'updated_at' => now(),
                ]);

                if ($adjQty < 0) {
                    $totalLossAmount += $totalCost;
                } else {
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

            if ($totalLossAmount > 0 && (!$invAccount || !$lossAccount)) {
                throw new \Exception('Akun persediaan atau beban selisih stok belum dipetakan. Posting dibatalkan.');
            }
            if ($totalGainAmount > 0 && (!$invAccount || !$gainAccount)) {
                throw new \Exception('Akun persediaan atau pendapatan selisih stok belum dipetakan. Posting dibatalkan.');
            }

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

    private function validateAdjustmentMapping(int $entityId, int $businessUnitId, int $warehouseId): void
    {
        $businessUnit = DB::table('business_units')
            ->where('id', $businessUnitId)
            ->where('entity_id', $entityId)
            ->where('is_active', 1)
            ->exists();

        $warehouse = DB::table('warehouses')
            ->where('id', $warehouseId)
            ->where('entity_id', $entityId)
            ->where('is_active', 1)
            ->exists();

        $mapped = DB::table('warehouse_business_units')
            ->where('entity_id', $entityId)
            ->where('warehouse_id', $warehouseId)
            ->where('business_unit_id', $businessUnitId)
            ->exists();

        if (!$businessUnit || !$warehouse || !$mapped) {
            throw new \Exception('Unit Bisnis dan gudang harus aktif, satu entitas, serta terhubung melalui pemetaan gudang.');
        }
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