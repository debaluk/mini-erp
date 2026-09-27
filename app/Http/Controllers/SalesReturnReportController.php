<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SalesReturnReportController extends Controller
{
    private function entityId(): int
    {
        return (int) (DB::table('entities')->value('id') ?? 1);
    }

    private function period(Request $request): array
    {
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

        return [$startDate, $endDate, $businessUnitId];
    }

    /**
     * Base return-item query.
     *
     * Analytics uses base_qty and return_value from the return engine.
     */
    private function returnItemsQuery(
        int $entity,
        string $startDate,
        string $endDate,
        ?int $businessUnitId = null
    ) {
        return DB::table('sales_return_items as sri')
            ->join('sales_returns as sr', 'sr.id', '=', 'sri.sales_return_id')
            ->join('sales as s', 's.id', '=', 'sr.sale_id')
            ->leftJoin('customers as c', 'c.id', '=', 'sr.customer_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'sr.business_unit_id')
            ->leftJoin('products as p', 'p.id', '=', 'sri.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'sri.unit_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'sr.warehouse_id')
            ->where('sr.entity_id', $entity)
            ->whereDate('sr.return_date', '>=', $startDate)
            ->whereDate('sr.return_date', '<=', $endDate)
            ->when($businessUnitId, fn ($q) =>
                $q->where('sr.business_unit_id', $businessUnitId)
            );
    }

    /**
     * Return-level query.
     */
    private function returnsQuery(
        int $entity,
        string $startDate,
        string $endDate,
        ?int $businessUnitId = null
    ) {
        return DB::table('sales_returns as sr')
            ->join('sales as s', 's.id', '=', 'sr.sale_id')
            ->leftJoin('customers as c', 'c.id', '=', 'sr.customer_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'sr.business_unit_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'sr.warehouse_id')
            ->where('sr.entity_id', $entity)
            ->whereDate('sr.return_date', '>=', $startDate)
            ->whereDate('sr.return_date', '<=', $endDate)
            ->when($businessUnitId, fn ($q) =>
                $q->where('sr.business_unit_id', $businessUnitId)
            );
    }

    /**
     * Determine refund classification using the same principle as
     * SalesReturnController::store():
     * - fully paid + cash method => cash
     * - fully paid + transfer/qris => bank
     * - otherwise => receivable
     *
     * First payment is intentionally used to mirror the current engine.
     */
    private function refundClassification(int $saleId): string
    {
        $sale = DB::table('sales')
            ->where('id', $saleId)
            ->first(['total']);

        if (!$sale) {
            return 'receivable';
        }

        $paid = (float) DB::table('payments')
            ->where('sale_id', $saleId)
            ->sum('amount');

        if ($paid < (float) $sale->total) {
            return 'receivable';
        }

        $payment = DB::table('payments')
            ->where('sale_id', $saleId)
            ->orderBy('id')
            ->first(['method']);

        if (!$payment) {
            return 'receivable';
        }

        return in_array(strtolower((string) $payment->method), [
            'transfer',
            'qris',
            'bank',
        ], true)
            ? 'bank'
            : 'cash';
    }

    public function index(Request $request)
    {
        $entity = $this->entityId();

        [$startDate, $endDate, $businessUnitId] = $this->period($request);

        $units = DB::table('business_units')
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        /*
         * ---------------------------------------------------------------
         * KPI
         * ---------------------------------------------------------------
         */
        $summary = $this->returnItemsQuery(
            $entity,
            $startDate,
            $endDate,
            $businessUnitId
        )
            ->selectRaw('
                COUNT(DISTINCT sr.id) as total_returns,
                COALESCE(SUM(sri.return_value), 0) as return_value,
                COALESCE(SUM(
                    CASE WHEN sri.condition = "good"
                    THEN sri.hpp_total ELSE 0 END
                ), 0) as hpp_return,
                COALESCE(SUM(
                    CASE WHEN sri.condition = "reject"
                    THEN sri.hpp_total ELSE 0 END
                ), 0) as reject_loss,
                COALESCE(SUM(
                    CASE WHEN sri.condition = "good"
                    THEN sri.base_qty ELSE 0 END
                ), 0) as good_qty,
                COALESCE(SUM(
                    CASE WHEN sri.condition = "reject"
                    THEN sri.base_qty ELSE 0 END
                ), 0) as reject_qty
            ')
            ->first();

        $returnValue = (float) ($summary->return_value ?? 0);
        $hppReturn = (float) ($summary->hpp_return ?? 0);
        $rejectLoss = (float) ($summary->reject_loss ?? 0);

        $refundCash = 0.0;
        $refundBank = 0.0;
        $refundReceivable = 0.0;

        $returnRows = $this->returnsQuery(
            $entity,
            $startDate,
            $endDate,
            $businessUnitId
        )
            ->select(
                'sr.id',
                'sr.sale_id',
                'sr.total'
            )
            ->get();

        foreach ($returnRows as $return) {
            $classification = $this->refundClassification((int) $return->sale_id);

            if ($classification === 'cash') {
                $refundCash += (float) $return->total;
            } elseif ($classification === 'bank') {
                $refundBank += (float) $return->total;
            } else {
                $refundReceivable += (float) $return->total;
            }
        }

        $grossProfitImpact = $returnValue - $hppReturn;

        /*
         * ---------------------------------------------------------------
         * RETURN PER BUSINESS UNIT
         * ---------------------------------------------------------------
         */
        $byBusinessUnit = $this->returnItemsQuery(
            $entity,
            $startDate,
            $endDate,
            $businessUnitId
        )
            ->select(
                'sr.business_unit_id',
                'bu.name as business_unit_name'
            )
            ->selectRaw('
                COUNT(DISTINCT sr.id) as return_count,
                COALESCE(SUM(sri.return_value), 0) as return_value,
                COALESCE(SUM(
                    CASE WHEN sri.condition = "good"
                    THEN sri.hpp_total ELSE 0 END
                ), 0) as hpp_return,
                COALESCE(SUM(
                    CASE WHEN sri.condition = "reject"
                    THEN sri.hpp_total ELSE 0 END
                ), 0) as reject_loss
            ')
            ->groupBy('sr.business_unit_id', 'bu.name')
            ->orderByDesc('return_value')
            ->get();

        /*
         * ---------------------------------------------------------------
         * TOP RETURNED PRODUCTS
         * ---------------------------------------------------------------
         */
        $topProducts = $this->returnItemsQuery(
            $entity,
            $startDate,
            $endDate,
            $businessUnitId
        )
            ->select(
                'sri.product_id',
                'p.name as product_name'
            )
            ->selectRaw('
                COALESCE(SUM(sri.base_qty), 0) as qty,
                COALESCE(SUM(sri.return_value), 0) as return_value,
                COALESCE(SUM(
                    CASE WHEN sri.condition = "good"
                    THEN sri.hpp_total ELSE 0 END
                ), 0) as hpp_good,
                COALESCE(SUM(
                    CASE WHEN sri.condition = "reject"
                    THEN sri.hpp_total ELSE 0 END
                ), 0) as reject_loss
            ')
            ->groupBy('sri.product_id', 'p.name')
            ->orderByDesc('qty')
            ->limit(20)
            ->get();

        /*
         * ---------------------------------------------------------------
         * GOOD VS REJECT
         * ---------------------------------------------------------------
         */
        $conditionSummary = $this->returnItemsQuery(
            $entity,
            $startDate,
            $endDate,
            $businessUnitId
        )
            ->select('sri.condition')
            ->selectRaw('
                COALESCE(SUM(sri.base_qty), 0) as qty,
                COALESCE(SUM(sri.return_value), 0) as return_value,
                COALESCE(SUM(sri.hpp_total), 0) as hpp
            ')
            ->groupBy('sri.condition')
            ->get();

        /*
         * ---------------------------------------------------------------
         * TREND
         * ---------------------------------------------------------------
         */
        $trend = $this->returnItemsQuery(
            $entity,
            $startDate,
            $endDate,
            $businessUnitId
        )
            ->selectRaw('DATE(sr.return_date) as period')
            ->selectRaw('COUNT(DISTINCT sr.id) as return_count')
            ->selectRaw('COALESCE(SUM(sri.return_value), 0) as return_value')
            ->selectRaw('COALESCE(SUM(sri.base_qty), 0) as qty')
            ->groupByRaw('DATE(sr.return_date)')
            ->orderBy('period')
            ->get();

        /*
         * ---------------------------------------------------------------
         * DETAIL
         * ---------------------------------------------------------------
         */
        $detail = $this->returnItemsQuery(
            $entity,
            $startDate,
            $endDate,
            $businessUnitId
        )
            ->select(
                'sr.id',
                'sr.return_no',
                'sr.return_date',
                's.invoice_no',
                'c.name as customer_name',
                'bu.name as business_unit_name',
                'p.name as product_name',
                'u.name as unit_name',
                'sri.qty',
                'sri.base_qty',
                'sri.return_value',
                'sri.hpp_total',
                'sri.condition',
                'w.name as warehouse_name',
                'sr.status'
            )
            ->orderByDesc('sr.return_date')
            ->orderByDesc('sr.id')
            ->orderByDesc('sri.id')
            ->paginate(25)
            ->withQueryString();

        $entityName = DB::table('entities')
            ->where('id', $entity)
            ->value('name');

        $selectedUnitName = $businessUnitId
            ? optional($units->firstWhere('id', $businessUnitId))->name
            : null;

        return view('inventori.laporan.retur-penjualan', compact(
            'startDate',
            'endDate',
            'businessUnitId',
            'units',
            'entityName',
            'selectedUnitName',
            'summary',
            'returnValue',
            'hppReturn',
            'rejectLoss',
            'grossProfitImpact',
            'refundCash',
            'refundBank',
            'refundReceivable',
            'byBusinessUnit',
            'topProducts',
            'conditionSummary',
            'trend',
            'detail'
        ));
    }

    public function exportExcel(Request $request)
    {
        $entity = $this->entityId();

        [$startDate, $endDate, $businessUnitId] = $this->period($request);

        /*
         * ---------------------------------------------------------------
         * DATA 1: SUMMARY
         * ---------------------------------------------------------------
         */
        $summary = $this->returnItemsQuery(
            $entity,
            $startDate,
            $endDate,
            $businessUnitId
        )
            ->selectRaw('
                COUNT(DISTINCT sr.id) as total_returns,
                COALESCE(SUM(sri.return_value), 0) as return_value,
                COALESCE(SUM(
                    CASE WHEN sri.condition = "good"
                    THEN sri.hpp_total ELSE 0 END
                ), 0) as hpp_return,
                COALESCE(SUM(
                    CASE WHEN sri.condition = "reject"
                    THEN sri.hpp_total ELSE 0 END
                ), 0) as reject_loss,
                COALESCE(SUM(
                    CASE WHEN sri.condition = "good"
                    THEN sri.base_qty ELSE 0 END
                ), 0) as good_qty,
                COALESCE(SUM(
                    CASE WHEN sri.condition = "reject"
                    THEN sri.base_qty ELSE 0 END
                ), 0) as reject_qty
            ')
            ->first();

        $refundCash = 0.0;
        $refundBank = 0.0;
        $refundReceivable = 0.0;

        $returnRows = $this->returnsQuery(
            $entity,
            $startDate,
            $endDate,
            $businessUnitId
        )
            ->select('sr.sale_id', 'sr.total')
            ->get();

        foreach ($returnRows as $return) {
            $classification = $this->refundClassification((int) $return->sale_id);

            if ($classification === 'cash') {
                $refundCash += (float) $return->total;
            } elseif ($classification === 'bank') {
                $refundBank += (float) $return->total;
            } else {
                $refundReceivable += (float) $return->total;
            }
        }

        $returnValue = (float) ($summary->return_value ?? 0);
        $hppReturn = (float) ($summary->hpp_return ?? 0);
        $rejectLoss = (float) ($summary->reject_loss ?? 0);

        /*
         * ---------------------------------------------------------------
         * DATA 2: ANALYSIS
         * ---------------------------------------------------------------
         */
        $byBusinessUnit = $this->returnItemsQuery(
            $entity,
            $startDate,
            $endDate,
            $businessUnitId
        )
            ->select(
                'sr.business_unit_id',
                'bu.name as business_unit_name'
            )
            ->selectRaw('
                COUNT(DISTINCT sr.id) as return_count,
                COALESCE(SUM(sri.return_value), 0) as return_value,
                COALESCE(SUM(
                    CASE WHEN sri.condition = "good"
                    THEN sri.hpp_total ELSE 0 END
                ), 0) as hpp_return,
                COALESCE(SUM(
                    CASE WHEN sri.condition = "reject"
                    THEN sri.hpp_total ELSE 0 END
                ), 0) as reject_loss
            ')
            ->groupBy('sr.business_unit_id', 'bu.name')
            ->orderByDesc('return_value')
            ->get();

        $topProducts = $this->returnItemsQuery(
            $entity,
            $startDate,
            $endDate,
            $businessUnitId
        )
            ->select(
                'sri.product_id',
                'p.name as product_name'
            )
            ->selectRaw('
                COALESCE(SUM(sri.base_qty), 0) as qty,
                COALESCE(SUM(sri.return_value), 0) as return_value,
                COALESCE(SUM(
                    CASE WHEN sri.condition = "good"
                    THEN sri.hpp_total ELSE 0 END
                ), 0) as hpp_good,
                COALESCE(SUM(
                    CASE WHEN sri.condition = "reject"
                    THEN sri.hpp_total ELSE 0 END
                ), 0) as reject_loss
            ')
            ->groupBy('sri.product_id', 'p.name')
            ->orderByDesc('qty')
            ->get();

        $conditionSummary = $this->returnItemsQuery(
            $entity,
            $startDate,
            $endDate,
            $businessUnitId
        )
            ->select('sri.condition')
            ->selectRaw('
                COALESCE(SUM(sri.base_qty), 0) as qty,
                COALESCE(SUM(sri.return_value), 0) as return_value,
                COALESCE(SUM(sri.hpp_total), 0) as hpp
            ')
            ->groupBy('sri.condition')
            ->get();

        $trend = $this->returnItemsQuery(
            $entity,
            $startDate,
            $endDate,
            $businessUnitId
        )
            ->selectRaw('DATE(sr.return_date) as period')
            ->selectRaw('COUNT(DISTINCT sr.id) as return_count')
            ->selectRaw('COALESCE(SUM(sri.return_value), 0) as return_value')
            ->selectRaw('COALESCE(SUM(sri.base_qty), 0) as qty')
            ->groupByRaw('DATE(sr.return_date)')
            ->orderBy('period')
            ->get();

        /*
         * ---------------------------------------------------------------
         * DATA 3: DETAIL
         * ---------------------------------------------------------------
         */
        $detail = $this->returnItemsQuery(
            $entity,
            $startDate,
            $endDate,
            $businessUnitId
        )
            ->select(
                'sr.return_no',
                'sr.return_date',
                's.invoice_no',
                'c.name as customer_name',
                'bu.name as business_unit_name',
                'p.name as product_name',
                'sri.qty',
                'sri.base_qty',
                'sri.return_value',
                'sri.hpp_total',
                'sri.condition',
                'w.name as warehouse_name',
                'sr.status'
            )
            ->orderByDesc('sr.return_date')
            ->orderByDesc('sr.id')
            ->orderByDesc('sri.id')
            ->get();

        /*
         * ---------------------------------------------------------------
         * EXCEL
         * ---------------------------------------------------------------
         */
        $spreadsheet = new Spreadsheet();

        /*
         * SHEET 1: Ringkasan Retur
         */
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Ringkasan Retur');

        $sheet->fromArray([
            ['LAPORAN RETUR PENJUALAN'],
            ['Periode', $startDate . ' s/d ' . $endDate],
            ['Business Unit', $businessUnitId ? 'Terpilih' : 'Semua'],
            [],
            ['Indikator', 'Nilai'],
            ['Total Retur', (int) ($summary->total_returns ?? 0)],
            ['Nilai Retur', $returnValue],
            ['Refund Kas', $refundCash],
            ['Refund Bank', $refundBank],
            ['Pengurang Piutang', $refundReceivable],
            ['HPP Retur Good', $hppReturn],
            ['Retur Good Qty', (float) ($summary->good_qty ?? 0)],
            ['Retur Reject Qty', (float) ($summary->reject_qty ?? 0)],
            ['Kerugian Reject', $rejectLoss],
            ['Dampak Laba Kotor', $returnValue - $hppReturn],
        ], null, 'A1');

        /*
         * SHEET 2: Analisis Retur
         */
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Analisis Retur');

        $row = 1;
        $sheet2->fromArray([
            ['RETUR PER BUSINESS UNIT'],
            ['Business Unit', 'Jumlah Retur', 'Nilai Retur', 'HPP Good', 'Kerugian Reject'],
        ], null, 'A' . $row);

        $row = 3;

        foreach ($byBusinessUnit as $item) {
            $sheet2->fromArray([
                [
                    $item->business_unit_name,
                    (int) $item->return_count,
                    (float) $item->return_value,
                    (float) $item->hpp_return,
                    (float) $item->reject_loss,
                ],
            ], null, 'A' . $row);

            $row++;
        }

        $row += 2;

        $sheet2->fromArray([
            ['PRODUK PALING BANYAK DIRETUR'],
            ['Produk', 'Qty', 'Nilai Retur', 'HPP Good', 'Kerugian Reject'],
        ], null, 'A' . $row);

        $row += 2;

        foreach ($topProducts as $item) {
            $sheet2->fromArray([
                [
                    $item->product_name,
                    (float) $item->qty,
                    (float) $item->return_value,
                    (float) $item->hpp_good,
                    (float) $item->reject_loss,
                ],
            ], null, 'A' . $row);

            $row++;
        }

        $row += 2;

        $sheet2->fromArray([
            ['GOOD VS REJECT'],
            ['Kondisi', 'Qty', 'Nilai Retur', 'HPP'],
        ], null, 'A' . $row);

        $row += 2;

        foreach ($conditionSummary as $item) {
            $sheet2->fromArray([
                [
                    strtoupper((string) $item->condition),
                    (float) $item->qty,
                    (float) $item->return_value,
                    (float) $item->hpp,
                ],
            ], null, 'A' . $row);

            $row++;
        }

        $row += 2;

        $sheet2->fromArray([
            ['TREN RETUR'],
            ['Tanggal', 'Jumlah Retur', 'Qty', 'Nilai Retur'],
        ], null, 'A' . $row);

        $row += 2;

        foreach ($trend as $item) {
            $sheet2->fromArray([
                [
                    $item->period,
                    (int) $item->return_count,
                    (float) $item->qty,
                    (float) $item->return_value,
                ],
            ], null, 'A' . $row);

            $row++;
        }

        /*
         * SHEET 3: Detail Transaksi
         */
        $sheet3 = $spreadsheet->createSheet();
        $sheet3->setTitle('Detail Transaksi');

        $sheet3->fromArray([
            [
                'No Retur',
                'Tanggal',
                'No Faktur',
                'Customer',
                'Business Unit',
                'Produk',
                'Qty',
                'Base Qty',
                'Nilai Retur',
                'HPP',
                'Kondisi',
                'Gudang',
                'Status',
            ],
        ], null, 'A1');

        $row = 2;

        foreach ($detail as $item) {
            $sheet3->fromArray([
                [
                    $item->return_no,
                    $item->return_date,
                    $item->invoice_no,
                    $item->customer_name,
                    $item->business_unit_name,
                    $item->product_name,
                    (float) $item->qty,
                    (float) $item->base_qty,
                    (float) $item->return_value,
                    (float) $item->hpp_total,
                    strtoupper((string) $item->condition),
                    $item->warehouse_name,
                    $item->status,
                ],
            ], null, 'A' . $row);

            $row++;
        }

        /*
         * Basic formatting.
         */
        foreach ([$sheet, $sheet2, $sheet3] as $ws) {
            foreach ($ws->getColumnIterator() as $column) {
                $columnIndex = $column->getColumnIndex();
                $ws->getColumnDimension($columnIndex)->setAutoSize(true);
            }
        }

        $sheet->getStyle('A1:B1')->getFont()->setBold(true);
        $sheet2->getStyle('A1:E2')->getFont()->setBold(true);
        $sheet3->getStyle('A1:M1')->getFont()->setBold(true);

        $filename = 'laporan-retur-penjualan-' . $startDate . '-' . $endDate . '.xlsx';

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(
            function () use ($writer) {
                $writer->save('php://output');
            },
            $filename,
            [
                'Content-Type' =>
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]
        );
    }
}
