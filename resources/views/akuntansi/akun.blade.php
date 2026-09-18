@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-1">Akun</h3>
        <div class="text-secondary">Chart of Accounts / daftar akun keuangan</div>
    </div>
    <a href="{{ route('akuntansi.akun.export-excel') }}" class="btn btn-success">
        Export Excel
    </a>
</div>

@php
    $children = $accounts->groupBy('parent_id');
    $typeLabels = [
        'asset' => 'Aset',
        'liability' => 'Hutang',
        'equity' => 'Modal',
        'revenue' => 'Pendapatan',
        'cogs' => 'HPP',
        'expense' => 'Biaya',
    ];
@endphp

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3">
        <div class="fw-semibold">Struktur Akun</div>
        <small class="text-secondary">Maksimal 3 level • akun level 3 dapat digunakan untuk posting</small>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:170px">Kode</th>
                    <th>Nama Akun</th>
                    <th style="width:170px">Type</th>
                    <th style="width:160px">Normal Balance</th>
                    <th class="text-end" style="width:150px">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse($children->get(null, collect()) as $a)
                @include('akuntansi.partials.akun-row', [
                    'account' => $a,
                    'children' => $children,
                    'nextCodes' => $nextCodes,
                    'depth' => 0,
                    'typeLabels' => $typeLabels
                ])
            @empty
                <tr><td colspan="5" class="text-center text-secondary py-5">Belum ada akun utama.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@foreach($accounts as $a)
    @if((int) $a->level < 3)
        <div class="modal fade" id="account-add-{{ $a->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
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
                                <input class="form-control bg-light" value="{{ $a->code }} — {{ $a->name }}" disabled>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nomor Akun</label>
                                <input class="form-control bg-light fw-semibold" value="{{ $nextCodes[$a->id] ?? '' }}" disabled>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nama Akun</label>
                                <input name="name" class="form-control" placeholder="Nama akun" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Normal Balance</label>
                                <select name="normal_balance" class="form-select" required>
                                    <option value="debit" @selected($a->normal_balance === 'debit')>Debit</option>
                                    <option value="credit" @selected($a->normal_balance === 'credit')>Credit</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Keterangan <span class="text-secondary">(opsional)</span></label>
                                <textarea name="description" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button class="btn btn-primary px-4">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <div class="modal fade" id="account-edit-{{ $a->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form method="POST" action="{{ route('akuntansi.akun.update', $a->id) }}">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Akun</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nomor Akun</label>
                            <input class="form-control bg-light fw-semibold" value="{{ $a->code }}" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Akun</label>
                            <input name="name" class="form-control" value="{{ $a->name }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Normal Balance</label>
                            <select name="normal_balance" class="form-select" required>
                                <option value="debit" @selected($a->normal_balance === 'debit')>Debit</option>
                                <option value="credit" @selected($a->normal_balance === 'credit')>Credit</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Keterangan <span class="text-secondary">(opsional)</span></label>
                            <textarea name="description" class="form-control" rows="2">{{ $a->description }}</textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button class="btn btn-primary px-4">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach

@if(session('success') || session('error') || $errors->any())
<div class="modal fade" id="account-message-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title {{ session('success') ? 'text-success' : 'text-danger' }}">
                    {{ session('success') ? 'Berhasil' : 'Perhatian' }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div class="fs-5">
                    {{ session('success') ?? session('error') ?? $errors->first() }}
                </div>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    bootstrap.Modal.getOrCreateInstance(document.getElementById('account-message-modal')).show();
});
</script>
@endif
@endsection
