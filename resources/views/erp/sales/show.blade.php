@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 screen-only">
    <div><h4 class="mb-1">Detail Penjualan</h4><div class="text-secondary small">{{ $sale->invoice_no }}</div></div>
    <div class="d-flex gap-2"><button type="button" class="btn btn-outline-primary" onclick="window.print()">🖨 Cetak Penjualan</button><a href="{{ route('inventori.penjualan') }}" class="btn btn-outline-secondary">← Kembali</a></div>
</div>

<div class="card shadow-sm screen-only">
<div class="card-body">
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Tanggal</label><input class="form-control" value="{{ \Carbon\Carbon::parse($sale->sale_date)->format('d/m/Y H:i') }}" readonly></div>
        <div class="col-md-6"><label class="form-label">Nomor</label><input class="form-control" value="{{ $sale->invoice_no }}" readonly></div>
        <div class="col-md-6"><label class="form-label">Customer</label><input class="form-control" value="{{ $sale->customer_name ?? 'Umum' }}" readonly></div>
        <div class="col-md-6"><label class="form-label">Unit</label><input class="form-control" value="{{ $sale->unit_name ?? '-' }}" readonly></div>
    </div>
    <hr class="my-4"><h6 class="mb-2">Detail Penjualan</h6>
    <div class="table-responsive"><table class="table align-middle"><thead class="table-light"><tr><th>Item</th><th>Satuan</th><th class="text-end">Qty</th><th class="text-end">Harga</th><th class="text-end">Diskon</th><th class="text-end">Subtotal</th></tr></thead>
    <tbody>@forelse($items as $i)<tr><td><b>{{ $i->code }}</b><div class="small text-secondary">{{ $i->name }}</div></td><td>{{ $i->unit_code ?? '-' }}</td><td class="text-end">{{ number_format((float)$i->qty,3,',','.') }}</td><td class="text-end">Rp {{ number_format((float)$i->unit_price,0,',','.') }}</td><td class="text-end">Rp {{ number_format((float)$i->discount,0,',','.') }}</td><td class="text-end">Rp {{ number_format((float)$i->total,0,',','.') }}</td></tr>@empty<tr><td colspan="6" class="text-center text-secondary py-4">Tidak ada detail item.</td></tr>@endforelse</tbody></table></div>
    <hr class="my-4"><div class="row g-4">
        <div class="col-md-6"><h6>Informasi Customer</h6><div class="small text-secondary">Customer</div><div class="fw-semibold mb-3">{{ $sale->customer_name ?? 'Umum' }}</div>
            @if(!empty($sale->customer_phone))<div class="small text-secondary">No. Telepon</div><div class="mb-3">{{ $sale->customer_phone }}</div>@endif
            @if(!empty($sale->customer_address))<div class="small text-secondary">Alamat</div><div class="mb-3">{{ $sale->customer_address }}</div>@endif
            <div class="small text-secondary">Piutang Sebelumnya</div><div class="fw-semibold">Rp {{ number_format($previousReceivable,0,',','.') }}</div>
            @if(!empty($sale->memo))<div class="small text-secondary mt-3">Memo</div><div>{{ $sale->memo }}</div>@endif
        </div>
        <div class="col-md-6"><h6>Informasi Transaksi</h6>
            <div class="d-flex justify-content-between py-1"><span>Subtotal</span><strong>Rp {{ number_format((float)$sale->subtotal,0,',','.') }}</strong></div>
            <div class="d-flex justify-content-between py-1"><span>Diskon (Rp)</span><strong>Rp {{ number_format((float)$sale->discount,0,',','.') }}</strong></div>
            <div class="d-flex justify-content-between border-top mt-2 pt-2 fs-5"><strong>TOTAL</strong><strong>Rp {{ number_format((float)$sale->total,0,',','.') }}</strong></div>
            <div class="mt-3"><label class="form-label">Cara Bayar</label><input class="form-control" value="{{ $payments->pluck('method')->unique()->map(fn($m) => $m === 'credit' ? 'Kredit / Bon' : $m)->implode(', ') ?: '-' }}" readonly></div>
            @if($sale->due_date)<div class="mt-3"><label class="form-label">Jatuh Tempo</label><input class="form-control" value="{{ \Carbon\Carbon::parse($sale->due_date)->format('d/m/Y') }}" readonly></div>@endif
        </div>
    </div>
</div></div>

