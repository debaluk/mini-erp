<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use App\Models\BusinessUnit;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Exports\CashTransferExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CashTransferController extends Controller
{
    public function index(Request $request)
    {
        $startDate      = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate        = $request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId = $request->query('business_unit_id');
        $fromAccountId  = $request->query('from_account_id');
        $toAccountId    = $request->query('to_account_id');

        $businessUnits = BusinessUnit::where('is_active', 1)->orderBy('code')->get();

        // Akun Kas & Bank Aktif (is_cash_bank = 1)
        $cashAccounts = ChartOfAccount::where('is_cash_bank', 1)
            ->where('is_postable', 1)
            ->where('is_active', 1)
            ->orderBy('code')
            ->get();

        // Query Jurnal Mutasi Internal (source_type = 'CASH_TRANSFER')
        $transfersQuery = Journal::with(['businessUnit', 'entries.account'])
            ->where('source_type', 'CASH_TRANSFER')
            ->whereDate('journal_date', '>=', $startDate)
            ->whereDate('journal_date', '<=', $endDate);

        if (!empty($businessUnitId)) {
            $transfersQuery->where('business_unit_id', $businessUnitId);
        }

        if (!empty($fromAccountId)) {
            $transfersQuery->whereHas('entries', function ($q) use ($fromAccountId) {
                $q->where('account_id', $fromAccountId)->where('credit', '>', 0);
            });
        }

        if (!empty($toAccountId)) {
            $transfersQuery->whereHas('entries', function ($q) use ($toAccountId) {
                $q->where('account_id', $toAccountId)->where('debit', '>', 0);
            });
        }

        $transfers = $transfersQuery->orderBy('journal_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // Rekapitulasi KPI
        $totalTransferAmount = 0;
        $totalBankDeposit    = 0; // Setoran ke Bank
        $totalCashRefill     = 0; // Pengisian Kas Kecil

        foreach ($transfers as $journal) {
            $debitEntry = $journal->entries->where('debit', '>', 0)->first();
            $amount     = (float) ($debitEntry?->debit ?? 0);
            $totalTransferAmount += $amount;

            $toAccName = strtolower($debitEntry?->account?->name ?? '');
            if (str_contains($toAccName, 'bank')) {
                $totalBankDeposit += $amount;
            } else {
                $totalCashRefill += $amount;
            }
        }

        return view('keuangan.kas-bank.transfer.index', compact(
            'businessUnits',
            'cashAccounts',
            'startDate',
            'endDate',
            'businessUnitId',
            'fromAccountId',
            'toAccountId',
            'transfers',
            'totalTransferAmount',
            'totalBankDeposit',
            'totalCashRefill'
        ));
    }

    /**
     * Simpan Transaksi Mutasi Internal Kas / Bank Baru (Auto Double-Entry)
     */
    public function store(Request $request)
    {
        $request->validate([
            'business_unit_id' => 'required|exists:business_units,id',
            'journal_date'     => 'required|date',
            'from_account_id'  => 'required|exists:chart_of_accounts,id',
            'to_account_id'    => 'required|exists:chart_of_accounts,id|different:from_account_id',
            'amount'           => 'required|numeric|min:1',
            'description'      => 'required|string|max:255',
        ], [
            'to_account_id.different' => 'Akun Kas/Bank Tujuan harus berbeda dengan Akun Asal.',
        ]);

        DB::beginTransaction();
        try {
            $journalNo = 'TRF-' . date('YmdHis') . '-' . strtoupper(substr(uniqid(), -3));

            // Header Jurnal
            $journal = Journal::create([
                'entity_id'        => auth()->user()->entity_id ?? 1,
                'business_unit_id' => $request->business_unit_id,
                'journal_no'       => $journalNo,
                'journal_date'     => $request->journal_date,
                'source_type'      => 'CASH_TRANSFER',
                'source_id'        => null,
                'description'      => '[Mutasi Internal] ' . $request->description,
                'status'           => 'posted',
            ]);

            // 1. Debit: Akun Kas/Bank Tujuan (Uang Masuk)
            JournalEntry::create([
                'journal_id' => $journal->id,
                'account_id' => $request->to_account_id,
                'debit'      => $request->amount,
                'credit'     => 0,
            ]);

            // 2. Kredit: Akun Kas/Bank Asal (Uang Keluar)
            JournalEntry::create([
                'journal_id' => $journal->id,
                'account_id' => $request->from_account_id,
                'debit'      => 0,
                'credit'     => $request->amount,
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Mutasi Internal Kas/Bank berhasil diproses & diposting ke Jurnal!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal memproses mutasi kas/bank: ' . $e->getMessage());
        }
    }

    public function export(Request $request)
    {
        $fileName = 'Laporan_Mutasi_Kas_Bank_' . date('Ymd_His') . '.xlsx';

        return Excel::download(new CashTransferExport($request), $fileName);
    }
}