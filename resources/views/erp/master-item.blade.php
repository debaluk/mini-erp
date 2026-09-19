@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-1">Item</h3>
        <div class="text-secondary">Master item</div>
    </div>
    <button type="button" class="btn btn-primary btn-sm" id="btn-add-item">+ Tambah Item</button>
</div>

<div id="item-alert"></div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table id="items-table" class="table table-sm table-hover align-middle mb-0 w-100">
            <thead>
                <tr>
                    <th>Kode Item</th>
                    <th>Barcode</th>
                    <th>Nama Item</th>
                    <th>Jenis</th>
                    <th>Satuan Dasar</th>
                    <th class="text-end">Minimum Stok</th>
                    <th class="text-center">Persediaan</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<div class="modal fade" id="itemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="item-form">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="item-modal-title">Tambah Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <div id="item-form-alert"></div>
                    <input type="hidden" name="_method" id="item-method" value="POST">
                    <input type="hidden" name="item_id" id="item-id">

                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label">Jenis Item <span class="text-danger">*</span></label>
                            <select name="item_type" id="item-type" class="form-select" required>
                                <option value="barang">Barang</option>
                                <option value="jasa">Jasa</option>
                                <option value="aset">Aset</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Kode Item</label>
                            <input type="text" id="item-code" class="form-control" value="Otomatis oleh sistem" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Barcode</label>
                            <input type="text" name="barcode" id="item-barcode" class="form-control" maxlength="100">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Nama Item <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="item-name" class="form-control" maxlength="255" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Satuan Dasar <span class="text-danger">*</span></label>
                            <select name="base_unit_id" id="item-base-unit" class="form-select" required>
                                <option value="">Pilih satuan</option>
                                @foreach($units as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="border rounded p-2 mt-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <div class="fw-semibold small">Unit</div>
                            <span class="text-secondary small">Pilih minimal satu Unit</span>
                        </div>
                        <div class="row g-1">
                            @foreach($businessUnits as $businessUnit)
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input item-business-unit" type="checkbox" name="business_unit_ids[]" value="{{ $businessUnit->id }}" id="item-unit-{{ $businessUnit->id }}">
                                        <label class="form-check-label small" for="item-unit-{{ $businessUnit->id }}">{{ $businessUnit->name }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="border rounded p-2 mt-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <div class="fw-semibold small">Konversi Satuan</div>
                            <button type="button" class="btn btn-outline-primary btn-sm py-1" id="add-conversion">+ Tambah</button>
                        </div>
                        <div class="text-secondary small mb-1">Faktor = jumlah Satuan Dasar dalam satu Satuan Alternatif.</div>
                        <div id="conversion-rows"></div>
                    </div>

                    <div class="row g-2 mt-1">
                        <div class="col-md-4">
                            <label class="form-label d-block">Kelola sebagai Persediaan?</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="manage_stock" value="1" checked>
                                <label class="form-check-label small">Ya</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="manage_stock" value="0">
                                <label class="form-check-label small">Tidak</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Minimum Stok</label>
                            <input type="number" name="minimum_stock" id="item-minimum-stock" class="form-control" min="0" step="1" inputmode="numeric" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label d-block">Status</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="status" value="1" checked>
                                <label class="form-check-label small">Aktif</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="status" value="0">
                                <label class="form-check-label small">Nonaktif</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="item-save">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const modalEl = document.getElementById('itemModal');
    const modal = new bootstrap.Modal(modalEl);
    const form = document.getElementById('item-form');
    const rows = document.getElementById('conversion-rows');
    const units = @json($units);
    const prefixes = { barang: 'BRG', jasa: 'JSA', aset: 'AST' };
    const dt = new DataTable('#items-table', {
        ajax: '{{ route('master.menu.produk') }}',
        processing: true,
        pageLength: 15,
        order: [[0, 'desc']],
        columns: [
            { data: 'code', className: 'fw-semibold' },
            { data: 'barcode', render: d => d || '-' },
            { data: 'name' },
            { data: 'item_type', render: d => String(d).charAt(0).toUpperCase() + String(d).slice(1) },
            { data: 'base_unit_name', render: d => d || '-' },
            { data: 'minimum_stock', className: 'text-end', render: d => formatNumber(d) },
            { data: 'manage_stock', className: 'text-center', render: d => d ? 'Ya' : 'Tidak' },
            { data: 'is_active', className: 'text-center', render: d => d ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' },
            { data: 'id', className: 'text-end', orderable: false, searchable: false, render: id => '<button type="button" class="btn btn-outline-primary btn-sm btn-edit-item" data-id="' + id + '">Edit</button>' }
        ]
    });

    function formatNumber(value) {
        const n = Number(value || 0);
        return Number.isInteger(n) ? String(n) : n.toLocaleString('id-ID', { maximumFractionDigits: 6 });
    }

    function normalizeFactor(value) {
        const n = Number(value);
        return Number.isFinite(n) ? n.toString() : String(value ?? '');
    }

    function updateCode() {
        const type = document.getElementById('item-type').value;
        document.getElementById('item-code').value = (prefixes[type] || 'BRG') + '-00001';
    }

    function addConversion(unitId = '', factor = '') {
        const row = document.createElement('div');
        row.className = 'row g-1 align-items-end mb-1 conversion-row';
        row.innerHTML =
            '<div class="col-6"><select name="conversion_unit_id[]" class="form-select form-select-sm"><option value="">Pilih satuan</option>' +
            units.map(u => '<option value="' + u.id + '"' + (String(u.id) === String(unitId) ? ' selected' : '') + '>' + u.name + '</option>').join('') +
            '</select></div>' +
            '<div class="col-4"><input type="text" inputmode="decimal" name="conversion_factor[]" class="form-control form-control-sm" value="' + normalizeFactor(factor) + '"></div>' +
            '<div class="col-2"><button type="button" class="btn btn-outline-danger btn-sm w-100 remove-conversion">Hapus</button></div>';
        rows.appendChild(row);
        row.querySelector('.remove-conversion').addEventListener('click', () => row.remove());
    }

    function resetForm() {
        form.reset();
        document.getElementById('item-method').value = 'POST';
        document.getElementById('item-id').value = '';
        document.getElementById('item-modal-title').textContent = 'Tambah Item';
        document.getElementById('item-save').textContent = 'Simpan';
        document.getElementById('item-form-alert').innerHTML = '';
        rows.innerHTML = '';
        document.querySelector('input[name="manage_stock"][value="1"]').checked = true;
        document.querySelector('input[name="status"][value="1"]').checked = true;
        document.getElementById('item-minimum-stock').disabled = false;
        updateCode();
    }

    document.getElementById('btn-add-item').addEventListener('click', () => {
        resetForm();
        modal.show();
        setTimeout(() => document.getElementById('item-name').focus(), 250);
    });

    document.getElementById('item-type').addEventListener('change', updateCode);
    document.getElementById('add-conversion').addEventListener('click', () => addConversion());

    document.querySelectorAll('input[name="manage_stock"]').forEach(input => input.addEventListener('change', function () {
        const el = document.getElementById('item-minimum-stock');
        el.disabled = this.value === '0';
        if (this.value === '0') el.value = '0';
    }));

    document.getElementById('items-table').addEventListener('click', async e => {
        const button = e.target.closest('.btn-edit-item');
        if (!button) return;
        const response = await fetch('{{ url('/master/produk') }}/' + button.dataset.id + '/edit', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
        const payload = await response.json();
        const item = payload.item;
        resetForm();
        document.getElementById('item-method').value = 'PUT';
        document.getElementById('item-id').value = item.id;
        document.getElementById('item-modal-title').textContent = 'Edit Item';
        document.getElementById('item-save').textContent = 'Simpan Perubahan';
        document.getElementById('item-type').value = item.item_type;
        document.getElementById('item-code').value = item.code;
        document.getElementById('item-barcode').value = item.barcode || '';
        document.getElementById('item-name').value = item.name || '';
        document.getElementById('item-base-unit').value = item.base_unit_id;
        document.querySelectorAll('.item-business-unit').forEach(cb => cb.checked = payload.selected_business_units.map(Number).includes(Number(cb.value)));
        rows.innerHTML = '';
        payload.conversions.forEach(x => addConversion(x.unit_id, normalizeFactor(x.conversion_factor)));
        document.querySelectorAll('input[name="manage_stock"]').forEach(r => r.checked = String(item.manage_stock ? 1 : 0) === r.value);
        document.getElementById('item-minimum-stock').value = normalizeFactor(item.minimum_stock);
        document.getElementById('item-minimum-stock').disabled = !item.manage_stock;
        document.querySelectorAll('input[name="status"]').forEach(r => r.checked = String(item.is_active ? 1 : 0) === r.value);
        modal.show();
        setTimeout(() => document.getElementById('item-name').focus(), 250);
    });

    form.addEventListener('submit', async e => {
        e.preventDefault();
        const save = document.getElementById('item-save');
        save.disabled = true;
        document.getElementById('item-form-alert').innerHTML = '';
        const id = document.getElementById('item-id').value;
        const url = id ? '{{ url('/master/produk') }}/' + id + '/edit' : '{{ route('master.item.store') }}';
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: new FormData(form)
        });
        const payload = await response.json();
        if (!response.ok) {
            const errors = payload.errors ? Object.values(payload.errors).flat().join('<br>') : (payload.message || 'Data tidak dapat disimpan.');
            document.getElementById('item-form-alert').innerHTML = '<div class="alert alert-danger py-2 small mb-2">' + errors + '</div>';
            save.disabled = false;
            return;
        }
        modal.hide();
        document.getElementById('item-alert').innerHTML = '<div class="alert alert-success py-2">' + payload.message + '</div>';
        dt.ajax.reload(null, false);
        save.disabled = false;
    });
})();
</script>
@endpush