<div class="invoice-print">
    <div class="invoice-head">
        <div class="entity-name">{{ $sale->entity_name ?? config('app.name') }}</div>
        <div class="invoice-title">NOTA PENJUALAN</div>
        <div class="invoice-number">{{ $sale->invoice_no }}</div>
    </div>

    <div class="invoice-info">
        <div><span>Tanggal</span><b>{{ \Carbon\Carbon::parse($sale->sale_date)->format('d/m/Y H:i') }}</b></div>
        <div><span>Customer</span><b>{{ $sale->customer_name ?? 'Umum' }}</b></div>
        <div><span>Unit</span><b>{{ $sale->unit_name ?? '-' }}</b></div>
    </div>

    <div class="dash-line"></div>

    <table class="invoice-items">
        <thead>
            <tr><th>Item</th><th class="qty">Qty</th><th class="money">Harga</th><th class="money">Jumlah</th></tr>
        </thead>
        <tbody>
        @forelse($items as $i)
            <tr>
                <td><b>{{ $i->code }}</b><small>{{ $i->name }}</small></td>
                <td class="qty">{{ number_format((float)$i->qty,3,',','.') }} {{ $i->unit_code ?? '' }}</td>
                <td class="money">{{ number_format((float)$i->unit_price,0,',','.') }}</td>
                <td class="money">{{ number_format((float)$i->total,0,',','.') }}</td>
            </tr>
            @if((float)$i->discount > 0)
            <tr><td colspan="3" class="line-discount">Diskon</td><td class="money">-{{ number_format((float)$i->discount,0,',','.') }}</td></tr>
            @endif
        @empty
            <tr><td colspan="4">Tidak ada item.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="dash-line"></div>

    <div class="summary">
        <div><span>Subtotal</span><b>Rp {{ number_format((float)$sale->subtotal,0,',','.') }}</b></div>
        <div><span>Diskon</span><b>Rp {{ number_format((float)$sale->discount,0,',','.') }}</b></div>
        <div class="grand-total"><span>TOTAL</span><b>Rp {{ number_format((float)$sale->total,0,',','.') }}</b></div>
    </div>

    <div class="payment-info">
        <div><span>Pembayaran</span><b>{{ $payments->pluck('method')->unique()->map(fn($m) => $m === 'credit' ? 'Kredit / Bon' : $m)->implode(', ') ?: '-' }}</b></div>
        @if($sale->due_date)
        <div><span>Jatuh Tempo</span><b>{{ \Carbon\Carbon::parse($sale->due_date)->format('d/m/Y') }}</b></div>
        @endif
        @if($previousReceivable > 0)
        <div><span>Piutang Sebelumnya</span><b>Rp {{ number_format($previousReceivable,0,',','.') }}</b></div>
        @endif
    </div>

    @if(!empty($sale->memo))
    <div class="memo">Memo: {{ $sale->memo }}</div>
    @endif

    <div class="sign-area">
        <div>Penerima,</div>
        <div class="sign-space"></div>
        <div>(__________________)</div>
    </div>

    <div class="thanks">Terima kasih</div>
</div>
@endsection

@push('styles')
<style>
.invoice-print { display:none; }

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

    body > *:not(.invoice-print) { display:none !important; }
    body .invoice-print { display:block !important; }

    body * { visibility:hidden !important; }
    .invoice-print, .invoice-print * { visibility:visible !important; }

    .invoice-print {
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

    .screen-only { display:none !important; }

    .invoice-head {
        text-align:center;
        line-height:1.35;
        padding-bottom:5mm;
        border-bottom:1px solid #000;
        margin-bottom:4mm;
    }
    .entity-name { font-size:15pt; font-weight:700; text-transform:uppercase; }
    .invoice-title { font-size:12pt; font-weight:700; margin-top:1mm; }
    .invoice-number { font-size:10pt; margin-top:1mm; }

    .invoice-info {
        display:grid;
        grid-template-columns:1fr 1fr;
        column-gap:10mm;
        row-gap:1.5mm;
        margin-bottom:4mm;
    }
    .invoice-info div {
        display:flex;
        justify-content:space-between;
        gap:5mm;
    }
    .invoice-info span { color:#333; }
    .invoice-info b { text-align:right; }

    .dash-line {
        border-top:1px dashed #000;
        margin:3mm 0;
    }

    .invoice-items {
        width:100% !important;
        border-collapse:collapse !important;
        table-layout:fixed !important;
    }
    .invoice-items th,
    .invoice-items td {
        border:0 !important;
        padding:2mm 1mm !important;
        vertical-align:top !important;
        font-size:9pt !important;
    }
    .invoice-items thead th {
        border-bottom:1px solid #000 !important;
        padding-bottom:2mm !important;
    }
    .invoice-items th:first-child,
    .invoice-items td:first-child { width:43%; text-align:left; }
    .invoice-items .qty { width:19%; text-align:right; }
    .invoice-items .money { width:19%; text-align:right; }
    .invoice-items td small {
        display:block;
        font-size:8pt;
        line-height:1.25;
        margin-top:1mm;
    }
    .line-discount {
        text-align:right;
        font-size:8pt !important;
        padding-top:0 !important;
    }

    .summary {
        width:65mm;
        margin-left:auto;
        font-size:9.5pt;
    }
    .summary > div {
        display:flex;
        justify-content:space-between;
        gap:5mm;
        padding:1.5mm 0;
    }
    .summary .grand-total {
        border-top:1px solid #000;
        margin-top:1mm;
        padding-top:3mm;
        font-size:12pt;
        font-weight:700;
    }

    .payment-info {
        width:100%;
        margin-top:5mm;
        padding-top:3mm;
        border-top:1px dashed #000;
        font-size:9.5pt;
    }
    .payment-info div {
        display:flex;
        justify-content:space-between;
        gap:8mm;
        padding:1mm 0;
    }
    .payment-info b { text-align:right; }

    .memo {
        margin-top:4mm;
        padding-top:3mm;
        border-top:1px dashed #000;
        font-size:9pt;
        word-break:break-word;
    }

    .sign-area {
        margin-top:10mm;
        margin-left:auto;
        width:45mm;
        text-align:center;
        font-size:9pt;
    }
    .sign-space { height:12mm; }

    .thanks {
        margin-top:8mm;
        text-align:center;
        font-size:9pt;
    }
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    const params = new URLSearchParams(window.location.search);
    if (params.get('print') !== '1') return;

    window.addEventListener('load', function () {
        let moved = false;
        const goToNewSale = function () {
            if (moved) return;
            moved = true;
            window.location.href = '{{ route('inventori.penjualan.create') }}';
        };

        window.addEventListener('afterprint', goToNewSale, { once: true });
        setTimeout(goToNewSale, 1200);
        window.print();
    });
})();
</script>
@endpush