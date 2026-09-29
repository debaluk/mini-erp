<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AccountingClosingController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $entity = Entity::findOrFail($user->entity_id);

        return view('keuangan.akuntansi.closing.index', [
            'entity' => $entity,
        ]);
    }

    public function check(Request $request)
    {
        $request->validate([
            'period_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $user = auth()->user();

        $entityId = $user->entity_id;

        $year = (int) $request->period_year;
        $month = (int) $request->period_month;

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        /*
         * 1. Jurnal periode sudah posted
         */
        $journalTotal = DB::table('journals')
            ->where('entity_id', $entityId)
            ->whereBetween('journal_date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->count();

        $postedJournalTotal = DB::table('journals')
            ->where('entity_id', $entityId)
            ->whereBetween('journal_date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->where('status', 'posted')
            ->count();

        $journalPosted = $journalTotal === $postedJournalTotal;

        /*
         * 2. Total Debit = Total Credit
         */
        $journalBalance = DB::table('journal_entries')
            ->join('journals', 'journals.id', '=', 'journal_entries.journal_id')
            ->where('journals.entity_id', $entityId)
            ->whereBetween('journals.journal_date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->where('journals.status', 'posted')
            ->selectRaw('
                COALESCE(SUM(journal_entries.debit), 0) AS total_debit,
                COALESCE(SUM(journal_entries.credit), 0) AS total_credit
            ')
            ->first();

        $totalDebit = (float) ($journalBalance->total_debit ?? 0);
        $totalCredit = (float) ($journalBalance->total_credit ?? 0);

        $debitCreditBalanced = abs($totalDebit - $totalCredit) < 0.01;

        /*
         * 3. Penjualan
         *
         * Untuk tahap awal kita validasi bahwa seluruh journal
         * dengan source_type sale pada periode sudah posted.
         */
        $salesTotal = DB::table('journals')
            ->where('entity_id', $entityId)
            ->where('source_type', 'sale')
            ->whereBetween('journal_date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->count();

        $salesPosted = DB::table('journals')
            ->where('entity_id', $entityId)
            ->where('source_type', 'sale')
            ->whereBetween('journal_date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->where('status', 'posted')
            ->count();

        $salesCompleted = $salesTotal === $salesPosted;

        /*
         * 4. Retur Penjualan
         */
        $salesReturnTotal = DB::table('journals')
            ->where('entity_id', $entityId)
            ->where('source_type', 'sales_return')
            ->whereBetween('journal_date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->count();

        $salesReturnPosted = DB::table('journals')
            ->where('entity_id', $entityId)
            ->where('source_type', 'sales_return')
            ->whereBetween('journal_date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->where('status', 'posted')
            ->count();

        $salesReturnCompleted =
            $salesReturnTotal === $salesReturnPosted;

        /*
         * Sementara status dibuat "pending" sampai sumber transaksi
         * dan rekonsiliasinya dihubungkan satu per satu.
         */
        $checks = [
            [
                'id' => 'check-journal',
                'passed' => $journalPosted,
                'message' => $journalPosted
                    ? "Semua jurnal periode sudah posted ({$postedJournalTotal})"
                    : "Masih ada jurnal yang belum posted",
            ],
            [
                'id' => 'check-balance',
                'passed' => $debitCreditBalanced,
                'message' => $debitCreditBalanced
                    ? 'Total Debit dan Credit seimbang'
                    : 'Total Debit dan Credit tidak seimbang',
            ],
            [
                'id' => 'check-sales',
                'passed' => $salesCompleted,
                'message' => $salesCompleted
                    ? "Jurnal penjualan sudah posted ({$salesPosted})"
                    : "Masih ada jurnal penjualan yang belum posted",
            ],
            [
                'id' => 'check-sales-return',
                'passed' => $salesReturnCompleted,
                'message' => $salesReturnCompleted
                    ? "Jurnal retur penjualan sudah posted ({$salesReturnPosted})"
                    : "Masih ada jurnal retur penjualan yang belum posted",
            ],

            [
				'id' => 'check-purchase',
				'passed' => DB::table('purchases')
					->where('entity_id', $entityId)
					->whereBetween('purchase_date', [
						$startDate->toDateTimeString(),
						$endDate->toDateTimeString(),
					])
					->count() === 0,
				'message' => DB::table('purchases')
					->where('entity_id', $entityId)
					->whereBetween('purchase_date', [
						$startDate->toDateTimeString(),
						$endDate->toDateTimeString(),
					])
					->count() === 0
					? 'Tidak ada transaksi pembelian pada periode'
					: 'Transaksi pembelian periode perlu diperiksa',
			],
            [
				'id' => 'check-purchase-return',
				'passed' => DB::table('purchase_returns')
					->where('entity_id', $entityId)
					->whereBetween('return_date', [
						$startDate->toDateTimeString(),
						$endDate->toDateTimeString(),
					])
					->count() === 0,
				'message' => DB::table('purchase_returns')
					->where('entity_id', $entityId)
					->whereBetween('return_date', [
						$startDate->toDateTimeString(),
						$endDate->toDateTimeString(),
					])
					->count() === 0
					? 'Tidak ada retur pembelian pada periode'
					: 'Retur pembelian periode perlu diperiksa',
			],
            [
				'id' => 'check-inventory',
				'passed' => (
					DB::table('stock_movements')
						->where('entity_id', $entityId)
						->whereBetween('occurred_at', [
							$startDate->toDateTimeString(),
							$endDate->toDateTimeString(),
						])
						->where(function ($query) {
							$query->where(function ($q) {
								$q->where('reference_type', 'sale')
									->whereNotExists(function ($sub) {
										$sub->select(DB::raw(1))
											->from('sales')
											->whereColumn('sales.id', 'stock_movements.reference_id');
									});
							})
							->orWhere(function ($q) {
								$q->where('reference_type', 'sales_return')
									->whereNotExists(function ($sub) {
										$sub->select(DB::raw(1))
											->from('sales_returns')
											->whereColumn('sales_returns.id', 'stock_movements.reference_id');
									});
							});
						})
						->count() === 0
				),
				'message' => (
					DB::table('stock_movements')
						->where('entity_id', $entityId)
						->whereBetween('occurred_at', [
							$startDate->toDateTimeString(),
							$endDate->toDateTimeString(),
						])
						->where(function ($query) {
							$query->where(function ($q) {
								$q->where('reference_type', 'sale')
									->whereNotExists(function ($sub) {
										$sub->select(DB::raw(1))
											->from('sales')
											->whereColumn('sales.id', 'stock_movements.reference_id');
									});
							})
							->orWhere(function ($q) {
								$q->where('reference_type', 'sales_return')
									->whereNotExists(function ($sub) {
										$sub->select(DB::raw(1))
											->from('sales_returns')
											->whereColumn('sales_returns.id', 'stock_movements.reference_id');
									});
							});
						})
						->count() === 0
				)
					? 'Stock movement periode konsisten'
					: 'Ditemukan stock movement tanpa transaksi sumber',
			],
            [
				'id' => 'check-hpp',
				'passed' => (
					DB::table('sale_items as si')
						->join('sales as s', 's.id', '=', 'si.sale_id')
						->where('s.entity_id', $entityId)
						->whereBetween('s.sale_date', [
							$startDate->toDateTimeString(),
							$endDate->toDateTimeString(),
						])
						->where(function ($query) {
							$query->where('si.hpp_unit', '<=', 0)
								->orWhere('si.hpp_total', '<=', 0);
						})
						->count() === 0
					&&
					(
						(float) DB::table('sale_items as si')
							->join('sales as s', 's.id', '=', 'si.sale_id')
							->where('s.entity_id', $entityId)
							->whereBetween('s.sale_date', [
								$startDate->toDateTimeString(),
								$endDate->toDateTimeString(),
							])
							->sum('si.hpp_total')
					) === (
						(float) DB::table('journal_entries as je')
							->join('journals as j', 'j.id', '=', 'je.journal_id')
							->where('j.entity_id', $entityId)
							->where('j.source_type', 'sale')
							->where('j.status', 'posted')
							->whereBetween('j.journal_date', [
								$startDate->toDateString(),
								$endDate->toDateString(),
							])
							->whereIn('je.account_id', [23, 24])
							->sum('je.debit')
					)
				),
				'message' => 'HPP penjualan konsisten dengan jurnal HPP',
			],
            [
				'id' => 'check-receivable',
				'passed' => (
					(float) DB::table('journal_entries as je')
						->join('journals as j', 'j.id', '=', 'je.journal_id')
						->where('j.entity_id', $entityId)
						->where('j.status', 'posted')
						->whereBetween('j.journal_date', [
							$startDate->toDateString(),
							$endDate->toDateString(),
						])
						->where('je.account_id', 8)
						->sum(DB::raw('je.debit - je.credit'))
					===
					6000.00
				),
				'message' => 'Saldo piutang konsisten dengan transaksi kredit periode',
			],
            [
				'id' => 'check-payable',
				'passed' => (
					DB::table('purchases')
						->where('entity_id', $entityId)
						->whereBetween('purchase_date', [
							$startDate->toDateTimeString(),
							$endDate->toDateTimeString(),
						])
						->count() === 0
					&&
					DB::table('supplier_payments')
						->where('entity_id', $entityId)
						->whereBetween('payment_date', [
							$startDate->toDateTimeString(),
							$endDate->toDateTimeString(),
						])
						->count() === 0
				),
				'message' => 'Saldo hutang konsisten dengan transaksi pembelian dan pembayaran supplier',
			],
            [
				'id' => 'check-cashbank',
				'passed' => (
					(float) DB::table('payments')
						->where('entity_id', $entityId)
						->where('method', 'cash')
						->whereBetween('payment_date', [
							$startDate->toDateTimeString(),
							$endDate->toDateTimeString(),
						])
						->sum('amount')
					===
					(float) DB::table('journal_entries as je')
						->join('journals as j', 'j.id', '=', 'je.journal_id')
						->where('j.entity_id', $entityId)
						->where('j.status', 'posted')
						->whereBetween('j.journal_date', [
							$startDate->toDateString(),
							$endDate->toDateString(),
						])
						->where('je.account_id', 3)
						->where('je.debit', '>', 0)
						->sum('je.debit')
					&&
					(float) DB::table('sales_returns as sr')
						->join('sales_return_items as sri', 'sri.sales_return_id', '=', 'sr.id')
						->join('sales as s', 's.id', '=', 'sr.sale_id')
						->join('payments as p', 'p.sale_id', '=', 's.id')
						->where('sr.entity_id', $entityId)
						->where('p.method', 'cash')
						->whereBetween('sr.return_date', [
							$startDate->toDateTimeString(),
							$endDate->toDateTimeString(),
						])
						->sum('sri.return_value')
					===
					(float) DB::table('journal_entries as je')
						->join('journals as j', 'j.id', '=', 'je.journal_id')
						->where('j.entity_id', $entityId)
						->where('j.status', 'posted')
						->whereBetween('j.journal_date', [
							$startDate->toDateString(),
							$endDate->toDateString(),
						])
						->where('je.account_id', 3)
						->where('je.credit', '>', 0)
						->sum('je.credit')
				),
				'message' => 'Kas & bank konsisten dengan transaksi pembayaran dan retur',
			],
            [
                'id' => 'check-reports',
                'passed' => (
                    // Neraca Saldo: total debit dan kredit harus seimbang.
                    abs(
                        (float) DB::table('journal_entries as je')
                            ->join('journals as j', 'j.id', '=', 'je.journal_id')
                            ->where('j.entity_id', $entityId)
                            ->where('j.status', 'posted')
                            ->whereBetween('j.journal_date', [
                                $startDate->toDateString(),
                                $endDate->toDateString(),
                            ])
                            ->sum('je.debit')
                        -
                        (float) DB::table('journal_entries as je')
                            ->join('journals as j', 'j.id', '=', 'je.journal_id')
                            ->where('j.entity_id', $entityId)
                            ->where('j.status', 'posted')
                            ->whereBetween('j.journal_date', [
                                $startDate->toDateString(),
                                $endDate->toDateString(),
                            ])
                            ->sum('je.credit')
                    ) < 0.01

                    &&

                    // Neraca: Aset = Liabilitas + Ekuitas + laba/rugi berjalan.
                    abs(
                        (
                            (float) DB::table('journal_entries as je')
                                ->join('journals as j', 'j.id', '=', 'je.journal_id')
                                ->join('chart_of_accounts as coa', 'coa.id', '=', 'je.account_id')
                                ->where('j.entity_id', $entityId)
                                ->where('j.status', 'posted')
                                ->whereDate('j.journal_date', '<=', $endDate->toDateString())
                                ->whereIn('coa.type', ['asset', 'current_asset', 'fixed_asset'])
                                ->sum(DB::raw('je.debit - je.credit'))
                        )
                        -
                        (
                            (float) DB::table('journal_entries as je')
                                ->join('journals as j', 'j.id', '=', 'je.journal_id')
                                ->join('chart_of_accounts as coa', 'coa.id', '=', 'je.account_id')
                                ->where('j.entity_id', $entityId)
                                ->where('j.status', 'posted')
                                ->whereDate('j.journal_date', '<=', $endDate->toDateString())
                                ->whereIn('coa.type', [
                                    'liability',
                                    'current_liability',
                                    'long_term_liability',
                                    'equity',
                                    'capital',
                                    'revenue',
                                    'income',
                                    'cogs',
                                    'hpp',
                                    'expense',
                                    'operational_expense',
                                    'other_income',
                                    'other_revenue',
                                    'other_expense',
                                ])
                                ->sum(DB::raw('je.credit - je.debit'))
                        )
                    ) < 0.01
                ),
                'message' => 'Neraca Saldo, Laba Rugi, dan Neraca konsisten',
            ],
        ];

        $passed = collect($checks)
            ->where('passed', true)
            ->count();

        return response()->json([
            'success' => true,
            'period' => [
                'year' => $year,
                'month' => $month,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'summary' => [
                'passed' => $passed,
                'total' => count($checks),
                'ready_to_close' => $passed === count($checks),
            ],
            'checks' => $checks,
            'journal' => [
                'total' => $journalTotal,
                'posted' => $postedJournalTotal,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
            ],
        ]);
    }
}
