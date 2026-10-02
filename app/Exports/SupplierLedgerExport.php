<?php

namespace App\Exports;

use App\Models\Supplier;
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

class SupplierLedgerExport implements FromCollection, WithHeadings, WithStyles, WithColumnFormatting, ShouldAutoSize
{
    protected int $supplierId;
    protected Request $request;

    public function __construct(int $supplierId, Request $request)
    {
        $this->supplierId = $supplierId;
        $this->request    = $request;
    }

    public function collection(): Enumerable
    {
        $startDate      = $this->request->query('start_date', now()->startOfYear()->format('Y-m-d'));
        $endDate        = $this->request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId = $this->request->query('business_unit_id');

        $initialPurchases = DB::table('purchases')
            ->where('supplier_id', $this->supplierId)->where('status', 'posted')
            ->whereDate('purchase_date', '<', $startDate)
            ->when($businessUnitId, fn($q) => $q->where('business_unit_id', $businessUnitId))->sum('total');

        $initialPayments = DB::table('supplier_payment_allocations as spa')
            ->join('supplier_payments as sp', 'sp.id', '=', 'spa.supplier_payment_id')
            ->join('purchases as pur', 'pur.id', '=', 'spa.purchase_id')
            ->where('pur.supplier_id', $this->supplierId)->where('pur.status', 'posted')
            ->where('spa.status', 'posted')->where('sp.status', 'posted')
            ->whereDate('sp.payment_date', '<', $startDate)
            ->when($businessUnitId, fn($q) => $q->where('pur.business_unit_id', $businessUnitId))
            ->sum('spa.amount');

        $openingBalance = $initialPurchases - $initialPayments;

        $purchaseEntries = DB::table('purchases')
            ->where('supplier_id', $this->supplierId)->where('status', 'posted')
            ->whereDate('purchase_date', '>=', $startDate)->whereDate('purchase_date', '<=', $endDate)
            ->when($businessUnitId, fn($q) => $q->where('business_unit_id', $businessUnitId))
            ->select('purchase_date as trans_date', 'purchase_no as ref_no', 'memo as description', DB::raw('0 as debit'), 'total as credit')->get();

        $paymentEntries = DB::table('supplier_payment_allocations as spa')
            ->join('supplier_payments as sp', 'sp.id', '=', 'spa.supplier_payment_id')
            ->join('purchases as pur', 'pur.id', '=', 'spa.purchase_id')
            ->where('pur.supplier_id', $this->supplierId)->where('pur.status', 'posted')
            ->where('spa.status', 'posted')->where('sp.status', 'posted')
            ->whereDate('sp.payment_date', '>=', $startDate)->whereDate('sp.payment_date', '<=', $endDate)
            ->when($businessUnitId, fn($q) => $q->where('pur.business_unit_id', $businessUnitId))
            ->select('sp.payment_date as trans_date', DB::raw("CONCAT('PAY-', pur.purchase_no) as ref_no"), 'sp.reference as description', 'spa.amount as debit', DB::raw('0 as credit'))->get();

        $mutations = $purchaseEntries->concat($paymentEntries)->sortBy('trans_date');

        $rows = collect();
        $rows->push([
            'date' => $startDate, 'ref_no' => '-', 'description' => 'SALDO AWAL HUTANG (OPENING BALANCE)',
            'debit' => 0, 'credit' => 0, 'balance' => $openingBalance
        ]);

        $runningBalance = $openingBalance;
        foreach ($mutations as $m) {
            $debit  = (float) $m->debit;
            $credit = (float) $m->credit;
            $runningBalance += ($credit - $debit);

            $rows->push([
                'date'        => Carbon::parse($m->trans_date)->format('d/m/Y H:i'),
                'ref_no'      => $m->ref_no,
                'description' => $m->description ?? ($credit > 0 ? 'Pembelian Tempo' : 'Pelunasan Hutang'),
                'debit'       => $debit,
                'credit'      => $credit,
                'balance'     => $runningBalance,
            ]);
        }

        return $rows;
    }

    public function headings(): array
    {
        $supplier = Supplier::find($this->supplierId);
        return ["KARTU HUTANG SUPPLIER: " . strtoupper($supplier?->name ?? ''), '', '', '', '', ''];
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

        $headings = ['Tanggal', 'No. Referensi', 'Keterangan Transaksi', 'Debit / Bayar (Rp)', 'Kredit / Pembelian (Rp)', 'Saldo Akhir (Rp)'];
        $sheet->fromArray($headings, null, 'A2');

        $sheet->getStyle('A2:F2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '8B5CF6']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->getStyle("A2:F{$highestRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
        ]);

        return [];
    }
}