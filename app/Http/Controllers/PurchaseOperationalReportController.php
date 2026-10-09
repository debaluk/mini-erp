<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class PurchaseOperationalReportController extends Controller
{
    private function entityId(): int { return (int) (DB::table('entities')->value('id') ?? 1); }

    private function period(Request $request): array
    {
        $start = $request->input('start_date', now()->startOfMonth()->toDateString());
        $end = $request->input('end_date', now()->endOfMonth()->toDateString());
        abort_if($start > $end, 422, 'Periode tanggal tidak valid.');
        return [$start, $end];
    }

    private function filters(int $entity): array
    {
        return [
            'entityName' => DB::table('entities')->where('id', $entity)->value('name') ?? 'MINI ERP',
            'units' => DB::table('business_units')->where('entity_id', $entity)->where('is_active', 1)->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function returnRows(int $entity, string $start, string $end, ?int $unitId)
    {
        return DB::table('purchase_returns as r')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'r.business_unit_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'r.supplier_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->where('r.entity_id', $entity)
            ->whereBetween('r.return_date', [$start . ' 00:00:00', $end . ' 23:59:59'])
            ->when($unitId, fn ($q) => $q->where('r.business_unit_id', $unitId))
            ->select(
                'r.id', 'r.return_no', 'r.return_date', 'r.status', 'r.reason',
                's.name as supplier_name', 'bu.name as unit_name', 'w.name as warehouse_name',
                DB::raw("(SELECT p.purchase_no FROM purchase_return_items pri JOIN purchase_items pi ON pi.id = pri.purchase_item_id JOIN purchases p ON p.id = pi.purchase_id WHERE pri.purchase_return_id = r.id ORDER BY pri.id LIMIT 1) as purchase_no"),
                DB::raw("(SELECT rc.receipt_no FROM purchase_return_items pri JOIN receipt_items ri ON ri.id = pri.receipt_item_id JOIN receipts rc ON rc.id = ri.receipt_id WHERE pri.purchase_return_id = r.id ORDER BY pri.id LIMIT 1) as receipt_no"),
                DB::raw("(SELECT COALESCE(SUM(pri.qty), 0) FROM purchase_return_items pri WHERE pri.purchase_return_id = r.id) as return_qty"),
                DB::raw("(SELECT COALESCE(SUM(pri.return_value), 0) FROM purchase_return_items pri WHERE pri.purchase_return_id = r.id) as total")
            )->orderByDesc('r.return_date')->orderByDesc('r.id');
    }

    public function returns(Request $request)
    {
        $entity = $this->entityId();
        [$startDate, $endDate] = $this->period($request);
        $businessUnitId = $request->filled('unit_id') ? (int) $request->input('unit_id') : null;
        $rows = $this->returnRows($entity, $startDate, $endDate, $businessUnitId)->paginate(15)->withQueryString();
        $summary = (int) DB::table('purchase_returns as r')->where('r.entity_id', $entity)->where('r.status', 'posted')
            ->whereBetween('r.return_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->when($businessUnitId, fn ($q) => $q->where('r.business_unit_id', $businessUnitId))->count();
        $total = (float) DB::table('purchase_return_items as pri')->join('purchase_returns as r', 'r.id', '=', 'pri.purchase_return_id')
            ->where('r.entity_id', $entity)->where('r.status', 'posted')->whereBetween('r.return_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->when($businessUnitId, fn ($q) => $q->where('r.business_unit_id', $businessUnitId))->sum('pri.return_value');
        $postedQty = (float) DB::table('purchase_return_items as pri')->join('purchase_returns as r', 'r.id', '=', 'pri.purchase_return_id')
            ->where('r.entity_id', $entity)->where('r.status', 'posted')->whereBetween('r.return_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->when($businessUnitId, fn ($q) => $q->where('r.business_unit_id', $businessUnitId))->sum('pri.qty');
        return view('inventori.laporan.retur-pembelian', array_merge($this->filters($entity), compact('rows', 'startDate', 'endDate', 'businessUnitId', 'summary', 'total', 'postedQty')));
    }

    public function exportReturns(Request $request)
    {
        $entity = $this->entityId();
        [$start, $end] = $this->period($request);
        $unitId = $request->filled('unit_id') ? (int) $request->input('unit_id') : null;
        $rows = $this->returnRows($entity, $start, $end, $unitId)->get();
        $entityName = DB::table('entities')->where('id', $entity)->value('name') ?? 'MINI ERP';
        $sheet = new Spreadsheet(); $ws = $sheet->getActiveSheet(); $ws->setTitle('Retur Pembelian');
        $ws->setCellValue('A1', $entityName); $ws->setCellValue('A2', 'LAPORAN RETUR PEMBELIAN');
        $ws->setCellValue('A3', 'Periode: ' . date('d/m/Y', strtotime($start)) . ' s/d ' . date('d/m/Y', strtotime($end)));
        $ws->setCellValue('A4', 'Tanggal Export: ' . now()->format('d/m/Y'));
        $headers = ['No. Retur', 'Tanggal', 'No. Pembelian', 'No. Penerimaan', 'Supplier', 'Unit Bisnis', 'Gudang', 'Qty Retur', 'Nilai Retur', 'Status', 'Alasan'];
        foreach ($headers as $i => $header) $ws->setCellValueByColumnAndRow($i + 1, 6, $header);
        $rowNo = 7;
        foreach ($rows as $r) {
            $values = [$r->return_no, $r->return_date ? date('d/m/Y', strtotime($r->return_date)) : '-', $r->purchase_no ?? '-', $r->receipt_no ?? '-', $r->supplier_name ?? '-', $r->unit_name ?? '-', $r->warehouse_name ?? '-', (float) $r->return_qty, (float) $r->total, $this->statusLabel($r->status), $r->reason ?? '-'];
            foreach ($values as $i => $value) $ws->setCellValueByColumnAndRow($i + 1, $rowNo, $value);
            $rowNo++;
        }
        $this->styleWorkbook($ws, $rowNo - 1, count($headers), 'H', ['I']);
        return $this->downloadWorkbook($sheet, 'Laporan_Retur_Pembelian_' . $start . '_' . $end . '.xlsx');
    }

    private function payableRows(int $entity, string $start, string $end, ?int $unitId)
    {
        $paid = DB::table('supplier_payment_allocations as spa')
            ->join('supplier_payments as sp', 'sp.id', '=', 'spa.supplier_payment_id')
            ->where('spa.status', 'posted')->where('sp.status', 'posted')->whereDate('sp.payment_date', '<=', $end)
            ->select('spa.purchase_id')->selectRaw('SUM(spa.amount) as paid_amount')->groupBy('spa.purchase_id');
        return DB::table('purchases as p')
            ->leftJoin('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'p.business_unit_id')
            ->leftJoinSub($paid, 'pay', 'pay.purchase_id', '=', 'p.id')
            ->where('p.entity_id', $entity)->where('p.status', 'posted')->whereNull('p.deleted_at')->where('p.payment_method', 'credit')
            ->whereBetween('p.purchase_date', [$start . ' 00:00:00', $end . ' 23:59:59'])
            ->when($unitId, fn ($q) => $q->where('p.business_unit_id', $unitId))
            ->select('p.id', 'p.purchase_no', 'p.purchase_date', 'p.due_date', 'p.payment_method', 'p.total', 'p.status', 's.name as supplier_name', 'bu.name as unit_name')
            ->selectRaw('COALESCE(pay.paid_amount, 0) as paid_amount')
            ->selectRaw('COALESCE((SELECT SUM(pri.return_value) FROM purchase_return_items pri JOIN purchase_returns pr ON pr.id = pri.purchase_return_id JOIN purchase_items pi ON pi.id = pri.purchase_item_id WHERE pi.purchase_id = p.id AND pr.status = "posted" AND pr.return_date <= ?), 0) as return_amount', [$end . ' 23:59:59'])
            ->selectRaw('GREATEST(p.total - COALESCE(pay.paid_amount, 0) - COALESCE((SELECT SUM(pri.return_value) FROM purchase_return_items pri JOIN purchase_returns pr ON pr.id = pri.purchase_return_id JOIN purchase_items pi ON pi.id = pri.purchase_item_id WHERE pi.purchase_id = p.id AND pr.status = "posted" AND pr.return_date <= ?), 0), 0) as outstanding_amount', [$end . ' 23:59:59'])
            ->orderByDesc('p.purchase_date')->orderByDesc('p.id');
    }

    public function payables(Request $request)
    {
        $entity = $this->entityId(); [$startDate, $endDate] = $this->period($request);
        $businessUnitId = $request->filled('unit_id') ? (int) $request->input('unit_id') : null;
        $rows = $this->payableRows($entity, $startDate, $endDate, $businessUnitId)->paginate(15)->withQueryString();
        $totals = DB::query()->fromSub($this->payableRows($entity, $startDate, $endDate, $businessUnitId), 'x')
            ->selectRaw('COALESCE(SUM(total),0) as purchase_total, COALESCE(SUM(paid_amount),0) as paid_total, COALESCE(SUM(return_amount),0) as return_total, COALESCE(SUM(outstanding_amount),0) as outstanding_total')->first();
        $summary = (int) DB::table('purchases as p')->where('p.entity_id', $entity)->where('p.status', 'posted')->whereNull('p.deleted_at')->where('p.payment_method', 'credit')
            ->whereBetween('p.purchase_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->when($businessUnitId, fn ($q) => $q->where('p.business_unit_id', $businessUnitId))->count();
        return view('inventori.laporan.hutang-pembelian', array_merge($this->filters($entity), compact('rows', 'startDate', 'endDate', 'businessUnitId', 'summary', 'totals')));
    }

    public function exportPayables(Request $request)
    {
        $entity = $this->entityId(); [$start, $end] = $this->period($request);
        $unitId = $request->filled('unit_id') ? (int) $request->input('unit_id') : null;
        $rows = $this->payableRows($entity, $start, $end, $unitId)->get();
        $entityName = DB::table('entities')->where('id', $entity)->value('name') ?? 'MINI ERP';
        $sheet = new Spreadsheet(); $ws = $sheet->getActiveSheet(); $ws->setTitle('Hutang Pembelian');
        $ws->setCellValue('A1', $entityName); $ws->setCellValue('A2', 'LAPORAN HUTANG PEMBELIAN');
        $ws->setCellValue('A3', 'Periode Faktur: ' . date('d/m/Y', strtotime($start)) . ' s/d ' . date('d/m/Y', strtotime($end)));
        $ws->setCellValue('A4', 'Tanggal Export: ' . now()->format('d/m/Y'));
        $headers = ['No. Pembelian', 'Tanggal', 'Jatuh Tempo', 'Supplier', 'Unit Bisnis', 'Cara Bayar', 'Total Pembelian', 'Pembayaran', 'Retur', 'Sisa Hutang', 'Status'];
        foreach ($headers as $i => $header) $ws->setCellValueByColumnAndRow($i + 1, 6, $header);
        $rowNo = 7;
        foreach ($rows as $r) {
            $values = [$r->purchase_no, $r->purchase_date ? date('d/m/Y', strtotime($r->purchase_date)) : '-', $r->due_date ? date('d/m/Y', strtotime($r->due_date)) : '-', $r->supplier_name ?? '-', $r->unit_name ?? '-', $this->paymentLabel($r->payment_method), (float) $r->total, (float) $r->paid_amount, (float) $r->return_amount, (float) $r->outstanding_amount, $this->payableStatus($r)];
            foreach ($values as $i => $value) $ws->setCellValueByColumnAndRow($i + 1, $rowNo, $value);
            $rowNo++;
        }
        $this->styleWorkbook($ws, $rowNo - 1, count($headers), null, ['G', 'H', 'I', 'J']);
        return $this->downloadWorkbook($sheet, 'Laporan_Hutang_Pembelian_' . $start . '_' . $end . '.xlsx');
    }

    private function statusLabel(?string $status): string
    {
        return match (strtolower((string) $status)) { 'posted' => 'Diposting', 'draft' => 'Draf', 'cancelled', 'canceled' => 'Dibatalkan', default => $status ?: '-' };
    }

    private function paymentLabel(?string $method): string
    {
        return match (strtolower((string) $method)) { 'cash' => 'Tunai', 'credit' => 'Kredit / Tempo', 'transfer' => 'Transfer', 'qris' => 'QRIS', default => $method ?: '-' };
    }

    private function payableStatus(object $row): string
    {
        if ((float) $row->outstanding_amount <= 0) return 'Lunas';
        if ($row->due_date && $row->due_date < now()->toDateString()) return 'Jatuh Tempo';
        if ((float) $row->paid_amount > 0 || (float) $row->return_amount > 0) return 'Sebagian';
        return 'Belum Dibayar';
    }

    private function styleWorkbook($ws, int $lastRow, int $lastColumnCount, ?string $qtyColumn, array $moneyColumns): void
    {
        $lastCol = Coordinate::stringFromColumnIndex($lastColumnCount);
        $ws->mergeCells('A1:' . $lastCol . '1'); $ws->mergeCells('A2:' . $lastCol . '2');
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $ws->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $ws->getStyle('A1:' . $lastCol . '4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle('A6:' . $lastCol . '6')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $ws->getStyle('A6:' . $lastCol . '6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('244062');
        $ws->getStyle('A6:' . $lastCol . max(6, $lastRow))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        if ($lastRow >= 7) {
            if ($qtyColumn) $ws->getStyle($qtyColumn . '7:' . $qtyColumn . $lastRow)->getNumberFormat()->setFormatCode('#,##0.000');
            foreach ($moneyColumns as $col) $ws->getStyle($col . '7:' . $col . $lastRow)->getNumberFormat()->setFormatCode('#,##0');
        }
        for ($i = 1; $i <= $lastColumnCount; $i++) $ws->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        $ws->freezePane('A7'); $ws->setAutoFilter('A6:' . $lastCol . max(6, $lastRow));
    }

    private function downloadWorkbook(Spreadsheet $sheet, string $filename)
    {
        return response()->streamDownload(function () use ($sheet) { (new Xlsx($sheet))->save('php://output'); }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
