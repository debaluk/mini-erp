@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-1">Akun</h3>
        <div class="text-secondary">Chart of Accounts</div>
    </div>
    <a href="{{ route('akuntansi.akun.export-excel') }}" class="btn btn-success">Export Excel</a>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th style="width:150px">Kode</th>
                <th>Nama Akun</th>
                <th style="width:150px">Normal Balance</th>
                <th style="width:100px">Posting</th>
                <th style="width:110px">Status</th>
                <th class="text-end" style="width:150px">Aksi</th>
            </tr></thead>
            <tbody>
            @forelse($accounts as $a)
                @php $hasChildren = $accounts->contains(fn($child) => $child->parent_id == $a->id); @endphp
                <tr>
                    <td class="fw-semibold">{{ $a->code }}</td>
                    <td>
                        <span style="padding-left:{{ max(0, ((int)$a->level - 1) * 28) }}px">
                            @if($a->level > 1)<span class="text-secondary me-1">└─</span>@endif
                            {{ $a->name }}
                        </span>
                    </td>
                    <td>{{ ucfirst($a->normal_balance) }}</td>
                    <td>{!! $a->is_postable ? '<span class="badge text-bg-primary">Ya</span>' : '<span class="badge text-bg-light text-dark border">Header</span>' !!}</td>
                    <td>{!! $a->is_active ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' !!}</td>
                    <td class="text-end">
                        @if($a->level < 3)
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#account-add-{{ $a->id }}">+</button>
                        @endif
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#account-edit-{{ $a->id }}">Edit</button>
                        @if(!$hasChildren && !$a->is_postable)
                            <form method="POST" action="{{ route('akuntansi.akun.delete', $a->id) }}" class="d-inline" onsubmit="return confirm('Hapus akun ini?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Hapus</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-secondary py-4">Belum ada akun utama.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@foreach($accounts->where('level','<',3) as $a)
<div class="modal fade" id="account-add-{{ $a->id }}" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
<form method="POST" action="{{ route('akuntansi.akun.store') }}">
@csrf
<input type="hidden" name="parent_id" value="{{ $a->id }}">
<div class="modal-header"><h5 class="modal-title">Tambah Akun</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3"><label class="form-label">Parent Akun</label><input class="form-control" value="{{ $a->code }} — {{ $a->name }}" disabled></div>
    <div class="mb-3"><label class="form-label">Kode Akun</label><input class="form-control" value="Otomatis oleh sistem" disabled></div>
    <div class="mb-3"><label class="form-label">Nama Akun</label><input name="name" class="form-control" placeholder="Contoh: Kas Kecil" required></div>
    <div class="mb-3"><label class="form-label">Normal Balance</label><select name="normal_balance" class="form-select" required><option value="debit">Debit</option><option value="credit">Credit</option></select></div>
    <div><label class="form-label">Keterangan</label><textarea name="description" class="form-control" rows="2"></textarea></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary">Simpan</button></div>
</form></div></div></div>
@endforeach

@foreach($accounts as $a)
<div class="modal fade" id="account-edit-{{ $a->id }}" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
<form method="POST" action="{{ route('akuntansi.akun.update', $a->id) }}">
@csrf @method('PUT')
<div class="modal-header"><h5 class="modal-title">Edit Akun {{ $a->code }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3"><label class="form-label">Kode Akun</label><input class="form-control" value="{{ $a->code }}" disabled></div>
    <div class="mb-3"><label class="form-label">Nama Akun</label><input name="name" class="form-control" value="{{ $a->name }}" required></div>
    <div class="mb-3"><label class="form-label">Normal Balance</label><select name="normal_balance" class="form-select" required><option value="debit" @selected($a->normal_balance==='debit')>Debit</option><option value="credit" @selected($a->normal_balance==='credit')>Credit</option></select></div>
    <div class="mb-3"><label class="form-label">Keterangan</label><textarea name="description" class="form-control" rows="2">{{ $a->description }}</textarea></div>
    <div class="form-check"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($a->is_active)> Aktif</div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary">Simpan Perubahan</button></div>
</form></div></div></div>
@endforeach
@endsection
