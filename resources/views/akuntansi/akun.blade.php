@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-1">Akun</h3>
        <div class="text-secondary">Daftar akun keuangan</div>
    </div>
    <a href="{{ route('akuntansi.akun.export-excel') }}" class="btn btn-success">Export Excel</a>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:150px">Kode</th>
                    <th>Nama Akun</th>
                    <th style="width:150px">Normal Balance</th>
                    <th class="text-end" style="width:100px">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse($accounts as $a)
                <tr>
                    <td class="fw-semibold">{{ $a->code }}</td>
                    <td>{{ $a->name }}</td>
                    <td>{{ ucfirst($a->normal_balance) }}</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-primary px-3" data-bs-toggle="modal" data-bs-target="#account-add-{{ $a->id }}">+</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-secondary py-4">Belum ada akun utama.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@foreach($accounts as $a)
<div class="modal fade" id="account-add-{{ $a->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('akuntansi.akun.store') }}">
                @csrf
                <input type="hidden" name="parent_id" value="{{ $a->id }}">

                <div class="modal-header">
                    <h5 class="modal-title">Tambah Akun</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Parent Akun</label>
                        <input class="form-control" value="{{ $a->code }} — {{ $a->name }}" disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nomor Akun</label>
                        <input class="form-control fw-semibold" value="{{ $nextCodes[$a->id] ?? '' }}" disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nama Akun</label>
                        <input name="name" class="form-control" placeholder="Contoh: Kas" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Normal Balance</label>
                        <select name="normal_balance" class="form-select" required>
                            <option value="debit" @selected($a->normal_balance === 'debit')>Debit</option>
                            <option value="credit" @selected($a->normal_balance === 'credit')>Credit</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Keterangan</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endsection
