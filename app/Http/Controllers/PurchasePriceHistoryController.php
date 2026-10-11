<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PurchasePriceHistoryController extends Controller
{
    private function entityId(): int
    {
        $entityId = (int) (auth()->user()->entity_id ?? 0);
        abort_unless($entityId > 0, 403, 'Entitas pengguna tidak valid.');
        return $entityId;
    }

    public function index(Request $request)
    {
        $entityId = $this->entityId();
        $products = DB::table('products')
            ->where('entity_id', $entityId)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $query = DB::table('purchase_order_items as poi')
            ->join('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
            ->join('products as p', 'p.id', '=', 'poi.product_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->leftJoin('units as u', 'u.id', '=', 'poi.unit_id')
            ->leftJoinSub(
                DB::table('receipt_items as ri')
                    ->join('receipts as r', 'r.id', '=', 'ri.receipt_id')
                    ->where('r.status', 'posted')
                    ->select(
                        'ri.purchase_order_item_id',
                        DB::raw('SUM(ri.base_qty) as received_base_qty'),
                        DB::raw('MAX(r.receipt_date) as last_receipt_date'),
                        DB::raw('GROUP_CONCAT(DISTINCT r.receipt_no ORDER BY r.receipt_date SEPARATOR ", ") as receipt_numbers')
                    )
                    ->whereNotNull('ri.purchase_order_item_id')
                    ->groupBy('ri.purchase_order_item_id'),
                'rh',
                'rh.purchase_order_item_id',
                '=',
                'poi.id'
            )
            ->leftJoinSub(
                DB::table('purchases as inv')
                    ->join('purchase_items as pi', 'pi.purchase_id', '=', 'inv.id')
                    ->whereNull('inv.deleted_at')
                    ->where('inv.status', '!=', 'cancelled')
                    ->where('inv.source_type', 'po')
                    ->select(
                        'inv.purchase_order_id',
                        'pi.product_id',
                        DB::raw('SUM(pi.base_qty) as invoiced_base_qty'),
                        DB::raw('SUM(pi.total) as invoiced_total'),
                        DB::raw('GROUP_CONCAT(DISTINCT inv.purchase_no ORDER BY inv.purchase_date SEPARATOR ", ") as invoice_numbers'),
                        DB::raw('MAX(inv.purchase_date) as last_invoice_date')
                    )
                    ->groupBy('inv.purchase_order_id', 'pi.product_id'),
                'ih',
                function ($join) {
                    $join->on('ih.purchase_order_id', '=', 'po.id')
                         ->on('ih.product_id', '=', 'poi.product_id');
                }
            )
            ->where('po.entity_id', $entityId)
            ->whereNull('po.deleted_at');

        if ($request->filled('product_id')) {
            $query->where('poi.product_id', (int) $request->input('product_id'));
        }
        if ($request->filled('start_date')) {
            $query->whereDate('po.po_date', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->whereDate('po.po_date', '<=', $request->input('end_date'));
        }
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('p.name', 'like', "%{$search}%")
                  ->orWhere('p.code', 'like', "%{$search}%")
                  ->orWhere('po.po_no', 'like', "%{$search}%")
                  ->orWhere('s.name', 'like', "%{$search}%");
            });
        }

        $rows = $query->select(
            'poi.id as po_item_id',
            'poi.product_id',
            'poi.qty as ordered_qty',
            'poi.base_qty as ordered_base_qty',
            'poi.unit_price as po_unit_price',
            'poi.total as po_line_total',
            'po.id as po_id',
            'po.po_no',
            'po.po_date',
            'po.status as po_status',
            'p.code as product_code',
            'p.name as product_name',
            'u.code as unit_code',
            's.name as supplier_name',
            'rh.received_base_qty',
            'rh.last_receipt_date',
            'rh.receipt_numbers',
            'ih.invoiced_base_qty',
            'ih.invoiced_total',
            'ih.invoice_numbers',
            'ih.last_invoice_date'
        )
        ->orderByDesc('po.po_date')
        ->orderByDesc('po.id')
        ->orderBy('p.name')
        ->get();

        foreach ($rows as $row) {
            $row->po_unit_price = (float) $row->po_unit_price;
            $row->invoice_unit_price = (float) $row->invoiced_base_qty > 0
                ? (float) $row->invoiced_total / (float) $row->invoiced_base_qty
                : null;
            $row->price_difference = $row->invoice_unit_price !== null
                ? $row->invoice_unit_price - $row->po_unit_price
                : null;
        }

        return view('inventori.pembelian.histori-harga', [
            'products' => $products,
            'rows' => $rows,
            'filters' => $request->only(['product_id', 'start_date', 'end_date', 'search']),
        ]);
    }
}
