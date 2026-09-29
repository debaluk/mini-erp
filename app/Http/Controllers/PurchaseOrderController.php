<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    private function entityId(): int
    {
        return (int) (DB::table('entities')->value('id') ?? 1);
    }

    public function index(Request $request)
    {
        $entity = $this->entityId();

        $suppliers = DB::table('suppliers')
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        $businessUnits = DB::table('business_units')
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $warehouses = DB::table('warehouses')
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        $products = DB::table('products as p')
            ->where('p.entity_id', $entity)
            ->where('p.is_active', 1)
            ->orderBy('p.name')
            ->get(['p.id', 'p.code', 'p.name', 'p.cost_price']);

        return view('inventori.pembelian.po.index', compact(
            'suppliers',
            'businessUnits',
            'warehouses',
            'products'
        ));
    }

    public function data(Request $request)
    {
        $entity = $this->entityId();

        $query = DB::table('purchase_orders as po')
            ->leftJoin('business_units as bu', 'po.business_unit_id', '=', 'bu.id')
            ->leftJoin('suppliers as sup', 'po.supplier_id', '=', 'sup.id')
            ->leftJoin('warehouses as wh', 'po.warehouse_id', '=', 'wh.id')
            ->where('po.entity_id', $entity)
            ->select([
                'po.id',
                'po.po_no',
                'po.po_date',
                'po.status',
                'bu.name as business_unit_name',
                'sup.name as supplier_name',
                'wh.name as warehouse_name',
            ]);

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('po.po_date', [
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59',
            ]);
        }

        if ($request->filled('business_unit_id')) {
            $query->where('po.business_unit_id', $request->business_unit_id);
        }

        if ($request->filled('supplier_id')) {
            $query->where('po.supplier_id', $request->supplier_id);
        }

        if ($request->filled('status')) {
            $query->where('po.status', $request->status);
        }

        $totalData = (clone $query)->count();

        $search = $request->input('search.value');

        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('po.po_no', 'like', "%{$search}%")
                    ->orWhere('bu.name', 'like', "%{$search}%")
                    ->orWhere('sup.name', 'like', "%{$search}%")
                    ->orWhere('wh.name', 'like', "%{$search}%");
            });
        }

        $totalFiltered = (clone $query)->count();

        $columns = [
            0 => 'po.po_no',
            1 => 'po.po_date',
            2 => 'bu.name',
            3 => 'sup.name',
            4 => 'wh.name',
            5 => 'po.status',
        ];

        $orderIndex = (int) $request->input('order.0.column', 0);
        $orderDir = $request->input('order.0.dir', 'asc');
        $orderColumn = $columns[$orderIndex] ?? 'po.po_no';

        $pos = $query
            ->orderBy($orderColumn, $orderDir)
            ->offset((int) $request->input('start', 0))
            ->limit((int) $request->input('length', 10))
            ->get();

        $data = [];

        foreach ($pos as $po) {
            $statusBadge = match ($po->status) {
                'approved' => '<span class="badge bg-success">APPROVED</span>',
                'pending_approval' => '<span class="badge bg-warning text-dark">PENDING APPROVAL</span>',
                'rejected' => '<span class="badge bg-danger">REJECTED</span>',
                'completed' => '<span class="badge bg-info">COMPLETED</span>',
                default => '<span class="badge bg-secondary">DRAFT</span>',
            };

            $actions = '<div class="btn-group btn-group-sm">
                <a href="' . route('purchase_orders.print', $po->id) . '" target="_blank" class="btn btn-outline-primary">🖨️ Cetak PO</a>';

            $data[] = [
                'po_number' => $po->po_no,
                'po_date' => date('d/m/Y', strtotime($po->po_date)),
                'business_unit_name' => $po->business_unit_name ?? '-',
                'supplier_name' => $po->supplier_name ?? '-',
                'warehouse_name' => $po->warehouse_name ?? '-',
                'total_amount_formatted' => '-',
                'status_badge' => $statusBadge,
                'action' => $actions,
            ];
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $totalData,
            'recordsFiltered' => $totalFiltered,
            'data' => $data,
        ]);
    }

    public function print($id)
    {
        $po = DB::table('purchase_orders as po')
            ->leftJoin('entities as e', 'po.entity_id', '=', 'e.id')
            ->leftJoin('business_units as bu', 'po.business_unit_id', '=', 'bu.id')
            ->leftJoin('suppliers as sup', 'po.supplier_id', '=', 'sup.id')
            ->leftJoin('warehouses as wh', 'po.warehouse_id', '=', 'wh.id')
            ->where('po.id', $id)
            ->select([
                'po.*',
                'e.name as entity_name',
                'bu.name as business_unit_name',
                'sup.name as supplier_name',
                'wh.name as warehouse_name',
            ])
            ->first();

        abort_if(!$po, 404);

        $items = DB::table('purchase_order_items as poi')
            ->leftJoin('products as p', 'poi.product_id', '=', 'p.id')
            ->leftJoin('units as u', 'poi.unit_id', '=', 'u.id')
            ->where('poi.purchase_order_id', $id)
            ->select([
                'poi.*',
                'p.code as product_code',
                'p.name as product_name',
                'u.code as unit_code',
            ])
            ->get();

        $total = $items->sum('total');
        $terbilang = $this->terbilang($total) . ' Rupiah';

        return view('inventori.pembelian.po.print', compact('po', 'items', 'terbilang'));
    }

    private function terbilang($angka)
    {
        $angka = (int) round($angka);

        $baca = [
            '', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima',
            'Enam', 'Tujuh', 'Delapan', 'Sembilan',
            'Sepuluh', 'Sebelas'
        ];

        if ($angka < 12) {
            return trim($baca[$angka]);
        }

        if ($angka < 20) {
            return trim($this->terbilang($angka - 10) . ' Belas');
        }

        if ($angka < 100) {
            return trim(
                $this->terbilang(intdiv($angka, 10)) .
                ' Puluh ' .
                $this->terbilang($angka % 10)
            );
        }

        if ($angka < 1000) {
            return trim(
                $this->terbilang(intdiv($angka, 100)) .
                ' Ratus ' .
                $this->terbilang($angka % 100)
            );
        }

        if ($angka < 1000000) {
            return trim(
                $this->terbilang(intdiv($angka, 1000)) .
                ' Ribu ' .
                $this->terbilang($angka % 1000)
            );
        }

        if ($angka < 1000000000) {
            return trim(
                $this->terbilang(intdiv($angka, 1000000)) .
                ' Juta ' .
                $this->terbilang($angka % 1000000)
            );
        }

        return trim(
            $this->terbilang(intdiv($angka, 1000000000)) .
            ' Milyar ' .
            $this->terbilang($angka % 1000000000)
        );
    }

    public function create()
    {
        $entity = $this->entityId();

        $suppliers = DB::table('suppliers')
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'phone', 'address']);

        $units = DB::table('business_units')
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $products = DB::table('products as p')
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->where('p.entity_id', $entity)
            ->where('p.is_active', 1)
            ->orderBy('p.name')
            ->get(['p.id', 'p.code', 'p.sku', 'p.name', 'p.cost_price', 'u.code as unit_code']);

        return view('inventori.pembelian.po.create', compact('suppliers', 'units', 'products'));
    }
}
