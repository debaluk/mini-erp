@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-1">Setup Mapping Account</h3>
        <div class="text-secondary">Tentukan akun COA untuk setiap kebutuhan transaksi Unit Bisnis.</div>
    </div>
    <a href="{{ route('pengaturan.konfigurasi') }}" class="btn btn-outline-secondary">Kembali</a>
</div>

@if(session('success'))
    <div class="alert alert-success py-2">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger py-2">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if($units->isEmpty())
    <div class="alert alert-light border">Belum ada Unit Bisnis.</div>
@else
<form method="POST" action="{{ route('pengaturan.account-mapping.save') }}">
    @csrf

    @foreach($units as $unit)
        @php
            $unitMappings = $mappings->get($unit->id, collect());
            $keys = $mappingKeys->get($unit->id, []);
            $type = ['retail' => 'Retail', 'production' => 'Produksi', 'service' => 'Jasa'][$unit->business_type] ?? $unit->business_type;
        @endphp

        <div class="card shadow-sm mb-3">
            <div class="card-header">
                <div class="fw-semibold">{{ $unit->code }} — {{ $unit->name }}</div>
                <div class="text-secondary small">{{ $type }}</div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @foreach($keys as $key)
                        @php
                            $current = $unitMappings->get($key)?->account_id;
                            $label = $mappingLabels[$key] ?? $key;
                        @endphp
                        <div class="col-md-6">
                            <label class="form-label mb-1">{{ $label }}</label>
                            <select name="accounts[{{ $unit->id }}][{{ $key }}]" class="form-select" required>
                                <option value="">Pilih akun</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}" @selected((string) $current === (string) $account->id)>
                                        {{ $account->code }} — {{ $account->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">{{ $key }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach

    <button type="submit" class="btn btn-primary">Simpan Mapping</button>
</form>
@endif
@endsection
