<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BusinessUnit;
use App\Models\Warehouse;
use App\Models\Product;
use App\Exports\StockTransferExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StockTransferController extends Controller
{
    /**
     * Halaman Utama List Mutasi Antar Gudang
     */
    public function index(Request $request)
    {
        $startDate      = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate        = $request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId = $request->query('business_unit_id');
        $fromWhId       = $request->query('from_warehouse_id');
        $toWhId         = $request->query('to_warehouse_id');
        $statusFilter   = $request->query('status');
        $search         = $request->query('search');

        $entityId      = $this->transferEntityId();
        $businessUnits = BusinessUnit::where('entity_id', $entityId)->where('is_active', 1)->orderBy('code')->get();
        $warehouses    = Warehouse::where('entity_id', $entityId)->where('is_active', 1)->orderBy('name')->get();
        $products      = collect();

        // Generate Kode Mutasi Otomatis (MUT-YYYYMMDD-XXX)
        $autoCode = $this->generateTransferCode();

        // Query Data Mutasi
        $query = DB::table('stock_transfers as st')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'st.business_unit_id')
            ->join('warehouses as w_from', 'w_from.id', '=', 'st.from_warehouse_id')
            ->join('warehouses as w_to', 'w_to.id', '=', 'st.to_warehouse_id')
            ->join('users as u_creator', 'u_creator.id', '=', 'st.created_by')
            ->where('st.entity_id', $entityId)
            ->whereDate('st.transfer_date', '>=', $startDate)
            ->whereDate('st.transfer_date', '<=', $endDate)
            ->whereNull('st.deleted_at');

        if ($businessUnitId) $query->where('st.business_unit_id', $businessUnitId);
        if ($fromWhId)       $query->where('st.from_warehouse_id', $fromWhId);
        if ($toWhId)         $query->where('st.to_warehouse_id', $toWhId);
        if ($statusFilter)   $query->where('st.status', $statusFilter);
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('st.transfer_no', 'LIKE', "%{$search}%")
                  ->orWhere('st.memo', 'LIKE', "%{$search}%");
            });
        }

        $transfers = $query->select(
            'st.*',
            'bu.name as business_unit_name',
            'w_from.name as from_warehouse_name',
            'w_to.name as to_warehouse_name',
            'u_creator.name as creator_name'
        )->orderBy('st.created_at', 'desc')->get();

        return view('inventori.persediaan.mutasi.index', compact(
            'businessUnits', 'warehouses', 'products', 'transfers',
            'startDate', 'endDate', 'businessUnitId', 'fromWhId',
            'toWhId', 'statusFilter', 'search', 'autoCode'
        ));
    }

    /**
     * AJAX Endpoint: Get Products with Stock for Source Warehouse
     */
    public function getWarehouseProducts(Request $request, $warehouseId)
    {
        $entityId = $this->transferEntityId();
        $businessUnitId = (int) $request->query('business_unit_id');

        if ($businessUnitId <= 0) {
            return response()->json(['message' => 'Pilih Unit Bisnis terlebih dahulu.'], 422);
        }

        $warehouseMapped = DB::table('warehouses as w')
            ->join('warehouse_business_units as wbu', function ($join) {
                $join->on('wbu.warehouse_id', '=', 'w.id')
                    ->on('wbu.entity_id', '=', 'w.entity_id');
            })
            ->where('w.id', $warehouseId)
            ->where('w.entity_id', $entityId)
            ->where('w.is_active', 1)
            ->where('wbu.business_unit_id', $businessUnitId)
            ->exists();

        if (!$warehouseMapped) {
            return response()->json(['message' => 'Gudang pengirim tidak teralokasi ke Unit Bisnis yang dipilih.'], 422);
        }

        $products = DB::table('products as p')
            ->join('warehouses_stocks as ws', function ($join) use ($warehouseId) {
                $join->on('ws.product_id', '=', 'p.id')
                     ->where('ws.warehouse_id', '=', $warehouseId);
            })
            ->join('warehouse_business_units as wbu', function ($join) {
                $join->on('wbu.warehouse_id', '=', 'ws.warehouse_id')
                     ->on('wbu.entity_id', '=', 'ws.entity_id');
            })
            ->join('product_business_units as pbu', function ($join) {
                $join->on('pbu.product_id', '=', 'p.id')
                     ->on('pbu.business_unit_id', '=', 'wbu.business_unit_id');
            })
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->whereColumn('p.entity_id', 'ws.entity_id')
            ->where('ws.entity_id', $entityId)
            ->where('wbu.business_unit_id', $businessUnitId)
            ->where('p.is_active', 1)
            ->where('p.manage_stock', 1)
            ->where('ws.qty', '>', 0)
            ->select(
                'p.id',
                'p.code',
                'p.name',
                'p.base_unit_id',
                'u.name as unit_name',
                'ws.qty as stock_qty'
            )
            ->orderBy('p.name')
            ->get();

        return response()->json($products);
    }

    /**
     * AJAX Endpoint: Get Detail Items Mutasi
     */
    public function getDetail($id)
    {
        $transfer = DB::table('stock_transfers as st')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'st.business_unit_id')
            ->join('warehouses as w_from', 'w_from.id', '=', 'st.from_warehouse_id')
            ->join('warehouses as w_to', 'w_to.id', '=', 'st.to_warehouse_id')
            ->join('users as u_creator', 'u_creator.id', '=', 'st.created_by')
            ->leftJoin('users as u_sender', 'u_sender.id', '=', 'st.approved_sender_by')
            ->leftJoin('users as u_receiver', 'u_receiver.id', '=', 'st.approved_receiver_by')
            ->where('st.id', $id)
            ->where('st.entity_id', $this->transferEntityId())
            ->whereNull('st.deleted_at')
            ->select(
                'st.*',
                'bu.name as business_unit_name',
                'w_from.name as from_warehouse_name',
                'w_to.name as to_warehouse_name',
                'u_creator.name as creator_name',
                'u_sender.name as sender_approver_name',
                'u_receiver.name as receiver_approver_name'
            )->first();

        if (!$transfer) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        $items = DB::table('stock_transfer_items as sti')
            ->join('products as p', 'p.id', '=', 'sti.product_id')
            ->leftJoin('units as un', 'un.id', '=', 'p.base_unit_id')
            ->where('sti.stock_transfer_id', $id)
            ->select('sti.*', 'p.code as product_code', 'p.name as product_name', 'un.name as unit_name')
            ->get();

        return response()->json([
            'success'  => true,
            'transfer' => $transfer,
            'items'    => $items,
        ]);
    }

    /**
     * Action: Simpan Pengajuan Mutasi Baru
     */
    public function store(Request $request)
    {
        $request->validate([
            'business_unit_id'  => 'required|exists:business_units,id',
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id'   => 'required|exists:warehouses,id|different:from_warehouse_id',
            'transfer_date'     => 'required|date',
            'products'          => 'required|array|min:1',
            'products.*'        => 'exists:products,id',
            'quantities'        => 'required|array|min:1',
            'quantities.*'      => 'numeric|min:0.01',
        ]);

        DB::beginTransaction();
        try {
            if (count($request->products) !== count($request->quantities) || array_keys($request->products) !== array_keys($request->quantities)) {
                throw new \Exception('Daftar item dan kuantitas tidak sesuai.');
            }
            if (count(array_unique(array_map('intval', $request->products))) !== count($request->products)) {
                throw new \Exception('Item yang sama tidak boleh dimasukkan lebih dari satu kali dalam satu mutasi.');
            }

            $entityId = $this->transferEntityId();
            $this->validateTransferMapping(
                $entityId,
                (int) $request->business_unit_id,
                (int) $request->from_warehouse_id,
                (int) $request->to_warehouse_id,
                $request->products
            );

            $transferNo = $this->generateTransferCode();

            $transferId = DB::table('stock_transfers')->insertGetId([
                'entity_id'         => $entityId,
                'business_unit_id'  => $request->business_unit_id,
                'transfer_no'       => $transferNo,
                'from_warehouse_id' => $request->from_warehouse_id,
                'to_warehouse_id'   => $request->to_warehouse_id,
                'transfer_date'     => $request->transfer_date,
                'status'            => 'draft',
                'memo'              => $request->memo,
                'created_by'        => auth()->id() ?? 1,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            foreach ($request->products as $idx => $prodId) {
                $qty = (float) $request->quantities[$idx];
                if ($qty <= 0) continue;

                DB::table('stock_transfer_items')->insert([
                    'stock_transfer_id' => $transferId,
                    'product_id'        => $prodId,
                    'quantity'          => $qty,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }

            DB::commit();
            return redirect()->back()->with('success', "Pengajuan Mutasi Barang [{$transferNo}] berhasil dibuat!");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menyimpan mutasi: ' . $e->getMessage());
        }
    }

    /**
     * Action: Update Data Mutasi (Hanya jika status 'draft')
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'business_unit_id'  => 'required|exists:business_units,id',
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id'   => 'required|exists:warehouses,id|different:from_warehouse_id',
            'transfer_date'     => 'required|date',
            'products'          => 'required|array|min:1',
            'products.*'        => 'required|integer|exists:products,id',
            'quantities'        => 'required|array|min:1',
            'quantities.*'      => 'required|numeric|min:0.01',
        ]);

        DB::beginTransaction();
        try {
            $transfer = DB::table('stock_transfers')
                ->where('id', $id)
                ->where('entity_id', $this->transferEntityId())
                ->lockForUpdate()
                ->first();
            if (!$transfer || $transfer->status !== 'draft' || !empty($transfer->deleted_at)) {
                throw new \Exception('Mutasi tidak dapat diubah karena sudah disetujui/dalam proses.');
            }
            if (count($request->products) !== count($request->quantities) || array_keys($request->products) !== array_keys($request->quantities)) {
                throw new \Exception('Daftar item dan kuantitas tidak sesuai.');
            }
            if (count(array_unique(array_map('intval', $request->products))) !== count($request->products)) {
                throw new \Exception('Item yang sama tidak boleh dimasukkan lebih dari satu kali dalam satu mutasi.');
            }

            $this->validateTransferMapping(
                (int) $transfer->entity_id,
                (int) $request->business_unit_id,
                (int) $request->from_warehouse_id,
                (int) $request->to_warehouse_id,
                $request->products
            );

            DB::table('stock_transfers')->where('id', $id)->update([
                'business_unit_id'  => $request->business_unit_id,
                'from_warehouse_id' => $request->from_warehouse_id,
                'to_warehouse_id'   => $request->to_warehouse_id,
                'transfer_date'     => $request->transfer_date,
                'memo'              => $request->memo,
                'updated_at'        => now(),
            ]);

            DB::table('stock_transfer_items')->where('stock_transfer_id', $id)->delete();

            foreach ($request->products as $idx => $prodId) {
                $qty = (float) $request->quantities[$idx];
                if ($qty <= 0) continue;

                DB::table('stock_transfer_items')->insert([
                    'stock_transfer_id' => $id,
                    'product_id'        => $prodId,
                    'quantity'          => $qty,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }

            DB::commit();
            return redirect()->back()->with('success', "Data Mutasi [{$transfer->transfer_no}] berhasil diperbarui!");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal memperbarui: ' . $e->getMessage());
        }
    }

    /**
     * Approval Step 1: Gudang Pengirim (TRANSFER_OUT)
     */
    public function approveSender($id)
    {
        DB::beginTransaction();
        try {
            $transfer = DB::table('stock_transfers')
                ->where('id', $id)
                ->where('entity_id', $this->transferEntityId())
                ->lockForUpdate()
                ->first();
            if (!$transfer || $transfer->status !== 'draft' || !empty($transfer->deleted_at)) {
                throw new \Exception('Status mutasi tidak valid untuk persetujuan pengirim.');
            }

            $items = DB::table('stock_transfer_items')->where('stock_transfer_id', $id)->lockForUpdate()->get();
            $this->assertTransferItemsValid($items);
            $this->validateTransferMapping(
                (int) $transfer->entity_id,
                (int) $transfer->business_unit_id,
                (int) $transfer->from_warehouse_id,
                (int) $transfer->to_warehouse_id,
                $items->pluck('product_id')->map(fn ($value) => (int) $value)->all()
            );

            foreach ($items as $item) {
                // Ambil data stok & avg_cost dari warehouses_stocks gudang pengirim
                $stock = DB::table('warehouses_stocks')
                    ->where('entity_id', $transfer->entity_id)
                    ->where('warehouse_id', $transfer->from_warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                if (!$stock || $stock->qty < $item->quantity) {
                    $product = DB::table('products')
                        ->where('id', $item->product_id)
                        ->first(['code', 'name']);

                    $productLabel = $product
                        ? "{$product->code} - {$product->name}"
                        : "ID {$item->product_id}";

                    throw new \Exception("Stok {$productLabel} di Gudang Pengirim tidak mencukupi!");
                }

                $unitCost = (float) $stock->avg_cost;

                // 1. Potong saldo stok di warehouses_stocks
                DB::table('warehouses_stocks')
                    ->where('id', $stock->id)
                    ->decrement('qty', $item->quantity);

                // Ambil data produk untuk unit_id
                $product = DB::table('products')->where('id', $item->product_id)->first();

                // 2. Insert ke stock_movements (TRANSFER_OUT: qty NEGATIF)
                DB::table('stock_movements')->insert([
                    'entity_id'         => $transfer->entity_id ?? (auth()->user()->entity_id ?? 1),
                    'business_unit_id'  => $transfer->business_unit_id,
                    'warehouse_id'      => $transfer->from_warehouse_id,
                    'product_id'        => $item->product_id,
                    'unit_id'           => $product->unit_id ?? null,
                    'transaction_qty'   => $item->quantity,
                    'conversion_factor' => 1.000000,
                    'movement_type'     => 'TRANSFER_OUT',
                    'qty'               => -$item->quantity, // Negatif
                    'unit_cost'         => $unitCost,        // Dari warehouses_stocks.avg_cost
                    'reference_type'    => 'transfer',
                    'reference_id'      => $transfer->id,
                    'occurred_at'       => now(),
                    'created_by'        => auth()->id() ?? 1,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }

            DB::table('stock_transfers')->where('id', $id)->update([
                'status'             => 'shipped',
                'approved_sender_by' => auth()->id() ?? 1,
                'approved_sender_at' => now(),
                'updated_at'         => now(),
            ]);

            DB::commit();
            return redirect()->back()->with('success', "Pengiriman Mutasi [{$transfer->transfer_no}] DISETUJUI oleh Gudang Pengirim!");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal Persetujuan Pengirim: ' . $e->getMessage());
        }
    }

    /**
     * Approval Step 2: Gudang Penerima (TRANSFER_IN)
     */
    public function approveReceiver(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $transfer = DB::table('stock_transfers')
                ->where('id', $id)
                ->where('entity_id', $this->transferEntityId())
                ->lockForUpdate()
                ->first();
            if (!$transfer || $transfer->status !== 'shipped' || !empty($transfer->deleted_at)) {
                throw new \Exception('Status mutasi tidak valid untuk persetujuan penerima.');
            }

            // Jika client mengirim gudang pilihan penerima, wajib sama dengan gudang tujuan dokumen.
            $selectedWarehouseId = $request->input('to_warehouse_id', $request->input('receiver_warehouse_id'));
            if ($selectedWarehouseId !== null && (int) $selectedWarehouseId !== (int) $transfer->to_warehouse_id) {
                throw new \Exception('Penerimaan ditolak: gudang yang dipilih berbeda dari gudang tujuan pada dokumen mutasi.');
            }

            $items = DB::table('stock_transfer_items')->where('stock_transfer_id', $id)->lockForUpdate()->get();
            $this->assertTransferItemsValid($items);
            $this->validateTransferMapping(
                (int) $transfer->entity_id,
                (int) $transfer->business_unit_id,
                (int) $transfer->from_warehouse_id,
                (int) $transfer->to_warehouse_id,
                $items->pluck('product_id')->map(fn ($value) => (int) $value)->all()
            );

            foreach ($items as $item) {
                // Ambil unit_cost dari record TRANSFER_OUT sebelumnya agar konsisten
                $outMovements = DB::table('stock_movements')
                    ->where('entity_id', $transfer->entity_id)
                    ->where('business_unit_id', $transfer->business_unit_id)
                    ->where('warehouse_id', $transfer->from_warehouse_id)
                    ->where('reference_type', 'transfer')
                    ->where('reference_id', $transfer->id)
                    ->where('movement_type', 'TRANSFER_OUT')
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->get();

                if ($outMovements->count() !== 1) {
                    throw new \Exception("Penerimaan ditolak: catatan stok keluar untuk item ID {$item->product_id} tidak ditemukan atau tidak unik.");
                }
                $unitCost = (float) $outMovements->first()->unit_cost;

                // Pastikan baris saldo tujuan ada sebelum dikunci.
                // insertOrIgnore menangani dua penerimaan bersamaan saat saldo awal belum ada;
                // unique warehouse/product menjaga hanya satu baris saldo.
                DB::table('warehouses_stocks')->insertOrIgnore([
                    'entity_id'    => $transfer->entity_id ?? (auth()->user()->entity_id ?? 1),
                    'warehouse_id' => $transfer->to_warehouse_id,
                    'product_id'   => $item->product_id,
                    'qty'          => 0,
                    'avg_cost'     => 0,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);

                $destStock = DB::table('warehouses_stocks')
                    ->where('entity_id', $transfer->entity_id)
                    ->where('warehouse_id', $transfer->to_warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                if (!$destStock) {
                    throw new \Exception("Saldo stok gudang tujuan tidak dapat dikunci untuk item ID {$item->product_id}.");
                }

                // 1. Tambah stok & hitung ulang Moving Average Cost (avg_cost) gudang penerima.
                $oldQty     = (float) $destStock->qty;
                $oldAvgCost = (float) $destStock->avg_cost;
                $newQty     = $oldQty + (float) $item->quantity;

                $newAvgCost = ($newQty > 0)
                    ? (($oldQty * $oldAvgCost) + ((float) $item->quantity * $unitCost)) / $newQty
                    : $unitCost;

                DB::table('warehouses_stocks')
                    ->where('id', $destStock->id)
                    ->update([
                        'qty'        => $newQty,
                        'avg_cost'   => $newAvgCost,
                        'updated_at' => now(),
                    ]);

                $product = DB::table('products')->where('id', $item->product_id)->first();

                // 2. Insert ke stock_movements (TRANSFER_IN: qty POSITIF)
                DB::table('stock_movements')->insert([
                    'entity_id'         => $transfer->entity_id ?? (auth()->user()->entity_id ?? 1),
                    'business_unit_id'  => $transfer->business_unit_id,
                    'warehouse_id'      => $transfer->to_warehouse_id,
                    'product_id'        => $item->product_id,
                    'unit_id'           => $product->unit_id ?? null,
                    'transaction_qty'   => $item->quantity,
                    'conversion_factor' => 1.000000,
                    'movement_type'     => 'TRANSFER_IN',
                    'qty'               => $item->quantity, // Positif
                    'unit_cost'         => $unitCost,       // Sama dengan unit_cost pengirim
                    'reference_type'    => 'transfer',
                    'reference_id'      => $transfer->id,
                    'occurred_at'       => now(),
                    'created_by'        => auth()->id() ?? 1,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }

            DB::table('stock_transfers')->where('id', $id)->update([
                'status'               => 'completed',
                'approved_receiver_by' => auth()->id() ?? 1,
                'approved_receiver_at' => now(),
                'updated_at'           => now(),
            ]);

            DB::commit();
            return redirect()->back()->with('success', "Penerimaan Barang [{$transfer->transfer_no}] DISETUJUI! Stok & HPP Gudang Tujuan telah diperbarui.");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal Persetujuan Penerima: ' . $e->getMessage());
        }
    }

    /**
     * Cetak Bukti Transfer / Surat Jalan Mutasi (Window Print View)
     */
    public function printProof($id)
    {
        $transfer = DB::table('stock_transfers as st')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'st.business_unit_id')
            ->join('warehouses as w_from', 'w_from.id', '=', 'st.from_warehouse_id')
            ->join('warehouses as w_to', 'w_to.id', '=', 'st.to_warehouse_id')
            ->join('users as u_creator', 'u_creator.id', '=', 'st.created_by')
            ->leftJoin('users as u_sender', 'u_sender.id', '=', 'st.approved_sender_by')
            ->leftJoin('users as u_receiver', 'u_receiver.id', '=', 'st.approved_receiver_by')
            ->where('st.id', $id)
            ->where('st.entity_id', $this->transferEntityId())
            ->whereNull('st.deleted_at')
            ->select(
                'st.*', 'bu.name as business_unit_name',
                'w_from.name as from_warehouse_name', 'w_from.address as from_warehouse_address',
                'w_to.name as to_warehouse_name', 'w_to.address as to_warehouse_address',
                'u_creator.name as creator_name',
                'u_sender.name as sender_approver_name',
                'u_receiver.name as receiver_approver_name'
            )->firstOrFail();

        $items = DB::table('stock_transfer_items as sti')
            ->join('products as p', 'p.id', '=', 'sti.product_id')
            ->leftJoin('units as un', 'un.id', '=', 'p.base_unit_id')
            ->where('sti.stock_transfer_id', $id)
            ->select('sti.*', 'p.code as product_code', 'p.name as product_name', 'un.name as unit_name')
            ->get();

        return view('inventori.persediaan.mutasi.print-proof', compact('transfer', 'items'));
    }

    /**
     * Export Excel List Mutasi
     */
    public function destroy($id)
    {
        try {
            $transferNo = DB::transaction(function () use ($id) {
                $transfer = DB::table('stock_transfers')
                    ->where('id', $id)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if (!$transfer) {
                    throw new \Exception('Mutasi tidak ditemukan.');
                }

                if ($transfer->status !== 'draft') {
                    throw new \Exception('Mutasi yang sudah dikirim atau diterima tidak dapat dihapus.');
                }

                DB::table('stock_transfers')
                    ->where('id', $id)
                    ->update([
                        'deleted_at' => now(),
                        'updated_at' => now(),
                    ]);

                return $transfer->transfer_no;
            });

            return redirect()->back()->with('success', "Mutasi {$transferNo} berhasil dihapus.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function exportList(Request $request)
    {
        return Excel::download(new StockTransferExport($request), 'Laporan_Mutasi_Gudang_' . date('Ymd_His') . '.xlsx');
    }


    /**
     * Validasi relasi BU -> gudang -> item untuk mutasi satu BU.
     * Transfer antar-BU selalu ditolak.
     */
    private function validateTransferMapping(int $entityId, int $businessUnitId, int $fromWarehouseId, int $toWarehouseId, array $productIds): void
    {
        $businessUnit = DB::table('business_units')
            ->where('id', $businessUnitId)
            ->where('entity_id', $entityId)
            ->where('is_active', 1)
            ->first();

        if (!$businessUnit) {
            throw new \Exception('Unit Bisnis tidak aktif atau bukan milik entitas ini.');
        }

        if ($fromWarehouseId === $toWarehouseId) {
            throw new \Exception('Gudang asal dan tujuan tidak boleh sama.');
        }

        $warehouses = DB::table('warehouses')
            ->whereIn('id', [$fromWarehouseId, $toWarehouseId])
            ->where('entity_id', $entityId)
            ->where('is_active', 1)
            ->get()
            ->keyBy('id');

        if (!$warehouses->has($fromWarehouseId) || !$warehouses->has($toWarehouseId)) {
            throw new \Exception('Gudang asal/tujuan tidak aktif atau bukan milik entitas ini.');
        }

        foreach ([$fromWarehouseId, $toWarehouseId] as $warehouseId) {
            $mapped = DB::table('warehouse_business_units')
                ->where('entity_id', $entityId)
                ->where('warehouse_id', $warehouseId)
                ->where('business_unit_id', $businessUnitId)
                ->exists();

            if (!$mapped) {
                throw new \Exception('Mutasi ditolak: gudang asal dan tujuan harus teralokasi ke Unit Bisnis yang sama.');
            }
        }

        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if (count($productIds) === 0) {
            throw new \Exception('Mutasi harus memiliki minimal satu item.');
        }

        foreach ($productIds as $productId) {
            $product = DB::table('products')
                ->where('id', $productId)
                ->where('entity_id', $entityId)
                ->where('is_active', 1)
                ->where('manage_stock', 1)
                ->first();

            if (!$product) {
                throw new \Exception("Item ID {$productId} tidak aktif, bukan milik entitas ini, atau tidak mengelola stok.");
            }

            $allocated = DB::table('product_business_units')
                ->where('product_id', $productId)
                ->where('business_unit_id', $businessUnitId)
                ->exists();

            if (!$allocated) {
                throw new \Exception("Mutasi ditolak: item {$product->code} - {$product->name} belum dialokasikan ke Unit Bisnis ini.");
            }
        }
    }

    /**
     * Pastikan item mutasi valid sebelum stok bergerak, termasuk data lama
     * yang mungkin dibuat sebelum validasi form diperketat.
     */
    private function assertTransferItemsValid($items): void
    {
        if ($items->isEmpty()) {
            throw new \Exception('Mutasi harus memiliki minimal satu item.');
        }

        $productIds = $items->pluck('product_id')->map(fn ($value) => (int) $value)->all();
        if (count(array_unique($productIds)) !== count($productIds)) {
            throw new \Exception('Mutasi ditolak: item yang sama tercatat lebih dari satu kali.');
        }

        foreach ($items as $item) {
            if (!is_numeric($item->quantity) || (float) $item->quantity <= 0) {
                throw new \Exception('Mutasi ditolak: kuantitas item harus lebih besar dari nol.');
            }
        }
    }

    private function transferEntityId(): int
    {
        return (int) (auth()->user()->entity_id ?? 1);
    }

    /**
     * Helper Generator Kode MUT-YYYYMMDD-XXX
     */
    private function generateTransferCode()
    {
        $dateStr = date('Ymd');
        $last    = DB::table('stock_transfers')
            ->where('transfer_no', 'LIKE', "MUT-{$dateStr}-%")
            ->orderBy('id', 'desc')
            ->first();

        if ($last) {
            $lastSeq = (int) substr($last->transfer_no, -3);
            $nextSeq = $lastSeq + 1;
        } else {
            $nextSeq = 1;
        }

        return 'MUT-' . $dateStr . '-' . str_pad($nextSeq, 3, '0', STR_PAD_LEFT);
    }
}