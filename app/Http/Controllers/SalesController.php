<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\SalesJournalService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class SalesController extends Controller
{
    private function entityId(): int
    {
        return (int) (DB::table('entities')->value('id') ?? 1);
    }

    private function salesPrefix(object $businessUnit): string
    {
        return match ($businessUnit->business_type) {
            'retail' => 'RET',
            'production' => 'PRO',
            'service' => 'JAS',
            default => throw new \RuntimeException('Jenis Business Unit tidak valid untuk nomor penjualan.'),
        };
    }

    private function nextInvoiceNo(int $entity, object $businessUnit, \Carbon\Carbon $saleDate): string
    {
        $prefix = $this->salesPrefix($businessUnit);
        $businessUnitId = (int) $businessUnit->id;
        $monthKey = $saleDate->format('Ym');
        $dateKey = $saleDate->format('Ymd');

        $existingNumbers = DB::table('sales')
            ->where('entity_id', $entity)
            ->where('business_unit_id', $businessUnitId)
            ->where('invoice_no', 'like', $prefix . '-' . $businessUnitId . '-' . $monthKey . '%')
            ->pluck('invoice_no');

        $lastSequence = 0;
        $regex = '/^' . preg_quote($prefix, '/') . '-' . $businessUnitId . '-' . $monthKey . '[0-9]{2}([0-9]{6})$/';

        foreach ($existingNumbers as $existingNumber) {
            if (preg_match($regex, (string) $existingNumber, $matches)) {
                $lastSequence = max($lastSequence, (int) $matches[1]);
            }
        }

        $sequence = $lastSequence + 1;
        abort_if($sequence > 999999, 422, 'Nomor urut penjualan bulan ini sudah mencapai batas 999999.');

        return $prefix . '-' . $businessUnitId . '-' . $dateKey . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    private function resolveSalePrice(int $productId, int $businessUnitId, int $unitId, int $entity): float
    {
        $price = DB::table('product_prices as pp')
            ->join('products as p', 'p.id', '=', 'pp.product_id')
            ->where('pp.product_id', $productId)
            ->where('pp.business_unit_id', $businessUnitId)
            ->where('pp.unit_id', $unitId)
            ->where('pp.price_type', 'retail')
            ->where('p.entity_id', $entity)
            ->where('p.is_active', 1)
            ->value('pp.selling_price');

        abort_unless($price !== null, 422, 'Harga jual item untuk Business Unit dan satuan tersebut belum tersedia.');

        return (float) $price;
    }

    private function resolveProductUnit(int $productId, ?int $unitId, int $entity): array
    {
        $product = DB::table('products')
            ->where('id', $productId)
            ->where('entity_id', $entity)
            ->first();

        abort_unless($product, 422, 'Item tidak valid.');

        $unitId = $unitId ?: (int) $product->base_unit_id;
        abort_unless($unitId > 0, 422, 'Satuan dasar item belum ditentukan.');

        if ($unitId === (int) $product->base_unit_id) {
            return ['unit_id' => $unitId, 'factor' => 1.0];
        }

        $conversion = DB::table('product_unit_conversions')
            ->where('product_id', $productId)
            ->where('unit_id', $unitId)
            ->where('is_active', 1)
            ->first();

        abort_unless($conversion && (float) $conversion->conversion_factor > 0, 422, 'Konversi satuan item tidak ditemukan atau tidak aktif.');

        return ['unit_id' => $unitId, 'factor' => (float) $conversion->conversion_factor];
    }

    public function index(Request $request)
    {
        $entity = $this->entityId();
        $startDate = $request->filled('start_date') ? $request->input('start_date') : now()->startOfMonth()->toDateString();
        $endDate = $request->filled('end_date') ? $request->input('end_date') : now()->endOfMonth()->toDateString();

        $rows = DB::table('sales as s')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 's.business_unit_id')
            ->where('s.entity_id', $entity)
            ->whereDate('s.sale_date', '>=', $startDate)
            ->whereDate('s.sale_date', '<=', $endDate)
            ->when($request->filled('customer'), fn ($q) => $q->where('c.name', 'like', '%'.$request->customer.'%'))
            ->when($request->filled('unit_id'), fn ($q) => $q->where('s.business_unit_id', $request->unit_id))
            ->when($request->filled('payment_method'), fn ($q) => $q->whereExists(function ($sub) use ($request) {
                $sub->select(DB::raw(1))
                    ->from('payments as fp')
                    ->whereColumn('fp.sale_id', 's.id')
                    ->where('fp.method', $request->payment_method);
            }))
            ->select(
                's.*',
                'c.name as customer_name',
                'bu.name as unit_name',
                DB::raw("(SELECT GROUP_CONCAT(DISTINCT p.method ORDER BY p.id SEPARATOR ', ') FROM payments p WHERE p.sale_id = s.id) as payment_methods")
            )
            ->orderByDesc('s.id')
            ->get();

        $units = DB::table('business_units')
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view('inventori.penjualan.tempo.index', compact('rows', 'units'));
    }

    public function report(Request $request)
    {
        $entity = $this->entityId();

        $startDate = $request->filled('start_date')
            ? $request->input('start_date')
            : now()->startOfMonth()->toDateString();

        $endDate = $request->filled('end_date')
            ? $request->input('end_date')
            : now()->endOfMonth()->toDateString();

        abort_if($startDate > $endDate, 422, 'Periode tanggal tidak valid.');

        $businessUnitId = $request->filled('unit_id')
            ? (int) $request->input('unit_id')
            : null;

        /*
        |--------------------------------------------------------------------------
        | Detail transaksi
        |--------------------------------------------------------------------------
        */
        $rows = DB::table('sales as s')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 's.business_unit_id')
            ->where('s.entity_id', $entity)
            ->where('s.status', 'posted')
            ->whereBetween('s.sale_date', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59',
            ])
            ->when($businessUnitId, fn ($q) =>
                $q->where('s.business_unit_id', $businessUnitId)
            )
            ->select(
                's.*',
                'c.name as customer_name',
                'bu.name as unit_name',
                DB::raw("(SELECT GROUP_CONCAT(DISTINCT p.method ORDER BY p.id SEPARATOR ', ')
                    FROM payments p
                    WHERE p.sale_id = s.id) as payment_methods"),
                DB::raw("(SELECT j.id
                    FROM journals j
                    WHERE j.entity_id = s.entity_id
                      AND j.source_type = 'sale'
                      AND j.source_id = s.id
                    LIMIT 1) as journal_id")
            )
            ->orderByDesc('s.sale_date')
            ->orderByDesc('s.id')
            ->paginate(10)
            ->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | Penjualan
        |--------------------------------------------------------------------------
        */
        $salesTotal = (float) DB::table('sales as s')
            ->where('s.entity_id', $entity)
            ->where('s.status', 'posted')
            ->whereBetween('s.sale_date', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59',
            ])
            ->when($businessUnitId, fn ($q) =>
                $q->where('s.business_unit_id', $businessUnitId)
            )
            ->sum('s.total');

        /*
        |--------------------------------------------------------------------------
        | Retur
        |--------------------------------------------------------------------------
        */
        $returnTotal = (float) DB::table('sales_returns as sr')
            ->where('sr.entity_id', $entity)
            ->where('sr.status', 'posted')
            ->whereBetween('sr.return_date', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59',
            ])
            ->when($businessUnitId, fn ($q) =>
                $q->where('sr.business_unit_id', $businessUnitId)
            )
            ->sum('sr.total');

        /*
        |--------------------------------------------------------------------------
        | HPP penjualan
        |--------------------------------------------------------------------------
        */
        $salesHpp = (float) DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->where('s.entity_id', $entity)
            ->where('s.status', 'posted')
            ->whereBetween('s.sale_date', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59',
            ])
            ->when($businessUnitId, fn ($q) =>
                $q->where('s.business_unit_id', $businessUnitId)
            )
            ->sum('si.hpp_total');

        /*
        |--------------------------------------------------------------------------
        | HPP retur
        |--------------------------------------------------------------------------
        | Hanya retur GOOD yang mengembalikan barang ke persediaan.
        */
        $returnHpp = (float) DB::table('sales_return_items as sri')
            ->join('sales_returns as sr', 'sr.id', '=', 'sri.sales_return_id')
            ->where('sr.entity_id', $entity)
            ->where('sr.status', 'posted')
            ->where('sri.condition', 'good')
            ->whereBetween('sr.return_date', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59',
            ])
            ->when($businessUnitId, fn ($q) =>
                $q->where('sr.business_unit_id', $businessUnitId)
            )
            ->sum('sri.hpp_total');

        $netSales = $salesTotal - $returnTotal;
        $netHpp = $salesHpp - $returnHpp;
        $grossProfit = $netSales - $netHpp;
        $margin = $netSales > 0
            ? ($grossProfit / $netSales) * 100
            : 0;

        /*
        |--------------------------------------------------------------------------
        | Penerimaan kas
        |--------------------------------------------------------------------------
        */
        $cashReceipt = (float) DB::table('payments as p')
            ->where('p.entity_id', $entity)
            ->where('p.method', 'cash')
            ->whereBetween('p.payment_date', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59',
            ])
            ->when($businessUnitId, fn ($q) =>
                $q->where('p.business_unit_id', $businessUnitId)
            )
            ->sum('p.amount');

        /*
        |--------------------------------------------------------------------------
        | Penerimaan bank
        |--------------------------------------------------------------------------
        */
        $bankReceipt = (float) DB::table('payments as p')
            ->where('p.entity_id', $entity)
            ->whereIn('p.method', ['transfer', 'qris'])
            ->whereBetween('p.payment_date', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59',
            ])
            ->when($businessUnitId, fn ($q) =>
                $q->where('p.business_unit_id', $businessUnitId)
            )
            ->sum('p.amount');

        $totalReceipt = $cashReceipt + $bankReceipt;

        /*
        |--------------------------------------------------------------------------
        | Penjualan kredit
        |--------------------------------------------------------------------------
        */
        $creditSales = (float) DB::table('sales as s')
            ->join('payments as p', function ($join) {
                $join->on('p.sale_id', '=', 's.id')
                    ->where('p.method', '=', 'credit');
            })
            ->where('s.entity_id', $entity)
            ->where('s.status', 'posted')
            ->whereBetween('s.sale_date', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59',
            ])
            ->when($businessUnitId, fn ($q) =>
                $q->where('s.business_unit_id', $businessUnitId)
            )
            ->sum('s.total');

        /*
        |--------------------------------------------------------------------------
        | Pembayaran piutang
        |--------------------------------------------------------------------------
        | Belum ada workflow pembayaran piutang terpisah.
        */
        $receivablePayments = 0;

        /*
        |--------------------------------------------------------------------------
        | Saldo piutang akhir
        |--------------------------------------------------------------------------
        | Kumulatif sampai akhir periode.
        */
        $receivable = (float) DB::table('sales as s')
            ->join('payments as p', function ($join) {
                $join->on('p.sale_id', '=', 's.id')
                    ->where('p.method', '=', 'credit');
            })
            ->where('s.entity_id', $entity)
            ->where('s.status', 'posted')
            ->where('s.sale_date', '<=', $endDate . ' 23:59:59')
            ->when($businessUnitId, fn ($q) =>
                $q->where('s.business_unit_id', $businessUnitId)
            )
            ->selectRaw('GREATEST(SUM(s.total) - SUM(p.paid_amount), 0) as saldo')
            ->value('saldo');

        /*
        |--------------------------------------------------------------------------
        | Komposisi pembayaran
        |--------------------------------------------------------------------------
        */
        $paymentComposition = [
            'cash' => $cashReceipt,
            'bank' => $bankReceipt,
            'credit' => $creditSales,
            'total' => $cashReceipt + $bankReceipt + $creditSales,
        ];

        /*
        |--------------------------------------------------------------------------
        | Trend harian
        |--------------------------------------------------------------------------
        */
        $trend = DB::table('sales as s')
            ->where('s.entity_id', $entity)
            ->where('s.status', 'posted')
            ->whereBetween('s.sale_date', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59',
            ])
            ->when($businessUnitId, fn ($q) =>
                $q->where('s.business_unit_id', $businessUnitId)
            )
            ->selectRaw('DATE(s.sale_date) as period')
            ->selectRaw('SUM(s.total) as sales')
            ->selectRaw('COUNT(*) as transactions')
            ->groupByRaw('DATE(s.sale_date)')
            ->orderBy('period')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Penjualan per Business Unit
        |--------------------------------------------------------------------------
        */
        $unitSales = DB::table('business_units as bu')
            ->leftJoin('sales as s', function ($join) use ($entity, $startDate, $endDate) {
                $join->on('s.business_unit_id', '=', 'bu.id')
                    ->where('s.entity_id', '=', $entity)
                    ->where('s.status', '=', 'posted')
                    ->whereBetween('s.sale_date', [
                        $startDate . ' 00:00:00',
                        $endDate . ' 23:59:59',
                    ]);
            })
            ->where('bu.entity_id', $entity)
            ->where('bu.is_active', 1)
            ->when($businessUnitId, fn ($q) =>
                $q->where('bu.id', $businessUnitId)
            )
            ->select('bu.id', 'bu.name')
            ->selectRaw('COALESCE(SUM(s.total), 0) as sales')
            ->groupBy('bu.id', 'bu.name')
            ->orderBy('bu.name')
            ->get();

        foreach ($unitSales as $unit) {
            $unitReturn = (float) DB::table('sales_returns as sr')
                ->where('sr.entity_id', $entity)
                ->where('sr.business_unit_id', $unit->id)
                ->where('sr.status', 'posted')
                ->whereBetween('sr.return_date', [
                    $startDate . ' 00:00:00',
                    $endDate . ' 23:59:59',
                ])
                ->sum('sr.total');

            $unitHpp = (float) DB::table('sale_items as si')
                ->join('sales as s', 's.id', '=', 'si.sale_id')
                ->where('s.entity_id', $entity)
                ->where('s.business_unit_id', $unit->id)
                ->where('s.status', 'posted')
                ->whereBetween('s.sale_date', [
                    $startDate . ' 00:00:00',
                    $endDate . ' 23:59:59',
                ])
                ->sum('si.hpp_total');

            $unitReturnHpp = (float) DB::table('sales_return_items as sri')
                ->join('sales_returns as sr', 'sr.id', '=', 'sri.sales_return_id')
                ->where('sr.entity_id', $entity)
                ->where('sr.business_unit_id', $unit->id)
                ->where('sr.status', 'posted')
                ->where('sri.condition', 'good')
                ->whereBetween('sr.return_date', [
                    $startDate . ' 00:00:00',
                    $endDate . ' 23:59:59',
                ])
                ->sum('sri.hpp_total');

            $unit->return = $unitReturn;
            $unit->net_sales = (float) $unit->sales - $unitReturn;
            $unit->hpp = $unitHpp - $unitReturnHpp;
            $unit->gross_profit = $unit->net_sales - $unit->hpp;
        }

        /*
        |--------------------------------------------------------------------------
        | Produk terlaris
        |--------------------------------------------------------------------------
        */
        $topProducts = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->leftJoin('products as p', 'p.id', '=', 'si.product_id')
            ->where('s.entity_id', $entity)
            ->where('s.status', 'posted')
            ->whereBetween('s.sale_date', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59',
            ])
            ->when($businessUnitId, fn ($q) =>
                $q->where('s.business_unit_id', $businessUnitId)
            )
            ->select(
                'p.id',
                DB::raw("COALESCE(p.name, '-') as product_name")
            )
            ->selectRaw('SUM(si.base_qty) as qty')
            ->selectRaw('SUM(si.total) as sales')
            ->selectRaw('SUM(si.hpp_total) as hpp')
            ->selectRaw('SUM(si.total - si.hpp_total) as gross_profit')
            ->groupBy('p.id', 'p.name')
            ->orderByDesc('sales')
            ->limit(10)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Pelanggan terbesar
        |--------------------------------------------------------------------------
        */
        $topCustomers = DB::table('sales as s')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->where('s.entity_id', $entity)
            ->where('s.status', 'posted')
            ->whereBetween('s.sale_date', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59',
            ])
            ->when($businessUnitId, fn ($q) =>
                $q->where('s.business_unit_id', $businessUnitId)
            )
            ->select(
                'c.id',
                DB::raw("COALESCE(c.name, 'Umum') as customer_name")
            )
            ->selectRaw('COUNT(s.id) as transactions')
            ->selectRaw('SUM(s.total) as sales')
            ->selectRaw("
                SUM(
                    CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM payments cp
                            WHERE cp.sale_id = s.id
                              AND cp.method = 'credit'
                        )
                        THEN GREATEST(
                            s.total - COALESCE((
                                SELECT SUM(cp2.paid_amount)
                                FROM payments cp2
                                WHERE cp2.sale_id = s.id
                            ), 0),
                            0
                        )
                        ELSE 0
                    END
                ) as receivable
            ")
            ->groupBy('c.id', 'c.name')
            ->orderByDesc('sales')
            ->limit(10)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Business Unit filter
        |--------------------------------------------------------------------------
        */
        $units = DB::table('business_units')
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $entityName = DB::table('entities')
            ->where('id', $entity)
            ->value('name') ?? 'NAMA ENTITAS';

        $selectedUnitName = $businessUnitId
            ? $units->firstWhere('id', $businessUnitId)?->name
            : 'Semua Business Unit';

        return view('inventori.laporan.penjualan', compact(
            'rows',
            'units',
            'startDate',
            'endDate',
            'businessUnitId',
            'entityName',
            'selectedUnitName',
            'salesTotal',
            'returnTotal',
            'netSales',
            'salesHpp',
            'returnHpp',
            'netHpp',
            'grossProfit',
            'margin',
            'cashReceipt',
            'bankReceipt',
            'totalReceipt',
            'creditSales',
            'receivablePayments',
            'receivable',
            'paymentComposition',
            'trend',
            'unitSales',
            'topProducts',
            'topCustomers'
        ));
    }

    public function export(Request $request)
    {
        $entity = $this->entityId();
        $startDate = $request->filled('start_date') ? $request->input('start_date') : now()->startOfMonth()->toDateString();
        $endDate = $request->filled('end_date') ? $request->input('end_date') : now()->endOfMonth()->toDateString();

        $rows = DB::table('sales as s')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 's.business_unit_id')
            ->where('s.entity_id', $entity)
            ->whereDate('s.sale_date', '>=', $startDate)
            ->whereDate('s.sale_date', '<=', $endDate)
            ->when($request->filled('customer'), fn ($q) => $q->where('c.name', 'like', '%'.$request->customer.'%'))
            ->when($request->filled('unit_id'), fn ($q) => $q->where('s.business_unit_id', $request->unit_id))
            ->when($request->filled('payment_method'), fn ($q) => $q->whereExists(function ($sub) use ($request) {
                $sub->select(DB::raw(1))
                    ->from('payments as fp')
                    ->whereColumn('fp.sale_id', 's.id')
                    ->where('fp.method', $request->payment_method);
            }))
            ->select(
                's.invoice_no',
                's.sale_date',
                DB::raw("COALESCE(c.name, 'Umum') as customer_name"),
                DB::raw("COALESCE(bu.name, '-') as unit_name"),
                DB::raw("(SELECT GROUP_CONCAT(DISTINCT p.method ORDER BY p.id SEPARATOR ', ') FROM payments p WHERE p.sale_id = s.id) as payment_methods"),
                's.due_date',
                's.subtotal',
                's.discount',
                's.total',
                's.status'
            )
            ->orderByDesc('s.sale_date')
            ->orderByDesc('s.id')
            ->get();

        return response()->json([
            'entity_name' => DB::table('entities')->where('id', $entity)->value('name') ?? 'NAMA ENTITAS',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'rows' => $rows,
        ]);
    }

    public function exportExcel(Request $request)
    {
        $entity = $this->entityId();

        $startDate = $request->filled('start_date')
            ? $request->input('start_date')
            : now()->startOfMonth()->toDateString();

        $endDate = $request->filled('end_date')
            ? $request->input('end_date')
            : now()->endOfMonth()->toDateString();

        abort_if($startDate > $endDate, 422, 'Periode tanggal tidak valid.');

        $businessUnitId = $request->filled('unit_id')
            ? (int) $request->input('unit_id')
            : null;

        $entityName = DB::table('entities')
            ->where('id', $entity)
            ->value('name') ?? 'NAMA ENTITAS';

        $units = DB::table('business_units')
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $selectedUnitName = $businessUnitId
            ? ($units->firstWhere('id', $businessUnitId)?->name ?? 'Business Unit tidak ditemukan')
            : 'Semua Business Unit';

        /*
        |--------------------------------------------------------------------------
        | KPI
        |--------------------------------------------------------------------------
        */

        $salesTotal = (float) DB::table('sales as s')
            ->where('s.entity_id', $entity)
            ->where('s.status', 'posted')
            ->whereBetween('s.sale_date', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59',
            ])
            ->when($businessUnitId, fn ($q) =>
                $q->where('s.business_unit_id', $businessUnitId)
            )
            ->sum('s.total');

        $returnTotal = (float) DB::table('sales_returns as sr')
            ->where('sr.entity_id', $entity)
            ->where('sr.status', 'posted')
            ->whereBetween('sr.return_date', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59',
            ])
            ->when($businessUnitId, fn ($q) =>
                $q->where('sr.business_unit_id', $businessUnitId)
            )
            ->sum('sr.total');

        $salesHpp = (float) DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->where('s.entity_id', $entity)
            ->where('s.status', 'posted')
            ->whereBetween('s.sale_date', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59',
            ])            ->when($businessUnitId, fn ($q) =>
                $q->where('s.business_unit_id', $businessUnitId)
            )
            ->sum('si.hpp_total');

        $returnHpp = (float) DB::table('sales_return_items as sri')
            ->join('sales_returns as sr', 'sr.id', '=', 'sri.sales_return_id')
            ->where('sr.entity_id', $entity)
            ->where('sr.status', 'posted')
            ->where('sri.condition', 'good')
            ->whereBetween('sr.return_date', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59',
            ])
            ->when($businessUnitId, fn ($q) =>
                $q->where('sr.business_unit_id', $businessUnitId)
            )
            ->sum('sri.hpp_total');

        $netSales = $salesTotal - $returnTotal;
        $netHpp = $salesHpp - $returnHpp;
        $grossProfit = $netSales - $netHpp;
        $margin = $netSales > 0
            ? ($grossProfit / $netSales) * 100
            : 0;

        /*
        |--------------------------------------------------------------------------
        | Penerimaan & Piutang
        |--------------------------------------------------------------------------
        */

        $cashReceipt = (float) DB::table('payments as p')
            ->where('p.entity_id', $entity)
            ->where('p.method', 'cash')
            ->whereBetween('p.payment_date', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59',
            ])
            ->when($businessUnitId, fn ($q) =>
                $q->where('p.business_unit_id', $businessUnitId)
            )
            ->sum('p.amount');

        $bankReceipt = (float) DB::table('payments as p')
            ->where('p.entity_id', $entity)
            ->whereIn('p.method', ['transfer', 'qris'])
            ->whereBetween('p.payment_date', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59',
            ])
            ->when($businessUnitId, fn ($q) =>
                $q->where('p.business_unit_id', $businessUnitId)
            )
            ->sum('p.amount');

        $totalReceipt = $cashReceipt + $bankReceipt;

        $creditSales = (float) DB::table('sales as s')
            ->join('payments as p', function ($join) {
                $join->on('p.sale_id', '=', 's.id')
                    ->where('p.method', '=', 'credit');
            })
            ->where('s.entity_id', $entity)
            ->where('s.status', 'posted')
            ->whereBetween('s.sale_date', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59',
            ])
            ->when($businessUnitId, fn ($q) =>
                $q->where('s.business_unit_id', $businessUnitId)
            )
            ->sum('s.total');

        $receivablePayments = 0;

        $receivable = (float) DB::table('sales as s')
            ->join('payments as p', function ($join) {
                $join->on('p.sale_id', '=', 's.id')
                    ->where('p.method', '=', 'credit');
            })
            ->where('s.entity_id', $entity)
            ->where('s.status', 'posted')
            ->where('s.sale_date', '<=', $endDate . ' 23:59:59')
            ->when($businessUnitId, fn ($q) =>
                $q->where('s.business_unit_id', $businessUnitId)
            )
            ->selectRaw('GREATEST(SUM(s.total) - SUM(p.paid_amount), 0) as saldo')
            ->value('saldo');

        $paymentComposition = [
            'cash' => $cashReceipt,
            'bank' => $bankReceipt,
            'credit' => $creditSales,
            'total' => $cashReceipt + $bankReceipt + $creditSales,
        ];

        /*
        |--------------------------------------------------------------------------
        | Trend
        |--------------------------------------------------------------------------
        */

        $trend = DB::table('sales as s')
            ->where('s.entity_id', $entity)
            ->where('s.status', 'posted')
            ->whereBetween('s.sale_date', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59',
            ])
            ->when($businessUnitId, fn ($q) =>
                $q->where('s.business_unit_id', $businessUnitId)
            )
            ->selectRaw('DATE(s.sale_date) as period')
            ->selectRaw('SUM(s.total) as sales')
            ->selectRaw('COUNT(*) as transactions')
            ->groupByRaw('DATE(s.sale_date)')
            ->orderBy('period')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Penjualan per BU
        |--------------------------------------------------------------------------
        */

        $unitSales = DB::table('business_units as bu')
            ->leftJoin('sales as s', function ($join) use ($entity, $startDate, $endDate) {
                $join->on('s.business_unit_id', '=', 'bu.id')
                    ->where('s.entity_id', '=', $entity)
                    ->where('s.status', '=', 'posted')
                    ->whereBetween('s.sale_date', [
                        $startDate . ' 00:00:00',
                        $endDate . ' 23:59:59',
                    ]);
            })
            ->where('bu.entity_id', $entity)
            ->where('bu.is_active', 1)
            ->when($businessUnitId, fn ($q) =>
                $q->where('bu.id', $businessUnitId)
            )
            ->select('bu.id', 'bu.name')
            ->selectRaw('COALESCE(SUM(s.total), 0) as sales')
            ->groupBy('bu.id', 'bu.name')
            ->orderBy('bu.name')
            ->get();

        foreach ($unitSales as $unit) {
            $unitReturn = (float) DB::table('sales_returns as sr')
                ->where('sr.entity_id', $entity)
                ->where('sr.business_unit_id', $unit->id)
                ->where('sr.status', 'posted')
                ->whereBetween('sr.return_date', [
                    $startDate . ' 00:00:00',
                    $endDate . ' 23:59:59',
                ])
                ->sum('sr.total');

            $unitHpp = (float) DB::table('sale_items as si')
                ->join('sales as s', 's.id', '=', 'si.sale_id')
                ->where('s.entity_id', $entity)
                ->where('s.business_unit_id', $unit->id)
                ->where('s.status', 'posted')
                ->whereBetween('s.sale_date', [
                    $startDate . ' 00:00:00',
                    $endDate . ' 23:59:59',
                ])
                ->sum('si.hpp_total');

            $unitReturnHpp = (float) DB::table('sales_return_items as sri')
                ->join('sales_returns as sr', 'sr.id', '=', 'sri.sales_return_id')
                ->where('sr.entity_id', $entity)
                ->where('sr.business_unit_id', $unit->id)
                ->where('sr.status', 'posted')
                ->where('sri.condition', 'good')
                ->whereBetween('sr.return_date', [
                    $startDate . ' 00:00:00',
                    $endDate . ' 23:59:59',
                ])
                ->sum('sri.hpp_total');

            $unit->return = $unitReturn;
            $unit->net_sales = (float) $unit->sales - $unitReturn;
            $unit->hpp = $unitHpp - $unitReturnHpp;
            $unit->gross_profit = $unit->net_sales - $unit->hpp;
        }

        /*
        |--------------------------------------------------------------------------
        | Produk terlaris
        |--------------------------------------------------------------------------
        */

        $topProducts = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->leftJoin('products as p', 'p.id', '=', 'si.product_id')
            ->where('s.entity_id', $entity)
            ->where('s.status', 'posted')
            ->whereBetween('s.sale_date', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59',
            ])
            ->when($businessUnitId, fn ($q) =>
                $q->where('s.business_unit_id', $businessUnitId)
            )
            ->select(
                'p.id',
                DB::raw("COALESCE(p.name, '-') as product_name")
            )
            ->selectRaw('SUM(si.base_qty) as qty')
            ->selectRaw('SUM(si.total) as sales')
            ->selectRaw('SUM(si.hpp_total) as hpp')
            ->selectRaw('SUM(si.total - si.hpp_total) as gross_profit')
            ->groupBy('p.id', 'p.name')
            ->orderByDesc('sales')
            ->limit(10)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Pelanggan terbesar
        |--------------------------------------------------------------------------
        */

        $topCustomers = DB::table('sales as s')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->where('s.entity_id', $entity)
            ->where('s.status', 'posted')
            ->whereBetween('s.sale_date', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59',
            ])
            ->when($businessUnitId, fn ($q) =>
                $q->where('s.business_unit_id', $businessUnitId)
            )
            ->select(
                'c.id',
                DB::raw("COALESCE(c.name, 'Umum') as customer_name")
            )
            ->selectRaw('COUNT(s.id) as transactions')
            ->selectRaw('SUM(s.total) as sales')
            ->selectRaw("
                SUM(
                    CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM payments cp
                            WHERE cp.sale_id = s.id
                              AND cp.method = 'credit'
                        )
                        THEN GREATEST(
                            s.total - COALESCE((
                                SELECT SUM(cp2.paid_amount)
                                FROM payments cp2
                                WHERE cp2.sale_id = s.id
                            ), 0),
                            0
                        )
                        ELSE 0
                    END
                ) as receivable
            ")
            ->groupBy('c.id', 'c.name')
            ->orderByDesc('sales')
            ->limit(10)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Detail transaksi
        |--------------------------------------------------------------------------
        */

        $rows = DB::table('sales as s')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 's.business_unit_id')
            ->where('s.entity_id', $entity)
            ->where('s.status', 'posted')
            ->whereBetween('s.sale_date', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59',
            ])
            ->when($businessUnitId, fn ($q) =>
                $q->where('s.business_unit_id', $businessUnitId)
            )
            ->select(
                's.invoice_no',
                's.sale_date',
                's.total',
                's.status',
                DB::raw("COALESCE(c.name, 'Umum') as customer_name"),
                DB::raw("COALESCE(bu.name, '-') as unit_name"),
                DB::raw("(SELECT GROUP_CONCAT(DISTINCT p.method ORDER BY p.id SEPARATOR ', ')
                    FROM payments p
                    WHERE p.sale_id = s.id) as payment_methods")
            )
            ->orderBy('s.sale_date')
            ->orderBy('s.id')
            ->get();

        $methodLabel = fn ($value) => match ($value) {
            'cash' => 'Tunai',
            'credit' => 'Kredit / Bon',
            'transfer' => 'Transfer',
            'qris' => 'QRIS',
            default => $value ?: '-',
        };

        $statusLabel = fn ($value) => match ($value) {
            'posted' => 'Diposting',
            'draft' => 'Draf',
            'cancelled' => 'Dibatalkan',
            default => $value ?: '-',
        };

        $spreadsheet = new Spreadsheet();

        $spreadsheet->getProperties()
            ->setCreator('Mini ERP')
            ->setTitle('Laporan Penjualan')
            ->setSubject('Analisis penjualan, penerimaan, dan piutang');

        $moneyFormat = '#,##0.00';
        $percentFormat = '0.00"%"';

        $applyTitle = function ($sheet, $range, $text) {
            $sheet->mergeCells($range);
            $cell = explode(':', $range)[0];
            $sheet->setCellValue($cell, $text);
            $sheet->getStyle($range)->getFont()->setBold(true)->setSize(15);
            $sheet->getStyle($range)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                ->setVertical(Alignment::VERTICAL_CENTER);
        };

        $applySection = function ($sheet, $range) {
            $sheet->mergeCells($range);
            $sheet->getStyle($range)->getFont()->setBold(true);
            $sheet->getStyle($range)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('D9EAF7');
        };

        $applyHeader = function ($sheet, $range) {
            $sheet->getStyle($range)->getFont()->setBold(true);
            $sheet->getStyle($range)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('EDEDED');
            $sheet->getStyle($range)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle($range)->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);
        };

        $applyBorders = function ($sheet, $range) {
            $sheet->getStyle($range)->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);
        };

        /*
        |--------------------------------------------------------------------------
        | Sheet 1
        |--------------------------------------------------------------------------
        */

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Ringkasan Penjualan');

        $applyTitle($sheet, 'A1:D1', $entityName);

        $sheet->setCellValue('A2', 'LAPORAN PENJUALAN');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(13);

        $sheet->setCellValue('A4', 'Periode');
        $sheet->setCellValue(
            'B4',
            date('d/m/Y', strtotime($startDate)) . ' s/d ' .
            date('d/m/Y', strtotime($endDate))
        );

        $sheet->setCellValue('A5', 'Business Unit');
        $sheet->setCellValue('B5', $selectedUnitName);

        $applySection($sheet, 'A7:B7');
        $sheet->setCellValue('A7', 'KPI PENJUALAN');

        $sheet->fromArray([
            ['Komponen', 'Nilai'],
            ['Penjualan', $salesTotal],
            ['Retur', $returnTotal],
            ['Penjualan Bersih', $netSales],
            ['HPP', $netHpp],
            ['Laba Kotor', $grossProfit],
            ['Margin', $margin],
        ], null, 'A8');

        $applyHeader($sheet, 'A8:B8');
        $applyBorders($sheet, 'A8:B14');
        $sheet->getStyle('B9:B13')->getNumberFormat()->setFormatCode($moneyFormat);
        $sheet->getStyle('B14')->getNumberFormat()->setFormatCode($percentFormat);

        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(25);

        /*
        |--------------------------------------------------------------------------
        | Sheet 2
        |--------------------------------------------------------------------------
        */

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Penerimaan & Piutang');

        $applyTitle($sheet, 'A1:D1', $entityName);

        $sheet->setCellValue('A2', 'PENERIMAAN & PIUTANG');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(13);

        $sheet->setCellValue('A4', 'Periode');
        $sheet->setCellValue(
            'B4',
            date('d/m/Y', strtotime($startDate)) . ' s/d ' .
            date('d/m/Y', strtotime($endDate))
        );

        $sheet->setCellValue('A5', 'Business Unit');
        $sheet->setCellValue('B5', $selectedUnitName);

        $applySection($sheet, 'A7:B7');
        $sheet->setCellValue('A7', 'PENERIMAAN & PIUTANG');

        $sheet->fromArray([
            ['Komponen', 'Nilai'],
            ['Kas Tunai', $cashReceipt],
            ['Bank', $bankReceipt],
            ['Total Penerimaan', $totalReceipt],
            ['Penjualan Kredit', $creditSales],
            ['Pembayaran Piutang', $receivablePayments],
            ['Saldo Piutang', $receivable],
        ], null, 'A8');

        $applyHeader($sheet, 'A8:B8');
        $applyBorders($sheet, 'A8:B14');
        $sheet->getStyle('B9:B14')->getNumberFormat()->setFormatCode($moneyFormat);

        $applySection($sheet, 'A16:B16');
        $sheet->setCellValue('A16', 'KOMPOSISI PEMBAYARAN');

        $sheet->fromArray([
            ['Komponen', 'Nilai'],
            ['Kas Tunai', $paymentComposition['cash']],
            ['Bank', $paymentComposition['bank']],
            ['Piutang', $paymentComposition['credit']],
            ['Total', $paymentComposition['total']],
        ], null, 'A17');

        $applyHeader($sheet, 'A17:B17');
        $applyBorders($sheet, 'A17:B21');
        $sheet->getStyle('B18:B21')->getNumberFormat()->setFormatCode($moneyFormat);

        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(25);

        /*
        |--------------------------------------------------------------------------
        | Sheet 3
        |--------------------------------------------------------------------------
        */

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Analisis Penjualan');

        $applyTitle($sheet, 'A1:F1', $entityName);

        $sheet->setCellValue('A2', 'ANALISIS PENJUALAN');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(13);

        $sheet->setCellValue('A4', 'Periode');
        $sheet->setCellValue(
            'B4',
            date('d/m/Y', strtotime($startDate)) . ' s/d ' .
            date('d/m/Y', strtotime($endDate))
        );

        $sheet->setCellValue('A5', 'Business Unit');
        $sheet->setCellValue('B5', $selectedUnitName);

        $applySection($sheet, 'A7:C7');
        $sheet->setCellValue('A7', 'TREND PENJUALAN HARIAN');

        $sheet->fromArray([
            ['Tanggal', 'Penjualan', 'Transaksi'],
        ], null, 'A8');

        $applyHeader($sheet, 'A8:C8');

        $row = 9;

        foreach ($trend as $item) {
            $sheet->fromArray([
                [
                    date('d/m/Y', strtotime($item->period)),
                    (float) $item->sales,
                    (int) $item->transactions,
                ],
            ], null, 'A' . $row);
            $row++;
        }

        $trendEnd = max(8, $row - 1);
        $applyBorders($sheet, 'A8:C' . $trendEnd);

        if ($row > 9) {
            $sheet->getStyle('B9:B' . ($row - 1))
                ->getNumberFormat()->setFormatCode($moneyFormat);
        }

        $unitStart = $row + 2;

        $applySection($sheet, 'A' . $unitStart . ':F' . $unitStart);
        $sheet->setCellValue('A' . $unitStart, 'PENJUALAN PER BUSINESS UNIT');

        $unitHeader = $unitStart + 1;

        $sheet->fromArray([
            ['Business Unit', 'Penjualan', 'Retur', 'Bersih', 'HPP', 'Laba Kotor'],
        ], null, 'A' . $unitHeader);

        $applyHeader($sheet, 'A' . $unitHeader . ':F' . $unitHeader);

        $row = $unitHeader + 1;

        foreach ($unitSales as $unit) {
            $sheet->fromArray([
                [
                    $unit->name,
                    (float) $unit->sales,
                    (float) $unit->return,
                    (float) $unit->net_sales,
                    (float) $unit->hpp,
                    (float) $unit->gross_profit,
                ],
            ], null, 'A' . $row);

            $row++;
        }

        $unitEnd = max($unitHeader, $row - 1);
        $applyBorders($sheet, 'A' . $unitHeader . ':F' . $unitEnd);

        if ($row > $unitHeader + 1) {
            $sheet->getStyle(
                'B' . ($unitHeader + 1) . ':F' . ($row - 1)
            )->getNumberFormat()->setFormatCode($moneyFormat);
        }

        $productStart = $row + 2;

        $applySection($sheet, 'A' . $productStart . ':E' . $productStart);
        $sheet->setCellValue('A' . $productStart, 'PRODUK TERLARIS');

        $productHeader = $productStart + 1;

        $sheet->fromArray([
            ['Produk', 'Qty', 'Penjualan', 'HPP', 'Laba Kotor'],
        ], null, 'A' . $productHeader);

        $applyHeader($sheet, 'A' . $productHeader . ':E' . $productHeader);

        $row = $productHeader + 1;

        foreach ($topProducts as $product) {
            $sheet->fromArray([
                [
                    $product->product_name,
                    (float) $product->qty,
                    (float) $product->sales,
                    (float) $product->hpp,
                    (float) $product->gross_profit,
                ],
            ], null, 'A' . $row);

            $row++;
        }

        $productEnd = max($productHeader, $row - 1);
        $applyBorders($sheet, 'A' . $productHeader . ':E' . $productEnd);

        if ($row > $productHeader + 1) {
            $sheet->getStyle(
                'B' . ($productHeader + 1) . ':B' . ($row - 1)
            )->getNumberFormat()->setFormatCode('#,##0.###');

            $sheet->getStyle(
                'C' . ($productHeader + 1) . ':E' . ($row - 1)
            )->getNumberFormat()->setFormatCode($moneyFormat);
        }

        $customerStart = $row + 2;

        $applySection($sheet, 'A' . $customerStart . ':D' . $customerStart);
        $sheet->setCellValue('A' . $customerStart, 'PELANGGAN TERBESAR');

        $customerHeader = $customerStart + 1;

        $sheet->fromArray([
            ['Pelanggan', 'Transaksi', 'Penjualan', 'Piutang'],
        ], null, 'A' . $customerHeader);

        $applyHeader($sheet, 'A' . $customerHeader . ':D' . $customerHeader);

        $row = $customerHeader + 1;

        foreach ($topCustomers as $customer) {
            $sheet->fromArray([
                [
                    $customer->customer_name,
                    (int) $customer->transactions,
                    (float) $customer->sales,
                    (float) $customer->receivable,
                ],
            ], null, 'A' . $row);

            $row++;
        }

        $customerEnd = max($customerHeader, $row - 1);
        $applyBorders($sheet, 'A' . $customerHeader . ':D' . $customerEnd);

        if ($row > $customerHeader + 1) {
            $sheet->getStyle(
                'C' . ($customerHeader + 1) . ':D' . ($row - 1)
            )->getNumberFormat()->setFormatCode($moneyFormat);
        }

        foreach (['A' => 30, 'B' => 18, 'C' => 18, 'D' => 18, 'E' => 18, 'F' => 18] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        /*
        |--------------------------------------------------------------------------
        | Sheet 4
        |--------------------------------------------------------------------------
        */

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Detail Transaksi');

        $applyTitle($sheet, 'A1:G1', $entityName);

        $sheet->setCellValue('A2', 'DETAIL TRANSAKSI PENJUALAN');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(13);

        $sheet->setCellValue('A4', 'Periode');
        $sheet->setCellValue(
            'B4',
            date('d/m/Y', strtotime($startDate)) . ' s/d ' .
            date('d/m/Y', strtotime($endDate))
        );

        $sheet->setCellValue('A5', 'Business Unit');
        $sheet->setCellValue('B5', $selectedUnitName);

        $sheet->fromArray([
            ['Tanggal', 'No Faktur', 'Pelanggan', 'Unit Bisnis', 'Pembayaran', 'Total', 'Status'],
        ], null, 'A7');

        $applyHeader($sheet, 'A7:G7');

        $row = 8;

        foreach ($rows as $item) {
            $methods = collect(explode(', ', (string) $item->payment_methods))
                ->map(fn ($method) => $methodLabel($method))
                ->implode(', ');

            $sheet->fromArray([
                [
                    date('d/m/Y', strtotime($item->sale_date)),
                    $item->invoice_no,
                    $item->customer_name,
                    $item->unit_name,
                    $methods ?: '-',
                    (float) $item->total,
                    $statusLabel($item->status),
                ],
            ], null, 'A' . $row);

            $row++;
        }

        $detailEnd = max(7, $row - 1);

        $applyBorders($sheet, 'A7:G' . $detailEnd);

        if ($row > 8) {
            $sheet->getStyle('F8:F' . ($row - 1))
                ->getNumberFormat()->setFormatCode($moneyFormat);
        }
        $sheet->freezePane('A8');
        $sheet->setAutoFilter('A7:G' . $detailEnd);

        foreach (['A' => 14, 'B' => 20, 'C' => 28, 'D' => 22, 'E' => 22, 'F' => 18, 'G' => 14] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $sheet->getDefaultRowDimension()->setRowHeight(20);
            $sheet->getSheetView()->setZoomScale(90);
        }

        $filename = 'laporan-penjualan_' . $startDate . '_' . $endDate . '.xlsx';

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(
            function () use ($writer) {
                $writer->save('php://output');
            },
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]
        );
    }

    private function productionProductIds(int $entity, int $businessUnitId): array
    {
        return DB::table('boms')
            ->where('entity_id', $entity)
            ->where('business_unit_id', $businessUnitId)
            ->where('is_active', 1)
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function assertSalePeriodOpen(string $date): void
    {
        $entity = $this->entityId();
        $period = DB::table('accounting_periods')
            ->where('entity_id', $entity)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->orderByDesc('id')
            ->first();

        if ($period && $period->status !== 'open') {
            abort(422, 'Periode akuntansi sudah ditutup atau sedang dalam proses closing. Penjualan tidak dapat diubah.');
        }
    }

    private function assertSalePeriodsOpen(string ...$dates): void
    {
        foreach (array_unique($dates) as $date) {
            $this->assertSalePeriodOpen($date);
        }
    }

    public function postJournal(int $id)
    {
        $sale = DB::table('sales')->where('id', $id)->where('entity_id', $this->entityId())->first();
        abort_unless($sale, 404);
        $this->assertSalePeriodOpen(CarbonCarbon::parse($sale->sale_date)->toDateString());

        $journalId = app(SalesJournalService::class)->post($id, $this->entityId());

        return back()->with('success', 'Jurnal penjualan berhasil diposting.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sale_date' => ['required', 'date_format:Y-m-d'],
            'customer_id' => ['nullable', 'integer'],
            'business_unit_id' => ['required', 'integer'],
            'payment_method' => ['required', 'in:Tunai,Transfer,QRIS,Kredit / Bon'],
            'due_date' => ['nullable', 'date', 'required_if:payment_method,Kredit / Bon'],
            'memo' => ['nullable', 'string', 'max:5000'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.unit_id' => ['nullable', 'integer'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.selling_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $entity = $this->entityId();

        $unit = DB::table('business_units')
            ->where('id', $data['business_unit_id'])
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->first();
        abort_unless($unit, 422, 'Business Unit tidak valid.');

        if (!empty($data['customer_id'])) {
            $customer = DB::table('customers')
                ->where('id', $data['customer_id'])
                ->where('entity_id', $entity)
                ->where('is_active', 1)
                ->first();
            abort_unless($customer, 422, 'Customer tidak valid.');
        }

        $saleDate = \Carbon\Carbon::createFromFormat('Y-m-d', $data['sale_date'])->startOfDay();
        $this->assertSalePeriodOpen($saleDate->toDateString());
        $prefix = $this->salesPrefix($unit);
        $lockName = 'sales_no:' . $entity . ':' . $prefix . ':' . $saleDate->format('Ym');
        $lock = DB::selectOne('SELECT GET_LOCK(?, 10) AS locked', [$lockName]);

        abort_unless((int) ($lock->locked ?? 0) === 1, 503, 'Nomor penjualan sedang diproses. Silakan coba lagi.');

        try {
            $saleId = DB::transaction(function () use ($data, $entity, $unit, $saleDate): int {
                $invoiceNo = $this->nextInvoiceNo($entity, $unit, $saleDate);
            $subtotal = 0.0;
            $lineItems = [];

            foreach ($data['items'] as $item) {
                $product = DB::table('products')
                    ->where('id', $item['product_id'])
                    ->where('entity_id', $entity)
                    ->where('is_active', 1)
                    ->first();
                abort_unless($product, 422, 'Item tidak valid.');

                $uom = $this->resolveProductUnit(
                    $product->id,
                    isset($item['unit_id']) ? (int) $item['unit_id'] : null,
                    $entity
                );

                $transactionQty = (float) $item['qty'];
                // Harga transaksi mengikuti harga yang dikirim dari frontend.
                // Backend tetap memvalidasi angka dan tidak mengambil ulang harga master.
                $transactionPrice = (float) $item['selling_price'];
                $baseQty = round($transactionQty * $uom['factor'], 9);
                $lineDiscount = min((float) ($item['discount'] ?? 0), $transactionQty * $transactionPrice);
                $lineTotal = round(($transactionQty * $transactionPrice) - $lineDiscount, 2);

                $stock = DB::table('warehouses_stocks as ws')
                    ->join('warehouses as w', 'w.id', '=', 'ws.warehouse_id')
                    ->where('ws.entity_id', $entity)
                    ->where('ws.product_id', $product->id)
                    ->where('w.business_unit_id', $unit->id)
                    ->lockForUpdate()
                    ->select('ws.*')
                    ->first();

                abort_unless($stock && (float) $stock->qty >= $baseQty, 422, 'Stok '.$product->name.' tidak mencukupi.');

                $hppUnit = (float) $stock->avg_cost;
                $hppTotal = round($baseQty * $hppUnit, 2);

                $subtotal += $lineTotal;

                $lineItems[] = [
                    'product' => $product,
                    'uom' => $uom,
                    'qty' => $transactionQty,
                    'base_qty' => $baseQty,
                    'price' => $transactionPrice,
                    'discount' => $lineDiscount,
                    'total' => $lineTotal,
                    'stock' => $stock,
                    'hpp_unit' => $hppUnit,
                    'hpp_total' => $hppTotal,
                ];
            }

            $subtotal = round($subtotal, 2);
            $discount = round(min((float) ($data['discount'] ?? 0), $subtotal), 2);
            $total = round($subtotal - $discount, 2);

            $saleId = DB::table('sales')->insertGetId([
                'entity_id' => $entity,
                'business_unit_id' => $unit->id,
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => auth()->id(),
                'invoice_no' => $invoiceNo,
                'sale_date' => $saleDate,
                'due_date' => $data['payment_method'] === 'Kredit / Bon' ? $data['due_date'] : null,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'status' => 'posted',
                'memo' => $data['memo'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($lineItems as $line) {
                DB::table('sale_items')->insert([
                    'sale_id' => $saleId,
                    'product_id' => $line['product']->id,
                    'unit_id' => $line['uom']['unit_id'],
                    'qty' => $line['qty'],
                    'conversion_factor' => $line['uom']['factor'],
                    'base_qty' => $line['base_qty'],
                    'unit_price' => $line['price'],
                    'base_unit_cost' => $line['hpp_unit'],
                    'discount' => $line['discount'],
                    'total' => $line['total'],
                    'hpp_unit' => $line['hpp_unit'],
                    'hpp_total' => $line['hpp_total'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('warehouses_stocks')->where('id', $line['stock']->id)->update([
                    'qty' => (float) $line['stock']->qty - $line['base_qty'],
                    'updated_at' => now(),
                ]);

                DB::table('stock_movements')->insert([
                    'entity_id' => $entity,
                    'business_unit_id' => $unit->id,
                    'warehouse_id' => $line['stock']->warehouse_id,
                    'product_id' => $line['product']->id,
                    'unit_id' => $line['uom']['unit_id'],
                    'transaction_qty' => $line['qty'],
                    'conversion_factor' => $line['uom']['factor'],
                    'movement_type' => 'sale_out',
                    'qty' => -$line['base_qty'],
                    'unit_cost' => $line['hpp_unit'],
                    'reference_type' => 'sale',
                    'reference_id' => $saleId,
                    'occurred_at' => $saleDate,
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($data['payment_method'] === 'Kredit / Bon') {
                DB::table('payments')->insert([
                    'entity_id' => $entity,
                    'business_unit_id' => $unit->id,
                    'sale_id' => $saleId,
                    'user_id' => auth()->id(),
                    'payment_date' => $saleDate,
                    'method' => 'credit',
                    'amount' => 0,
                    'paid_amount' => 0,
                    'change_amount' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('payments')->insert([
                    'entity_id' => $entity,
                    'business_unit_id' => $unit->id,
                    'sale_id' => $saleId,
                    'user_id' => auth()->id(),
                    'payment_date' => now(),
                    'method' => match ($data['payment_method']) {
                        'Tunai' => 'cash',
                        'Transfer' => 'transfer',
                        'QRIS' => 'qris',
                        default => throw new \RuntimeException('Metode pembayaran tidak valid.'),
                    },
                    'amount' => $total,
                    'paid_amount' => $total,
                    'change_amount' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            app(SalesJournalService::class)->post($saleId, $entity);

                return $saleId;
            });
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'sale_id' => $saleId,
                'redirect' => route('inventori.penjualan.show', $saleId),
            ]);
        }

        return redirect()->route('inventori.penjualan.show', $saleId)->with('success', 'Penjualan berhasil diposting.');
    }

    public function edit(int $id)
    {
        $entity = $this->entityId();
        $sale = DB::table('sales')->where('id', $id)->where('entity_id', $entity)->first();
        abort_unless($sale, 404);
        abort_if($sale->status !== 'posted', 422, 'Hanya penjualan yang masih diposting yang dapat diedit.');
        $this->assertSalePeriodOpen(\Carbon\Carbon::parse($sale->sale_date)->toDateString());

        $user = auth()->user();
        $units = DB::table('business_units as bu')
            ->where('bu.entity_id', $entity)->where('bu.is_active', 1)
            ->when($user->role !== 'owner', function ($query) use ($user) {
                $query->join('user_business_units as ubu', 'ubu.business_unit_id', '=', 'bu.id')
                    ->where('ubu.user_id', $user->id);
            })->orderBy('bu.name')->get(['bu.id', 'bu.code', 'bu.name', 'bu.business_type']);

        $customers = DB::table('customers as c')
            ->where('c.entity_id', $entity)
            ->where('c.is_active', 1)
            ->leftJoin(DB::raw('(SELECT s.customer_id, SUM(GREATEST(s.total - COALESCE(p.paid_amount,0),0)) AS outstanding
                FROM sales s
                LEFT JOIN (SELECT sale_id, SUM(COALESCE(paid_amount,0)) paid_amount FROM payments GROUP BY sale_id) p ON p.sale_id=s.id
                WHERE s.entity_id='.$entity.' AND s.customer_id IS NOT NULL
                AND EXISTS (SELECT 1 FROM payments cp WHERE cp.sale_id=s.id AND cp.method="credit")
                GROUP BY s.customer_id) ob'), 'ob.customer_id', '=', 'c.id')
            ->orderBy('c.name')
            ->get(['c.id', 'c.name', DB::raw('COALESCE(ob.outstanding,0) as outstanding')]);

        $products = DB::table('products as p')
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->where('p.entity_id', $entity)->where('p.is_active', 1)
            ->select('p.id','p.code','p.sku','p.barcode','p.name','p.base_unit_id','u.code as base_unit_code','u.name as base_unit_name')
            ->orderBy('p.name')->get();

        $productionProductIds = $units
            ->filter(fn ($unit) => $unit->business_type === 'production')
            ->mapWithKeys(fn ($unit) => [$unit->id => $this->productionProductIds($entity, (int) $unit->id)])
            ->all();

        $productPrices = DB::table('product_prices')
            ->whereIn('product_id', $products->pluck('id'))->whereIn('business_unit_id', $units->pluck('id'))
            ->where('price_type','retail')->get(['product_id','business_unit_id','unit_id','selling_price'])->groupBy('product_id');

        $productConversions = DB::table('product_unit_conversions as puc')
            ->join('units as u','u.id','=','puc.unit_id')
            ->whereIn('puc.product_id',$products->pluck('id'))->where('puc.is_active',1)
            ->orderBy('u.name')->get(['puc.product_id','puc.unit_id','puc.conversion_factor','puc.is_default_sale','u.code','u.name'])
            ->groupBy('product_id');

        $productCatalog = $products->map(function ($product) use ($productPrices, $productConversions) {
            return [
                'id'=>(int)$product->id,'code'=>$product->code,'sku'=>$product->sku,'barcode'=>$product->barcode,
                'name'=>$product->name,'base_unit_id'=>(int)$product->base_unit_id,
                'base_unit_code'=>$product->base_unit_code,'base_unit_name'=>$product->base_unit_name,
                'prices'=>($productPrices[$product->id] ?? collect())->values(),
                'conversions'=>($productConversions[$product->id] ?? collect())->values(),
            ];
        })->values();

        $saleItems = DB::table('sale_items')->where('sale_id',$sale->id)->orderBy('id')
            ->get(['product_id','unit_id','qty','unit_price','discount']);

        $payments = DB::table('payments')->where('sale_id',$sale->id)->orderBy('id')->get();
        $paymentMethod = match ($payments->first()->method ?? 'cash') {
            'cash'=>'Tunai','transfer'=>'Transfer','qris'=>'QRIS','credit'=>'Kredit / Bon',default=>'Tunai',
        };

        $initialItems = $saleItems->map(fn ($item) => [
            'product_id'=>(int)$item->product_id,'unit_id'=>(int)$item->unit_id,
            'qty'=>(float)$item->qty,'price'=>(float)$item->unit_price,'discount'=>(float)$item->discount,
        ])->values();

        return view('inventori.penjualan.tempo.edit', compact(
            'sale','units','customers','productCatalog','initialItems','paymentMethod','productionProductIds'
        ));
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'sale_date'=>['required','date_format:Y-m-d'],'customer_id'=>['nullable','integer'],
            'business_unit_id'=>['required','integer'],'payment_method'=>['required','in:Tunai,Transfer,QRIS,Kredit / Bon'],
            'due_date'=>['nullable','date','required_if:payment_method,Kredit / Bon'],
            'memo'=>['nullable','string','max:5000'],'discount'=>['nullable','numeric','min:0'],
            'items'=>['required','array','min:1'],'items.*.product_id'=>['required','integer'],
            'items.*.unit_id'=>['nullable','integer'],'items.*.qty'=>['required','numeric','gt:0'],
            'items.*.selling_price'=>['required','numeric','min:0'],'items.*.discount'=>['nullable','numeric','min:0'],
        ]);

        $entity=$this->entityId();
        $newDate=\Carbon\Carbon::createFromFormat('Y-m-d',$data['sale_date'])->startOfDay();

        DB::transaction(function () use ($data,$id,$entity,$newDate) {
            $sale=DB::table('sales')->where('id',$id)->where('entity_id',$entity)->lockForUpdate()->first();
            abort_unless($sale,404);
            abort_if($sale->status!=='posted',422,'Penjualan sudah dibatalkan dan tidak dapat diedit.');

            $oldDate=\Carbon\Carbon::parse($sale->sale_date)->toDateString();
            $this->assertSalePeriodsOpen($oldDate,$newDate->toDateString());

            $unit=DB::table('business_units')->where('id',$data['business_unit_id'])->where('entity_id',$entity)->where('is_active',1)->first();
            abort_unless($unit,422,'Business Unit tidak valid.');

            if(!empty($data['customer_id'])){
                abort_unless(DB::table('customers')->where('id',$data['customer_id'])->where('entity_id',$entity)->where('is_active',1)->exists(),422,'Customer tidak valid.');
            }

            $oldPayments=DB::table('payments')->where('sale_id',$id)->lockForUpdate()->get();
            $paidAmount=(float)$oldPayments->sum(fn($p)=>(float)$p->paid_amount);
            $oldMethod=$oldPayments->first()->method ?? 'cash';
            $newMethod=match($data['payment_method']){
                'Tunai'=>'cash','Transfer'=>'transfer','QRIS'=>'qris','Kredit / Bon'=>'credit',
            };
            if($paidAmount>0 && $oldMethod!==$newMethod){
                abort(422,'Cara bayar tidak dapat diubah karena transaksi sudah memiliki pembayaran.');
            }

            $oldMovements=DB::table('stock_movements')->where('reference_type','sale')->where('reference_id',$id)->lockForUpdate()->get();
            foreach($oldMovements as $movement){
                $stock=DB::table('warehouses_stocks')->where('entity_id',$entity)->where('warehouse_id',$movement->warehouse_id)->where('product_id',$movement->product_id)->lockForUpdate()->first();
                abort_unless($stock,422,'Stok transaksi lama tidak ditemukan untuk proses koreksi.');
                DB::table('warehouses_stocks')->where('id',$stock->id)->update(['qty'=>(float)$stock->qty+abs((float)$movement->qty),'updated_at'=>now()]);
            }
            DB::table('stock_movements')->where('reference_type','sale')->where('reference_id',$id)->delete();

            $subtotal=0.0; $lineItems=[];
            foreach($data['items'] as $item){
                $product=DB::table('products')->where('id',$item['product_id'])->where('entity_id',$entity)->where('is_active',1)->first();
                abort_unless($product,422,'Item tidak valid.');
                $uom=$this->resolveProductUnit($product->id,isset($item['unit_id'])?(int)$item['unit_id']:null,$entity);
                $qty=(float)$item['qty']; $price=(float)$item['selling_price'];
                $lineDiscount=min((float)($item['discount']??0),$qty*$price);
                $lineTotal=round(($qty*$price)-$lineDiscount,2); $baseQty=round($qty*$uom['factor'],9);

                $stock=DB::table('warehouses_stocks as ws')->join('warehouses as w','w.id','=','ws.warehouse_id')
                    ->where('ws.entity_id',$entity)->where('ws.product_id',$product->id)->where('w.business_unit_id',$unit->id)
                    ->lockForUpdate()->select('ws.*')->first();
                abort_unless($stock && (float)$stock->qty >= $baseQty,422,'Stok '.$product->name.' tidak mencukupi.');
                $hppUnit=(float)$stock->avg_cost; $hppTotal=round($baseQty*$hppUnit,2); $subtotal+=$lineTotal;
                $lineItems[]=compact('product','uom','qty','price','lineDiscount','lineTotal','baseQty','stock','hppUnit','hppTotal');
            }

            $subtotal=round($subtotal,2);
            $discount=round(min((float)($data['discount']??0),$subtotal),2);
            $total=round($subtotal-$discount,2);
            $nonCreditPaid=(float)$oldPayments->where('method','!=','credit')->sum(fn($p)=>(float)$p->paid_amount);
            abort_if($total<$nonCreditPaid,422,'Total baru tidak boleh lebih kecil dari jumlah yang sudah dibayar.');

            DB::table('sale_items')->where('sale_id',$id)->delete();
            foreach($lineItems as $line){
                DB::table('sale_items')->insert([
                    'sale_id'=>$id,'product_id'=>$line['product']->id,'unit_id'=>$line['uom']['unit_id'],'qty'=>$line['qty'],
                    'conversion_factor'=>$line['uom']['factor'],'base_qty'=>$line['baseQty'],'unit_price'=>$line['price'],
                    'base_unit_cost'=>$line['hppUnit'],'discount'=>$line['lineDiscount'],'total'=>$line['lineTotal'],
                    'hpp_unit'=>$line['hppUnit'],'hpp_total'=>$line['hppTotal'],'created_at'=>now(),'updated_at'=>now(),
                ]);
                DB::table('warehouses_stocks')->where('id',$line['stock']->id)->update(['qty'=>(float)$line['stock']->qty-$line['baseQty'],'updated_at'=>now()]);
                DB::table('stock_movements')->insert([
                    'entity_id'=>$entity,'business_unit_id'=>$unit->id,'warehouse_id'=>$line['stock']->warehouse_id,'product_id'=>$line['product']->id,
                    'unit_id'=>$line['uom']['unit_id'],'transaction_qty'=>$line['qty'],'conversion_factor'=>$line['uom']['factor'],
                    'movement_type'=>'sale_out','qty'=>-$line['baseQty'],'unit_cost'=>$line['hppUnit'],'reference_type'=>'sale','reference_id'=>$id,
                    'occurred_at'=>$newDate,'created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now(),
                ]);
            }

            DB::table('sales')->where('id',$id)->update([
                'business_unit_id'=>$unit->id,'customer_id'=>$data['customer_id']??null,'sale_date'=>$newDate,
                'due_date'=>$newMethod==='credit'?$data['due_date']:null,'subtotal'=>$subtotal,'discount'=>$discount,'total'=>$total,
                'memo'=>$data['memo']??null,'updated_at'=>now(),
            ]);

            $primary=$oldPayments->first();
            if($primary){
                $update=['business_unit_id'=>$unit->id,'method'=>$newMethod,'updated_at'=>now()];
                if($newMethod==='credit'){
                    $update['payment_date']=$newDate;$update['amount']=0;$update['paid_amount']=0;$update['change_amount']=0;
                }else{
                    $update['amount']=$total;$update['paid_amount']=$total;$update['change_amount']=0;
                }
                DB::table('payments')->where('id',$primary->id)->update($update);
            }else{
                DB::table('payments')->insert([
                    'entity_id'=>$entity,'business_unit_id'=>$unit->id,'sale_id'=>$id,'user_id'=>auth()->id(),'payment_date'=>$newDate,
                    'method'=>$newMethod,'amount'=>$newMethod==='credit'?0:$total,'paid_amount'=>$newMethod==='credit'?0:$total,'change_amount'=>0,
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
            }

            app(SalesJournalService::class)->post($id,$entity);
        });

        return response()->json(['ok'=>true,'sale_id'=>$id,'redirect'=>route('inventori.penjualan.show',$id)]);
    }

    public function destroy(int $id)
    {
        $entity=$this->entityId();
        $result=DB::transaction(function() use($id,$entity){
            $sale=DB::table('sales')->where('id',$id)->where('entity_id',$entity)->lockForUpdate()->first();
            abort_unless($sale,404); abort_if($sale->status==='cancelled',422,'Penjualan sudah dibatalkan.');
            $this->assertSalePeriodOpen(\Carbon\Carbon::parse($sale->sale_date)->toDateString());

            $payments=DB::table('payments')->where('sale_id',$id)->lockForUpdate()->get();
            $paid=(float)$payments->sum(fn($p)=>(float)$p->paid_amount);
            if($paid>0){ return 'paid'; }

            $movements=DB::table('stock_movements')->where('reference_type','sale')->where('reference_id',$id)->lockForUpdate()->get();
            foreach($movements as $movement){
                $stock=DB::table('warehouses_stocks')->where('entity_id',$entity)->where('warehouse_id',$movement->warehouse_id)->where('product_id',$movement->product_id)->lockForUpdate()->first();
                abort_unless($stock,422,'Stok transaksi tidak ditemukan untuk pembatalan.');
                DB::table('warehouses_stocks')->where('id',$stock->id)->update(['qty'=>(float)$stock->qty+abs((float)$movement->qty),'updated_at'=>now()]);
            }
            DB::table('stock_movements')->where('reference_type','sale')->where('reference_id',$id)->delete();

            $journal=DB::table('journals')->where('entity_id',$entity)->where('source_type','sale')->where('source_id',$id)->lockForUpdate()->first();
            if($journal){
                DB::table('journal_entries')->where('journal_id',$journal->id)->delete();
                DB::table('journals')->where('id',$journal->id)->delete();
            }

            DB::table('sales')->where('id',$id)->update(['status'=>'cancelled','updated_at'=>now()]);
            DB::table('payments')->where('sale_id',$id)->where('paid_amount',0)->delete();
        });
        if($result==='paid') return back()->with('error','Hapus gagal! Penjualan sudah memiliki pembayaran.');
        return back()->with('success','Penjualan berhasil dibatalkan dan efek stok/jurnal telah dibalik.');
    }

    public function show($id)
    {
        $id = (int) $id;
        $entity = $this->entityId();

        $sale = DB::table('sales as s')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 's.business_unit_id')
            ->where('s.entity_id', $entity)
            ->where('s.id', $id)
            ->select('s.*', 'c.name as customer_name', 'c.phone as customer_phone', 'c.address as customer_address', 'bu.name as unit_name')
            ->first();

        abort_unless($sale, 404);

        $items = DB::table('sale_items as si')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'si.unit_id')
            ->leftJoin('units as bu', 'bu.id', '=', 'p.base_unit_id')
            ->where('si.sale_id', $sale->id)
            ->select(
                'p.code',
                'p.name',
                'u.code as transaction_unit_code',
                'bu.code as base_unit_code',
                'si.qty',
                'si.conversion_factor',
                'si.base_qty',
                'si.unit_price',
                'si.discount',
                'si.total',
                'si.hpp_unit',
                'si.hpp_total'
            )
            ->get();

        $payments = DB::table('payments')->where('sale_id', $sale->id)->orderBy('id')->get();

        $previousReceivable = 0;
        if ($sale->customer_id) {
            $previousReceivable = (float) (
                DB::table('sales as ps')
                    ->where('ps.entity_id', $entity)
                    ->where('ps.customer_id', $sale->customer_id)
                    ->where('ps.id', '<', $sale->id)
                    ->whereExists(function ($q) {
                        $q->select(DB::raw(1))
                            ->from('payments as cp')
                            ->whereColumn('cp.sale_id', 'ps.id')
                            ->where('cp.method', 'credit');
                    })
                    ->leftJoin(DB::raw('(SELECT sale_id, SUM(COALESCE(paid_amount,0)) paid_amount FROM payments GROUP BY sale_id) pp'), 'pp.sale_id', '=', 'ps.id')
                    ->sum(DB::raw('GREATEST(ps.total - COALESCE(pp.paid_amount,0),0)'))
            );
        }

        return view('inventori.penjualan.tempo.show', compact('sale', 'items', 'payments', 'previousReceivable'));
    }

    public function print(int $id)
    {
        return $this->show($id);
    }

    public function create()
    {
        $entity = $this->entityId();
        $user = auth()->user();

        $units = DB::table('business_units as bu')
            ->where('bu.entity_id', $entity)
            ->where('bu.is_active', 1)
            ->when($user->role !== 'owner', function ($query) use ($user) {
                $query->join('user_business_units as ubu', 'ubu.business_unit_id', '=', 'bu.id')
                    ->where('ubu.user_id', $user->id);
            })
            ->orderBy('bu.name')
            ->get(['bu.id', 'bu.code', 'bu.name', 'bu.business_type']);

        $defaultUnitId = $user->default_business_unit_id;
        if ($defaultUnitId && !$units->contains('id', (int) $defaultUnitId)) {
            $defaultUnitId = $units->first()->id ?? null;
        }

        $customers = DB::table('customers as c')
            ->where('c.entity_id', $entity)
            ->where('c.is_active', 1)
            ->leftJoin(DB::raw('(SELECT s.customer_id, SUM(GREATEST(s.total - COALESCE(p.paid_amount,0),0)) AS outstanding
                FROM sales s
                LEFT JOIN (SELECT sale_id, SUM(COALESCE(paid_amount,0)) paid_amount FROM payments GROUP BY sale_id) p ON p.sale_id=s.id
                WHERE s.entity_id='.$entity.' AND s.customer_id IS NOT NULL
                AND EXISTS (SELECT 1 FROM payments cp WHERE cp.sale_id=s.id AND cp.method="credit")
                GROUP BY s.customer_id) ob'), 'ob.customer_id', '=', 'c.id')
            ->orderBy('c.name')
            ->get(['c.id', 'c.name', DB::raw('COALESCE(ob.outstanding,0) as outstanding')]);

        $products = DB::table('products as p')
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->where('p.entity_id', $entity)
            ->where('p.is_active', 1)
            ->select('p.id', 'p.code', 'p.sku', 'p.barcode', 'p.name', 'p.base_unit_id', 'u.code as base_unit_code', 'u.name as base_unit_name')
            ->orderBy('p.name')
            ->get();

        $productionProductIds = $units
            ->filter(fn ($unit) => $unit->business_type === 'production')
            ->mapWithKeys(fn ($unit) => [$unit->id => $this->productionProductIds($entity, (int) $unit->id)])
            ->all();

        $productPrices = DB::table('product_prices')
            ->whereIn('product_id', $products->pluck('id'))
            ->whereIn('business_unit_id', $units->pluck('id'))
            ->where('price_type', 'retail')
            ->get(['product_id', 'business_unit_id', 'unit_id', 'selling_price'])
            ->groupBy('product_id');

        $productConversions = DB::table('product_unit_conversions as puc')
            ->join('units as u', 'u.id', '=', 'puc.unit_id')
            ->whereIn('puc.product_id', $products->pluck('id'))
            ->where('puc.is_active', 1)
            ->orderBy('u.name')
            ->get(['puc.product_id', 'puc.unit_id', 'puc.conversion_factor', 'puc.is_default_sale', 'u.code', 'u.name'])
            ->groupBy('product_id');

        $productCatalog = $products->map(function ($product) use ($productPrices, $productConversions) {
            return [
                'id' => (int) $product->id,
                'code' => $product->code,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'base_unit_id' => (int) $product->base_unit_id,
                'base_unit_code' => $product->base_unit_code,
                'base_unit_name' => $product->base_unit_name,
                'prices' => ($productPrices[$product->id] ?? collect())->values(),
                'conversions' => ($productConversions[$product->id] ?? collect())->values(),
            ];
        })->values();

        return view('inventori.penjualan.tempo.create', compact(
            'units',
            'defaultUnitId',
            'customers',
            'productCatalog',
            'productionProductIds'
        ));
    }
}