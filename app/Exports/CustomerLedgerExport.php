<?php

namespace App\Exports;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CustomerLedgerExport implements FromCollection, WithHeadings, WithStyles, WithColumnFormatting, ShouldAutoSize
{
    protected int $customerId;
    protected Request $request;

    public function __construct(int $customerId, Request $request)
    {
        $this->customerId = $customerId;
        $this->request    = $request;
    }

    public function collection(): Enumerable
    {
        $startDate      = $this->request->query('start_date', now()->startOfYear()->format('Y-m-d'));
        $endDate        = $this->request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId = $this->request->query('business_unit_id');

        $initialSales = DB::table('sales')
            ->where('customer_id', $this->customerId)->where('status', 'posted')
            ->whereDate('sale_date', '<', $startDate)
            ->when($businessUnitId, fn($q) => $q->where('business_unit_id', $businessUnitId))->sum('total');

        $initialPayments = DB::table('payments as p')
            ->join('sales as s', 's.id', '=', 'p.sale_id')
            ->where('s.customer_id', $this->customerId)->where('s.status', 'posted')
            ->whereDate('p.payment_date', '<', $startDate)
            ->when($businessUnitId, fn($q) => $q->where('s.business_unit_id', $businessUnitId))->sum('p.paid_amount');

        $openingBalance = $initialSales - $initialPayments;

        $salesEntries = DB::table('sales')
            ->where('customer_id', $this->customerId)->where('status', 'posted')
            ->whereDate('sale_date', '>=', $startDate)->whereDate('sale_date', '<=', $endDate)
            ->when($businessUnitId, fn($q) => $q->where('business_unit_id', $businessUnitId))
            ->select('sale_date as trans_date', 'invoice_no as ref_no', 'memo as description', 'total as debit', DB::raw('0 as credit'), DB::raw("'SALE' as type"))->get();

        $paymentEntries = DB::table('payments as p')
            ->join('sales as s', 's.id', '=', 'p.sale_id')
            ->where('s.customer_id', $this->customerId)->where('s.status', 'posted')
            ->whereDate('p.payment_date', '>=', $startDate)->whereDate('p.payment_date', '<=', $endDate)
            ->when($businessUnitId, fn($q) => $q->where('s.business_unit_id', $businessUnitId))
            ->select('p.payment_date as trans_date', DB::raw("CONCAT('PAY-', s.invoice_no) as ref_no"), 'p.reference as description', DB::raw('0 as debit'), 'p.paid_amount as credit', DB::raw("'PAYMENT' as type"))->get();

        $mutations = $salesEntries->concat($paymentEntries)->sortBy('trans_date');

        $rows = collect();
        $rows->push([
            'date' => $startDate, 'ref_no' => '-', 'description' => 'SALDO AWAL PIUTANG (OPENING BALANCE)',
            'debit' => 0, 'credit' => 0, 'balance' => $openingBalance
        ]);

        $runningBalance = $openingBalance;
        foreach ($mutations as $m) {
            $debit  = (float) $m->debit;
            $credit = (float) $m->credit;
            $runningBalance += ($debit - $credit);

            $rows->push([
                'date'        => Carbon::parse($m->trans_date)->format('d/m/Y H:i'),
                'ref_no'      => $m->ref_no,
                'description' => $m->description ?? ($m->type === 'SALE' ? 'Penjualan Kredit/Bon' : 'Pelunasan Piutang'),
                'debit'       => $debit,
                'credit'      => $credit,
                'balance'     => $runningBalance,
            ]);
        }

        return $rows;
    }

    public function headings(): array
    {
        $customer = Customer::find($this->customerId);
        return ["KARTU PIUTANG PELANGGAN: " . strtoupper($customer?->name ?? ''), '', '', '', '', ''];
    }

    public function columnFormats(): array
    {
        return ['D' => '#,##0.00', 'E' => '#,##0.00', 'F' => '#,##0.00'];
    }

    public function styles(Worksheet $sheet): ?array
    {
        $highestRow = $sheet->getHighestRow();
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F2937']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $headings = ['Tanggal', 'No. Referensi', 'Keterangan Transaksi', 'Debit (Rp)', 'Kredit (Rp)', 'Saldo Akhir (Rp)'];
        $sheet->fromArray($headings, null, 'A2');

        $sheet->getStyle('A2:F2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3B82F6']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->getStyle("A2:F{$highestRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
        ]);

        return [];
    }
}