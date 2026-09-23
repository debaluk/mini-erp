@extends('layouts.app')

@section('content')
<div class="mb-4">
    <h3 class="mb-1">Konfigurasi</h3>
    <div class="text-secondary">Pengaturan dasar cara kerja sistem ERP.</div>
</div>

<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#mapping-coa" type="button" role="tab">
            Mapping COA
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#mapping-gudang" type="button" role="tab">
            Mapping Gudang ↔ Unit Bisnis
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#nota-invoice" type="button" role="tab">
            Setup Nota/Invoice
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#nomor-dokumen" type="button" role="tab">
            Nomor Dokumen
        </button>
    </li>
</ul>

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

<div class="tab-content">
    <div class="tab-pane fade show active" id="mapping-coa" role="tabpanel">
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
    </div>

    <div class="tab-pane fade" id="mapping-gudang" role="tabpanel">
        <form method="POST" action="{{ route('pengaturan.warehouse-mapping.save') }}">
            @csrf
            <div class="card shadow-sm">
                <div class="card-header">
                    <div class="fw-semibold">Mapping Gudang ↔ Unit Bisnis</div>
                    <div class="text-secondary small">Satu Unit Bisnis hanya boleh memiliki satu Gudang aktif.</div>
                </div>
                <div class="card-body">
                    @if($warehouseList->isEmpty())
                        <div class="alert alert-light border mb-0">Belum ada Gudang.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th>Gudang</th><th>Unit Bisnis</th></tr></thead>
                                <tbody>
                                @foreach($warehouseList as $warehouse)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $warehouse->code }}</div>
                                            <div class="text-secondary small">{{ $warehouse->name }}</div>
                                        </td>
                                        <td>
                                            <select name="warehouse_business_units[{{ $warehouse->id }}]" class="form-select">
                                                <option value="">— Tidak dipetakan —</option>
                                                @foreach($units as $unit)
                                                    <option value="{{ $unit->id }}" @selected((int) ($warehouseMappings->get($warehouse->id)?->business_unit_id ?? 0) === (int) $unit->id)>
                                                        {{ $unit->code }} — {{ $unit->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                <div class="card-footer bg-white">
                    <button type="submit" class="btn btn-primary">Simpan Mapping Gudang</button>
                </div>
            </div>
        </form>
    </div>

    <div class="tab-pane fade" id="nota-invoice" role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Setup Nota/Invoice</div>
            <div class="card-body text-secondary">
                Setup Nota/Invoice disiapkan pada tahap berikutnya.
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="nomor-dokumen" role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Nomor Dokumen</div>
            <div class="card-body text-secondary">
                Setup Nomor Dokumen disiapkan pada tahap berikutnya.
            </div>
        </div>
    </div>
</div>
@endsection
