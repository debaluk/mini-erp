@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-1">Item</h3>
        <div class="text-secondary">Master item</div>
    </div>
    <div class="d-flex gap-2"><button type="button" class="btn btn-outline-success btn-sm" id="btn-export-item">Export Excel</button><button type="button" class="btn btn-primary btn-sm" id="btn-add-item">+ Tambah Item</button></div>
</div>



<div class="d-flex align-items-center gap-2 mb-2">
    <label for="item-unit-filter" class="small text-secondary mb-0">Unit</label>
    <select id="item-unit-filter" class="form-select form-select-sm" style="max-width: 220px">
        <option value="">Semua</option>
        @foreach($businessUnits as $businessUnit)
            <option value="{{ $businessUnit->id }}">{{ $businessUnit->name }}</option>
        @endforeach
    </select>
</div>

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
                            <input type="text" id="item-code" class="form-control" value="Otomatis" readonly>
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
                            <div class="d-flex justify-content-between align-items-center">
                                <label class="form-label mb-1">Satuan Dasar <span class="text-danger">*</span></label>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" id="btn-add-uom">+ Satuan</button>
                            </div>
                            <div id="new-uom-form" class="border rounded p-2 mb-2 d-none">
                                <div class="row g-1">
                                    <div class="col-4"><input type="text" id="new-uom-code" class="form-control form-control-sm" placeholder="Kode satuan"></div>
                                    <div class="col-5"><input type="text" id="new-uom-name" class="form-control form-control-sm" placeholder="Nama satuan"></div>
                                    <div class="col-3 d-flex gap-1">
                                        <button type="button" class="btn btn-primary btn-sm flex-fill" id="save-new-uom">Simpan</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="cancel-new-uom">Batal</button>
                                    </div>
                                </div>
                                
                            </div>
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
                            <div>
                                <span class="text-secondary small me-2">Pilih minimal satu Unit</span>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" id="btn-add-business-unit">+ Unit</button>
                            </div>
                        </div>
                        <div id="new-business-unit-form" class="border rounded p-2 mb-2 d-none">
                            <div class="row g-1">
                                <div class="col-4"><input type="text" id="new-business-unit-code" class="form-control form-control-sm" placeholder="Kode unit"></div>
                                <div class="col-5"><input type="text" id="new-business-unit-name" class="form-control form-control-sm" placeholder="Nama unit"></div>
                                <div class="col-3 d-flex gap-1">
                                    <button type="button" class="btn btn-primary btn-sm flex-fill" id="save-new-business-unit">Simpan</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="cancel-new-business-unit">Batal</button>
                                </div>
                            </div>
                            
                        </div>
                        <div class="row g-1" id="item-business-unit-options">
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

