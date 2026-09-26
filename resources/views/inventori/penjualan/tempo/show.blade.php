@extends('layouts.app')
@section('content')
@if(request()->boolean('print'))
@include('components.print.faktur-penjualan', [
    'entityName' => $sale->entity_name ?? config('app.name'),
    'customerPhone' => $sale->customer_phone ?? null,
    'customerAddress' => $sale->customer_address ?? null,
    'documentNo' => $sale->invoice_no,
    'date' => $sale->sale_date,
    'customerName' => $sale->customer_name ?? 'Umum',
    'unitName' => $sale->unit_name ?? '-',
    'items' => $items,
    'subtotal' => $sale->subtotal,
    'discount' => $sale->discount,
    'total' => $sale->total,
    'payment' => $payments->pluck('method')->unique()->map(fn($m) => $m === 'credit' ? 'Kredit / Bon' : $m)->implode(', ') ?: '-',
    'dueDate' => $sale->due_date,
    'previousReceivable' => $previousReceivable,
    'memo' => $sale->memo,
])
@else
<div class="d-flex justify-content-between align-items-center mb-3 screen-only">
    <div><h4 class="mb-1">Detail Penjualan</h4><div class="text-secondary small">{{ $sale->invoice_no }}</div></div>
    <div class="d-flex gap-2">
        <a href="{{ request()->fullUrlWithQuery(['print' => 1]) }}" class="btn btn-outline-primary">🖨 Cetak Penjualan</a>
        <a href="{{ route('inventori.penjualan') }}" class="btn btn-outline-secondary">← Kembali</a>
    </div>
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
    <div class="table-responsive"><table class="table align-middle"><thead class="table-light"><tr><th>Item</th><th>Satuan Transaksi</th><th class="text-end">Qty Transaksi</th><th class="text-end">Qty Base</th><th class="text-end">Harga</th><th class="text-end">Diskon</th><th class="text-end">Subtotal</th></tr></thead>
    <tbody>@forelse($items as $i)<tr><td><b>{{ $i->code }}</b><div class="small text-secondary">{{ $i->name }}</div></td><td>{{ $i->transaction_unit_code ?? '-' }}</td><td class="text-end">{{ number_format((float)$i->qty,3,',','.') }}</td><td class="text-end">{{ number_format((float)$i->base_qty,3,',','.') }} {{ $i->base_unit_code ?? '' }}</td><td class="text-end">Rp {{ number_format((float)$i->unit_price,0,',','.') }}</td><td class="text-end">Rp {{ number_format((float)$i->discount,0,',','.') }}</td><td class="text-end">Rp {{ number_format((float)$i->total,0,',','.') }}</td></tr>@empty<tr><td colspan="7" class="text-center text-secondary py-4">Tidak ada detail item.</td></tr>@endforelse</tbody></table></div>
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
@endif
@endsection

@if(request()->boolean('print'))
@push('scripts')
<script>
window.addEventListener('load', function () {
    window.print();
});

window.addEventListener('afterprint', function () {
    window.close();
});
</script>
@endpush
@endif