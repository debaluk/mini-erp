<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $entity = DB::table('entities')->first();
        $entityId = $entity?->id;
        $period = $request->query('period', now()->format('Y-m'));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            $period = now()->format('Y-m');
        }

        $startDate = Carbon::createFromFormat('Y-m-d', $period . '-01')->startOfMonth()->toDateString();
        $endDate = Carbon::createFromFormat('Y-m-d', $period . '-01')->endOfMonth()->toDateString();
        $businessUnitId = $request->query('business_unit_id');
        $businessUnits = DB::table('business_units')->where('is_active', 1)->orderBy('code')->get();
        $validUnitIds = $businessUnits->pluck('id')->map(fn ($id) => (string) $id)->all();
        if ($businessUnitId && !in_array((string) $businessUnitId, $validUnitIds, true)) {
            $businessUnitId = null;
        }

        $products = DB::table('products')->when($entityId, fn ($q) => $q->where('entity_id', $entityId))->count();
        $customers = DB::table('customers')->when($entityId, fn ($q) => $q->where('entity_id', $entityId))->count();
        $suppliers = DB::table('suppliers')->when($entityId, fn ($q) => $q->where('entity_id', $entityId))->count();

        $stockQuery = DB::table('warehouses_stocks')->when($entityId, fn ($q) => $q->where('entity_id', $entityId));
        if ($businessUnitId && DB::getSchemaBuilder()->hasColumn('warehouses_stocks', 'business_unit_id')) {
            $stockQuery->where('business_unit_id', $businessUnitId);
        }
        $stockValue = (float) $stockQuery->selectRaw('COALESCE(SUM(qty * avg_cost), 0) as amount')->value('amount');

        $salesQuery = DB::table('sales')
            ->where('status', 'posted')
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->when($entityId, fn ($q) => $q->where('entity_id', $entityId))
            ->when($businessUnitId, fn ($q) => $q->where('business_unit_id', $businessUnitId));
        $salesTotal = (float) (clone $salesQuery)->sum('total');

        $purchaseQuery = DB::table('purchases')
            ->where('status', 'received')
            ->whereBetween('purchase_date', [$startDate, $endDate])
            ->when($entityId, fn ($q) => $q->where('entity_id', $entityId))
            ->when($businessUnitId, fn ($q) => $q->where('business_unit_id', $businessUnitId));
        $purchasesTotal = (float) (clone $purchaseQuery)->sum('total');

        // The general ledger remains the source of truth for net sales and COGS.
        $ledgerAvailable = DB::getSchemaBuilder()->hasTable('journal_entries')
            && DB::getSchemaBuilder()->hasTable('journals')
            && DB::getSchemaBuilder()->hasTable('chart_of_accounts');

        $ledgerRows = collect();
        if ($ledgerAvailable) {
            $ledgerRows = DB::table('journal_entries as je')
                ->join('journals as j', 'j.id', '=', 'je.journal_id')
                ->join('chart_of_accounts as coa', 'coa.id', '=', 'je.account_id')
                ->where('j.status', 'posted')
                ->whereDate('j.journal_date', '>=', $startDate)
                ->whereDate('j.journal_date', '<=', $endDate)
                ->when($businessUnitId, fn ($q) => $q->where('j.business_unit_id', $businessUnitId))
                ->select('coa.code', 'coa.name', 'j.business_unit_id')
                ->selectRaw('SUM(je.debit) as debit_total, SUM(je.credit) as credit_total')
                ->groupBy('coa.code', 'coa.name', 'j.business_unit_id')
                ->get();
        }

        $grossSales = (float) $ledgerRows->filter(fn ($r) => str_starts_with((string) $r->code, '4') && (string) $r->code !== '4000301')
            ->sum(fn ($r) => (float) $r->credit_total - (float) $r->debit_total);
        $salesReturns = (float) $ledgerRows->filter(fn ($r) => (string) $r->code === '4000301')
            ->sum(fn ($r) => (float) $r->debit_total - (float) $r->credit_total);
        $netSales = $grossSales - $salesReturns;
        $cogs = (float) $ledgerRows->filter(fn ($r) => str_starts_with((string) $r->code, '5'))
            ->sum(fn ($r) => (float) $r->debit_total - (float) $r->credit_total);
        $grossProfit = $netSales - $cogs;
        $grossMargin = $netSales != 0.0 ? ($grossProfit / $netSales) * 100 : 0.0;

        $cashBankBalance = 0.0;
        $cashMovement = 0.0;
        if ($ledgerAvailable) {
            $cashAccountIds = DB::table('chart_of_accounts')
                ->where('is_cash_bank', 1)->where('is_postable', 1)->where('is_active', 1)->pluck('id');
            if ($cashAccountIds->isNotEmpty()) {
                $cashBase = DB::table('journal_entries as je')
                    ->join('journals as j', 'j.id', '=', 'je.journal_id')
                    ->whereIn('je.account_id', $cashAccountIds)
                    ->where('j.status', 'posted')
                    ->when($businessUnitId, fn ($q) => $q->where('j.business_unit_id', $businessUnitId));
                $cashBankBalance = (float) (clone $cashBase)->whereDate('j.journal_date', '<=', $endDate)
                    ->selectRaw('COALESCE(SUM(je.debit - je.credit), 0) as balance')->value('balance');
                $cashMovement = (float) (clone $cashBase)->whereDate('j.journal_date', '>=', $startDate)
                    ->whereDate('j.journal_date', '<=', $endDate)
                    ->selectRaw('COALESCE(SUM(je.debit - je.credit), 0) as movement')->value('movement');
            }
        }

        // Monthly trend is sourced from posted ledger entries, using the same COA grouping.
        $trendLabels = [];
        $trendSales = [];
        $trendProfit = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::parse($startDate)->subMonths($i);
            $monthStart = $month->copy()->startOfMonth()->toDateString();
            $monthEnd = $month->copy()->endOfMonth()->toDateString();
            $trendLabels[] = $month->translatedFormat('M');
            if ($ledgerAvailable) {
                $rows = DB::table('journal_entries as je')
                    ->join('journals as j', 'j.id', '=', 'je.journal_id')
                    ->join('chart_of_accounts as coa', 'coa.id', '=', 'je.account_id')
                    ->where('j.status', 'posted')
                    ->whereBetween(DB::raw('DATE(j.journal_date)'), [$monthStart, $monthEnd])
                    ->when($businessUnitId, fn ($q) => $q->where('j.business_unit_id', $businessUnitId))
                    ->select('coa.code')
                    ->selectRaw('SUM(je.debit) as debit_total, SUM(je.credit) as credit_total')
                    ->groupBy('coa.code')->get();
                $gs = (float) $rows->filter(fn ($r) => str_starts_with((string) $r->code, '4') && (string) $r->code !== '4000301')
                    ->sum(fn ($r) => (float) $r->credit_total - (float) $r->debit_total);
                $ret = (float) $rows->filter(fn ($r) => (string) $r->code === '4000301')
                    ->sum(fn ($r) => (float) $r->debit_total - (float) $r->credit_total);
                $ns = $gs - $ret;
                $co = (float) $rows->filter(fn ($r) => str_starts_with((string) $r->code, '5'))
                    ->sum(fn ($r) => (float) $r->debit_total - (float) $r->credit_total);
                $trendSales[] = round($ns, 2);
                $trendProfit[] = round($ns - $co, 2);
            } else {
                $trendSales[] = 0;
                $trendProfit[] = 0;
            }
        }

        $buSales = DB::table('sales as s')
            ->leftJoin('business_units as bu', 'bu.id', '=', 's.business_unit_id')
            ->where('s.status', 'posted')
            ->whereBetween('s.sale_date', [$startDate, $endDate])
            ->when($entityId, fn ($q) => $q->where('s.entity_id', $entityId))
            ->when($businessUnitId, fn ($q) => $q->where('s.business_unit_id', $businessUnitId))
            ->groupBy('s.business_unit_id', 'bu.code', 'bu.name')
            ->select('s.business_unit_id', 'bu.code', 'bu.name')
            ->selectRaw('SUM(s.total) as total')
            ->get();

        $buCards = $businessUnits->map(function ($bu) use ($buSales, $salesQuery) {
            $sales = (float) ($buSales->firstWhere('business_unit_id', $bu->id)->total ?? 0);
            return (object) ['id' => $bu->id, 'code' => $bu->code, 'name' => $bu->name, 'sales' => $sales];
        });

        // Aging summaries follow the same invoice/payment allocation rules as the existing AR/AP reports.
        $arBuckets = ['current' => 0.0, '1_30' => 0.0, '31_60' => 0.0, 'above_60' => 0.0];
        if (DB::getSchemaBuilder()->hasTable('sales') && DB::getSchemaBuilder()->hasTable('payments')) {
            $arInvoices = DB::table('sales as s')
                ->leftJoinSub(DB::table('payments')->select('sale_id')->selectRaw('SUM(paid_amount) as total_paid')
                    ->whereDate('payment_date', '<=', $endDate)->groupBy('sale_id'), 'pay', 'pay.sale_id', '=', 's.id')
                ->where('s.status', 'posted')->whereDate('s.sale_date', '<=', $endDate)
                ->when($entityId, fn ($q) => $q->where('s.entity_id', $entityId))
                ->when($businessUnitId, fn ($q) => $q->where('s.business_unit_id', $businessUnitId))
                ->select('s.sale_date', 's.due_date', 's.total')
                ->selectRaw('GREATEST(s.total - COALESCE(pay.total_paid, 0), 0) as remaining')->get();
            foreach ($arInvoices as $invoice) {
                $remaining = (float) $invoice->remaining;
                if ($remaining <= 0) continue;
                $days = Carbon::parse($invoice->due_date ?: $invoice->sale_date)->startOfDay()->diffInDays(Carbon::parse($endDate)->startOfDay(), false);
                $bucket = $days <= 0 ? 'current' : ($days <= 30 ? '1_30' : ($days <= 60 ? '31_60' : 'above_60'));
                $arBuckets[$bucket] += $remaining;
            }
        }

        $apBuckets = ['current' => 0.0, '1_30' => 0.0, '31_60' => 0.0, 'above_60' => 0.0];
        if (DB::getSchemaBuilder()->hasTable('purchases') && DB::getSchemaBuilder()->hasTable('supplier_payment_allocations') && DB::getSchemaBuilder()->hasTable('supplier_payments')) {
            $apInvoices = DB::table('purchases as p')
                ->leftJoinSub(DB::table('supplier_payment_allocations as spa')
                    ->join('supplier_payments as sp', 'sp.id', '=', 'spa.supplier_payment_id')
                    ->where('spa.status', 'posted')->where('sp.status', 'posted')
                    ->whereDate('sp.payment_date', '<=', $endDate)
                    ->select('spa.purchase_id')->selectRaw('SUM(spa.amount) as total_paid')->groupBy('spa.purchase_id'),
                    'pay', 'pay.purchase_id', '=', 'p.id')
                ->where('p.status', 'posted')->whereDate('p.purchase_date', '<=', $endDate)
                ->when($entityId, fn ($q) => $q->where('p.entity_id', $entityId))
                ->when($businessUnitId, fn ($q) => $q->where('p.business_unit_id', $businessUnitId))
                ->select('p.purchase_date', 'p.due_date', 'p.total')
                ->selectRaw('GREATEST(p.total - COALESCE(pay.total_paid, 0), 0) as remaining')->get();
            foreach ($apInvoices as $invoice) {
                $remaining = (float) $invoice->remaining;
                if ($remaining <= 0) continue;
                $days = Carbon::parse($invoice->due_date ?: $invoice->purchase_date)->startOfDay()->diffInDays(Carbon::parse($endDate)->startOfDay(), false);
                $bucket = $days <= 0 ? 'current' : ($days <= 30 ? '1_30' : ($days <= 60 ? '31_60' : 'above_60'));
                $apBuckets[$bucket] += $remaining;
            }
        }

        $stockAdjustments = 0.0;
        if (DB::getSchemaBuilder()->hasTable('stock_adjustments') && DB::getSchemaBuilder()->hasTable('stock_adjustment_items')) {
            $stockAdjustments = (float) DB::table('stock_adjustment_items as i')
                ->join('stock_adjustments as a', 'a.id', '=', 'i.stock_adjustment_id')
                ->where('a.status', 'posted')->whereNull('a.deleted_at')
                ->whereBetween('a.adjustment_date', [$startDate, $endDate])
                ->when($businessUnitId, fn ($q) => $q->where('a.business_unit_id', $businessUnitId))
                ->sum('i.total_cost');
        }

        $recentTransactions = collect();
        if (DB::getSchemaBuilder()->hasTable('journals')) {
            $recentTransactions = DB::table('journals as j')
                ->leftJoin('business_units as bu', 'bu.id', '=', 'j.business_unit_id')
                ->where('j.status', 'posted')
                ->whereBetween('j.journal_date', [$startDate, $endDate])
                ->when($businessUnitId, fn ($q) => $q->where('j.business_unit_id', $businessUnitId))
                ->orderByDesc('j.journal_date')->orderByDesc('j.id')->limit(8)
                ->select('j.id', 'j.journal_date', 'j.journal_no', 'j.source_type', 'j.description', 'bu.code as bu_code')
                ->get();
        }

        $buChartLabels = $buCards->map(fn ($bu) => $bu->code . ' — ' . $bu->name)->values();
        $buChartValues = $buCards->pluck('sales')->values();

        return view('dashboard.index', compact(
            'entityId', 'period', 'startDate', 'endDate', 'businessUnitId', 'businessUnits',
            'products', 'customers', 'suppliers', 'stockValue', 'salesTotal', 'purchasesTotal',
            'netSales', 'grossProfit', 'grossMargin', 'cashBankBalance', 'cashMovement',
            'trendLabels', 'trendSales', 'trendProfit', 'buCards', 'arBuckets', 'apBuckets',
            'stockAdjustments', 'recentTransactions', 'buChartLabels', 'buChartValues'
        ));
    }
}
