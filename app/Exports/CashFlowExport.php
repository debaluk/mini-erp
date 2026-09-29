<?php

namespace App\Exports;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
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

class CashFlowExport implements 
    FromCollection, 
    WithHeadings, 
    WithStyles, 
    WithColumnFormatting, 
    ShouldAutoSize
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function collection(): Enumerable
    {
        $startDate      = $this->request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate        = $this->request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId = $this->request->query('business_unit_id');
        $cashAccountId  = $this->request->query('cash_account_id');

        $cashAccounts = ChartOfAccount::where('is_cash_bank', 1)->where('is_postable', 1)->where('is_active', 1)->get();
        $cashAccountIds = !empty($cashAccountId) ? [(int) $cashAccountId] : $cashAccounts->pluck('id')->toArray();

        // Saldo Awal
        $initialBalance = (float) JournalEntry::whereHas('journal', function ($q) use ($startDate, $businessUnitId) {
                $q->where('status', 'posted')->whereDate('journal_date', '<', $startDate);
                if (!empty($businessUnitId)) $q->where('business_unit_id', $businessUnitId);
            })->whereIn('account_id', $cashAccountIds)->sum(DB::raw('debit - credit'));

        // Mutasi Periode Ini
        $cashEntries = JournalEntry::with(['journal', 'account'])
            ->whereHas('journal', function ($q) use ($startDate, $endDate, $businessUnitId) {
                $q->where('status', 'posted')->whereDate('journal_date', '>=', $startDate)->whereDate('journal_date', '<=', $endDate);
                if (!empty($businessUnitId)) $q->where('business_unit_id', $businessUnitId);
            })->whereIn('account_id', $cashAccountIds)->get();

        $opIn = []; $opOut = []; $totalOpIn = 0; $totalOpOut = 0;
        $invIn = []; $invOut = []; $totalInvIn = 0; $totalInvOut = 0;
        $finIn = []; $finOut = []; $totalFinIn = 0; $totalFinOut = 0;

        foreach ($cashEntries as $entry) {
            $journal    = $entry->journal;
            $sourceType = $journal->source_type ?? 'GENERAL';
            $debit      = (float) $entry->debit;
            $credit     = (float) $entry->credit;

            if ($debit > 0) {
                $creditCounterparts = JournalEntry::with('account')
                    ->where('journal_id', $journal->id)->where('credit', '>', 0)->get();

                $nonCashCounterparts = $creditCounterparts->reject(function ($cp) use ($cashAccountIds) {
                    return in_array($cp->account_id, $cashAccountIds);
                });

                if ($nonCashCounterparts->isEmpty()) continue;

                $primaryCp = $nonCashCounterparts->sortByDesc('credit')->first();
                $cpAccount = $primaryCp?->account;
                $cpType    = strtolower($cpAccount?->type ?? '');
                $cpName    = $cpAccount ? "[{$cpAccount->code}] {$cpAccount->name}" : 'Penerimaan Lainnya';

                if ($sourceType === 'AR_PAYMENT' || str_contains(strtolower($cpName), 'piutang')) {
                    $label = 'Pelunasan Piutang Usaha';
                    $opIn[$label] = ($opIn[$label] ?? 0) + $debit; $totalOpIn += $debit;
                } elseif (in_array($cpType, ['equity', 'liability', 'kewajiban']) && (str_contains(strtolower($cpAccount?->name), 'modal') || str_contains(strtolower($cpAccount?->name), 'pinjaman'))) {
                    $finIn[$cpName] = ($finIn[$cpName] ?? 0) + $debit; $totalFinIn += $debit;
                } elseif (str_contains(strtolower($cpAccount?->name), 'aset tetap') || str_contains(strtolower($cpAccount?->name), 'peralatan') || str_contains(strtolower($cpAccount?->name), 'kendaraan')) {
                    $invIn[$cpName] = ($invIn[$cpName] ?? 0) + $debit; $totalInvIn += $debit;
                } else {
                    $opIn[$cpName] = ($opIn[$cpName] ?? 0) + $debit; $totalOpIn += $debit;
                }
            }

            if ($credit > 0) {
                $debitCounterparts = JournalEntry::with('account')
                    ->where('journal_id', $journal->id)->where('debit', '>', 0)->get();

                $nonCashCounterparts = $debitCounterparts->reject(function ($cp) use ($cashAccountIds) {
                    return in_array($cp->account_id, $cashAccountIds);
                });

                if ($nonCashCounterparts->isEmpty()) continue;

                $primaryCp = $nonCashCounterparts->sortByDesc('debit')->first();
                $cpAccount = $primaryCp?->account;
                $cpType    = strtolower($cpAccount?->type ?? '');
                $cpName    = $cpAccount ? "[{$cpAccount->code}] {$cpAccount->name}" : 'Pengeluaran Lainnya';

                if ($sourceType === 'AP_PAYMENT' || str_contains(strtolower($cpName), 'hutang')) {
                    $label = 'Pembayaran Hutang Vendor (AP)';
                    $opOut[$label] = ($opOut[$label] ?? 0) + $credit; $totalOpOut += $credit;
                } elseif (in_array($cpType, ['equity', 'liability', 'kewajiban']) && (str_contains(strtolower($cpAccount?->name), 'prive') || str_contains(strtolower($cpAccount?->name), 'dividen') || str_contains(strtolower($cpAccount?->name), 'pinjaman'))) {
                    $finOut[$cpName] = ($finOut[$cpName] ?? 0) + $credit; $totalFinOut += $credit;
                } elseif (str_contains(strtolower($cpAccount?->name), 'aset tetap') || str_contains(strtolower($cpAccount?->name), 'peralatan') || str_contains(strtolower($cpAccount?->name), 'kendaraan') || str_contains(strtolower($cpAccount?->name), 'mesin')) {
                    $invOut[$cpName] = ($invOut[$cpName] ?? 0) + $credit; $totalInvOut += $credit;
                } else {
                    $opOut[$cpName] = ($opOut[$cpName] ?? 0) + $credit; $totalOpOut += $credit;
                }
            }
        }

        $netOp  = $totalOpIn - $totalOpOut;
        $netInv = $totalInvIn - $totalInvOut;
        $netFin = $totalFinIn - $totalFinOut;
        $netChange = $netOp + $netInv + $netFin;
        $ending    = $initialBalance + $netChange;

        $rows = collect();
        $rows->push(['SALDO AWAL KAS & BANK', $initialBalance]);
        $rows->push(['1. ARUS KAS DARI AKTIVITAS OPERASIONAL', '']);
        foreach ($opIn as $k => $v) $rows->push(["   Penerimaan: {$k}", $v]);
        foreach ($opOut as $k => $v) $rows->push(["   Pengeluaran: {$k}", -$v]);
        $rows->push(['Arus Kas Bersih dari Aktivitas Operasional', $netOp]);

        $rows->push(['2. ARUS KAS DARI AKTIVITAS INVESTASI', '']);
        foreach ($invIn as $k => $v) $rows->push(["   Penerimaan: {$k}", $v]);
        foreach ($invOut as $k => $v) $rows->push(["   Pengeluaran: {$k}", -$v]);
        $rows->push(['Arus Kas Bersih dari Aktivitas Investasi', $netInv]);

        $rows->push(['3. ARUS KAS DARI AKTIVITAS PENDANAAN', '']);
        foreach ($finIn as $k => $v) $rows->push(["   Penerimaan: {$k}", $v]);
        foreach ($finOut as $k => $v) $rows->push(["   Pengeluaran: {$k}", -$v]);
        $rows->push(['Arus Kas Bersih dari Aktivitas Pendanaan', $netFin]);

        $rows->push(['KENAIKAN / (PENURUNAN) BERSIH KAS & BANK', $netChange]);
        $rows->push(['SALDO AKHIR KAS & BANK', $ending]);

        return $rows;
    }

    public function headings(): array
    {
        return ['LAPORAN ARUS KAS (DIRECT METHOD)', ''];
    }

    public function columnFormats(): array
    {
        return ['B' => '#,##0.00'];
    }

    public function styles(Worksheet $sheet): ?array
    {
        $highestRow = $sheet->getHighestRow();

        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '111827']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $sheet->getStyle("A2:B{$highestRow}")->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']],
            ],
        ]);

        return [];
    }
}