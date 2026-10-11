@extends('layouts.app')
@section('content')
<div class="container-fluid px-4 py-3">
    <h3 class="mb-1 fw-bold">Voucher Bayar</h3><div class="text-secondary small mb-3">Permohonan dari unit kerja dan realisasi pembayaran oleh Keuangan.</div>
    @if(session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger py-2">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif
    <form method="GET" action="{{ route('keuangan.voucher.index') }}" class="card border-0 shadow-sm mb-3"><div class="card-body"><div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label">Kelompok Voucher</label><select name="category" class="form-select"><option value="">Semua Kelompok</option>@foreach(['UPAH_PRODUKSI'=>'Upah Produksi','HUTANG_SUPPLIER'=>'Hutang Supplier','OPERASIONAL'=>'Operasional','LAINNYA'=>'Lainnya'] as $k=>$label)<option value="{{ $k }}" @selected($category===$k)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">Semua Status</option>@foreach(['MENUNGGU_PEMBAYARAN'=>'Menunggu Pembayaran','DIBAYAR_SEBAGIAN'=>'Dibayar Sebagian','LUNAS'=>'Lunas'] as $k=>$label)<option value="{{ $k }}" @selected($status===$k)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-auto"><button class="btn btn-primary">Tampilkan</button></div><div class="col-auto"><a class="btn btn-outline-secondary" href="{{ route('keuangan.voucher.index') }}">Reset</a></div>
    </div></div></form>
    <div class="table-responsive bg-white rounded shadow-sm"><table class="table table-sm table-hover align-middle mb-0"><thead class="table-light"><tr><th>No. Voucher</th><th>Kelompok</th><th>Pekerja / Keterangan</th><th>Unit</th><th>Tgl Pengajuan</th><th>Jadwal Bayar</th><th class="text-end">Total</th><th class="text-end">Dibayar</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
    @forelse($vouchers as $v)
        @php($labels=['UPAH_PRODUKSI'=>'Upah Produksi','HUTANG_SUPPLIER'=>'Hutang Supplier','OPERASIONAL'=>'Operasional','LAINNYA'=>'Lainnya'])
        @php($statuses=['MENUNGGU_PEMBAYARAN'=>'Menunggu Pembayaran','DIBAYAR_SEBAGIAN'=>'Dibayar Sebagian','LUNAS'=>'Lunas'])
        @php($remaining=max(0,(float)$v->total_amount-(float)$v->paid_amount))
        <tr><td class="fw-semibold text-nowrap">{{ $v->voucher_no }}</td><td>{{ $labels[$v->category]??$v->category }}</td><td>{{ $v->worker_name ?: $v->description }}<div class="small text-secondary">{{ $v->description }}</div>@if($v->spk_list)<div class="small mt-1"><span class="fw-semibold">SPK:</span> {{ $v->spk_list }}</div>@endif<div class="small text-secondary">{{ $v->item_count }} rincian upah</div></td><td>{{ $v->business_unit_name }}</td><td>{{ \Carbon\Carbon::parse($v->request_date)->format('d/m/Y') }}</td><td>{{ \Carbon\Carbon::parse($v->scheduled_payment_date)->format('d/m/Y') }}</td><td class="text-end text-nowrap">Rp {{ number_format((float)$v->total_amount,0,',','.') }}</td><td class="text-end text-nowrap">Rp {{ number_format((float)$v->paid_amount,0,',','.') }}</td><td><span class="badge {{ $v->status==='LUNAS'?'text-bg-success':($v->status==='DIBAYAR_SEBAGIAN'?'text-bg-warning':'text-bg-secondary') }}">{{ $statuses[$v->status]??$v->status }}</span></td><td class="text-nowrap">
        @if($v->category==='UPAH_PRODUKSI' && $remaining>0)<button type="button" class="btn btn-sm btn-outline-primary btn-pay" data-bs-toggle="modal" data-bs-target="#modalBayar" data-id="{{ $v->id }}" data-no="{{ $v->voucher_no }}" data-worker="{{ $v->worker_name }}" data-remaining="{{ $remaining }}"><i class="bi bi-wallet2 me-1"></i> Bayar</button>@else<span class="text-secondary">—</span>@endif
        </td></tr>
    @empty<tr><td colspan="10" class="text-center text-secondary py-4">Belum ada voucher.</td></tr>@endforelse
    </tbody></table></div><div class="mt-3">{{ $vouchers->links() }}</div>
    <div class="small text-secondary mt-2">Pembayaran Hutang Supplier, Operasional, dan Lainnya belum diaktifkan; saat ini realisasi hanya tersedia untuk Upah Produksi.</div>
</div>
<div class="modal fade" id="modalBayar" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow"><div class="modal-header bg-primary text-white py-2"><h6 class="modal-title fw-bold">Realisasi Pembayaran Voucher</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<form id="formBayar" method="POST" action="">@csrf<div class="modal-body"><div class="mb-2"><div class="small text-secondary">Nomor Voucher</div><strong id="voucherNo"></strong></div><div class="mb-3"><div class="small text-secondary">Pekerja</div><strong id="voucherWorker"></strong></div><div class="row g-2">
<div class="col-6"><label class="form-label">Tanggal Bayar</label><input type="date" name="payment_date" class="form-control" value="{{ now()->toDateString() }}" required></div>
<div class="col-6"><label class="form-label">Nominal Bayar</label><input type="number" name="amount" id="voucherAmount" class="form-control" min="0.01" step="0.01" required></div>
<div class="col-12"><label class="form-label">Akun Kas / Bank</label><select name="cash_account_id" class="form-select" required><option value="">Pilih akun kas/bank</option>@foreach($cashAccounts as $a)<option value="{{ $a->id }}">{{ $a->code }} - {{ $a->name }}</option>@endforeach</select></div></div><div class="small text-secondary mt-2">Sisa voucher: <strong id="voucherRemaining"></strong></div></div><div class="modal-footer py-2"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan Pembayaran</button></div></form>
</div></div></div>
<script>
document.querySelectorAll('.btn-pay').forEach(b=>b.addEventListener('click',()=>{const n=Number(b.dataset.remaining||0);document.getElementById('voucherNo').textContent=b.dataset.no;document.getElementById('voucherWorker').textContent=b.dataset.worker||'-';document.getElementById('voucherRemaining').textContent='Rp '+n.toLocaleString('id-ID');const a=document.getElementById('voucherAmount');a.max=n.toFixed(2);a.value=n.toFixed(2);document.getElementById('formBayar').action='/keuangan/voucher/'+b.dataset.id+'/bayar';}));
</script>
@endsection
