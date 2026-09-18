@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-1">Akun</h3>
        <div class="text-secondary">Chart of Accounts — struktur 3 level</div>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#account-modal">+ Akun</button>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th>Kode</th><th>Nama Akun</th><th>Level</th><th>Kelompok</th><th>Normal</th><th>Posting</th><th>Status</th><th class="text-end">Aksi</th>
            </tr></thead>
            <tbody>
            @forelse($accounts as $a)
                <tr>
                    <td class="fw-semibold">{{ $a->code }}</td>
                    <td style="padding-left:{{ ($a->level - 1) * 28 }}px">{{ $a->name }}</td>
                    <td>Level {{ $a->level }}</td>
                    <td>{{ strtoupper($a->type) }}</td>
                    <td>{{ strtoupper($a->normal_balance) }}</td>
                    <td>{!! $a->is_postable ? '<span class="badge text-bg-primary">Ya</span>' : '<span class="badge text-bg-secondary">Header</span>' !!}</td>
                    <td>{!! $a->is_active ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' !!}</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#account-edit-{{ $a->id }}">Edit</button>
                        <form method="POST" action="{{ route('akuntansi.akun.delete', $a->id) }}" class="d-inline" onsubmit="return confirm('Hapus akun ini?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Hapus</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-secondary py-4">Belum ada akun.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="account-modal" tabindex="-1">
<div class="modal-dialog modal-lg"><div class="modal-content">
<form method="POST" action="{{ route('akuntansi.akun.store') }}">
@csrf
<div class="modal-header"><h5 class="modal-title">Tambah Akun</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<div class="row g-3">
<div class="col-md-4"><label class="form-label">Kode Akun</label><input name="code" class="form-control" maxlength="7" pattern="\d{3}(\d{2})?(\d{2})?" placeholder="100 / 10001 / 1000101" required><div class="form-text">Level 1 = 3 digit, Level 2 = 5 digit, Level 3 = 7 digit.</div></div>
<div class="col-md-8"><label class="form-label">Nama Akun</label><input name="name" class="form-control" required></div>
<div class="col-md-4"><label class="form-label">Tipe</label><select name="type" class="form-select" required><option value="asset">Asset</option><option value="liability">Liability</option><option value="equity">Equity</option><option value="revenue">Revenue</option><option value="cogs">COGS</option><option value="expense">Expense</option></select></div>
<div class="col-md-4"><label class="form-label">Normal Balance</label><select name="normal_balance" class="form-select" required><option value="debit">Debit</option><option value="credit">Credit</option></select></div>
<div class="col-md-4"><label class="form-label">Parent Akun</label><select name="parent_id" class="form-select"><option value="">- Tidak ada parent (Level 1) -</option>@foreach($parents as $p)<option value="{{ $p->id }}">{{ $p->code }} — {{ $p->name }} (Level {{ $p->level }})</option>@endforeach</select></div>
<div class="col-md-4"><div class="form-check mt-4"><input type="hidden" name="is_cash_bank" value="0"><input class="form-check-input" type="checkbox" name="is_cash_bank" value="1" id="is_cash_bank"><label class="form-check-label" for="is_cash_bank">Kas / Bank</label></div></div>
<div class="col-12"><label class="form-label">Keterangan</label><textarea name="description" class="form-control" rows="2"></textarea></div>
</div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary">Simpan</button></div>
</form></div></div></div>

@foreach($accounts as $a)
<div class="modal fade" id="account-edit-{{ $a->id }}" tabindex="-1">
<div class="modal-dialog modal-lg"><div class="modal-content"><form method="POST" action="{{ route('akuntansi.akun.update', $a->id) }}">@csrf @method('PUT')
<div class="modal-header"><h5 class="modal-title">Edit Akun {{ $a->code }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><div class="row g-3">
<div class="col-md-4"><label class="form-label">Kode Akun</label><input name="code" class="form-control" maxlength="7" value="{{ $a->code }}" required></div>
<div class="col-md-8"><label class="form-label">Nama Akun</label><input name="name" class="form-control" value="{{ $a->name }}" required></div>
<div class="col-md-4"><label class="form-label">Tipe</label><select name="type" class="form-select">@foreach(['asset'=>'Asset','liability'=>'Liability','equity'=>'Equity','revenue'=>'Revenue','cogs'=>'COGS','expense'=>'Expense'] as $k=>$v)<option value="{{ $k }}" @selected($a->type===$k)>{{ $v }}</option>@endforeach</select></div>
<div class="col-md-4"><label class="form-label">Normal Balance</label><select name="normal_balance" class="form-select"><option value="debit" @selected($a->normal_balance==='debit')>Debit</option><option value="credit" @selected($a->normal_balance==='credit')>Credit</option></select></div>
<div class="col-md-4"><label class="form-label">Parent Akun</label><select name="parent_id" class="form-select"><option value="">- Tidak ada parent -</option>@foreach($parents as $p) @if($p->id !== $a->id)<option value="{{ $p->id }}" @selected($a->parent_id==$p->id)>{{ $p->code }} — {{ $p->name }} (Level {{ $p->level }})</option>@endif @endforeach</select></div>
<div class="col-md-4"><div class="form-check mt-4"><input type="hidden" name="is_cash_bank" value="0"><input class="form-check-input" type="checkbox" name="is_cash_bank" value="1" @checked($a->is_cash_bank)> <label class="form-check-label">Kas / Bank</label></div></div>
<div class="col-md-4"><div class="form-check mt-4"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($a->is_active)> Aktif</div></div>
<div class="col-12"><label class="form-label">Keterangan</label><textarea name="description" class="form-control" rows="2">{{ $a->description }}</textarea></div>
</div></div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary">Simpan Perubahan</button></div>
</form></div></div></div>
@endforeach
@endsection
