@props([
    'entityName' => config('app.name'),
    'title' => 'NOTA',
    'documentNo' => '-',
    'date' => null,
    'customerName' => 'Umum',
    'unitName' => '-',
    'items' => collect(),
    'subtotal' => 0,
    'discount' => 0,
    'total' => 0,
    'payment' => '-',
    'dueDate' => null,
    'previousReceivable' => 0,
    'memo' => null,
])

<div class="nota-print">
    <div class="nota-head">
        <div class="nota-entity">{{ $entityName }}</div>
        <div class="nota-title">{{ $title }}</div>
        <div class="nota-number">{{ $documentNo }}</div>
    </div>

    <div class="nota-info">
        <div><span>Tanggal</span><b>{{ $date ? \Carbon\Carbon::parse($date)->format('d/m/Y H:i') : '-' }}</b></div>
        <div><span>Customer</span><b>{{ $customerName ?: 'Umum' }}</b></div>
        <div><span>Unit</span><b>{{ $unitName ?: '-' }}</b></div>
    </div>

    <div class="nota-line"></div>

    <table class="nota-items">
        <thead>
            <tr>
                <th>Item</th>
                <th class="nota-qty">Qty</th>
                <th class="nota-money">Harga</th>
                <th class="nota-money">Jumlah</th>
            </tr>
        </thead>
        <tbody>
        @forelse($items as $item)
            <tr>
                <td><b>{{ $item->code }}</b><small>{{ $item->name }}</small></td>
                <td class="nota-qty">{{ number_format((float)$item->qty,3,',','.') }} {{ $item->unit_code ?? '' }}</td>
                <td class="nota-money">{{ number_format((float)$item->unit_price,0,',','.') }}</td>
                <td class="nota-money">{{ number_format((float)$item->total,0,',','.') }}</td>
            </tr>
            @if((float)$item->discount > 0)
            <tr>
                <td colspan="3" class="nota-item-discount">Diskon</td>
                <td class="nota-money">-{{ number_format((float)$item->discount,0,',','.') }}</td>
            </tr>
            @endif
        @empty
            <tr><td colspan="4">Tidak ada item.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="nota-line"></div>

    <div class="nota-summary">
        <div><span>Subtotal</span><b>Rp {{ number_format((float)$subtotal,0,',','.') }}</b></div>
        <div><span>Diskon</span><b>Rp {{ number_format((float)$discount,0,',','.') }}</b></div>
        <div class="nota-grand"><span>TOTAL</span><b>Rp {{ number_format((float)$total,0,',','.') }}</b></div>
    </div>

    <div class="nota-payment">
        <div><span>Pembayaran</span><b>{{ $payment }}</b></div>
        @if($dueDate)
        <div><span>Jatuh Tempo</span><b>{{ \Carbon\Carbon::parse($dueDate)->format('d/m/Y') }}</b></div>
        @endif
        @if((float)$previousReceivable > 0)
        <div><span>Piutang Sebelumnya</span><b>Rp {{ number_format((float)$previousReceivable,0,',','.') }}</b></div>
        @endif
    </div>

    @if(!empty($memo))
    <div class="nota-memo">Memo: {{ $memo }}</div>
    @endif

    <div class="nota-sign">
        <div>Penerima,</div>
        <div class="nota-sign-space"></div>
        <div>(__________________)</div>
    </div>

    <div class="nota-thanks">Terima kasih</div>
</div>

@once
@push('styles')
<style>
.nota-print { display:none; }

@media print {
    @page {
        size: 165mm 216mm;
        margin: 8mm;
    }

    html, body {
        width: 165mm !important;
        min-width: 165mm !important;
        margin: 0 !important;
        padding: 0 !important;
        background:#fff !important;
        font-family: Arial, Helvetica, sans-serif !important;
        font-size: 10pt !important;
    }

    body > * { display:none !important; }
    body .nota-print { display:block !important; }
    body * { visibility:hidden !important; }
    .nota-print, .nota-print * { visibility:visible !important; }

    .nota-print {
        position:absolute !important;
        left:0 !important;
        top:0 !important;
        width:149mm !important;
        max-width:149mm !important;
        min-height:200mm !important;
        margin:0 !important;
        padding:0 !important;
        box-sizing:border-box !important;
        color:#000 !important;
        background:#fff !important;
    }

    .nota-head {
        text-align:center;
        line-height:1.35;
        padding-bottom:5mm;
        border-bottom:1px solid #000;
        margin-bottom:4mm;
    }
    .nota-entity { font-size:15pt; font-weight:700; text-transform:uppercase; }
    .nota-title { font-size:12pt; font-weight:700; margin-top:1mm; }
    .nota-number { font-size:10pt; margin-top:1mm; }

    .nota-info {
        display:grid;
        grid-template-columns:1fr 1fr;
        column-gap:10mm;
        row-gap:1.5mm;
        margin-bottom:4mm;
    }
    .nota-info div,
    .nota-payment div,
    .nota-summary div {
        display:flex;
        justify-content:space-between;
        gap:5mm;
    }
    .nota-info b,
    .nota-payment b,
    .nota-summary b { text-align:right; }

    .nota-line { border-top:1px dashed #000; margin:3mm 0; }

    .nota-items {
        width:100% !important;
        border-collapse:collapse !important;
        table-layout:fixed !important;
    }
    .nota-items th,
    .nota-items td {
        border:0 !important;
        padding:2mm 1mm !important;
        vertical-align:top !important;
        font-size:9pt !important;
    }
    .nota-items thead th {
        border-bottom:1px solid #000 !important;
        padding-bottom:2mm !important;
    }
    .nota-items th:first-child,
    .nota-items td:first-child { width:43%; text-align:left; }
    .nota-items .nota-qty { width:19%; text-align:right; }
    .nota-items .nota-money { width:19%; text-align:right; }
    .nota-items td small {
        display:block;
        font-size:8pt;
        line-height:1.25;
        margin-top:1mm;
    }
    .nota-item-discount {
        text-align:right;
        font-size:8pt !important;
        padding-top:0 !important;
    }

    .nota-summary {
        width:65mm;
        margin-left:auto;
        font-size:9.5pt;
    }
    .nota-summary > div { padding:1.5mm 0; }
    .nota-summary .nota-grand {
        border-top:1px solid #000;
        margin-top:1mm;
        padding-top:3mm;
        font-size:12pt;
        font-weight:700;
    }

    .nota-payment {
        width:100%;
        margin-top:5mm;
        padding-top:3mm;
        border-top:1px dashed #000;
        font-size:9.5pt;
    }
    .nota-payment div { padding:1mm 0; }

    .nota-memo {
        margin-top:4mm;
        padding-top:3mm;
        border-top:1px dashed #000;
        font-size:9pt;
        word-break:break-word;
    }

    .nota-sign {
        margin-top:10mm;
        margin-left:auto;
        width:45mm;
        text-align:center;
        font-size:9pt;
    }
    .nota-sign-space { height:12mm; }

    .nota-thanks {
        margin-top:8mm;
        text-align:center;
        font-size:9pt;
    }
}
</style>
@endpush
@endonce