<div class="modal fade" id="itemMessageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2" id="itemMessageHeader">
                <h5 class="modal-title" id="itemMessageTitle">Berhasil</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center" id="itemMessageBody"></div>
            <div class="modal-footer justify-content-center py-2">
                <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const modalEl = document.getElementById('itemModal');
    const modal = new bootstrap.Modal(modalEl);
    const messageModal = document.getElementById('itemMessageModal');
    const messageHeader = document.getElementById('itemMessageHeader');
    const messageTitle = document.getElementById('itemMessageTitle');
    const messageBody = document.getElementById('itemMessageBody');
    function showMessage(message, type = 'success') {
        messageHeader.className = 'modal-header py-2 ' + (type === 'danger' ? 'bg-danger text-white' : type === 'warning' ? 'bg-warning' : 'bg-success text-white');
        messageTitle.textContent = type === 'danger' ? 'Gagal' : type === 'warning' ? 'Perhatian' : 'Berhasil';
        messageBody.textContent = message;
        bootstrap.Modal.getOrCreateInstance(messageModal).show();
    }
    const form = document.getElementById('item-form');
    const rows = document.getElementById('conversion-rows');
    let units = @json($units);
    let businessUnits = @json($businessUnits);
    const dt = new DataTable('#items-table', {
        ajax: {
            url: '{{ route('master.menu.produk') }}',
            data: function (d) {
                d.business_unit_id = document.getElementById('item-unit-filter').value;
            }
        },
        processing: true,
        serverSide: true,
        ordering: true,
        pageLength: 15,
        columns: [
            { data: 'code', className: 'fw-semibold' },
            { data: 'barcode', render: d => d || '-' },
            { data: 'name' },
            { data: 'item_type', render: d => String(d).charAt(0).toUpperCase() + String(d).slice(1) },
            { data: 'base_unit_name', render: d => d || '-' },
            { data: 'minimum_stock', className: 'text-end', render: d => formatNumber(d) },
            { data: 'manage_stock', className: 'text-center', render: d => d ? 'Ya' : 'Tidak' },
            { data: 'is_active', className: 'text-center', render: d => d ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' },
            { data: 'id', className: 'text-end', orderable: false, searchable: false, render: id => '<div class="d-inline-flex gap-1"><button type="button" class="btn btn-outline-primary btn-sm btn-edit-item" data-id="' + id + '">Edit</button><button type="button" class="btn btn-outline-danger btn-sm btn-delete-item" data-id="' + id + '">Hapus</button></div>' }
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

    function showInlineForm(id) {
        const el = document.getElementById(id);
        el.classList.toggle('d-none');
        if (!el.classList.contains('d-none')) {
            const input = el.querySelector('input');
            if (input) setTimeout(() => input.focus(), 50);
        }
    }

    function resetInlineForms() {
        ['new-uom-form', 'new-business-unit-form'].forEach(id => document.getElementById(id).classList.add('d-none'));
        ['new-uom-code', 'new-uom-name', 'new-business-unit-code', 'new-business-unit-name'].forEach(id => document.getElementById(id).value = '');
        
    }

    function refreshConversionUnitOptions() {
        document.querySelectorAll('.conversion-row select').forEach(select => {
            const current = select.value;
            select.innerHTML = '<option value="">Pilih satuan</option>' + units.map(u => '<option value="' + u.id + '">' + u.name + '</option>').join('');
            select.value = current;
        });
    }

    function appendBusinessUnitOption(unit, checked = true) {
        const wrapper = document.createElement('div');
        wrapper.className = 'col-md-4';
        wrapper.innerHTML = '<div class="form-check"><input class="form-check-input item-business-unit" type="checkbox" name="business_unit_ids[]" value="' + unit.id + '" id="item-unit-' + unit.id + '"' + (checked ? ' checked' : '') + '><label class="form-check-label small" for="item-unit-' + unit.id + '">' + unit.name + '</label></div>';
        document.getElementById('item-business-unit-options').appendChild(wrapper);
    }

    function resetForm() {
        form.reset();
        document.getElementById('item-method').value = 'POST';
        document.getElementById('item-id').value = '';
        document.getElementById('item-modal-title').textContent = 'Tambah Item';
        document.getElementById('item-save').textContent = 'Simpan';
        
        rows.innerHTML = '';
        document.querySelector('input[name="manage_stock"][value="1"]').checked = true;
        document.querySelector('input[name="status"][value="1"]').checked = true;
        document.getElementById('item-minimum-stock').disabled = false;
        document.getElementById('item-code').value = 'Otomatis';
        resetInlineForms();
    }

    document.getElementById('item-unit-filter').addEventListener('change', () => dt.ajax.reload());
    document.getElementById('btn-export-item').addEventListener('click', () => {
        const params = new URLSearchParams();
        const unitId = document.getElementById('item-unit-filter').value;
        const search = dt.search();
        if (unitId) params.set('business_unit_id', unitId);
        if (search) params.set('search', search);
        window.location.href = '{{ route('master.item.export-excel') }}' + (params.toString() ? '?' + params.toString() : '');
    });


    document.getElementById('items-table').addEventListener('click', async (event) => {
        const button = event.target.closest('.btn-delete-item');
        if (!button) return;
        const id = button.dataset.id;
        if (!confirm('Hapus item ini? Data item yang sudah digunakan dalam transaksi tidak dapat dihapus.')) return;
        button.disabled = true;
        const response = await fetch('{{ url('/master/produk') }}/' + id, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });
        const payload = await response.json();
        button.disabled = false;
        if (!response.ok) {
            showMessage(payload.message || 'Item tidak dapat dihapus.', 'danger');
            return;
        }
        showMessage(payload.message || 'Item berhasil dihapus.');
        dt.ajax.reload(null, false);
    });

    document.getElementById('btn-add-item').addEventListener('click', () => {
        resetForm();
        modal.show();
        setTimeout(() => document.getElementById('item-name').focus(), 250);
    });

    document.getElementById('btn-add-uom').addEventListener('click', () => showInlineForm('new-uom-form'));
    document.getElementById('cancel-new-uom').addEventListener('click', resetInlineForms);
    document.getElementById('btn-add-business-unit').addEventListener('click', () => showInlineForm('new-business-unit-form'));
    document.getElementById('cancel-new-business-unit').addEventListener('click', resetInlineForms);

    async function postInline(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: body
        });
        const payload = await response.json();
        if (!response.ok) {
            const errors = payload.errors ? Object.values(payload.errors).flat().join('<br>') : (payload.message || 'Data tidak dapat disimpan.');
            showMessage(errors.replace(/<br>/g, '\n'), 'danger');
            return null;
        }
        return payload;
    }

    document.getElementById('save-new-uom').addEventListener('click', async () => {
        const btn = document.getElementById('save-new-uom');
        btn.disabled = true;
        const body = new FormData();
        body.append('code', document.getElementById('new-uom-code').value.trim());
        body.append('name', document.getElementById('new-uom-name').value.trim());
        const payload = await postInline('{{ route('master.item.inline-uom.store') }}', body);
        btn.disabled = false;
        if (!payload) return;
        units.push(payload.unit);
        const option = new Option(payload.unit.name, payload.unit.id, true, true);
        document.getElementById('item-base-unit').add(option);
        refreshConversionUnitOptions();
        resetInlineForms();
        document.getElementById('item-base-unit').value = payload.unit.id;
    });

    document.getElementById('save-new-business-unit').addEventListener('click', async () => {
        const btn = document.getElementById('save-new-business-unit');
        btn.disabled = true;
        const body = new FormData();
        body.append('code', document.getElementById('new-business-unit-code').value.trim());
        body.append('name', document.getElementById('new-business-unit-name').value.trim());
        const payload = await postInline('{{ route('master.item.inline-unit.store') }}', body);
        btn.disabled = false;
        if (!payload) return;
        businessUnits.push(payload.business_unit);
        appendBusinessUnitOption(payload.business_unit, true);
        resetInlineForms();
    });

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
            showMessage(errors.replace(/<br>/g, '\n'), 'danger');
            save.disabled = false;
            return;
        }
        modal.hide();
        showMessage(payload.message || 'Item berhasil disimpan.');
        dt.ajax.reload(null, false);
        save.disabled = false;
    });
})();
</script>
@endpush
