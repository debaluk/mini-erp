@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-1">Kasir / Shift</h3>
        <div class="text-secondary">Buka shift, kontrol kas, dan closing kasir</div>
    </div>
    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">Dashboard</a>
</div>

@if(session('success'))
<div class="alert alert-success py-2">{{ session('success') }}</div>
@endif
@if($errors->any())
<div class="alert alert-danger py-2"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

@if($openShift)
<div class="alert alert-success d-flex justify-content-between align-items-center">
    <div><strong>Shift Aktif</strong><br><span class="small">Dibuka {{ $openShift->opened_at }}</span></div>
    <span class="badge text-bg-success">OPEN</span>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-lg-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-secondary small">Saldo Awal</div><div class="fs-5 fw-semibold">Rp {{ number_format($summary['opening_cash'],0,',','.') }}</div></div></div></div>
    <div class="col-md-6 col-lg-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-secondary small">Penjualan Tunai</div><div class="fs-5 fw-semibold">Rp {{ number_format($summary['cash_sales'],0,',','.') }}</div></div></div></div>
    <div class="col-md-6 col-lg-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-secondary small">Kas Masuk</div><div class="fs-5 fw-semibold">Rp {{ number_format($summary['cash_in'],0,',','.') }}</div></div></div></div>
    <div class="col-md-6 col-lg-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-secondary small">Kas Keluar</div><div class="fs-5 fw-semibold">Rp {{ number_format($summary['cash_out'],0,',','.') }}</div></div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-semibold">Rekap Shift Berjalan</div>
            <div class="card-body">
                <div class="d-flex justify-content-between border-bottom py-2"><span>Saldo Awal</span><strong>Rp {{ number_format($summary['opening_cash'],0,',','.') }}</strong></div>
                <div class="d-flex justify-content-between border-bottom py-2"><span>+ Penjualan Tunai</span><strong>Rp {{ number_format($summary['cash_sales'],0,',','.') }}</strong></div>
                <div class="d-flex justify-content-between border-bottom py-2"><span>- Kembalian</span><strong>Rp {{ number_format($summary['cash_change'],0,',','.') }}</strong></div>
                <div class="d-flex justify-content-between border-bottom py-2"><span>+ Kas Masuk</span><strong>Rp {{ number_format($summary['cash_in'],0,',','.') }}</strong></div>
                <div class="d-flex justify-content-between border-bottom py-2"><span>- Kas Keluar</span><strong>Rp {{ number_format($summary['cash_out'],0,',','.') }}</strong></div>
                <div class="d-flex justify-content-between pt-3 fs-5"><span>Kas Menurut Sistem</span><strong>Rp {{ number_format($summary['expected_cash'],0,',','.') }}</strong></div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-semibold">Kas Masuk / Keluar</div>
            <div class="card-body">
                <form method="POST" action="{{ route('pos.shift.movement') }}">
                    @csrf
                    <div class="mb-2"><label class="form-label">Jenis</label><select name="movement_type" class="form-select" required><option value="in">Kas Masuk</option><option value="out">Kas Keluar</option></select></div>
                    <div class="mb-2"><label class="form-label">Jumlah</label><input name="amount" type="number" min="0.01" step="0.01" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Keterangan</label><input name="description" class="form-control" maxlength="255" required></div>
                    <button class="btn btn-primary w-100">Simpan</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header fw-semibold">Closing Kasir</div>
    <div class="card-body">
        <form method="POST" action="{{ route('pos.shift.close') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-5"><label class="form-label">Kas Fisik / Kas Aktual</label><input name="closing_cash" type="number" min="0" step="0.01" class="form-control form-control-lg" required placeholder="Hitung uang fisik kasir"></div>
            <div class="col-md-4"><div class="text-secondary small">Kas menurut sistem</div><div class="fs-5 fw-semibold">Rp {{ number_format($summary['expected_cash'],0,',','.') }}</div></div>
            <div class="col-md-3"><button class="btn btn-warning btn-lg w-100">Tutup Shift</button></div>
        </form>
        <div class="form-text mt-2">Setelah ditutup, shift tidak dapat menerima transaksi baru.</div>
    </div>
