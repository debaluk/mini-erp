<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesReceivableReportController extends Controller
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

        $customerId = $request->filled('customer_id')
            ? (int) $request->input('customer_id')
            : null;

        $status = $request->input('status', 'all');

        return [
            $startDate,
            $endDate,
            $businessUnitId,
            $customerId,
            $status,
        ];
    }

    private function baseQuery(
        int $entity,
        string $startDate,
        string $endDate,
        ?int $businessUnitId = null,
        ?int $customerId = null
    ) {
        $payments = DB::table('payments')
            ->select(
                'sale_id',
                DB::raw('COALESCE(SUM(paid_amount), 0) as paid_amount')
            )
            ->groupBy('sale_id');

        return DB::table('sales as s')
            ->leftJoinSub($payments, 'pay', function ($join) {
                $join->on('pay.sale_id', '=', 's.id');
            })
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 's.business_unit_id')
            ->where('s.entity_id', $entity)
            ->where('s.status', 'posted')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('payments as cp')
                    ->whereColumn('cp.sale_id', 's.id')
                    ->where('cp.method', 'credit');
            })
            ->whereDate('s.sale_date', '>=', $startDate)
            ->whereDate('s.sale_date', '<=', $endDate)
            ->when($businessUnitId, fn ($q) =>
                $q->where('s.business_unit_id', $businessUnitId)
            )
            ->when($customerId, fn ($q) =>
                $q->where('s.customer_id', $customerId)
            )
            ->select(
                's.id',
                's.invoice_no',
                's.sale_date',
                's.due_date',
                's.customer_id',
                'c.name as customer_name',
                's.business_unit_id',
                'bu.name as business_unit_name',
                's.total'
            )
            ->selectRaw('COALESCE(pay.paid_amount, 0) as paid_amount')
            ->selectRaw('GREATEST(s.total - COALESCE(pay.paid_amount, 0), 0) as outstanding')
            ->groupBy(
                's.id',
                's.invoice_no',
                's.sale_date',
                's.due_date',
                's.customer_id',
                'c.name',
                's.business_unit_id',
                'bu.name',
                's.total',
                'pay.paid_amount'
            );
    }

    private function statusSql(string $status): ?string
    {
        return match ($status) {
            'unpaid' => 'GREATEST(s.total - COALESCE(pay.paid_amount, 0), 0) > 0',
            'belum_jatuh_tempo' => 'GREATEST(s.total - COALESCE(pay.paid_amount, 0), 0) > 0 AND (s.due_date IS NULL OR DATE(s.due_date) > CURRENT_DATE)',
            'jatuh_tempo' => 'GREATEST(s.total - COALESCE(pay.paid_amount, 0), 0) > 0 AND DATE(s.due_date) = CURRENT_DATE',
            'lewat_jatuh_tempo' => 'GREATEST(s.total - COALESCE(pay.paid_amount, 0), 0) > 0 AND s.due_date IS NOT NULL AND DATE(s.due_date) < CURRENT_DATE',
            'lunas' => 'GREATEST(s.total - COALESCE(pay.paid_amount, 0), 0) <= 0',
            default => null,
        };
    }

    public function exportExcel(Request $request)
    {
        $entity = $this->entityId();

        [
            $startDate,
            $endDate,
            $businessUnitId,
            $customerId,
            $status,
        ] = $this->period($request);

        $query = $this->baseQuery(
            $entity,
            $startDate,
            $endDate,
            $businessUnitId,
            $customerId
        );

        $statusSql = $this->statusSql($status);

        if ($statusSql) {
            $query->whereRaw($statusSql);
        }

        $rows = $query
            ->orderByRaw('CASE WHEN s.due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('s.due_date')
            ->orderBy('s.sale_date')
            ->orderBy('s.invoice_no')
            ->get();

        $totalInvoice = (float) $rows->sum('total');
        $totalPaid = (float) $rows->sum('paid_amount');
        $totalOutstanding = (float) $rows->sum('outstanding');

        $today = now()->toDateString();

        $belumJatuhTempo = (float) $rows
            ->filter(fn ($row) =>
                (float) $row->outstanding > 0 &&
                ($row->due_date === null ||
                    substr((string) $row->due_date, 0, 10) > $today)
            )
            ->sum('outstanding');

        $jatuhTempo = (float) $rows
            ->filter(fn ($row) =>
                (float) $row->outstanding > 0 &&
                $row->due_date !== null &&
                substr((string) $row->due_date, 0, 10) === $today
            )
            ->sum('outstanding');

        $lewatJatuhTempo = (float) $rows
            ->filter(fn ($row) =>
                (float) $row->outstanding > 0 &&
                $row->due_date !== null &&
                substr((string) $row->due_date, 0, 10) < $today
            )
            ->sum('outstanding');

        $units = DB::table('business_units')
            ->where('entity_id', $entity)
            ->where('id', $businessUnitId)
            ->value('name');

        $customer = DB::table('customers')
            ->where('entity_id', $entity)
            ->where('id', $customerId)
            ->value('name');

        $statusLabels = [
            'unpaid' => 'Belum Lunas',
            'belum_jatuh_tempo' => 'Belum Jatuh Tempo',
            'jatuh_tempo' => 'Jatuh Tempo',
            'lewat_jatuh_tempo' => 'Lewat Jatuh Tempo',
            'lunas' => 'Lunas',
        ];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Piutang Penjualan');

        // Judul
        $sheet->mergeCells('A1:J1');
        $sheet->setCellValue('A1', 'LAPORAN PIUTANG PENJUALAN');
        $sheet->getStyle('A1:J1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1:J1')->getAlignment()->setHorizontal('center');

        // Filter / periode
        $sheet->setCellValue('B3', 'PERIODE');
        $sheet->mergeCells('C3:J3');
        $sheet->setCellValue('C3', $startDate . ' s/d ' . $endDate);

        $sheet->setCellValue('B4', 'BUSINESS UNIT');
        $sheet->mergeCells('C4:J4');
        $sheet->setCellValue('C4', $units ?: 'Semua');

        $sheet->setCellValue('B5', 'CUSTOMER');
        $sheet->mergeCells('C5:J5');
        $sheet->setCellValue('C5', $customer ?: 'Semua');

        $sheet->setCellValue('B6', 'STATUS');
        $sheet->mergeCells('C6:J6');
        $sheet->setCellValue('C6', $statusLabels[$status] ?? 'Semua');

        $sheet->setCellValue('B7', 'TANGGAL CETAK');
        $sheet->mergeCells('C7:J7');
        $sheet->setCellValue('C7', now()->format('d-m-Y H:i:s'));

        $sheet->getStyle('B3:B7')->getFont()->setBold(true);

        // Ringkasan
        $sheet->mergeCells('B9:E9');
        $sheet->setCellValue('B9', 'RINGKASAN PIUTANG');
        $sheet->getStyle('B9:E9')->getFont()->setBold(true);

        $summary = [
            ['B10', 'Total Nilai Faktur', 'C10', $totalInvoice],
            ['B11', 'Total Dibayar', 'C11', $totalPaid],
            ['B12', 'Total Sisa Piutang', 'C12', $totalOutstanding],
            ['E10', 'Belum Jatuh Tempo', 'F10', $belumJatuhTempo],
            ['E11', 'Jatuh Tempo', 'F11', $jatuhTempo],
            ['E12', 'Lewat Jatuh Tempo', 'F12', $lewatJatuhTempo],
        ];

        foreach ($summary as [$labelCell, $label, $valueCell, $value]) {
            $sheet->setCellValue($labelCell, $label);
            $sheet->setCellValue($valueCell, $value);
        }

        $sheet->getStyle('B10:B12')->getFont()->setBold(true);
        $sheet->getStyle('E10:E12')->getFont()->setBold(true);
        $sheet->getStyle('C10:C12')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('F10:F12')->getNumberFormat()->setFormatCode('#,##0.00');

        // Detail
        $detailHeaderRow = 14;

        $headers = [
            'No',
            'Faktur',
            'Tanggal',
            'Customer',
            'Business Unit',
            'Jatuh Tempo',
            'Nilai Faktur',
            'Dibayar',
            'Sisa Piutang',
            'Status',
        ];

        foreach ($headers as $column => $header) {
            $cell = chr(ord('A') + $column) . $detailHeaderRow;
            $sheet->setCellValue($cell, $header);
        }

        $sheet->getStyle('A' . $detailHeaderRow . ':J' . $detailHeaderRow)
            ->getFont()
            ->setBold(true);

        $rowNumber = $detailHeaderRow + 1;

        foreach ($rows as $index => $row) {
            $outstanding = (float) $row->outstanding;

            $dueDate = $row->due_date
                ? substr((string) $row->due_date, 0, 10)
                : null;

            if ($outstanding <= 0) {
                $statusLabel = 'Lunas';
            } elseif ($dueDate === null || $dueDate > $today) {
                $statusLabel = 'Belum Jatuh Tempo';
            } elseif ($dueDate === $today) {
                $statusLabel = 'Jatuh Tempo';
            } else {
                $statusLabel = 'Lewat Jatuh Tempo';
            }

            $values = [
                $index + 1,
                $row->invoice_no,
                substr((string) $row->sale_date, 0, 10),
                $row->customer_name ?: '-',
                $row->business_unit_name ?: '-',
                $dueDate ?: '-',
                (float) $row->total,
                (float) $row->paid_amount,
                $outstanding,
                $statusLabel,
            ];

            foreach ($values as $column => $value) {
                $cell = chr(ord('A') + $column) . $rowNumber;
                $sheet->setCellValue($cell, $value);
            }

            $rowNumber++;
        }

        $totalRow = $rowNumber;

        $sheet->setCellValue('F' . $totalRow, 'TOTAL');
        $sheet->setCellValue('G' . $totalRow, $totalInvoice);
        $sheet->setCellValue('H' . $totalRow, $totalPaid);
        $sheet->setCellValue('I' . $totalRow, $totalOutstanding);

        $sheet->getStyle('F' . $totalRow . ':I' . $totalRow)
            ->getFont()
            ->setBold(true);

        $sheet->getStyle('G15:I' . $totalRow)
            ->getNumberFormat()
            ->setFormatCode('#,##0.00');

        $sheet->getStyle('A1:J' . $totalRow)
            ->getAlignment()
            ->setVertical('center');

        foreach (range('A', 'J') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'laporan-piutang-penjualan-' . $startDate . '-' . $endDate . '.xlsx';

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

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

    public function index(Request $request)
    {
        $entity = $this->entityId();

        [
            $startDate,
            $endDate,
            $businessUnitId,
            $customerId,
            $status,
        ] = $this->period($request);

        $units = DB::table('business_units')
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $customers = DB::table('customers')
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $query = $this->baseQuery(
            $entity,
            $startDate,
            $endDate,
            $businessUnitId,
            $customerId
        );

        $statusSql = $this->statusSql($status);

        if ($statusSql) {
            $query->whereRaw($statusSql);
        }

        $rows = $query
            ->orderByRaw('CASE WHEN s.due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('s.due_date')
            ->orderBy('s.sale_date')
            ->orderBy('s.invoice_no')
            ->get();

        $totalInvoice = (float) $rows->sum('total');
        $totalPaid = (float) $rows->sum('paid_amount');
        $totalOutstanding = (float) $rows->sum('outstanding');

        $today = now()->toDateString();

        $belumJatuhTempo = (float) $rows
            ->filter(fn ($row) =>
                (float) $row->outstanding > 0 &&
                ($row->due_date === null || substr((string) $row->due_date, 0, 10) > $today)
            )
            ->sum('outstanding');

        $jatuhTempo = (float) $rows
            ->filter(fn ($row) =>
                (float) $row->outstanding > 0 &&
                $row->due_date !== null &&
                substr((string) $row->due_date, 0, 10) === $today
            )
            ->sum('outstanding');

        $lewatJatuhTempo = (float) $rows
            ->filter(fn ($row) =>
                (float) $row->outstanding > 0 &&
                $row->due_date !== null &&
                substr((string) $row->due_date, 0, 10) < $today
            )
            ->sum('outstanding');

        return view('inventori.laporan.piutang-penjualan', compact(
            'rows',
            'units',
            'customers',
            'startDate',
            'endDate',
            'businessUnitId',
            'customerId',
            'status',
            'totalInvoice',
            'totalPaid',
            'totalOutstanding',
            'belumJatuhTempo',
            'jatuhTempo',
            'lewatJatuhTempo'
        ));
    }
}
