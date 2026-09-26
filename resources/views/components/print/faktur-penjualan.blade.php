@props([
    'entityName' => config('app.name'),
    'documentNo' => '-',
    'date' => null,
    'customerName' => 'Umum',
    'customerPhone' => null,
    'customerAddress' => null,
    'unitName' => '-',
    'items' => collect(),
    'subtotal' => 0,
    'discount' => 0,
    'total' => 0,
    'payment' => '-',
    'dueDate' => null,
    'memo' => null,
])

@php
$terbilang = function ($number) use (&$terbilang) {
    $number = (int) round($number);

    $angka = [
        0 => 'Nol',
        1 => 'Satu',
        2 => 'Dua',
        3 => 'Tiga',
        4 => 'Empat',
        5 => 'Lima',
        6 => 'Enam',
        7 => 'Tujuh',
        8 => 'Delapan',
        9 => 'Sembilan',
        10 => 'Sepuluh',
        11 => 'Sebelas',
    ];

    if ($number < 12) {
        return $angka[$number];
    }

    if ($number < 20) {
        return $terbilang($number - 10) . ' Belas';
    }

    if ($number < 100) {
        return $terbilang(intdiv($number, 10)) . ' Puluh' .
            ($number % 10 ? ' ' . $terbilang($number % 10) : '');
    }

    if ($number < 200) {
        return 'Seratus' .
            ($number % 100 ? ' ' . $terbilang($number % 100) : '');
    }

    if ($number < 1000) {
        return $terbilang(intdiv($number, 100)) . ' Ratus' .
            ($number % 100 ? ' ' . $terbilang($number % 100) : '');
    }

    if ($number < 2000) {
        return 'Seribu' .
            ($number % 1000 ? ' ' . $terbilang($number % 1000) : '');
    }

    if ($number < 1000000) {
        return $terbilang(intdiv($number, 1000)) . ' Ribu' .
            ($number % 1000 ? ' ' . $terbilang($number % 1000) : '');
    }

    if ($number < 1000000000) {
        return $terbilang(intdiv($number, 1000000)) . ' Juta' .
            ($number % 1000000 ? ' ' . $terbilang($number % 1000000) : '');
    }

    if ($number < 1000000000000) {
        return $terbilang(intdiv($number, 1000000000)) . ' Miliar' .
            ($number % 1000000000 ? ' ' . $terbilang($number % 1000000000) : '');
    }

    return $terbilang(intdiv($number, 1000000000000)) . ' Triliun' .
        ($number % 1000000000000 ? ' ' . $terbilang($number % 1000000000000) : '');
};

$terbilangText = $terbilang($total) . ' Rupiah';
@endphp

