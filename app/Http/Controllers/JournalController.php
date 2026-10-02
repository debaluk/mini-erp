<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

// Import Model
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\BusinessUnit;
use App\Models\ChartOfAccount; // Atau App\Models\Account (sesuai nama model COA Anda)

// Import Class Export Excel
use App\Exports\JournalGroupedMatrixExport;


class JournalController extends Controller
{
    /**
     * Display halaman utama / list jurnal.
     */
    public function index(Request $request)
    {
        $businessUnits = BusinessUnit::all();
        $accounts      = ChartOfAccount::where('is_postable', true)
                            ->orderBy('code', 'asc')
                            ->get();

        return view('keuangan.akuntansi.jurnal-umum.index', compact('businessUnits', 'accounts'));
    }

    /**
     * Server-Side DataTables Processing (AJAX JSON)
     */
    public function data(Request $request)
    {
        $draw   = (int) $request->input('draw', 1);
        $start  = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 15);
        $search = $request->input('search.value');

        // Parameter Filter Form
        $filters = [
            'start_date'       => $request->input('start_date'),
            'end_date'         => $request->input('end_date'),
            'business_unit_id' => $request->input('business_unit_id'),
            'source_type'      => $request->input('source_type'),
            'search'           => $search,
        ];

        // Base Query dengan Local Scope Filter dari Model Journal
        $query = Journal::with(['businessUnit', 'entries.account'])
            ->filter($filters);

        $totalRecords    = Journal::count();
        $filteredRecords = $query->count();

