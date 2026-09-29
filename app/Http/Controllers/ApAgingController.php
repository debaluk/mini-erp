<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BusinessUnit;
use App\Models\Supplier;
use App\Exports\ApAgingExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ApAgingController extends Controller
{
    /**
     * Halaman Utama: Dashboard Aging Hutang Supplier
     */
    public function index(Request $request)
    {
        $asOfDate       = $request->query('as_of_date', date('Y-m-d'));
        $businessUnitId = $request->query('business_unit_id');
        $supplierId     = $request->query('supplier_id');
        $bucketFilter   = $request->query('bucket'); // current, 1_30, 31_60, above_60
        $search         = $request->query('search');

        $businessUnits = BusinessUnit::where('is_active', 1)->orderBy('code')->get();
        $suppliers     = Supplier::where('is_active', 1)->orderBy('name')->get();

        // -------------------------------------------------------------------
        // 1. QUERY FAKTUR PEMBELIAN KREDIT PER CUT-OFF DATE
        // -------------------------------------------------------------------
        $query = DB::table('purchases as p')
            ->join('suppliers as sup', 'sup.id', '=', 'p.supplier_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'p.business_unit_id')
            ->leftJoinSub(
                DB::table('supplier_payment_allocations as spa')
                    ->join('supplier_payments as sp', 'sp.id', '=', 'spa.supplier_payment_id')
                    ->select('spa.purchase_id', DB::raw('COALESCE(SUM(spa.amount), 0) as total_paid'))
                    ->where('spa.status', 'posted')
                    ->where('sp.status', 'posted')
                    ->whereDate('sp.payment_date', '<=', $asOfDate)
                    ->groupBy('spa.purchase_id'),
                'pay', 'pay.purchase_id', '=', 'p.id'
            )
            ->where('p.status', 'posted')
            ->whereDate('p.purchase_date', '<=', $asOfDate);

        if (!empty($businessUnitId)) $query->where('p.business_unit_id', $businessUnitId);
        if (!empty($supplierId))     $query->where('p.supplier_id', $supplierId);
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('p.purchase_no', 'LIKE', "%{$search}%")
                  ->orWhere('sup.name', 'LIKE', "%{$search}%");
            });
        }

        $rawPurchases = $query->select(
            'p.id', 'p.purchase_no', 'p.purchase_date', 'p.due_date',
            'p.business_unit_id', 'bu.name as business_unit_name',
            'p.supplier_id', 'sup.name as supplier_name', 'sup.phone as supplier_phone',
            'p.total',
            DB::raw('COALESCE(pay.total_paid, 0) as paid_amount'),
            DB::raw('GREATEST(p.total - COALESCE(pay.total_paid, 0), 0) as remaining_amount')
        )->orderBy('p.purchase_date', 'asc')->get();

        // -------------------------------------------------------------------
        // 2. KATEGORISASI BUCKET AGING PER FAKTUR
        // -------------------------------------------------------------------
        $totalAp           = 0;
        $totalCurrent      = 0;
        $totalAging1to30   = 0;
        $totalAging31to60  = 0;
        $totalAgingAbove60 = 0;

        $today = Carbon::parse($asOfDate);
        $supplierSummary = [];

        $purchaseList = $rawPurchases->filter(fn($pur) => $pur->remaining_amount > 0)->map(function ($pur) use ($today, &$totalAp, &$totalCurrent, &$totalAging1to30, &$totalAging31to60, &$totalAgingAbove60, &$supplierSummary) {
            $remaining = (float) $pur->remaining_amount;
            $totalAp  += $remaining;

            $dueDate = $pur->due_date ? Carbon::parse($pur->due_date) : Carbon::parse($pur->purchase_date);
            $overdueDays = $today->diffInDays($dueDate, false); // minus jika overdue

            $currentVal = 0; $aging1 = 0; $aging2 = 0; $aging3 = 0;

            if ($overdueDays >= 0) {
                // Belum Jatuh Tempo (Current / Lancar)
                $currentVal    = $remaining;
                $totalCurrent += $remaining;
                $pur->bucket   = 'current';
                $pur->days     = 0;
            } else {
                // Overdue
                $days      = abs($overdueDays);
                $pur->days = $days;

                if ($days <= 30) {
                    $aging1          = $remaining;
                    $totalAging1to30 += $remaining;
                    $pur->bucket     = '1_30';
                } elseif ($days <= 60) {
                    $aging2           = $remaining;
                    $totalAging31to60 += $remaining;
                    $pur->bucket      = '31_60';
                } else {
                    $aging3            = $remaining;
                    $totalAgingAbove60 += $remaining;
                    $pur->bucket       = 'above_60';
                }
            }

            $pur->val_current  = $currentVal;
            $pur->val_1_30     = $aging1;
            $pur->val_31_60    = $aging2;
            $pur->val_above_60 = $aging3;

            // Grouping Rekap per Supplier
            $sId = $pur->supplier_id;
            if (!isset($supplierSummary[$sId])) {
                $supplierSummary[$sId] = (object) [
                    'supplier_name' => $pur->supplier_name,
                    'phone'         => $pur->supplier_phone ?? '-',
                    'current'       => 0,
                    'aging_1_30'    => 0,
                    'aging_31_60'   => 0,
                    'aging_above_60'=> 0,
                    'total_ap'      => 0,
                ];
            }

            $supplierSummary[$sId]->current        += $currentVal;
            $supplierSummary[$sId]->aging_1_30     += $aging1;
            $supplierSummary[$sId]->aging_31_60    += $aging2;
            $supplierSummary[$sId]->aging_above_60 += $aging3;
            $supplierSummary[$sId]->total_ap       += $remaining;

            return $pur;
        });

        // Filter Tambahan per Bucket jika Dipilih
        if (!empty($bucketFilter)) {
            $filteredList = $purchaseList->filter(fn($p) => $p->bucket === $bucketFilter);
        } else {
            $filteredList = $purchaseList;
        }

        return view('keuangan.laporan.aging-hutang', compact(
            'businessUnits', 'suppliers', 'asOfDate', 'businessUnitId',
            'supplierId', 'bucketFilter', 'search', 'filteredList',
            'supplierSummary', 'totalAp', 'totalCurrent', 'totalAging1to30',
            'totalAging31to60', 'totalAgingAbove60'
        ));
    }

    /**
     * Action Export Excel Aging Hutang
     */
    public function export(Request $request)
    {
        return Excel::download(new ApAgingExport($request), 'Laporan_Aging_Hutang_Supplier_' . date('Ymd_His') . '.xlsx');
    }
}