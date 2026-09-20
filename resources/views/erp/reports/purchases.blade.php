@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1">Laporan Pembelian</h4>
        <div class="text-secondary small">Daftar transaksi pembelian</div>
    </div>
    <a href="{{ route('laporan.pembelian.export', request()->query()) }}" class="btn btn-success">⬇ Export Excel</a>
</div>

<div class="card shadow-sm">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('laporan.pembelian') }}" class="row g-2 align-items-end">
            <div class="col-md-2"><label class="form-label">Tanggal Mulai</label><input type="date" name="start_date" value="{{ request('start_date', now()->startOfMonth()->toDateString()) }}" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">Tanggal Akhir</label><input type="date" name="end_date" value="{{ request('end_date', now()->endOfMonth()->toDateString()) }}" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">Supplier</label><input name="supplier" value="{{ request('supplier') }}" class="form-control" placeholder="Cari supplier..."></div>
            <div class="col-md-2"><label class="form-label">Unit</label><select name="unit_id" class="form-select"><option value="">Semua Unit</option>@foreach($units as $u)<option value="{{ $u->id }}" @selected((string)request('unit_id') === (string)$u->id)>{{ $u->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Cara Bayar</label><select name="payment_method" class="form-select"><option value="">Semua</option><option value="Tunai" @selected(request('payment_method') === 'Tunai')>Tunai</option><option value="Transfer" @selected(request('payment_method') === 'Transfer')>Transfer</option><option value="QRIS" @selected(request('payment_method') === 'QRIS')>QRIS</option><option value="Kredit / Bon" @selected(request('payment_method') === 'Kredit / Bon')>Kredit / Bon</option></select></div>
            <div class="col-md-1"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">Semua</option><option value="draft" @selected(request('status') === 'draft')>Draft</option><option value="posted" @selected(request('status') === 'posted')>Posted</option><option value="received" @selected(request('status') === 'received')>Received</option></select></div>
            <div class="col-md-1"><button class="btn btn-outline-primary w-100">Cari</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr>
                <th>No. Pembelian</th><th>Tanggal</th><th>Supplier</th><th>Unit</th><th>Cara Bayar</th><th>Jatuh Tempo</th>
                <th class="text-end">Subtotal</th><th class="text-end">Diskon</th><th class="text-end">Total</th><th>Status</th>
            </tr></thead>
            <tbody>
            @forelse($rows as $r)
                <tr>
                    <td class="fw-semibold">{{ $r->purchase_no }}</td>
                    <td>{{ \Carbon\Carbon::parse($r->purchase_date)->format('d/m/Y') }}</td>
                    <td>{{ $r->supplier_name ?? '-' }}</td>
                    <td>{{ $r->unit_name ?? '-' }}</td>
                    <td>{{ $r->payment_method ?? '-' }}</td>
                    <td>{{ !empty($r->due_date) ? \Carbon\Carbon::parse($r->due_date)->format('d/m/Y') : '-' }}</td>
                    <td class="text-end">Rp {{ number_format((float)($r->subtotal ?? 0),0,',','.') }}</td>
                    <td class="text-end">Rp {{ number_format((float)($r->discount ?? 0),0,',','.') }}</td>
                    <td class="text-end">Rp {{ number_format((float)($r->total ?? 0),0,',','.') }}</td>
                    <td><span class="badge text-bg-secondary">{{ $r->status ?? '-' }}</span></td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-center text-secondary py-4">Belum ada transaksi pembelian.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="small text-secondary">@if($rows->total()) Menampilkan {{ $rows->firstItem() }}–{{ $rows->lastItem() }} dari {{ $rows->total() }} transaksi @else Tidak ada transaksi @endif</div>
        @if($rows->lastPage() > 1)
        <nav><ul class="pagination pagination-sm mb-0">
            <li class="page-item {{ $rows->onFirstPage() ? 'disabled' : '' }}"><a class="page-link" href="{{ $rows->previousPageUrl() ?? '#' }}">‹</a></li>
            @foreach($rows->getUrlRange(max(1,$rows->currentPage()-2),min($rows->lastPage(),$rows->currentPage()+2)) as $page=>$url)
                <li class="page-item {{ $page==$rows->currentPage() ? 'active' : '' }}"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
            @endforeach
            <li class="page-item {{ $rows->currentPage() >= $rows->lastPage() ? 'disabled' : '' }}"><a class="page-link" href="{{ $rows->nextPageUrl() ?? '#' }}">›</a></li>
        </ul></nav>
        @endif
    </div>
</div>
@endsection
