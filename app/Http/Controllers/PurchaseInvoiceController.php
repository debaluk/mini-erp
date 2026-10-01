<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BusinessUnit;
use App\Models\Warehouse;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Exports\PurchaseInvoiceExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PurchaseInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $startDate      = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate        = $request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        
        $businessUnits = BusinessUnit::where('is_active', 1)->orderBy('code')->get();
        $warehouses    = Warehouse::where('is_active', 1)->orderBy('name')->get();
        $suppliers     = Supplier::where('is_active', 1)->orderBy('name')->get();

        return view('inventori.pembelian.faktur.index', compact(
            'businessUnits', 'warehouses', 'suppliers', 'startDate', 'endDate'
        ));
    }

    /**
     * AJAX DataTables Server-Side / JSON Data
     */
    public function data(Request $request)
    {
        $startDate      = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate        = $request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId = $request->query('business_unit_id');
        $supplierId     = $request->query('supplier_id');
        $warehouseId    = $request->query('warehouse_id');
        $paymentType    = $request->query('payment_type');
        $statusFilter   = $request->query('status');

        $query = DB::table('purchases as p')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'p.business_unit_id')
            ->join('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->join('warehouses as w', 'w.id', '=', 'p.warehouse_id')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'p.purchase_order_id')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->whereNull('p.deleted_at')
            ->whereDate('p.purchase_date', '>=', $startDate)
            ->whereDate('p.purchase_date', '<=', $endDate);

        if ($businessUnitId) $query->where('p.business_unit_id', $businessUnitId);
        if ($supplierId)     $query->where('p.supplier_id', $supplierId);
        if ($warehouseId)    $query->where('p.warehouse_id', $warehouseId);
        if ($paymentType)    $query->where('p.payment_type', $paymentType);
        if ($statusFilter)   $query->where('p.status', $statusFilter);

        $data = $query->select(
            'p.*',
            'bu.name as business_unit_name',
            's.name as supplier_name',
            'w.name as warehouse_name',
            'po.po_no',
            'u.name as creator_name'
        )->orderBy('p.created_at', 'desc')->get();

        foreach ($data as $item) {
            $item->formatted_date = Carbon::parse($item->purchase_date)->format('d/m/Y');
            $item->formatted_due  = $item->due_date ? Carbon::parse($item->due_date)->format('d/m/Y') : '-';
            $item->formatted_grand = 'Rp ' . number_format($item->grand_total, 0, ',', '.');
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Form Buat Faktur Pembelian (Full-Page)
     */
    public function create(Request $request)
    {
        $businessUnits = BusinessUnit::where('is_active', 1)->orderBy('code')->get();
        $warehouses    = Warehouse::where('is_active', 1)->orderBy('name')->get();
        $suppliers     = Supplier::where('is_active', 1)->orderBy('name')->get();
        $products      = Product::where('is_active', 1)->orderBy('name')->get();
        $autoInvNo     = $this->generateInvoiceCode();

        $fromPoId      = $request->query('from_po');
        $selectedPo    = null;
        $poItems       = collect();

        if ($fromPoId) {
            $selectedPo = DB::table('purchase_orders')->where('id', $fromPoId)->first();
            if ($selectedPo) {
                $poItems = DB::table('purchase_order_items as poi')
                    ->join('products as p', 'p.id', '=', 'poi.product_id')
                    ->leftJoin('units as u', 'u.id', '=', 'poi.unit_id')
                    ->where('poi.purchase_order_id', $fromPoId)
                    ->select('poi.*', 'p.code as product_code', 'p.name as product_name', 'u.name as unit_name')
                    ->get();
            }
        }

        $approvedPos = DB::table('purchase_orders as po')
            ->join('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->whereIn('po.status', ['approved', 'partial'])
            ->whereNull('po.deleted_at')
            ->select('po.id', 'po.po_no', 'po.po_date', 's.name as supplier_name')
            ->orderBy('po.id', 'desc')
            ->get();

        return view('inventori.pembelian.faktur.create', compact(
            'businessUnits', 'warehouses', 'suppliers', 'products', 'autoInvNo',
            'fromPoId', 'selectedPo', 'poItems', 'approvedPos'
        ));
    }

    /**
     * Pull Item PO via AJAX
     */
    public function getPoItems($poId)
    {
        $po = DB::table('purchase_orders')->where('id', $poId)->first();
        if (!$po) return response()->json(['success' => false, 'message' => 'PO tidak ditemukan'], 404);

        $items = DB::table('purchase_order_items as poi')
            ->join('products as p', 'p.id', '=', 'poi.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'poi.unit_id')
            ->where('poi.purchase_order_id', $poId)
            ->select('poi.*', 'p.code as product_code', 'p.name as product_name', 'u.name as unit_name')
            ->get();

        return response()->json(['success' => true, 'po' => $po, 'items' => $items]);
    }

    /**
     * Simpan Pembelian (Draft / Post Direct)
     */
    public function store(Request $request)
    {
        $request->validate([
            'business_unit_id' => 'required|exists:business_units,id',
            'supplier_id'      => 'required|exists:suppliers,id',
            'warehouse_id'     => 'required|exists:warehouses,id',
            'purchase_date'    => 'required|date',
            'payment_type'     => 'required|in:cash,credit',
            'products'         => 'required|array|min:1',
            'qty'              => 'required|array|min:1',
            'unit_price'       => 'required|array|min:1',
        ]);

        DB::beginTransaction();
        try {
            $invNo = $this->generateInvoiceCode();
            $goodsReceived = $request->has('goods_received') ? 1 : 0;
            $dueDate = ($request->payment_type === 'credit') ? ($request->due_date ?? now()->addDays(30)->format('Y-m-d')) : null;

            $purchaseId = DB::table('purchases')->insertGetId([
                'entity_id'           => auth()->user()->entity_id ?? 1,
                'business_unit_id'    => $request->business_unit_id,
                'supplier_id'         => $request->supplier_id,
                'warehouse_id'        => $request->warehouse_id,
                'purchase_order_id'   => $request->purchase_order_id ?? null,
                'user_id'             => auth()->id() ?? 1,
                'invoice_no'          => $invNo,
                'supplier_invoice_no' => $request->supplier_invoice_no ?? $invNo,
                'purchase_date'       => $request->purchase_date,
                'due_date'            => $dueDate,
                'payment_type'        => $request->payment_type,
                'goods_received'      => $goodsReceived,
                'status'              => 'draft',
                'memo'                => $request->memo,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            $subtotal = 0;
            foreach ($request->products as $idx => $prodId) {
                $qty      = (float) $request->qty[$idx];
                $price    = (float) $request->unit_price[$idx];
                $discount = (float) ($request->discount[$idx] ?? 0);
                $total    = ($qty * $price) - $discount;
                $subtotal += $total;

                $product = Product::find($prodId);

                DB::table('purchase_items')->insert([
                    'purchase_id'       => $purchaseId,
                    'product_id'        => $prodId,
                    'unit_id'           => $product->base_unit_id ?? null,
                    'qty'               => $qty,
                    'conversion_factor' => 1.000000,
                    'base_qty'          => $qty,
                    'unit_price'        => $price,
                    'discount'          => $discount,
                    'total'             => $total,
                ]);
            }

            $taxAmount = (float) ($request->tax_amount ?? 0);
            $grandTotal = $subtotal + $taxAmount;

            DB::table('purchases')->where('id', $purchaseId)->update([
                'subtotal'    => $subtotal,
                'tax_amount'  => $taxAmount,
                'grand_total' => $grandTotal,
            ]);

            DB::commit();

            if ($request->has('post_now')) {
                return $this->executePosting($purchaseId);
            }

            return redirect()->route('inventori.pembelian.index')
                ->with('swal_success', "Draft Faktur Pembelian [{$invNo}] berhasil disimpan!");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('swal_error', 'Gagal menyimpan pembelian: ' . $e->getMessage());
        }
    }

    /**
     * Form Edit Draft Pembelian
     */
    public function edit($id)
    {
        $p = DB::table('purchases')->where('id', $id)->whereNull('deleted_at')->firstOrFail();

        if ($p->status === 'posted') {
            return redirect()->route('inventori.pembelian.show', $id)
                ->with('swal_error', 'Transaksi POSTED bersifat permanen dan tidak dapat diedit!');
        }

        $businessUnits = BusinessUnit::where('is_active', 1)->orderBy('code')->get();
        $warehouses    = Warehouse::where('is_active', 1)->orderBy('name')->get();
        $suppliers     = Supplier::where('is_active', 1)->orderBy('name')->get();
        $products      = Product::where('is_active', 1)->orderBy('name')->get();

        $items = DB::table('purchase_items as pi')
            ->join('products as pr', 'pr.id', '=', 'pi.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'pi.unit_id')
            ->where('pi.purchase_id', $id)
            ->select('pi.*', 'pr.code as product_code', 'pr.name as product_name', 'u.name as unit_name')
            ->get();

        return view('inventori.pembelian.faktur.edit', compact('p', 'businessUnits', 'warehouses', 'suppliers', 'products', 'items'));
    }

    /**
     * Action Post Faktur Pembelian
     */
    public function post($id)
    {
        return $this->executePosting($id);
    }

    private function executePosting($id)
    {
        $p = DB::table('purchases')->where('id', $id)->whereNull('deleted_at')->first();
        if (!$p || $p->status === 'posted') {
            return redirect()->back()->with('swal_error', 'Faktur sudah berstatus POSTED / tidak ditemukan.');
        }

        DB::beginTransaction();
        try {
            $items = DB::table('purchase_items')->where('purchase_id', $id)->get();

            // LOGIKA ATURAN 7: JIKA BARANG DITERIMA DICENTANG -> STOK BERTAMBAH & HPP RECALCULATED
            if ((int) $p->goods_received === 1) {
                foreach ($items as $item) {
                    $qty      = (float) $item->qty;
                    $price    = (float) $item->unit_price;

                    $stock = DB::table('warehouses_stocks')
                        ->where('warehouse_id', $p->warehouse_id)
                        ->where('product_id', $item->product_id)
                        ->first();

                    $oldQty  = $stock ? (float) $stock->qty : 0;
                    $oldCost = $stock ? (float) $stock->avg_cost : 0;

                    // Moving Average HPP Recalculation
                    $newQty  = $oldQty + $qty;
                    $newCost = ($newQty > 0) ? (($oldQty * $oldCost) + ($qty * $price)) / $newQty : $price;

                    // Update / Insert Stok Gudang
                    if ($stock) {
                        DB::table('warehouses_stocks')
                            ->where('id', $stock->id)
                            ->update([
                                'qty'        => $newQty,
                                'avg_cost'   => $newCost,
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::table('warehouses_stocks')->insert([
                            'warehouse_id' => $p->warehouse_id,
                            'product_id'   => $item->product_id,
                            'qty'          => $newQty,
                            'avg_cost'     => $newCost,
                            'created_at'   => now(),
                            'updated_at'   => now(),
                        ]);
                    }

                    // Mutasi Stok
                    DB::table('stock_movements')->insert([
                        'entity_id'         => $p->entity_id,
                        'business_unit_id'  => $p->business_unit_id,
                        'warehouse_id'      => $p->warehouse_id,
                        'product_id'        => $item->product_id,
                        'unit_id'           => $item->unit_id,
                        'transaction_qty'   => $qty,
                        'conversion_factor' => 1.000000,
                        'movement_type'     => 'PURCHASE_IN',
                        'qty'               => $qty,
                        'unit_cost'         => $price,
                        'reference_type'    => 'purchase',
                        'reference_id'      => $p->id,
                        'occurred_at'       => now(),
                        'created_by'        => auth()->id() ?? 1,
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ]);
                }
            }

            // LOGIKA ATURAN 8: CARA BAYAR TUNAI VS KREDIT (HUTANG USAHA)
            $invAccount    = ChartOfAccount::where('code', '1000401')->first() ?? ChartOfAccount::where('type', 'asset')->where('name', 'LIKE', '%Persediaan%')->first();
            $unbilledAccount = ChartOfAccount::where('code', '2000105')->first() ?? ChartOfAccount::where('type', 'liability')->where('name', 'LIKE', '%Hutang Belum Ditagih%')->first();
            $payableAccount = ChartOfAccount::where('code', '2000101')->first() ?? ChartOfAccount::where('type', 'liability')->where('name', 'LIKE', '%Hutang Usaha%')->first();
            $cashAccount    = ChartOfAccount::where('code', '1000101')->first() ?? ChartOfAccount::where('type', 'asset')->where('name', 'LIKE', '%Kas%')->first();

            $debitAccount  = ((int) $p->goods_received === 1) ? $invAccount->id : $unbilledAccount->id;
            $creditAccount = ($p->payment_type === 'cash') ? $cashAccount->id : $payableAccount->id;

            // Auto-Jurnal GL
            $journal = Journal::create([
                'entity_id'        => $p->entity_id,
                'business_unit_id' => $p->business_unit_id,
                'journal_no'       => 'JRN-PUR-' . date('YmdHis'),
                'journal_date'     => $p->purchase_date,
                'source_type'      => 'purchase_invoice',
                'source_id'        => $p->id,
                'description'      => "Faktur Pembelian #{$p->invoice_no} ({$p->payment_type})",
                'status'           => 'posted',
            ]);

            JournalEntry::create(['journal_id' => $journal->id, 'account_id' => $debitAccount, 'debit' => $p->grand_total, 'credit' => 0]);
            JournalEntry::create(['journal_id' => $journal->id, 'account_id' => $creditAccount, 'debit' => 0, 'credit' => $p->grand_total]);

            // Update Status -> POSTED
            DB::table('purchases')->where('id', $id)->update(['status' => 'posted', 'updated_at' => now()]);

            DB::commit();
            return redirect()->route('inventori.pembelian.show', $id)
                ->with('swal_success', "Faktur Pembelian [{$p->invoice_no}] BERHASIL DIPOSTING! Stok & Jurnal Keuangan telah diperbarui.");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('swal_error', 'Gagal memproses posting: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
        $p = DB::table('purchases as p')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'p.business_unit_id')
            ->join('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->join('warehouses as w', 'w.id', '=', 'p.warehouse_id')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'p.purchase_order_id')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->where('p.id', $id)
            ->whereNull('p.deleted_at')
            ->select('p.*', 'bu.name as business_unit_name', 's.name as supplier_name', 's.address as supplier_address', 'w.name as warehouse_name', 'po.po_no', 'u.name as creator_name')
            ->firstOrFail();

        $items = DB::table('purchase_items as pi')
            ->join('products as pr', 'pr.id', '=', 'pi.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'pi.unit_id')
            ->where('pi.purchase_id', $id)
            ->select('pi.*', 'pr.code as product_code', 'pr.name as product_name', 'u.name as unit_name')
            ->get();

        $journals = DB::table('journals')->where('source_id', $id)->where('source_type', 'purchase_invoice')->get();
        foreach ($journals as $j) {
            $j->entries = DB::table('journal_entries as je')
                ->join('chart_of_accounts as coa', 'coa.id', '=', 'je.account_id')
                ->where('je.journal_id', $j->id)
                ->select('je.*', 'coa.code as account_code', 'coa.name as account_name')
                ->get();
        }

        return view('inventori.pembelian.faktur.show', compact('p', 'items', 'journals'));
    }

    public function destroy($id)
    {
        $p = DB::table('purchases')->where('id', $id)->first();
        if ($p && $p->status === 'posted') {
            return redirect()->back()->with('swal_error', 'Faktur POSTED bersifat permanen dan tidak dapat dihapus!');
        }

        DB::table('purchases')->where('id', $id)->update(['deleted_at' => now()]);
        return redirect()->route('inventori.pembelian.index')->with('swal_success', 'Draft Faktur Pembelian berhasil dihapus.');
    }

    public function printInvoice($id)
    {
        $p = DB::table('purchases as p')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'p.business_unit_id')
            ->join('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->join('warehouses as w', 'w.id', '=', 'p.warehouse_id')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->where('p.id', $id)
            ->select('p.*', 'bu.name as business_unit_name', 's.name as supplier_name', 's.address as supplier_address', 's.phone as supplier_phone', 'w.name as warehouse_name', 'u.name as creator_name')
            ->firstOrFail();

        $items = DB::table('purchase_items as pi')
            ->join('products as pr', 'pr.id', '=', 'pi.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'pi.unit_id')
            ->where('pi.purchase_id', $id)
            ->select('pi.*', 'pr.code as product_code', 'pr.name as product_name', 'u.name as unit_name')
            ->get();

        return view('inventori.pembelian.faktur.print-invoice', compact('p', 'items'));
    }

    public function printList(Request $request)
    {
        $startDate      = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate        = $request->query('end_date', now()->endOfMonth()->format('Y-m-d'));

        $purchases = DB::table('purchases as p')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'p.business_unit_id')
            ->join('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->join('warehouses as w', 'w.id', '=', 'p.warehouse_id')
            ->whereNull('p.deleted_at')
            ->whereDate('p.purchase_date', '>=', $startDate)
            ->whereDate('p.purchase_date', '<=', $endDate)
            ->select('p.*', 'bu.name as business_unit_name', 's.name as supplier_name', 'w.name as warehouse_name')
            ->orderBy('p.created_at', 'desc')->get();

        return view('inventori.pembelian.faktur.print-list', compact('purchases', 'startDate', 'endDate'));
    }

    public function exportExcel(Request $request)
    {
        return Excel::download(new PurchaseInvoiceExport($request), 'Laporan_Rekap_Pembelian_' . date('Ymd_His') . '.xlsx');
    }

    private function generateInvoiceCode()
    {
        $dateStr = date('Ymd');
        $last    = DB::table('purchases')
            ->where('invoice_no', 'LIKE', "INV-{$dateStr}-%")
            ->orderBy('id', 'desc')
            ->first();

        $nextSeq = $last ? ((int) substr($last->invoice_no, -3)) + 1 : 1;
        return 'INV-' . $dateStr . '-' . str_pad($nextSeq, 3, '0', STR_PAD_LEFT);
    }
}