        $journals = $query->orderBy('journal_date', 'desc')
            ->orderBy('id', 'desc')
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function ($journal) {
                return [
                    'id'                 => $journal->id,
                    'journal_no'         => $journal->journal_no,
                    'journal_date'       => $journal->journal_date ? $journal->journal_date->format('d/m/Y') : '-',
                    'business_unit_name' => $journal->businessUnit->name ?? '-',
                    'source_type'        => $journal->source_type ?? 'manual',
                    'source_id'          => $this->sourceReference($journal),
                    'description'        => $journal->description,
                    'total_debit'        => (float) $journal->entries->sum('debit'),
                    'total_credit'       => (float) $journal->entries->sum('credit'),
                    'is_editable'        => $journal->is_editable, // True khusus source_type 'manual' / 'jurnal_umum'
                ];
            });

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data'            => $journals,
        ]);
    }

    private function sourceReference($journal): string
    {
        if (!$journal->source_id) {
            return '-';
        }

        $reference = match (strtolower((string) $journal->source_type)) {
            'sale' => DB::table('sales')->where('id', $journal->source_id)->value('invoice_no'),
            'purchase', 'po' => DB::table('purchases')->where('id', $journal->source_id)->value('purchase_no'),
            'receipt' => DB::table('receipts')->where('id', $journal->source_id)->value('receipt_no'),
            'purchase_return' => DB::table('purchase_returns')->where('id', $journal->source_id)->value('return_no'),
            default => null,
        };

        return $reference ?: (string) $journal->source_id;
    }

    /**
     * Ambil Detail Jurnal + Rincian Debit Kredit (AJAX JSON untuk Modal)
     */
    public function show($id)
    {
        $journal = Journal::with(['businessUnit', 'entries.account'])->find($id);

        if (!$journal) {
            return response()->json(['success' => false, 'message' => 'Data jurnal tidak ditemukan.'], 404);
        }

        return response()->json([
            'success' => true,
            'journal' => [
                'id'                 => $journal->id,
                'journal_no'         => $journal->journal_no,
                'journal_date'       => $journal->journal_date->format('Y-m-d'),
                'journal_date_formatted' => $journal->journal_date->format('d/m/Y'),
                'business_unit_id'   => $journal->business_unit_id,
                'business_unit_name' => $journal->businessUnit->name ?? '-',
                'source_type'        => $journal->source_type ?? 'manual',
                'source_id'          => $this->sourceReference($journal),
                'description'        => $journal->description,
                'is_editable'        => $journal->is_editable,
                'entries'            => $journal->entries->map(function ($entry) {
                    return [
                        'id'           => $entry->id,
                        'account_id'   => $entry->account_id,
                        'account_code' => $entry->account->code ?? '-',
                        'account_name' => $entry->account->name ?? '-',
                        'debit'        => (float) $entry->debit,
                        'credit'       => (float) $entry->credit,
                    ];
                }),
            ]
        ]);
    }

    /**
     * Simpan Jurnal Umum Manual Baru (ACT-01)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'business_unit_id' => 'required|exists:business_units,id',
            'journal_date'     => 'required|date',
            'description'      => 'required|string|max:255',
            'entries'          => 'required|array|min:2',
            'entries.*.account_id' => 'required|exists:chart_of_accounts,id',
            'entries.*.debit'      => 'required|numeric|min:0',
            'entries.*.credit'     => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        // Validasi Balance (Total Debit HARUS sama dengan Total Kredit)
        $totalDebit  = collect($request->entries)->sum('debit');
        $totalCredit = collect($request->entries)->sum('credit');

        if (abs($totalDebit - $totalCredit) > 0.01) {
            return response()->json([
                'success' => false, 
                'message' => 'Jurnal tidak seimbang (Unbalanced)! Total Debit (Rp ' . number_format($totalDebit) . ') != Total Kredit (Rp ' . number_format($totalCredit) . ').'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $entityId = auth()->user()->entity_id ?? 1;
            
            // Auto Generate No. Jurnal (Contoh: JRN/202609/0001)
            $period    = date('Ym', strtotime($request->journal_date));
            $lastCount = Journal::where('entity_id', $entityId)
                            ->where('journal_no', 'like', "JRN/{$period}/%")
                            ->count();
            $nextNo    = str_pad($lastCount + 1, 4, '0', STR_PAD_LEFT);
            $journalNo = "JRN/{$period}/{$nextNo}";

            // 1. Simpan Header
            $journal = Journal::create([
                'entity_id'        => $entityId,
                'business_unit_id' => $request->business_unit_id,
                'journal_no'       => $journalNo,
                'journal_date'     => $request->journal_date,
                'source_type'      => 'manual',
                'source_id'        => null,
                'description'      => $request->description,
                'status'           => 'posted',
            ]);

            // 2. Simpan Detail Entries
            foreach ($request->entries as $entry) {
                if ($entry['debit'] > 0 || $entry['credit'] > 0) {
                    JournalEntry::create([
                        'journal_id' => $journal->id,
                        'account_id' => $entry['account_id'],
                        'debit'      => $entry['debit'],
                        'credit'     => $entry['credit'],
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true, 
                'message' => "Jurnal Umum {$journalNo} berhasil disimpan dan diposting.",
                'data'    => $journal
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan jurnal: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update Jurnal Umum Manual
     */
    public function update(Request $request, $id)
    {
        $journal = Journal::find($id);

        if (!$journal) {
            return response()->json(['success' => false, 'message' => 'Jurnal tidak ditemukan.'], 404);
        }

        // GUARD: Hanya Jurnal Manual yang boleh di-edit
        if (!$journal->is_editable) {
            return response()->json([
                'success' => false, 
                'message' => '?? Akses Ditolak! Jurnal otomatis dari sistem tidak dapat di-edit.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'business_unit_id' => 'required|exists:business_units,id',
            'journal_date'     => 'required|date',
            'description'      => 'required|string|max:255',
            'entries'          => 'required|array|min:2',
            'entries.*.account_id' => 'required|exists:chart_of_accounts,id',
            'entries.*.debit'      => 'required|numeric|min:0',
            'entries.*.credit'     => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        // Validasi Balance
        $totalDebit  = collect($request->entries)->sum('debit');
        $totalCredit = collect($request->entries)->sum('credit');

        if (abs($totalDebit - $totalCredit) > 0.01) {
            return response()->json(['success' => false, 'message' => 'Jurnal tidak seimbang! Total Debit != Total Kredit.'], 422);
        }

        try {
            DB::beginTransaction();

            // Update Header
            $journal->update([
                'business_unit_id' => $request->business_unit_id,
                'journal_date'     => $request->journal_date,
                'description'      => $request->description,
            ]);

            // Re-create Entries
            JournalEntry::where('journal_id', $journal->id)->delete();

            foreach ($request->entries as $entry) {
                if ($entry['debit'] > 0 || $entry['credit'] > 0) {
                    JournalEntry::create([
                        'journal_id' => $journal->id,
                        'account_id' => $entry['account_id'],
                        'debit'      => $entry['debit'],
                        'credit'     => $entry['credit'],
                    ]);
                }
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => "Jurnal {$journal->journal_no} berhasil diperbarui."]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui jurnal: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Hapus Jurnal Umum Manual
     */
    public function destroy($id)
    {
        $journal = Journal::find($id);

        if (!$journal) {
            return response()->json(['success' => false, 'message' => 'Jurnal tidak ditemukan.'], 404);
        }

        // GUARD: Hanya Jurnal Manual yang boleh dihapus
        if (!$journal->is_editable) {
            return response()->json(['success' => false, 'message' => '?? Akses Ditolak! Jurnal otomatis sistem tidak dapat dihapus.'], 403);
        }

        try {
            DB::beginTransaction();
            JournalEntry::where('journal_id', $journal->id)->delete();
            $journal->delete();
            DB::commit();

            return response()->json(['success' => true, 'message' => 'Jurnal berhasil dihapus.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menghapus jurnal: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Export Excel Jurnal Berpasangan (Opsi 2: Matrix & Akun Lawan)
     */
    /*public function export(Request $request)
    {
        $fileName = 'Export_Jurnal_' . date('Ymd_His') . '.xlsx';
        return Excel::download(new JournalGroupedMatrixExport($request), $fileName);
    }*/
	
	public function export(Request $request)
    {
        $fileName = 'Export_Jurnal_Matrix_' . date('Ymd_His') . '.xlsx';

        return Excel::download(new JournalGroupedMatrixExport($request), $fileName);
    }
}