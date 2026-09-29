<?php

namespace App\Exports;

use App\Models\Journal;
use Illuminate\Http\Request;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;

class JournalGroupedMatrixExport implements 
    FromCollection, 
    WithHeadings, 
    WithMapping, 
    WithStyles, 
    WithColumnFormatting, 
    ShouldAutoSize
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Ambil data Journal Header dan ratakan (flatten) baris detailnya
     */
    public function collection(): Enumerable
    {
        $journals = Journal::with(['businessUnit', 'entries.account'])
            ->filter([
                'start_date'       => $this->request->query('start_date'),
                'end_date'         => $this->request->query('end_date'),
                'business_unit_id' => $this->request->query('business_unit_id'),
                'source_type'      => $this->request->query('source_type'),
            ])
            ->orderBy('journal_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $entries = collect();

        foreach ($journals as $journal) {
            if ($journal->entries && $journal->entries->count() > 0) {
                foreach ($journal->entries as $entry) {
                    $entry->setRelation('journal', $journal);
                    $entries->push($entry);
                }
            }
        }

        return $entries;
    }

    /**
     * Judul Header 12 Kolom Matrix Excel
     */
    public function headings(): array
    {
        return [
            'No. Jurnal',
            'Tanggal',
            'Business Unit',
            'Tipe / Sumber',
            'No. Ref / Transaksi',
            'Keterangan / Memo Jurnal',
            'Kode Akun',
            'Nama Akun COA',
            'Debit (Rp)',
            'Kredit (Rp)',
            'Kode Akun Lawan',
            'Nama Akun Lawan',
        ];
    }

    /**
     * Mapping data per baris
     */
    public function map($entry): array
    {
        $journal = $entry->journal;

        // 1. Format Tanggal Aman
        $formattedDate = '-';
        if ($journal?->journal_date) {
            try {
                $formattedDate = Carbon::parse($journal->journal_date)->format('d/m/Y');
            } catch (\Exception $e) {
                $formattedDate = (string) $journal->journal_date;
            }
        }

        // 2. Logika Mencari Akun Lawan (Offset / Counterpart Account)
        $counterpartCode = '-';
        $counterpartName = '-';

        if ($journal?->entries && $journal->entries->count() > 1) {
            if ((float) $entry->debit > 0) {
                $counterpart = $journal->entries->where('credit', '>', 0)->first();
            } else {
                $counterpart = $journal->entries->where('debit', '>', 0)->first();
            }

            if ($counterpart?->account) {
                $counterpartCode = $counterpart->account->code ?? '-';
                $counterpartName = $counterpart->account->name ?? '-';
            }
        }

        return [
            $journal?->journal_no ?? '-',
            $formattedDate,
            $journal?->businessUnit?->name ?? '-',
            strtoupper($journal?->source_type ?? 'MANUAL'),
            $journal?->source_id ?? '-',
            $journal?->description ?? '-',
            $entry->account?->code ?? '-',
            $entry->account?->name ?? '-',
            (float) ($entry->debit ?? 0),
            (float) ($entry->credit ?? 0),
            $counterpartCode,
            $counterpartName,
        ];
    }

    /**
     * Format Angka Desimal/Mata Uang untuk Debit (I) & Kredit (J)
     */
    public function columnFormats(): array
    {
        return [
            'I' => '#,##0.00',
            'J' => '#,##0.00',
        ];
    }

    /**
     * Styling Excel (dengan Type Hint : ?array agar kompatibel)
     */
    public function styles(Worksheet $sheet): ?array
    {
        $highestRow = $sheet->getHighestRow();

        // 1. Header Styling (Baris 1)
        $sheet->getStyle('A1:L1')->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size'  => 11,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F2937'],
            ],
            'alignment' => [
                'vertical'   => Alignment::VERTICAL_CENTER,
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(28);

        // 2. Alignment Kolom Data
        $sheet->getStyle("A2:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("B2:B{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D2:D{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("E2:E{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("G2:G{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("K2:K{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // 3. Border Tipis Sel
        if ($highestRow >= 2) {
            $sheet->getStyle("A1:L{$highestRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color'       => ['rgb' => 'D1D5DB'],
                    ],
                ],
            ]);
        }

        return [];
    }
}