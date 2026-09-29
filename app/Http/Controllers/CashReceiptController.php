<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Exports\CashReceiptExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;

class CashReceiptController extends Controller
{
    public function index(Request $request)
    {
        $startDate      = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate        = $request->query('end_date', now()->endOfMonth()->format('Y-m-d'));
        $businessUnitId = $request->query('business_unit_id');
        $cashAccountId  = $request->query('cash_account_id');
        $receiptType    = $request->query('receipt_type');
        $search         = $request->query('search');

        $businessUnits = BusinessUnit::where('is_active', 1)->orderBy('code')->get();
        $customers     = Customer::where('is_active', 1)->orderBy('name')->get();

        // 1. Dropdown Akun Kas & Bank (is_cash_bank = 1)
        $cashAccounts = ChartOfAccount::where('is_cash_bank', 1)
            ->where('is_postable', 1)
            ->where('is_active', 1)
            ->orderBy('code')
            ->get();

        // 2. Dropdown COA Hierarki Level 2 (Header Group) dan Level 3 (Postable Child)
        $coaLevel2 = ChartOfAccount::where('level', 2)
            ->where('is_active', 1)
            ->with(['children' => function ($query) {
                $query->where('level', 3)
                      ->where('is_postable', 1)
                      ->where('is_active', 1)
                      ->orderBy('code');
            }])
            ->orderBy('code')
            ->get();

        // Base Query Filter
        $baseQuery = Journal::with(['businessUnit', 'entries.account'])
            ->whereIn('source_type', ['CASH_IN', 'AR_PAYMENT'])
            ->whereDate('journal_date', '>=', $startDate)
            ->whereDate('journal_date', '<=', $endDate);

        if (!empty($businessUnitId)) {
            $baseQuery->where('business_unit_id', $businessUnitId);
        }

        if (!empty($receiptType)) {
            $baseQuery->where('source_type', $receiptType);
        }

        if (!empty($cashAccountId)) {
            $baseQuery->whereHas('entries', function ($q) use ($cashAccountId) {
                $q->where('account_id', $cashAccountId)->where('debit', '>', 0);
            });
        }

        if (!empty($search)) {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('journal_no', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Hitung Ringkasan KPI Seluruh Data Terfilter
        $allFilteredData    = (clone $baseQuery)->get();
        $totalReceiptAmount = 0;
        $totalArPayment     = 0;
        $totalOtherReceipt  = 0;

        foreach ($allFilteredData as $journal) {
            $cashEntry = $journal->entries->where('debit', '>', 0)->first();
            $amount    = (float) ($cashEntry?->debit ?? 0);
            $totalReceiptAmount += $amount;

            if ($journal->source_type === 'AR_PAYMENT') {
                $totalArPayment += $amount;
            } else {
                $totalOtherReceipt += $amount;
            }
        }

        // Server-Side Pagination
        $receipts = $baseQuery->orderBy('journal_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('keuangan.kas-bank.masuk.index', compact(
            'businessUnits',
            'customers',
            'cashAccounts',
            'coaLevel2',
            'startDate',
            'endDate',
            'businessUnitId',
            'cashAccountId',
            'receiptType',
            'search',
            'receipts',
            'totalReceiptAmount',
            'totalArPayment',
            'totalOtherReceipt'
        ));
    }

    /**
     * AJAX Endpoint: Ambil Faktur Belum Lunas Milik Customer
     */
    /*public function getUnpaidInvoices($customerId)
    {
        $invoices = DB::table('sales')
            ->where('customer_id', $customerId)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->select('id', 'invoice_no', 'sale_date', 'total', 'paid_amount', DB::raw('(total - paid_amount) as remaining_amount'))
            ->orderBy('sale_date', 'asc')
            ->get();

        return response()->json([
            'success'  => true,
            'invoices' => $invoices
        ]);
    }*/
	public function getUnpaidInvoices($customerId)
	{
		$invoices = DB::table('sales as s')
			->leftJoinSub(
				DB::table('payments')
					->select(
						'sale_id',
						DB::raw('COALESCE(SUM(paid_amount), 0) as paid_amount')
					)
					->groupBy('sale_id'),
				'pay',
				'pay.sale_id',
				'=',
				's.id'
			)
			->where('s.customer_id', $customerId)
			->where('s.status', 'posted')
			->select(
				's.id',
				's.invoice_no',
				's.sale_date',
				's.total',
				DB::raw('COALESCE(pay.paid_amount, 0) as paid_amount'),
				DB::raw('GREATEST(s.total - COALESCE(pay.paid_amount, 0), 0) as remaining_amount')
			)
			->whereRaw('GREATEST(s.total - COALESCE(pay.paid_amount, 0), 0) > 0')
			->orderBy('s.sale_date', 'asc')
			->get();

		return response()->json([
			'success'  => true,
			'invoices' => $invoices,
		]);
	}

    /**
     * Action 1: Simpan Pelunasan Piutang Pelanggan (AR Payment)
     */
    public function storeArPayment(Request $request)
{
    $request->validate([
        'customer_id'   => 'required|exists:customers,id',
        'journal_date'  => 'required|date',
        'cash_account_id' => 'required|exists:chart_of_accounts,id',
        'amount'        => 'required|numeric|min:1',
        'invoice_ids'   => 'required|array|min:1',
        'pay_amounts'   => 'required|array',
        'description'   => 'required|string|max:255',
    ]);

    DB::beginTransaction();

    try {
        $arAccount = ChartOfAccount::where('code', '1000301')->first()
            ?? ChartOfAccount::where('type', 'asset')
                ->where('name', 'LIKE', '%Piutang%')
                ->first();

        if (!$arAccount) {
            throw new \Exception('Akun COA Piutang Usaha (1000301) tidak ditemukan.');
        }

        $totalPaidActual = 0;
        $journalBusinessUnitId = null;
        $entityId = auth()->user()->entity_id ?? 1;

        foreach ($request->invoice_ids as $invoiceId) {

            $payAmount = (float) ($request->pay_amounts[$invoiceId] ?? 0);

            if ($payAmount <= 0) {
                continue;
            }

            /*
             * Ambil faktur sekaligus pastikan milik customer
             * yang dipilih di popup.
             */
            $sale = DB::table('sales')
                ->where('id', $invoiceId)
                ->where('customer_id', $request->customer_id)
                ->where('status', 'posted')
                ->first();

            if (!$sale) {
                throw new \Exception(
                    "Faktur penjualan ID {$invoiceId} tidak ditemukan atau bukan milik pelanggan tersebut."
                );
            }

            /*
             * Hitung pembayaran yang sudah pernah masuk.
             */
            $alreadyPaid = (float) DB::table('payments')
                ->where('sale_id', $sale->id)
                ->sum('paid_amount');

            $remaining = max((float) $sale->total - $alreadyPaid, 0);

            if ($remaining <= 0) {
                throw new \Exception(
                    "Faktur {$sale->invoice_no} sudah lunas."
                );
            }

            if ($payAmount > $remaining) {
                throw new \Exception(
                    "Pembayaran faktur {$sale->invoice_no} melebihi sisa piutang."
                );
            }

            /*
             * BU pelunasan mengikuti BU dari faktur asal.
             */
            if ($journalBusinessUnitId === null) {
                $journalBusinessUnitId = $sale->business_unit_id;
            } elseif ($journalBusinessUnitId != $sale->business_unit_id) {
                throw new \Exception(
                    'Faktur yang dipilih berasal dari Business Unit berbeda. Pelunasan harus diproses sesuai BU faktur asal.'
                );
            }

            /*
             * Catat pembayaran.
             */
            DB::table('payments')->insert([
                'entity_id'        => $sale->entity_id,
                'business_unit_id' => $sale->business_unit_id,
                'sale_id'          => $sale->id,
                'user_id'          => auth()->id(),
                'payment_date'     => $request->journal_date,
                'method'           => 'transfer',
                'amount'           => $payAmount,
                'paid_amount'      => $payAmount,
                'change_amount'    => 0,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            $totalPaidActual += $payAmount;
        }

        if ($totalPaidActual <= 0) {
            throw new \Exception(
                'Nominal pembayaran pada faktur yang dicentang tidak boleh 0.'
            );
        }

        /*
         * Pastikan nominal header sama dengan pembayaran aktual.
         */
        if (abs((float) $request->amount - $totalPaidActual) > 0.01) {
            throw new \Exception(
                'Nominal pembayaran tidak sama dengan total pembayaran faktur.'
            );
        }

        if ($journalBusinessUnitId === null) {
            throw new \Exception('Business Unit faktur tidak ditemukan.');
        }

        /*
         * Header jurnal.
         */
        $journalNo = 'AR-' . date('YmdHis') . '-' .
            strtoupper(substr(uniqid(), -3));

        $journal = Journal::create([
            'entity_id'        => $entityId,
            'business_unit_id' => $journalBusinessUnitId,
            'journal_no'       => $journalNo,
            'journal_date'     => $request->journal_date,
            'source_type'      => 'AR_PAYMENT',
            'source_id'        => null,
            'description'      => $request->description,
            'status'           => 'posted',
        ]);

        /*
         * Debit Kas/Bank.
         */
        JournalEntry::create([
            'journal_id' => $journal->id,
            'account_id' => $request->cash_account_id,
            'debit'      => $totalPaidActual,
            'credit'     => 0,
        ]);

        /*
         * Kredit Piutang Usaha.
         */
        JournalEntry::create([
            'journal_id' => $journal->id,
            'account_id' => $arAccount->id,
            'debit'      => 0,
            'credit'     => $totalPaidActual,
        ]);

        DB::commit();

        return redirect()->back()->with(
            'success',
            'Pembayaran/Cicilan Piutang berhasil disimpan & diposting!'
        );

    } catch (\Exception $e) {

        DB::rollBack();

        return redirect()->back()->with(
            'error',
            'Gagal memproses pelunasan: ' . $e->getMessage()
        );
    }
}
    /**
     * Action 2: Simpan Penerimaan Kas/Bank Umum
     */
    public function storeOtherReceipt(Request $request)
    {
        $request->validate([
            'business_unit_id'       => 'required|exists:business_units,id',
            'journal_date'           => 'required|date',
            'cash_account_id'        => 'required|exists:chart_of_accounts,id',
            'counterpart_account_id' => 'required|exists:chart_of_accounts,id',
            'amount'                 => 'required|numeric|min:1',
            'description'            => 'required|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $journalNo = 'KM-' . date('YmdHis') . '-' . strtoupper(substr(uniqid(), -3));

            $journal = Journal::create([
                'entity_id'        => auth()->user()->entity_id ?? 1,
                'business_unit_id' => $request->business_unit_id,
                'journal_no'       => $journalNo,
                'journal_date'     => $request->journal_date,
                'source_type'      => 'CASH_IN',
                'source_id'        => null,
                'description'      => $request->description,
                'status'           => 'posted',
            ]);

            // Debit: Kas/Bank Penerima
            JournalEntry::create([
                'journal_id' => $journal->id,
                'account_id' => $request->cash_account_id,
                'debit'      => $request->amount,
                'credit'     => 0,
            ]);

            // Kredit: Akun Sumber / Lawan
            JournalEntry::create([
                'journal_id' => $journal->id,
                'account_id' => $request->counterpart_account_id,
                'debit'      => 0,
                'credit'     => $request->amount,
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Penerimaan Kas/Bank berhasil disimpan!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal memproses penerimaan: ' . $e->getMessage());
        }
    }

    public function export(Request $request)
    {
        $fileName = 'Laporan_Penerimaan_Kas_Bank_' . date('Ymd_His') . '.xlsx';
        return Excel::download(new CashReceiptExport($request), $fileName);
    }
}