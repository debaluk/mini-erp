<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Exports\ArAgingExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ArAgingController extends Controller
{
    /**
     * Halaman Utama: Dashboard Aging Piutang
     */
    public function index(Request $request)
    {
        $asOfDate       = $request->query('as_of_date', date('Y-m-d'));
        $businessUnitId = $request->query('business_unit_id');
        $customerId     = $request->query('customer_id');
        $bucketFilter   = $request->query('bucket'); // current, 1_30, 31_60, above_60
        $search         = $request->query('search');

        $businessUnits = BusinessUnit::where('is_active', 1)->orderBy('code')->get();
        $customers     = Customer::where('is_active', 1)->orderBy('name')->get();

        // -------------------------------------------------------------------
        // 1. QUERY FAKTUR PENJUALAN KREDIT TERDAMPARKAN PER CUT-OFF DATE
        // -------------------------------------------------------------------
        $query = DB::table('sales as s')
            ->join('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 's.business_unit_id')
            ->leftJoinSub(
                DB::table('payments')
                    ->select('sale_id', DB::raw('COALESCE(SUM(paid_amount), 0) as total_paid'))
                    ->whereDate('payment_date', '<=', $asOfDate)
                    ->groupBy('sale_id'),
                'pay', 'pay.sale_id', '=', 's.id'
            )
            ->where('s.status', 'posted')
            ->whereDate('s.sale_date', '<=', $asOfDate);

        if (!empty($businessUnitId)) $query->where('s.business_unit_id', $businessUnitId);
        if (!empty($customerId))     $query->where('s.customer_id', $customerId);
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('s.invoice_no', 'LIKE', "%{$search}%")
                  ->orWhere('c.name', 'LIKE', "%{$search}%");
            });
        }

        $rawInvoices = $query->select(
            's.id', 's.invoice_no', 's.sale_date', 's.due_date',
            's.business_unit_id', 'bu.name as business_unit_name',
            's.customer_id', 'c.name as customer_name', 'c.phone as customer_phone',
            'c.credit_limit', 's.total',
            DB::raw('COALESCE(pay.total_paid, 0) as paid_amount'),
            DB::raw('GREATEST(s.total - COALESCE(pay.total_paid, 0), 0) as remaining_amount')
        )->orderBy('s.sale_date', 'asc')->get();

        // -------------------------------------------------------------------
        // 2. KATEGORISASI BUCKET AGING PER FAKTUR
        // -------------------------------------------------------------------
        $totalAr           = 0;
        $totalCurrent      = 0;
        $totalAging1to30   = 0;
        $totalAging31to60  = 0;
        $totalAgingAbove60 = 0;

        $today = Carbon::parse($asOfDate);
        $customerSummary = [];

        $invoiceList = $rawInvoices->filter(fn($inv) => $inv->remaining_amount > 0)->map(function ($inv) use ($today, &$totalAr, &$totalCurrent, &$totalAging1to30, &$totalAging31to60, &$totalAgingAbove60, &$customerSummary) {
            $remaining = (float) $inv->remaining_amount;
            $totalAr  += $remaining;

            $dueDate = $inv->due_date ? Carbon::parse($inv->due_date) : Carbon::parse($inv->sale_date);
            $overdueDays = $today->diffInDays($dueDate, false); // minus jika overdue

            $currentVal = 0; $aging1 = 0; $aging2 = 0; $aging3 = 0;

            if ($overdueDays >= 0) {
                // Belum Jatuh Tempo (Current / Lancar)
                $currentVal    = $remaining;
                $totalCurrent += $remaining;
                $inv->bucket   = 'current';
                $inv->days     = 0;
            } else {
                // Overdue
                $days      = abs($overdueDays);
                $inv->days = $days;

                if ($days <= 30) {
                    $aging1          = $remaining;
                    $totalAging1to30 += $remaining;
                    $inv->bucket     = '1_30';
                } elseif ($days <= 60) {
                    $aging2           = $remaining;
                    $totalAging31to60 += $remaining;
                    $inv->bucket      = '31_60';
                } else {
                    $aging3            = $remaining;
                    $totalAgingAbove60 += $remaining;
                    $inv->bucket       = 'above_60';
                }
            }

            $inv->val_current  = $currentVal;
            $inv->val_1_30     = $aging1;
            $inv->val_31_60    = $aging2;
            $inv->val_above_60 = $aging3;

            // Grouping Rekap per Customer
            $cId = $inv->customer_id;
            if (!isset($customerSummary[$cId])) {
                $customerSummary[$cId] = (object) [
                    'customer_name' => $inv->customer_name,
                    'phone'         => $inv->customer_phone ?? '-',
                    'credit_limit'  => (float) $inv->credit_limit,
                    'current'       => 0,
                    'aging_1_30'    => 0,
                    'aging_31_60'   => 0,
                    'aging_above_60'=> 0,
                    'total_ar'      => 0,
                ];
            }

            $customerSummary[$cId]->current        += $currentVal;
            $customerSummary[$cId]->aging_1_30     += $aging1;
            $customerSummary[$cId]->aging_31_60    += $aging2;
            $customerSummary[$cId]->aging_above_60 += $aging3;
            $customerSummary[$cId]->total_ar       += $remaining;

            return $inv;
        });

        // Filter Tambahan per Bucket jika Dipilih
        if (!empty($bucketFilter)) {
            $filteredList = $invoiceList->filter(fn($i) => $i->bucket === $bucketFilter);
        } else {
            $filteredList = $invoiceList;
        }

        return view('keuangan.laporan.aging-piutang', compact(
            'businessUnits', 'customers', 'asOfDate', 'businessUnitId',
            'customerId', 'bucketFilter', 'search', 'filteredList',
            'customerSummary', 'totalAr', 'totalCurrent', 'totalAging1to30',
            'totalAging31to60', 'totalAgingAbove60'
        ));
    }

    /**
     * Action Export Excel Aging Piutang
     */
    public function export(Request $request)
    {
        return Excel::download(new ArAgingExport($request), 'Laporan_Aging_Piutang_' . date('Ymd_His') . '.xlsx');
    }
}