</div>
@else
<div class="card shadow-sm mb-4">
    <div class="card-header fw-semibold">Buka Shift</div>
    <div class="card-body">
        <form method="POST" action="{{ route('pos.shift.open') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-5"><label class="form-label">Saldo Awal Kas</label><input name="opening_cash" type="number" min="0" step="0.01" class="form-control form-control-lg" value="0" required></div>
            <div class="col-md-4"><div class="text-secondary small">Kasir</div><div class="fw-semibold">{{ auth()->user()->name }}</div></div>
            <div class="col-md-3"><button class="btn btn-primary btn-lg w-100">Buka Shift</button></div>
        </form>
    </div>
</div>
@endif

<div class="card shadow-sm">
    <div class="card-header fw-semibold">Riwayat Shift Saya</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>#</th><th>Buka</th><th>Tutup</th><th class="text-end">Saldo Awal</th><th class="text-end">Kas Sistem</th><th class="text-end">Kas Aktual</th><th class="text-end">Selisih</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            @forelse($shifts as $s)
                <tr>
                    <td>{{ $s->id }}</td>
                    <td>{{ $s->opened_at }}</td>
                    <td>{{ $s->closed_at ?? '—' }}</td>
                    <td class="text-end">Rp {{ number_format($s->opening_cash,0,',','.') }}</td>
                    <td class="text-end">Rp {{ number_format($s->expected_cash ?? 0,0,',','.') }}</td>
                    <td class="text-end">{{ $s->closing_cash === null ? '—' : 'Rp '.number_format($s->closing_cash,0,',','.') }}</td>
                    <td class="text-end fw-semibold">{{ $s->cash_difference === null ? '—' : 'Rp '.number_format($s->cash_difference,0,',','.') }}</td>
                    <td><span class="badge {{ $s->status === 'open' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ strtoupper($s->status) }}</span></td>
                    <td class="text-end"><button class="btn btn-outline-primary btn-sm" title="Lihat Rekap" onclick="showShift({{ $s->id }})">Detail</button></td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-secondary py-4">Belum ada shift.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if(method_exists($shifts,'links'))<div class="card-footer">{{ $shifts->links() }}</div>@endif
</div>

<div class="modal fade" id="shiftDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Rekap Shift</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" id="shiftDetailBody">Memuat...</div>
    </div></div>
</div>
@endsection

@push('scripts')
<script>
async function showShift(id){
    const modal=new bootstrap.Modal(document.getElementById('shiftDetailModal'));
    document.getElementById('shiftDetailBody').innerHTML='Memuat...';
    modal.show();
    const r=await fetch('/pos/shift/'+id+'/detail');
    const d=await r.json();
    const s=d.shift, x=d.summary;
    const money=v=>'Rp '+Number(v||0).toLocaleString('id-ID',{minimumFractionDigits:0});
    document.getElementById('shiftDetailBody').innerHTML=
        '<div class="row g-3">'+
        '<div class="col-md-6"><div class="border rounded p-3"><div class="text-secondary small">Status</div><strong>'+String(s.status).toUpperCase()+'</strong><hr><div>Dibuka: '+s.opened_at+'</div><div>Ditutup: '+(s.closed_at||'—')+'</div></div></div>'+
        '<div class="col-md-6"><div class="border rounded p-3"><div class="text-secondary small">Rekonsiliasi</div><div>Kas Sistem: <strong>'+money(x.expected_cash)+'</strong></div><div>Kas Aktual: <strong>'+money(x.closing_cash)+'</strong></div><div>Selisih: <strong>'+money(x.difference)+'</strong></div></div></div>'+
        '</div>';
}
</script>
@endpush
