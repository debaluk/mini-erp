@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-1">Tambah Item</h3>
        <div class="text-secondary">Master item</div>
    </div>
    <a href="{{ route('master.menu.produk') }}" class="btn btn-outline-secondary btn-sm">Kembali</a>
</div>

@if ($errors->any())
    <div class="alert alert-danger py-2">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('master.item.store') }}" class="card shadow-sm">
    @csrf
    <div class="card-body">
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Jenis Item <span class="text-danger">*</span></label>
                <select name="item_type" id="item_type" class="form-select" required>
                    <option value="barang" @selected(old('item_type', 'barang') === 'barang')>Barang</option>
                    <option value="jasa" @selected(old('item_type') === 'jasa')>Jasa</option>
                    <option value="aset" @selected(old('item_type') === 'aset')>Aset</option>
                </select>
            </div>

            <div class="col-md-4 mb-3">
                <label class="form-label">Kode Item</label>
                <input type="text" id="code_preview" class="form-control" value="BRG-00001" readonly>
                <div class="form-text">Otomatis berdasarkan jenis item.</div>
            </div>

            <div class="col-md-4 mb-3">
                <label class="form-label">Barcode</label>
                <input type="text" name="barcode" class="form-control" value="{{ old('barcode') }}" maxlength="100">
                <div class="form-text">Opsional. Jika diisi harus unik.</div>
            </div>

            <div class="col-md-8 mb-3">
                <label class="form-label">Nama Item <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" maxlength="255" required autofocus>
            </div>

            <div class="col-md-4 mb-3">
                <label class="form-label">Satuan Dasar <span class="text-danger">*</span></label>
                <div class="input-group">
                    <select name="base_unit_id" class="form-select" required>
                        <option value="">Pilih satuan</option>
                        @foreach($units as $unit)
                            <option value="{{ $unit->id }}" @selected((string) old('base_unit_id') === (string) $unit->id)>{{ $unit->name }}</option>
                        @endforeach
                    </select>
                    <a href="{{ route('master.menu.satuan') }}" target="_blank" class="btn btn-outline-secondary">+ Satuan</a>
                </div>
            </div>
        </div>

        <div class="border rounded p-3 mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="fw-semibold">Unit</div>
                <span class="text-secondary small">Pilih minimal satu Unit</span>
            </div>
            <div class="row">
                @foreach($businessUnits as $businessUnit)
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox"
                                name="business_unit_ids[]"
                                value="{{ $businessUnit->id }}"
                                id="business_unit_{{ $businessUnit->id }}"
                                @checked(in_array($businessUnit->id, old('business_unit_ids', [])))>
                            <label class="form-check-label" for="business_unit_{{ $businessUnit->id }}">
                                {{ $businessUnit->name }}
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="form-text mt-2">Satu item dapat digunakan oleh lebih dari satu Unit.</div>
        </div>

        <div class="border rounded p-3 mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="fw-semibold">Konversi Satuan</div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="add-conversion">+ Tambah Konversi</button>
            </div>
            <div class="text-secondary small mb-2">Opsional. Faktor konversi menyatakan berapa Satuan Dasar dalam satu Satuan Alternatif.</div>
            <div id="conversion-rows"></div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label d-block">Kelola sebagai Persediaan?</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="manage_stock" id="manage_stock_yes" value="1" @checked(old('manage_stock', '1') === '1')>
                    <label class="form-check-label" for="manage_stock_yes">Ya</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="manage_stock" id="manage_stock_no" value="0" @checked(old('manage_stock') === '0')>
                    <label class="form-check-label" for="manage_stock_no">Tidak</label>
                </div>
            </div>

            <div class="col-md-4 mb-3">
                <label class="form-label">Minimum Stok</label>
                <input type="number" name="minimum_stock" id="minimum_stock" class="form-control" min="0" step="0.001" value="{{ old('minimum_stock', '0') }}">
            </div>

            <div class="col-md-4 mb-3">
                <label class="form-label d-block">Status</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="status" id="status_active" value="1" @checked(old('status', '1') === '1')>
                    <label class="form-check-label" for="status_active">Aktif</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="status" id="status_inactive" value="0" @checked(old('status') === '0')>
                    <label class="form-check-label" for="status_inactive">Nonaktif</label>
                </div>
            </div>
        </div>
    </div>

    <div class="card-footer bg-white d-flex justify-content-end gap-2">
        <a href="{{ route('master.menu.produk') }}" class="btn btn-outline-secondary">Batal</a>
        <button type="submit" class="btn btn-primary">Simpan</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const typeSelect = document.getElementById('item_type');
    const codePreview = document.getElementById('code_preview');
    const prefixes = {barang:'BRG', jasa:'JSA', aset:'AST'};
    function updateCodePreview() {
        codePreview.value = (prefixes[typeSelect.value] || 'BRG') + '-00001';
    }
    typeSelect.addEventListener('change', updateCodePreview);
    updateCodePreview();

    const conversionRows = document.getElementById('conversion-rows');
    const addButton = document.getElementById('add-conversion');
    const units = @json($units);
    const existing = @json($conversions ?? []);
    const oldIds = @json(old('conversion_unit_id', []));
    const oldFactors = @json(old('conversion_factor', []));
    const oldPurchase = @json(old('conversion_default_purchase', []));
    const oldSale = @json(old('conversion_default_sale', []));

    function addRow(unitId = '', factor = '', defaultPurchase = false, defaultSale = false) {
        const row = document.createElement('div');
        row.className = 'row g-2 align-items-end mb-2 conversion-row';
        row.innerHTML = `
            <div class="col-md-4">
                <label class="form-label">Satuan Alternatif</label>
                <select name="conversion_unit_id[]" class="form-select">
                    <option value="">Pilih satuan</option>
                    ${units.map(u => `<option value="${u.id}" ${String(u.id) === String(unitId) ? 'selected' : ''}>${u.name}</option>`).join('')}
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Faktor ke Base Unit</label>
                <input type="text" inputmode="decimal" name="conversion_factor[]" class="form-control" value="${String(factor).replace(/(\\.\\d*?[1-9])0+$|\\.0+$/,"$1")}">
            </div>
            <div class="col-md-2">
                <div class="form-check">
                    <select name="conversion_default_purchase[]" class="form-select form-select-sm"><option value="0">Tidak</option><option value="1" ${defaultPurchase ? 'selected' : ''}>Ya</option></select>
                    <label class="form-check-label">Default Beli</label>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-check">
                    <select name="conversion_default_sale[]" class="form-select form-select-sm"><option value="0">Tidak</option><option value="1" ${defaultSale ? 'selected' : ''}>Ya</option></select>
                    <label class="form-check-label">Default Jual</label>
                </div>
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-outline-danger w-100 remove">×</button>
            </div>`;
        conversionRows.appendChild(row);
        row.querySelector('.remove').addEventListener('click', () => row.remove());
    }

    addButton.addEventListener('click', () => addRow());

    if (oldIds.length) {
        oldIds.forEach((id, i) => addRow(id, oldFactors[i] ?? '', !!oldPurchase[i], !!oldSale[i]));
    } else {
        existing.forEach(x => addRow(x.unit_id, x.conversion_factor, !!x.is_default_purchase, !!x.is_default_sale));
    }

    document.querySelectorAll('input[name="manage_stock"]').forEach(input => input.addEventListener('change', function () {
        const minimum = document.getElementById('minimum_stock');
        minimum.disabled = this.value === '0';
        if (this.value === '0') minimum.value = '0';
    }));
})();
</script>
@endpush