<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PurchaseReportController extends Controller
{
    private function entityId(): int
    {
        return (int) (DB::table('entities')->value('id') ?? 1);
    }

    private function dates(Request $request): array
    {
        $start = $request->input('start_date', now()->startOfMonth()->toDateString());
        $end = $request->input('end_date', now()->endOfMonth()->toDateString());
        abort_if($start > $end, 422, 'Periode tanggal tidak valid.');

        return [$start, $end];
    }

    private function query(Request $request, int $entity, string $start, string $end)
    {
        return DB::table('purchases as p')
            ->leftJoin('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'p.business_unit_id')
            ->where('p.entity_id', $entity)
            ->whereBetween('p.purchase_date', [$start . ' 00:00:00', $end . ' 23:59:59'])
            ->when($request->filled('supplier'), fn ($q) => $q->where('s.name', 'like', '%' . $request->input('supplier') . '%'))
            ->when($request->filled('unit_id'), fn ($q) => $q->where('p.business_unit_id', $request->input('unit_id')))
            ->when($request->filled('payment_method'), fn ($q) => $q->where('p.payment_method', $request->input('payment_method')))
            ->when($request->filled('status'), fn ($q) => $q->where('p.status', $request->input('status')));
    }

    private function selectColumns($query)
    {
        return $query->select(
            'p.id', 'p.purchase_no', 'p.purchase_date', 'p.subtotal', 'p.discount', 'p.total',
            'p.payment_method', 'p.due_date', 'p.status',
            's.name as supplier_name', 'bu.name as unit_name'
        );
    }

    public function index(Request $request)
    {
        $entity = $this->entityId();
        [$startDate, $endDate] = $this->dates($request);

        $baseQuery = fn () => $this->query($request, $entity, $startDate, $endDate);
        $summary = (clone $baseQuery())->selectRaw(
            'COUNT(*) as transaction_count, COALESCE(SUM(p.subtotal),0) as subtotal_total, COALESCE(SUM(p.discount),0) as discount_total, COALESCE(SUM(p.total),0) as purchase_total'
        )->first();

        $rows = $this->selectColumns($baseQuery())
            ->orderByDesc('p.purchase_date')
            ->orderByDesc('p.id')
            ->paginate(10)
            ->withQueryString();

        $suppliers = DB::table('suppliers')->where('entity_id', $entity)->where('is_active', 1)->orderBy('name')->get(['id', 'name']);
        $units = DB::table('business_units')->where('entity_id', $entity)->where('is_active', 1)->orderBy('name')->get(['id', 'name']);
        $entityName = DB::table('entities')->where('id', $entity)->value('name') ?? 'NAMA ENTITAS';
        $selectedUnitName = $request->filled('unit_id')
            ? ($units->firstWhere('id', (int) $request->input('unit_id'))?->name ?? 'Unit Bisnis tidak ditemukan')
            : 'Semua Unit Bisnis';

        return view('inventori.laporan.pembelian', compact(
            'rows', 'suppliers', 'units', 'entityName', 'startDate', 'endDate',
            'selectedUnitName', 'summary'
        ));
    }

    public function export(Request $request)
    {
        $entity = $this->entityId();
        [$startDate, $endDate] = $this->dates($request);
        $rows = $this->selectColumns($this->query($request, $entity, $startDate, $endDate))
            ->orderBy('p.purchase_date')->orderBy('p.id')->get();
        $entityName = DB::table('entities')->where('id', $entity)->value('name') ?? 'NAMA ENTITAS';
        $units = DB::table('business_units')->where('entity_id', $entity)->where('is_active', 1)->orderBy('name')->get();
        $selectedUnitName = $request->filled('unit_id')
            ? ($units->firstWhere('id', (int) $request->input('unit_id'))?->name ?? 'Unit Bisnis tidak ditemukan')
            : 'Semua Unit Bisnis';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Pembelian');
        $sheet->mergeCells('A1:J1');
        $sheet->setCellValue('A1', $entityName);
        $sheet->mergeCells('A2:J2');
        $sheet->setCellValue('A2', 'LAPORAN TRANSAKSI PEMBELIAN');
        $sheet->mergeCells('A3:J3');
        $sheet->setCellValue('A3', 'Periode: ' . date('d/m/Y', strtotime($startDate)) . ' s/d ' . date('d/m/Y', strtotime($endDate)));
        $sheet->mergeCells('A4:J4');
        $sheet->setCellValue('A4', 'Unit Bisnis: ' . $selectedUnitName . ' | Tanggal Export: ' . now()->format('d/m/Y'));
        $headers = ['No. Pembelian', 'Tanggal', 'Supplier', 'Unit Bisnis', 'Cara Bayar', 'Jatuh Tempo', 'Subtotal', 'Diskon', 'Total', 'Status'];
        foreach ($headers as $index => $header) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($column . '6', $header);
        }
        $rowNumber = 7;
        foreach ($rows as $row) {
            $values = [
                $row->purchase_no,
                $row->purchase_date ? date('d/m/Y', strtotime($row->purchase_date)) : '',
                $row->supplier_name ?? '-',
                $row->unit_name ?? '-',
                $row->payment_method ?? '-',
                $row->due_date ? date('d/m/Y', strtotime($row->due_date)) : '-',
                (float) $row->subtotal,
                (float) $row->discount,
                (float) $row->total,
                $row->status ?? '-',
            ];
            foreach ($values as $index => $value) {
                $column = Coordinate::stringFromColumnIndex($index + 1);
                $sheet->setCellValue($column . $rowNumber, $value);
            }
            $rowNumber++;
        }
        $lastRow = max(6, $rowNumber - 1);
        $sheet->getStyle('A1:J1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2:J2')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A6:J6')->getFont()->setBold(true);
        $sheet->getStyle('A6:J6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E9ECEF');
        $sheet->getStyle("A6:J{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("G7:I{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("G7:I{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("A6:J{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->freezePane('A7');
        $sheet->setAutoFilter("A6:J{$lastRow}");
        foreach (range('A', 'J') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, "laporan-pembelian_{$startDate}_{$endDate}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
