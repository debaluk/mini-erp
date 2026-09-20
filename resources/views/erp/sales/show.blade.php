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

<div class="receipt-print">
    <div class="receipt-header">
        <div class="receipt-entity">{{ $sale->entity_name ?? config('app.name') }}</div>
        <div class="receipt-title">NOTA PENJUALAN</div>
        <div>{{ $sale->invoice_no }}</div>
    </div>

    <div class="receipt-meta">
        <div><span>Tanggal</span><b>{{ \Carbon\Carbon::parse($sale->sale_date)->format('d/m/Y H:i') }}</b></div>
        <div><span>Customer</span><b>{{ $sale->customer_name ?? 'Umum' }}</b></div>
        <div><span>Unit</span><b>{{ $sale->unit_name ?? '-' }}</b></div>
    </div>

    <div class="receipt-line"></div>

    <table class="receipt-items">
        <thead><tr><th>Item</th><th class="qty">Qty</th><th class="price">Harga</th><th class="total">Jumlah</th></tr></thead>
        <tbody>
        @forelse($items as $i)
            <tr>
                <td><b>{{ $i->code }}</b><small>{{ $i->name }}</small></td>
                <td class="qty">{{ number_format((float)$i->qty,3,',','.') }} {{ $i->unit_code ?? '' }}</td>
                <td class="price">{{ number_format((float)$i->unit_price,0,',','.') }}</td>
                <td class="total">{{ number_format((float)$i->total,0,',','.') }}</td>
            </tr>
            @if((float)$i->discount > 0)
            <tr><td colspan="3" class="item-discount">Diskon {{ number_format((float)$i->discount,0,',','.') }}</td><td class="total">-{{ number_format((float)$i->discount,0,',','.') }}</td></tr>
            @endif
        @empty
            <tr><td colspan="4" class="text-center">Tidak ada item.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="receipt-line"></div>

    <div class="receipt-total">
        <div><span>Subtotal</span><b>Rp {{ number_format((float)$sale->subtotal,0,',','.') }}</b></div>
        <div><span>Diskon</span><b>Rp {{ number_format((float)$sale->discount,0,',','.') }}</b></div>
        <div class="grand"><span>TOTAL</span><b>Rp {{ number_format((float)$sale->total,0,',','.') }}</b></div>
    </div>

    <div class="receipt-payment">
        <div><span>Pembayaran</span><b>{{ $payments->pluck('method')->unique()->map(fn($m) => $m === 'credit' ? 'Kredit / Bon' : $m)->implode(', ') ?: '-' }}</b></div>
        @if($sale->due_date)
        <div><span>Jatuh Tempo</span><b>{{ \Carbon\Carbon::parse($sale->due_date)->format('d/m/Y') }}</b></div>
        @endif
        @if($previousReceivable > 0)
        <div><span>Piutang Sebelumnya</span><b>Rp {{ number_format($previousReceivable,0,',','.') }}</b></div>
        @endif
    </div>

    @if(!empty($sale->memo))
    <div class="receipt-memo">Memo: {{ $sale->memo }}</div>
    @endif

    <div class="receipt-footer">Terima kasih</div>
</div>
@endsection

@push('styles')
<style>
.receipt-print { display:none; }

@media print {
    @page { size: 80mm auto; margin: 3mm; }

    html, body {
        width: 80mm !important;
        margin: 0 !important;
        padding: 0 !important;
        font-family: Arial, sans-serif !important;
        font-size: 10px !important;
    }

    body * { visibility: hidden !important; }
    .receipt-print, .receipt-print * { visibility: visible !important; }
    .receipt-print {
        display:block !important;
        width: 74mm !important;
        max-width: 74mm !important;
        margin:0 !important;
        padding:0 !important;
        color:#000 !important;
        font-size:10px !important;
    }

    .screen-only { display:none !important; }

    .receipt-header { text-align:center; line-height:1.35; margin-bottom:6px; }
    .receipt-entity { font-size:13px; font-weight:700; text-transform:uppercase; }
    .receipt-title { font-size:12px; font-weight:700; margin-top:2px; }

    .receipt-meta { line-height:1.45; }
    .receipt-meta div, .receipt-payment div, .receipt-total div {
        display:flex;
        justify-content:space-between;
        gap:8px;
    }
    .receipt-meta span, .receipt-payment span, .receipt-total span { white-space:nowrap; }
    .receipt-meta b, .receipt-payment b, .receipt-total b { text-align:right; }

    .receipt-line { border-top:1px dashed #000; margin:6px 0; }

    .receipt-items { width:100% !important; border-collapse:collapse; table-layout:fixed; }
    .receipt-items th, .receipt-items td {
        padding:2px 0 !important;
        border:0 !important;
        vertical-align:top;
        font-size:9px !important;
    }
    .receipt-items th { font-weight:700; border-bottom:1px solid #000 !important; padding-bottom:3px !important; }
    .receipt-items td small { display:block; font-size:8px !important; line-height:1.2; }
    .receipt-items th:first-child, .receipt-items td:first-child { width:38%; text-align:left; }
    .receipt-items .qty { width:22%; text-align:right; }
    .receipt-items .price { width:20%; text-align:right; }
    .receipt-items .total { width:20%; text-align:right; }
    .item-discount { text-align:right; font-size:8px !important; }

    .receipt-total { line-height:1.5; }
    .receipt-total .grand {
        border-top:1px solid #000;
        margin-top:3px;
        padding-top:4px;
        font-size:12px !important;
        font-weight:700;
    }

    .receipt-payment { margin-top:6px; line-height:1.5; }
    .receipt-memo { margin-top:7px; padding-top:5px; border-top:1px dashed #000; word-break:break-word; }
    .receipt-footer { text-align:center; margin-top:10px; font-size:9px; }
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