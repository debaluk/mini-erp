<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseReportController extends Controller
{
    private function entityId(): int
    {
        return (int) (DB::table('entities')->value('id') ?? 1);
    }

    private function query(Request $request, int $entity)
    {
        return DB::table('purchases as p')
            ->leftJoin('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'p.base_unit_id')
            ->where('p.entity_id', $entity)
            ->whereBetween('p.purchase_date', [
                $request->input('start_date', now()->startOfMonth()->toDateString()) . ' 00:00:00',
                $request->input('end_date', now()->endOfMonth()->toDateString()) . ' 23:59:59',
            ])
            ->when($request->filled('supplier'), fn ($q) => $q->where('s.name', 'like', '%' . $request->supplier . '%'))
            ->when($request->filled('unit_id'), fn ($q) => $q->where('p.base_unit_id', $request->unit_id))
            ->when($request->filled('payment_method'), fn ($q) => $q->where('p.payment_method', $request->payment_method))
            ->when($request->filled('status'), fn ($q) => $q->where('p.status', $request->status));
    }

    public function index(Request $request)
    {
        $entity = $this->entityId();
        $rows = $this->query($request, $entity)
            ->select(
                'p.purchase_no', 'p.purchase_date', 'p.subtotal', 'p.discount', 'p.total',
                'p.payment_method', 'p.due_date', 'p.status',
                's.name as supplier_name', 'bu.name as unit_name'
            )
            ->orderByDesc('p.purchase_date')
            ->orderByDesc('p.id')
            ->paginate(15)
            ->withQueryString();

        $suppliers = DB::table('suppliers')->where('entity_id', $entity)->where('is_active', 1)->orderBy('name')->get(['id','name']);
        $units = DB::table('business_units')->where('entity_id', $entity)->where('is_active', 1)->orderBy('name')->get(['id','name']);
        $entityName = DB::table('entities')->where('id', $entity)->value('name') ?? 'MINI ERP';

        return view('erp.reports.purchases', compact('rows', 'suppliers', 'units', 'entityName'));
    }

    public function export(Request $request)
    {
        $entity = $this->entityId();
        $start = $request->input('start_date', now()->startOfMonth()->toDateString());
        $end = $request->input('end_date', now()->endOfMonth()->toDateString());

        abort_if($start > $end, 422, 'Periode tanggal tidak valid.');

        $rows = $this->query($request, $entity)
            ->select(
                'p.purchase_no', 'p.purchase_date', 'p.subtotal', 'p.discount', 'p.total',
                'p.payment_method', 'p.due_date', 'p.status',
                's.name as supplier_name', 'bu.name as unit_name'
            )
            ->orderBy('p.purchase_date')
            ->orderBy('p.id')
            ->get();

        $entityName = DB::table('entities')->where('id', $entity)->value('name') ?? 'MINI ERP';
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $num = fn ($v) => (string) (float) $v;

        $html = '<html><head><meta charset="UTF-8"><style>
            body{font-family:Arial,sans-serif}
            .kop{font-size:16px;font-weight:700}
            .title{font-size:14px;font-weight:700}
            table{border-collapse:collapse;margin-top:14px}
            th,td{border:1px solid #000;padding:5px}
            th{font-weight:700}
            .right{text-align:right}
        </style></head><body>';
        $html .= '<div class="kop">' . $e($entityName) . '</div>';
        $html .= '<div class="title">LAPORAN TRANSAKSI PEMBELIAN</div>';
        $html .= '<div>Periode : ' . $e(date('d/m/Y', strtotime($start))) . ' s/d ' . $e(date('d/m/Y', strtotime($end))) . '</div>';
        $html .= '<div>Tgl Export : ' . $e(now()->format('d/m/Y')) . '</div>';
        $html .= '<br><table><thead><tr>';
        foreach (['No. Pembelian','Tanggal','Supplier','Unit','Cara Bayar','Jatuh Tempo','Subtotal','Diskon','Total','Status'] as $heading) {
            $html .= '<th>' . $e($heading) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $r) {
            $html .= '<tr>';
            $html .= '<td>' . $e($r->purchase_no) . '</td>';
            $html .= '<td>' . $e(date('d/m/Y', strtotime($r->purchase_date))) . '</td>';
            $html .= '<td>' . $e($r->supplier_name ?? '-') . '</td>';
            $html .= '<td>' . $e($r->unit_name ?? '-') . '</td>';
            $html .= '<td>' . $e($r->payment_method ?? '-') . '</td>';
            $html .= '<td>' . (!empty($r->due_date) ? $e(date('d/m/Y', strtotime($r->due_date))) : '-') . '</td>';
            foreach ([$r->subtotal, $r->discount, $r->total] as $value) {
                $v = $num($value);
                $html .= '<td class="right" x:num="' . $e($v) . '">' . $e($v) . '</td>';
            }
            $html .= '<td>' . $e($r->status ?? '-') . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></body></html>';

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laporan-pembelian_' . $start . '_' . $end . '.xls"',
        ]);
    }
}
