@extends('layouts.app')
@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h3 class="mb-1 fw-bold">Posting Upah</h3><div class="text-secondary small">Ajukan pembayaran berdasarkan upah hasil produksi yang sudah di-closing.</div></div><span class="badge text-bg-primary">{{ $workerGroups->count() }} kelompok pekerja</span></div>
    @if(session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger py-2">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif
    @forelse($workerGroups as $rows)
        @php($first = $rows->first())
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2"><div><strong>{{ $first->worker_name }}</strong> <span class="text-secondary small ms-2">{{ $first->business_unit_name }}</span><div class="small text-secondary">{{ $rows->count() }} rincian SPK belum diajukan</div></div><div class="text-end"><small class="text-secondary">Total tersedia</small><div class="fw-bold">Rp {{ number_format((float)$rows->sum('amount'),0,',','.') }}</div></div></div>
            <form method="POST" action="{{ route('produksi.posting-upah.store') }}">@csrf
                <input type="hidden" name="worker_id" value="{{ $first->worker_id }}"><input type="hidden" name="business_unit_id" value="{{ $first->business_unit_id }}">
                <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead class="table-light"><tr><th style="width:38px"><input class="form-check-input select-all-wages" type="checkbox" aria-label="Pilih semua"></th><th>Tanggal</th><th>SPK</th><th>No. Produksi</th><th>Keterangan</th><th class="text-end">Nominal</th></tr></thead><tbody>
                @foreach($rows as $row)<tr><td><input class="form-check-input wage-checkbox" type="checkbox" name="wage_ids[]" value="{{ $row->wage_id }}"></td><td>{{ \Carbon\Carbon::parse($row->production_date)->format('d/m/Y') }}</td><td class="fw-semibold">{{ $row->wo_no }}</td><td>{{ $row->production_no }}</td><td>{{ $row->wage_description }}</td><td class="text-end text-nowrap">Rp {{ number_format((float)$row->amount,0,',','.') }}</td></tr>@endforeach
                </tbody></table></div>
                <div class="p-3 border-top bg-light"><div class="row g-2 align-items-end"><div class="col-md-3"><label class="form-label">Jadwal Bayar</label><input type="date" name="scheduled_payment_date" class="form-control" value="{{ now()->toDateString() }}" required></div><div class="col-md-6"><label class="form-label">Catatan (opsional)</label><input type="text" name="description" class="form-control" maxlength="255" placeholder="Permohonan pembayaran upah {{ $first->worker_name }}"></div><div class="col-md-3 text-md-end"><button class="btn btn-primary" onclick="if(!this.form.querySelector('.wage-checkbox:checked')){event.preventDefault();alert('Pilih minimal satu rincian upah.')}"><i class="bi bi-file-earmark-check me-1"></i> Buat Voucher</button></div></div></div>
            </form>
        </div>
    @empty
        <div class="card border-0 shadow-sm"><div class="card-body text-center py-5"><i class="bi bi-check2-circle fs-1 text-success"></i><h5 class="mt-2">Tidak ada upah yang menunggu pengajuan</h5><div class="text-secondary small">Upah hasil closing yang belum masuk voucher akan tampil di sini.</div></div></div>
    @endforelse
</div>
<script>
document.querySelectorAll('.select-all-wages').forEach(m=>m.addEventListener('change',()=>{m.closest('form').querySelectorAll('.wage-checkbox').forEach(c=>c.checked=m.checked)}));
document.querySelectorAll('.wage-checkbox').forEach(c=>c.addEventListener('change',()=>{const f=c.closest('form'), all=[...f.querySelectorAll('.wage-checkbox')], m=f.querySelector('.select-all-wages');m.checked=all.every(x=>x.checked);m.indeterminate=all.some(x=>x.checked)&&!m.checked;}));
</script>
@endsection
