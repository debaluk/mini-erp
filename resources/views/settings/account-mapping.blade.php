@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-1">Setup Mapping Account</h3>
        <div class="text-secondary">Pilih akun HPP untuk masing-masing Unit Bisnis.</div>
    </div>
    <a href="{{ route('pengaturan.konfigurasi') }}" class="btn btn-outline-secondary">Kembali</a>
</div>

@if(session('success'))
    <div class="alert alert-success py-2">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger py-2">
        <ul class="mb-0">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

@forelse($units as $unit)
@php
    $current = $mappings[$unit->id]->account_id ?? null;
    $type = ['retail'=>'Toko', 'production'=>'Produksi', 'service'=>'Jasa'][$unit->business_type] ?? $unit->business_type;
@endphp
@if($loop->first)
<form method="POST" action="{{ route('pengaturan.account-mapping.save') }}">
    @csrf
@endif

<div class="row align-items-center mb-3">
    <div class="col-md-4">
        <label class="form-label mb-1"><strong>{{ $type }}</strong></label>
        <div class="text-secondary small">{{ $unit->code }} — {{ $unit->name }}</div>
    </div>
    <div class="col-md-8">
        <select name="accounts[{{ $unit->id }}]" class="form-select" required>
            <option value="">Pilih akun</option>
            @foreach($accounts as $account)
                <option value="{{ $account->id }}" @selected((string)$current === (string)$account->id)>
                    {{ $account->code }} — {{ $account->name }}
                </option>
            @endforeach
        </select>
    </div>
</div>

@if($loop->last)
    <button type="submit" class="btn btn-primary">Simpan</button>
</form>
@endif

@empty
<div class="alert alert-light border">Belum ada Unit Bisnis.</div>
@endforelse
@endsection