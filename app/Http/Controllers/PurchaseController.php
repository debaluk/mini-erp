<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\BusinessUnit;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\Entity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    /**
     * 1. Halaman Utama Index PO
     */
    public function index()
    {
        $entityId = auth()->user()->entity_id ?? 1;

        $businessUnits = BusinessUnit::where('entity_id', $entityId)->get();
        $suppliers = Supplier::where('entity_id', $entityId)->where('is_active', 1)->get();
        $warehouses = Warehouse::where('entity_id', $entityId)->get();
        $products = Product::where('entity_id', $entityId)->where('is_active', 1)->get();

        return view('purchase_orders.index', compact('businessUnits', 'suppliers', 'warehouses', 'products'));
    }

    /**
     * 2. DataTables Server-Side AJAX Handler
     */
    public function data(Request $request)
    {
        $entityId = auth()->user()->entity_id ?? 1;

        $query = DB::table('purchase_orders as po')
            ->leftJoin('business_units as bu', 'po.business_unit_id', '=', 'bu.id')
            ->leftJoin('suppliers as sup', 'po.supplier_id', '=', 'sup.id')
            ->leftJoin('warehouses as wh', 'po.warehouse_id', '=', 'wh.id')
            ->select([
                'po.id',
                'po.po_no',
                'po.po_date',
                'po.status',
                'po.total_amount',
                'bu.name as business_unit_name',
                'sup.name as supplier_name',
                'wh.name as warehouse_name',
            ])
            ->where('po.entity_id', $entityId);

        // Filter Tanggal
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('po.po_date', [$request->start_date . ' 00:00:00', $request->end_date . ' 23:59:59']);
        }

        // Filter Unit Bisnis
        if ($request->filled('business_unit_id')) {
            $query->where('po.business_unit_id', $request->business_unit_id);
        }

        // Filter Supplier
        if ($request->filled('supplier_id')) {
            $query->where('po.supplier_id', $request->supplier_id);
        }

        // Filter Status PO
        if ($request->filled('status')) {
            $query->where('po.status', $request->status);
        }

        $totalData = $query->count();

        // Search Keyword
        if ($request->has('search') && !empty($request->input('search.value'))) {
            $search = $request->input('search.value');
            $query->where(function ($q) use ($search) {
                $q->where('po.po_no', 'like', "%{$search}%")
                  ->orWhere('sup.name', 'like', "%{$search}%")
                  ->orWhere('bu.name', 'like', "%{$search}%")
                  ->orWhere('wh.name', 'like', "%{$search}%");
            });
        }

        $totalFiltered = $query->count();

        $limit = $request->input('length', 10);
        $start = $request->input('start', 0);
        $orderColumnIndex = $request->input('order.0.column', 0);
        $orderDir = $request->input('order.0.dir', 'desc');

        $columns = [
            0 => 'po.po_no',
            1 => 'po.po_date',
            2 => 'bu.name',
            3 => 'sup.name',
            4 => 'wh.name',
            5 => 'po.total_amount',
            6 => 'po.status',
        ];

        $orderColumn = $columns[$orderColumnIndex] ?? 'po.po_no';

        $pos = $query->orderBy($orderColumn, $orderDir)
            ->offset($start)
            ->limit($limit)
            ->get();

        $data = [];
        foreach ($pos as $po) {
            $statusBadge = match ($po->status) {
                'approved'         => '<span class="badge bg-success">APPROVED</span>',
                'pending_approval' => '<span class="badge bg-warning text-dark">PENDING APPROVAL</span>',
                'rejected'         => '<span class="badge bg-danger">REJECTED</span>',
                'completed'        => '<span class="badge bg-info">COMPLETED</span>',
                default            => '<span class="badge bg-secondary">DRAFT</span>',
            };

            $actions = '<div class="btn-group btn-group-sm">
                <a href="' . route('purchase_orders.print', $po->id) . '" target="_blank" class="btn btn-outline-primary" title="Cetak PO ini">🖨️ Cetak PO</a>';

            if ($po->status === 'pending_approval') {
                $actions .= '
                <button type="button" class="btn btn-dark btnApprovePO" 
                    data-id="' . $po->id . '" 
                    data-pono="' . $po->po_no . '" 
                    data-supplier="' . htmlspecialchars($po->supplier_name) . '" 
                    data-total="Rp ' . number_format($po->total_amount, 0, ',', '.') . '">
                    ⚡ Approve
                </button>';
            }

            $actions .= '</div>';

            $data[] = [
                'po_no'              => $po->po_no,
                'po_date'                => date('d/m/Y', strtotime($po->po_date)),
                'business_unit_name'     => $po->business_unit_name ?? '-',
                'supplier_name'          => $po->supplier_name ?? '-',
                'warehouse_name'         => $po->warehouse_name ?? '-',
                'total_amount_formatted' => 'Rp ' . number_format($po->total_amount, 2, ',', '.'),
                'status_badge'           => $statusBadge,
                'action'                 => $actions,
            ];
        }

        return response()->json([
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data"            => $data
        ]);
    }

    /**
     * 3. Simpan Transaksi PO Baru
     */
    public function store(Request $request)
    {
        $request->validate([
            'business_unit_id' => 'required|exists:business_units,id',
            'supplier_id'      => 'required|exists:suppliers,id',
            'warehouse_id'     => 'required|exists:warehouses,id',
            'po_date'          => 'required|date',
            'items'            => 'required|array|min:1',
        ]);

        DB::beginTransaction();
        try {
            $entityId = auth()->user()->entity_id ?? 1;
            $poNumber = 'PO/' . date('Ym') . '/' . str_pad(PurchaseOrder::count() + 1, 4, '0', STR_PAD_LEFT);

            $po = PurchaseOrder::create([
                'entity_id'        => $entityId,
                'business_unit_id' => $request->business_unit_id,
                'supplier_id'      => $request->supplier_id,
                'warehouse_id'     => $request->warehouse_id,
                'po_no'        => $poNumber,
                'po_date'          => $request->po_date,
                'status'           => 'pending_approval',
                'total_amount'     => $request->total_amount ?? 0,
                'memo'             => $request->memo,
                'created_by'       => auth()->id() ?? 1,
            ]);

            foreach ($request->items as $item) {
                $qty = $item['quantity'];
                $price = $item['unit_price'];
                $disc = $item['discount_amount'] ?? 0;
                $subtotal = max(0, ($qty * $price) - $disc);

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id'        => $item['product_id'],
                    'quantity'          => $qty,
                    'unit_price'        => $price,
                    'discount_amount'   => $disc,
                    'subtotal'          => $subtotal,
                ]);
            }

            DB::commit();
            return redirect()->route('purchase_orders.index')->with('success', "Purchase Order {$poNumber} berhasil disimpan!");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * 4. Cetak Single PO (Cetak per PO)
     */
    public function print($id)
    {
        $po = PurchaseOrder::with(['entity', 'businessUnit', 'supplier', 'warehouse', 'items.product', 'creator', 'approver'])
            ->findOrFail($id);

        $terbilang = $this->terbilang($po->total_amount) . ' Rupiah';

        return view('purchase_orders.print', compact('po', 'terbilang'));
    }

    /**
     * 5. Approval Status PO (Approve / Reject)
     */
    public function approval(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);

        $po = PurchaseOrder::findOrFail($id);

        $po->update([
            'status'              => $request->status,
            'approved_by'         => auth()->id() ?? 1,
            'approved_at'         => now(),
            'cancellation_reason' => $request->status === 'rejected' ? $request->cancellation_reason : null,
        ]);

        return redirect()->route('purchase_orders.index')->with('success', "Status PO {$po->po_no} berhasil diperbarui!");
    }

    /**
     * 6. Export Excel dengan Kop Entitas, Judul, Periode, & Tanggal Cetak
     */
    public function exportExcel(Request $request)
    {
        $entityId = auth()->user()->entity_id ?? 1;
        $entity = Entity::find($entityId);
        $entityName = $entity->name ?? 'PERUSAHAAN / ENTITAS ERP';

        // Filter Periode Info
        $startDate = $request->filled('start_date') ? date('d/m/Y', strtotime($request->start_date)) : 'Awal';
        $endDate = $request->filled('end_date') ? date('d/m/Y', strtotime($request->end_date)) : 'Akhir';
        $periodeText = "{$startDate} s/d {$endDate}";
        $cetakTanggal = date('d/m/Y H:i:s');

        // Fetch Filtered Data
        $query = DB::table('purchase_orders as po')
            ->leftJoin('business_units as bu', 'po.business_unit_id', '=', 'bu.id')
            ->leftJoin('suppliers as sup', 'po.supplier_id', '=', 'sup.id')
            ->leftJoin('warehouses as wh', 'po.warehouse_id', '=', 'wh.id')
            ->select([
                'po.po_no',
                'po.po_date',
                'bu.name as business_unit',
                'sup.name as supplier',
                'wh.name as warehouse',
                'po.total_amount',
                'po.status',
                'po.memo'
            ])
            ->where('po.entity_id', $entityId);

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('po.po_date', [$request->start_date . ' 00:00:00', $request->end_date . ' 23:59:59']);
        }
        if ($request->filled('business_unit_id')) $query->where('po.business_unit_id', $request->business_unit_id);
        if ($request->filled('supplier_id')) $query->where('po.supplier_id', $request->supplier_id);
        if ($request->filled('status')) $query->where('po.status', $request->status);

        $pos = $query->orderBy('po.po_date', 'desc')->get();

        $filename = "Daftar_Purchase_Order_" . date('Ymd_His') . ".xls";

        // Generate HTML-based Excel with Header Kop, Title, Period & Print Date
        return response()->stream(function() use ($entityName, $periodeText, $cetakTanggal, $pos) {
            echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
            echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
            echo '<style>
                body { font-family: Arial, sans-serif; font-size: 11pt; }
                .kop { font-size: 14pt; font-weight: bold; text-transform: uppercase; color: #1a365d; }
                .title { font-size: 13pt; font-weight: bold; text-transform: uppercase; margin-top: 5px; }
                .meta { font-size: 10pt; color: #4a5568; }
                table { border-collapse: collapse; width: 100%; margin-top: 15px; }
                th { background-color: #2b6cb0; color: #ffffff; font-weight: bold; border: 1px solid #1a365d; padding: 8px; text-align: center; }
                td { border: 1px solid #cbd5e0; padding: 6px; font-size: 10pt; }
                .text-center { text-align: center; }
                .text-right { text-align: right; }
                .total-row { font-weight: bold; background-color: #edf2f7; }
            </style></head><body>';

            echo '<div class="kop">' . htmlspecialchars($entityName) . '</div>';
            echo '<div class="title">DAFTAR PURCHASE ORDER</div>';
            echo '<div class="meta"><strong>Periode:</strong> ' . htmlspecialchars($periodeText) . '</div>';
            echo '<div class="meta"><strong>Tanggal Cetak:</strong> ' . htmlspecialchars($cetakTanggal) . '</div>';
            echo '<br>';

            echo '<table>';
            echo '<thead>
                <tr>
                    <th>No</th>
                    <th>No. PO</th>
                    <th>Tanggal PO</th>
                    <th>Unit Bisnis</th>
                    <th>Supplier</th>
                    <th>Gudang Tujuan</th>
                    <th>Total Nominal (Rp)</th>
                    <th>Status</th>
                    <th>Catatan / Memo</th>
                </tr>
            </thead><tbody>';

            $totalNominal = 0;
            foreach ($pos as $idx => $po) {
                $totalNominal += $po->total_amount;
                echo '<tr>';
                echo '<td class="text-center">' . ($idx + 1) . '</td>';
                echo '<td>' . htmlspecialchars($po->po_no) . '</td>';
                echo '<td class="text-center">' . date('d/m/Y', strtotime($po->po_date)) . '</td>';
                echo '<td>' . htmlspecialchars($po->business_unit ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($po->supplier ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($po->warehouse ?? '-') . '</td>';
                echo '<td class="text-right">' . number_format($po->total_amount, 2, ',', '.') . '</td>';
                echo '<td class="text-center">' . strtoupper($po->status) . '</td>';
                echo '<td>' . htmlspecialchars($po->memo ?? '-') . '</td>';
                echo '</tr>';
            }

            echo '<tr class="total-row">';
            echo '<td colspan="6" class="text-right">GRAND TOTAL:</td>';
            echo '<td class="text-right">Rp ' . number_format($totalNominal, 2, ',', '.') . '</td>';
            echo '<td colspan="2"></td>';
            echo '</tr>';

            echo '</tbody></table></body></html>';
        }, 200, [
            "Content-Type"        => "application/vnd.ms-excel; charset=UTF-8",
            "Content-Disposition" => "attachment; filename={$filename}",
            "Pragma"              => "no-cache",
            "Expires"             => "0"
        ]);
    }

    /**
     * Konversi angka ke Terbilang Rupiah
     */
    private function terbilang($angka)
    {
        $baca = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
        if ($angka < 12) return ' ' . $baca[$angka];
        if ($angka < 20) return $this->terbilang($angka - 10) . ' Belas';
        if ($angka < 100) return $this->terbilang($angka / 10) . ' Puluh' . $this->terbilang($angka % 10);
        if ($angka < 1000) return $this->terbilang($angka / 100) . ' Ratus' . $this->terbilang($angka % 100);
        if ($angka < 1000000) return $this->terbilang($angka / 1000) . ' Ribu' . $this->terbilang($angka % 1000);
        if ($angka < 1000000000) return $this->terbilang($angka / 1000000) . ' Juta' . $this->terbilang($angka % 1000000);
        return trim($this->terbilang($angka / 1000000000) . ' Milyar' . $this->terbilang(fmod($angka, 1000000000)));
    }
}
