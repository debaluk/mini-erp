@props([
    'entityName' => config('app.name'),
    'documentNo' => '-',
    'date' => null,
    'customerName' => 'Umum',
    'unitName' => '-',
    'items' => collect(),
    'subtotal' => 0,
    'discount' => 0,
    'total' => 0,
    'payment' => '-',
    'memo' => null,
])

<div class="nota-pos-print">
    <div class="nota-pos-head">
        <div class="nota-pos-entity">{{ $entityName }}</div>
        <div class="nota-pos-title">NOTA PENJUALAN</div>
        <div>{{ $documentNo }}</div>
    </div>

    <div class="nota-pos-info">
        <div>{{ $date ? \Carbon\Carbon::parse($date)->format('d/m/Y H:i') : '-' }}</div>
        <div>Customer: {{ $customerName ?: 'Umum' }}</div>
        <div>{{ $unitName ?: '-' }}</div>
    </div>

    <div class="nota-pos-line"></div>

    <table class="nota-pos-items">
        <thead>
            <tr>
                <th>Barang</th>
                <th class="qty">Qty</th>
                <th class="money">Harga</th>
                <th class="money">Jumlah</th>
            </tr>
        </thead>
        <tbody>
        @forelse($items as $item)
            <tr>
                <td>
                    <b>{{ $item->code }}</b>
                    <small>{{ $item->name }}</small>
                </td>
                <td class="qty">{{ rtrim(rtrim(number_format((float)$item->qty,3,',','.'),'0'),',') }} {{ $item->unit_code ?? '' }}</td>
                <td class="money">{{ number_format((float)$item->unit_price,0,',','.') }}</td>
                <td class="money">{{ number_format((float)$item->total,0,',','.') }}</td>
            </tr>
            @if((float)$item->discount > 0)
            <tr>
                <td colspan="3" class="discount">Diskon</td>
                <td class="money">-{{ number_format((float)$item->discount,0,',','.') }}</td>
            </tr>
            @endif
        @empty
            <tr><td colspan="4">Tidak ada item.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="nota-pos-line"></div>

    <div class="nota-pos-summary">
        <div><span>Subtotal</span><b>Rp {{ number_format((float)$subtotal,0,',','.') }}</b></div>
        <div><span>Diskon</span><b>Rp {{ number_format((float)$discount,0,',','.') }}</b></div>
        <div class="grand"><span>TOTAL</span><b>Rp {{ number_format((float)$total,0,',','.') }}</b></div>
    </div>

    <div class="nota-pos-payment">
        Pembayaran: <b>{{ $payment }}</b>
    </div>

    @if(!empty($memo))
        <div class="nota-pos-memo">{{ $memo }}</div>
    @endif

    <div class="nota-pos-thanks">Terima kasih</div>
</div>

@once
@push('styles')
<style>
.nota-pos-print { display:none; }

@media print {
    @page {
        size: 80mm auto;
        margin: 3mm;
    }

    html, body {
        width: 80mm !important;
        min-width: 80mm !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
        font-family: "Courier New", Courier, monospace !important;
        font-size: 9pt !important;
        line-height: 1.25 !important;
    }

    body > * { display:none !important; }
    body * { visibility:hidden !important; }
    body .nota-pos-print,
    body .nota-pos-print * { visibility:visible !important; }

    .nota-pos-print {
        display:block !important;
        position:absolute !important;
        left:0 !important;
        top:0 !important;
        width:74mm !important;
        max-width:74mm !important;
        box-sizing:border-box !important;
        color:#000 !important;
        background:#fff !important;
    }

    .nota-pos-head {
        text-align:center;
        padding-bottom:3mm;
        border-bottom:1px dashed #000;
    }

    .nota-pos-entity {
        font-size:12pt;
        font-weight:700;
        text-transform:uppercase;
    }

    .nota-pos-title {
        font-size:10pt;
        font-weight:700;
        margin:1mm 0;
    }

    .nota-pos-info {
        margin-top:3mm;
        font-size:8.5pt;
    }

    .nota-pos-line {
        border-top:1px dashed #000;
        margin:2.5mm 0;
    }

    .nota-pos-items {
        width:100%;
        border-collapse:collapse;
        table-layout:fixed;
    }

    .nota-pos-items th,
    .nota-pos-items td {
        padding:1.5mm 0.5mm;
        vertical-align:top;
        font-size:8.5pt;
    }

    .nota-pos-items th:first-child,
    .nota-pos-items td:first-child { width:40%; text-align:left; }

    .nota-pos-items .qty { width:18%; text-align:right; }
    .nota-pos-items .money { width:21%; text-align:right; }

    .nota-pos-items td small {
        display:block;
        font-size:7.5pt;
        line-height:1.2;
    }

    .nota-pos-items thead th {
        border-bottom:1px solid #000;
    }

    .nota-pos-items .discount {
        text-align:right;
        font-size:7.5pt;
    }

    .nota-pos-summary {
        font-size:8.5pt;
    }

    .nota-pos-summary > div {
        display:flex;
        justify-content:space-between;
        padding:1mm 0;
        gap:3mm;
    }

    .nota-pos-summary .grand {
        border-top:1px solid #000;
        margin-top:1mm;
        padding-top:2mm;
        font-size:11pt;
        font-weight:700;
    }

    .nota-pos-payment {
        border-top:1px dashed #000;
        margin-top:3mm;
        padding-top:2mm;
        font-size:8.5pt;
    }

    .nota-pos-memo {
        border-top:1px dashed #000;
        margin-top:2mm;
        padding-top:2mm;
        font-size:8pt;
        word-break:break-word;
    }

    .nota-pos-thanks {
        text-align:center;
        margin-top:5mm;
        padding-top:2mm;
        border-top:1px dashed #000;
        font-size:8.5pt;
    }
}
</style>
@endpush
@endonce