<div class="faktur-sal01">
    <div class="faktur-header">
        <div class="faktur-company">
            <div class="faktur-logo">LOGO</div>
            <div>
                <div class="faktur-company-name">{{ $entityName }}</div>
                <div>Alamat Lengkap Perusahaan</div>
                <div>Telp: 0812-xxx | NPWP: xx.xxx.xxx</div>
            </div>
        </div>

        <div class="faktur-title">
            <strong>FAKTUR PENJUALAN</strong>
            <div>No: {{ $documentNo }}</div>
            <div>Tgl: {{ $date ? \Carbon\Carbon::parse($date)->format('d/m/Y') : '-' }}</div>
        </div>
    </div>

    <div class="faktur-info">
        <div>
            <div class="faktur-section-title">KEPADA YTH:</div>
            <div>Nama Customer : {{ $customerName ?: 'Umum' }}</div>
            <div>Alamat : {{ $customerAddress ?: '-' }}</div>
            <div>Telepon : {{ $customerPhone ?: '-' }}</div>
        </div>

        <div>
            <div class="faktur-section-title">DETAIL TRANSAKSI:</div>
            <div>Metode Bayar : {{ $payment }}</div>
            <div>Jatuh Tempo : {{ $dueDate ? \Carbon\Carbon::parse($dueDate)->format('d/m/Y') : '-' }}</div>
            <div>Unit Bisnis : {{ $unitName ?: '-' }}</div>
        </div>
    </div>

    @php
        $printItems = collect($items)->values();
        $printPages = $printItems->chunk(15);
    @endphp

    @forelse($printPages as $pageIndex => $pageItems)
        <table class="faktur-items {{ $pageIndex < $printPages->count() - 1 ? 'faktur-page-break' : '' }}">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Kode</th>
                    <th>Nama Barang</th>
                    <th>Qty</th>
                    <th>Satuan</th>
                    <th>Harga (Rp)</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
            @foreach($pageItems as $itemIndex => $item)
                @php
                    $globalIndex = ($pageIndex * 15) + $itemIndex;
                @endphp
                <tr>
                    <td class="center">{{ $globalIndex + 1 }}</td>
                    <td>{{ $item->code }}</td>
                    <td>{{ $item->name }}</td>
                    <td class="right">{{ number_format((float)$item->qty, 0, ',', '.') }}</td>
                    <td class="center">{{ $item->transaction_unit_code ?? $item->unit_code ?? '-' }}</td>
                    <td class="right">{{ number_format((float)$item->unit_price, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format((float)$item->total, 0, ',', '.') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @empty
        <table class="faktur-items">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Kode</th>
                    <th>Nama Barang</th>
                    <th>Qty</th>
                    <th>Satuan</th>
                    <th>Harga (Rp)</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="7" class="center">Tidak ada item.</td>
                </tr>
            </tbody>
        </table>
    @endforelse

    <div class="faktur-bottom">
        <div class="faktur-notes">
            <div class="terbilang-box"><strong>Terbilang:</strong> {{ $terbilangText }}</div>
            <div class="faktur-memo">
                <strong>CATATAN / MEMO:</strong><br>
                {{ $memo ?: '-' }}
                <br><br>
                * Barang yang sudah dibeli tidak dapat ditukar
                <br>
                * Pembayaran via Transfer
                <br>
                Bank: ____________________
                <br>
                No. Rekening: ____________________
                <br>
                A.n: ____________________
            </div>
        </div>

        <div class="faktur-total">
            <div><span>Subtotal</span><strong>Rp {{ number_format((float)$subtotal, 0, ',', '.') }}</strong></div>
            <div><span>Diskon Global</span><strong>Rp {{ number_format((float)$discount, 0, ',', '.') }}</strong></div>
            <div class="grand-total"><span>TOTAL BERSIH</span><strong>Rp {{ number_format((float)$total, 0, ',', '.') }}</strong></div>
        </div>
    </div>

    <div class="faktur-signatures">
        <div>Penerima / Customer<div class="signature-line">( .................... )</div></div>
        <div>Pengirim / Gudang<div class="signature-line">( .................... )</div></div>
        <div>Hormat Kami / Sales<div class="signature-line">( .................... )</div></div>
    </div>
</div>

<style>
.faktur-sal01 {
    display: none;
}

@media print {
    @page {
        size: 216mm 139mm;
        margin: 4mm 6mm;
    }

    html,
    body {
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
        color: #000 !important;
        font-family: "Courier New", Courier, monospace !important;
        font-size: 9.5pt !important;
        line-height: 1.2 !important;
    }

    body * {
        visibility: hidden !important;
    }

    body .faktur-sal01 {
        display: block !important;
        visibility: visible !important;
        width: 100% !important;
        box-sizing: border-box !important;
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
    }

    body .faktur-sal01,
    body .faktur-sal01 * {
        visibility: visible !important;
    }

    .faktur-header {
        display: grid;
        grid-template-columns: 55% 45%;
        width: 100%;
        margin-bottom: 3px;
    }

    .faktur-company {
        display: flex;
        gap: 3mm;
    }

    .faktur-logo {
        width: 18mm;
        height: 14mm;
        border: 1px solid #000;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 8pt;
    }

    .faktur-company-name {
        font-size: 11pt;
        font-weight: bold;
        text-transform: uppercase;
    }

    .faktur-title {
        text-align: right;
        font-size: 9.5pt;
    }

    .faktur-title strong {
        display: block;
        font-size: 13pt;
        font-weight: bold;
        text-transform: uppercase;
    }

    .faktur-info {
        display: grid;
        grid-template-columns: 55% 45%;
        width: 100%;
        padding: 3px 0;
        border-top: 2px dashed #000;
        border-bottom: 1px dashed #000;
        line-height: 1.2;
    }

    .faktur-section-title {
        font-weight: bold;
        text-transform: uppercase;
    }

    .faktur-items {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        margin: 2px 0;
    }

    .faktur-page-break {
        break-after: page;
        page-break-after: always;
    }

    .faktur-items tr {
        break-inside: avoid;
        page-break-inside: avoid;
    }

    .faktur-items th {
        border-top: 1px dashed #000;
        border-bottom: 1px dashed #000;
        padding: 3px 2px;
        font-size: 9pt;
        text-align: left;
        font-weight: bold;
    }

    .faktur-items td {
        padding: 2px;
        vertical-align: top;
        font-size: 9pt;
    }

    .faktur-items th:nth-child(1) { width: 5%; }
    .faktur-items th:nth-child(2) { width: 15%; }
    .faktur-items th:nth-child(3) { width: 38%; }
    .faktur-items th:nth-child(4) { width: 10%; }
    .faktur-items th:nth-child(5) { width: 10%; }
    .faktur-items th:nth-child(6) { width: 10%; }
    .faktur-items th:nth-child(7) { width: 12%; }

    .center {
        text-align: center;
    }

    .right {
        text-align: right;
    }

    .faktur-bottom {
        display: grid;
        grid-template-columns: 58% 42%;
        width: 100%;
        border-top: 1px dashed #000;
        padding-top: 3px;
    }

    .faktur-notes {
        padding-right: 10px;
        line-height: 1.2;
    }

    .terbilang-box {
        font-style: italic;
        font-size: 8.5pt;
        border: 1px dashed #000;
        padding: 3px 5px;
        margin-bottom: 3px;
    }

    .faktur-memo {
        font-size: 8.5pt;
        margin-top: 2px;
    }

    .faktur-total {
        width: 100%;
    }

    .faktur-total > div {
        display: flex;
        justify-content: space-between;
        padding: 1px 0;
    }

    .faktur-total .grand-total {
        font-weight: bold;
        border-top: 1px dashed #000;
        border-bottom: 1px dashed #000;
        padding: 2px 0;
    }

    .faktur-signatures {
        width: 100%;
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        margin-top: 6px;
        text-align: center;
    }

    .faktur-signatures > div {
        height: 38px;
        vertical-align: bottom;
    }

    .signature-line {
        margin-top: 12px;
    }
}
</style>
