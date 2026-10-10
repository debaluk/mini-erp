@extends('layouts.app')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h4 class="mb-1">Master Formula / BOM</h4>
        <div class="text-secondary small">Standar resep produksi — produk jadi, output, dan kebutuhan material.</div>
    </div>
    <button type="button" class="btn btn-primary" id="btnAddBom">
        <i class="bi bi-plus-lg me-1"></i> Tambah BOM
    </button>
</div>


@if($errors->any())
    <div class="alert alert-danger py-2">
        <div class="fw-semibold mb-1">BOM belum tersimpan.</div>
        <ul class="mb-0 ps-3">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-header bg-primary bg-opacity-10 text-primary border-bottom d-flex justify-content-between align-items-center py-3">
        <div class="fw-semibold"><i class="bi bi-list-ul me-2"></i>Daftar Formula Produksi</div>
        <span class="badge rounded-pill bg-white text-primary border border-primary-subtle">{{ $boms->total() }} BOM</span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-primary">
                <tr>
                    <th style="width: 50px;">No</th>
                    <th>Kode</th>
                    <th>Nama Formula</th>
                    <th>Produk Jadi</th>
                    <th class="text-end">Output</th>
                    <th class="text-center">Material</th>
                    <th class="text-center">Status</th>
                    <th class="text-end" style="width: 145px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse($boms as $bom)
                <tr>
                    <td class="text-secondary">{{ $boms->firstItem() + $loop->index }}</td>
                    <td class="fw-semibold">{{ $bom->code }}</td>
                    <td>{{ $bom->name }}</td>
                    <td>
                        <div class="fw-semibold">{{ $bom->product_name }}</div>
                        <div class="text-secondary small">{{ $bom->product_sku }}</div>
                    </td>
                    <td class="text-end">
                        {{ FormatIndo::indo($bom->output_qty, 2) }}
                        <span class="text-secondary small">{{ $bom->output_unit_code }}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge rounded-pill text-bg-info">{{ $bom->items->count() }} bahan</span>
                    </td>
                    <td class="text-center">
                        @if($bom->is_active)
                            <span class="badge rounded-pill text-bg-success"><i class="bi bi-check-circle-fill me-1"></i>Aktif</span>
                        @else
                            <span class="badge rounded-pill text-bg-secondary">Nonaktif</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button"
                                    class="btn btn-outline-primary btn-edit"
                                    data-bom="{{ base64_encode(json_encode([
                                        'id' => $bom->id,
                                        'product_id' => $bom->product_id,
                                        'code' => $bom->code,
                                        'name' => $bom->name,
                                        'output_qty' => $bom->output_qty,
                                        'items' => $bom->items->map(fn($item) => [
                                            'product_id' => $item->product_id,
                                            'unit_id' => $item->unit_id,
                                            'qty' => $item->qty,
                                        ])->values(),
                                    ])) }}"
                                    title="Edit">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <a href="{{ route('master.bom.print', $bom->id) }}"
                               class="btn btn-outline-secondary"
                               target="_blank"
                               title="Cetak">
                                <i class="bi bi-printer"></i>
                            </a>
                            <button type="button"
                                    class="btn btn-outline-danger btn-delete"
                                    data-url="{{ route('master.bom.delete', $bom->id) }}"
                                    title="Hapus">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-secondary py-5">
                        <i class="bi bi-diagram-3 fs-3 d-block mb-2"></i>
                        Belum ada BOM.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($boms->hasPages())
        <div class="card-footer bg-light d-flex justify-content-end">
            {{ $boms->links() }}
        </div>
    @endif
</div>

<div class="modal fade" id="bomModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-fullscreen-md-down">
        <div class="modal-content">
            <form method="POST" id="bomForm">
                @csrf
                <input type="hidden" name="_method" id="bomMethod" value="POST">

                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-1" id="bomModalTitle">Tambah BOM</h5>
                        <div class="text-secondary small">BOM hanya berisi material/bahan produksi.</div>
                    </div>
                    <button type="button" class="btn-close " data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body overflow-auto" style="max-height: calc(100vh - 180px); min-height: 0;">
                    <div class="mb-4">
                        <div>
                            <div class="row g-3">
                                <div class="col-lg-5">
                                    <label class="form-label">Produk Jadi <span class="text-danger">*</span></label>
                                    <select name="product_id" id="bomProduct" class="form-select" required>
                                        <option value="">Pilih produk jadi</option>
                                        @foreach($products as $p)
                                            <option value="{{ $p->id }}"
                                                    data-unit="{{ $p->base_unit_code ?? '' }}">
                                                {{ $p->sku }} — {{ $p->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">Output menggunakan base unit produk jadi.</div>
                                </div>
                                <div class="col-lg-2">
                                    <label class="form-label">Kode BOM <span class="text-danger">*</span></label>
                                    <input type="text" name="code" id="bomCode" class="form-control" maxlength="100" required>
                                </div>
                                <div class="col-lg-3">
                                    <label class="form-label">Nama Formula <span class="text-danger">*</span></label>
                                    <input type="text" name="name" id="bomName" class="form-control" maxlength="255" required>
                                </div>
                                <div class="col-lg-2">
                                    <label class="form-label">Output <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="text" name="output_qty" id="bomOutput" class="form-control qty-input" inputmode="decimal" autocomplete="off" required>
                                        <span class="input-group-text" id="outputUnit">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border-top pt-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <div class="fw-semibold">Material / Bahan Baku <span class="text-danger">*</span></div>
                                <div class="text-secondary small">Satuan mengikuti base unit atau konversi produk.</div>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="addMaterial">
                                <i class="bi bi-plus-lg me-1"></i> Tambah Material
                            </button>
                        </div>

                        <div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Material</th>
                                            <th style="width: 220px;">Satuan</th>
                                            <th style="width: 180px;">Qty</th>
                                            <th style="width: 70px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="materialRows"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <small class="text-secondary me-auto"><span class="text-danger">*</span> Wajib diisi</small>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success px-4">
                        <i class="bi bi-check-lg me-1"></i><span id="bomSubmitText">Simpan BOM</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<form method="POST" id="deleteBomForm" class="d-none">
    @csrf
    @method('DELETE')
</form>

<template id="materialRowTemplate">
    <tr class="material-row">
        <td>
            <select name="material_product_id[]" class="form-select material-product" required>
                <option value="">Pilih material</option>
                @foreach($products as $p)
                    <option value="{{ $p->id }}">{{ $p->sku }} — {{ $p->name }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <select name="material_unit_id[]" class="form-select material-unit" required disabled>
                <option value="">Pilih material dulu</option>
            </select>
        </td>
        <td>
            <input type="text" name="material_qty[]" class="form-control material-qty qty-input" inputmode="decimal" autocomplete="off" placeholder="0,00" required>
        </td>
        <td class="text-end">
            <button type="button" class="btn btn-outline-danger btn-sm remove-material" title="Hapus material">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    </tr>
</template>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const bomModalEl = document.getElementById('bomModal');
    const bomModal = new bootstrap.Modal(bomModalEl);
    const form = document.getElementById('bomForm');
    const method = document.getElementById('bomMethod');
    const title = document.getElementById('bomModalTitle');
    const submitText = document.getElementById('bomSubmitText');
    const rows = document.getElementById('materialRows');
    const template = document.getElementById('materialRowTemplate');
    const productSelect = document.getElementById('bomProduct');
    const outputUnit = document.getElementById('outputUnit');
    const productUnits = @json($productUnits);

    const routes = {
        store: @json(route('master.bom.store')),
        update: @json(route('master.bom.update', ['id' => '__ID__'])),
    };

    function unitOptions(productId, selectedId = '') {
        const units = productUnits[String(productId)] || productUnits[productId] || [];
        if (!units.length) {
            return '<option value="">Satuan belum tersedia</option>';
        }

        return '<option value="">Pilih satuan</option>' + units.map(unit => {
            const selected = String(unit.id) === String(selectedId) ? ' selected' : '';
            const suffix = unit.is_base ? ' — Base Unit' : ' — 1 unit = ' + Number(unit.factor).toLocaleString('id-ID');
            return '<option value="' + unit.id + '"' + selected + '>' +
                escapeHtml(unit.code || '') + ' — ' + escapeHtml(unit.name || '') + escapeHtml(suffix) +
                '</option>';
        }).join('');
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, char => ({
            '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;'
        }[char]));
    }

    function normalizeQty(value) {
        const raw = String(value ?? '').trim();

        if (!raw) return '';

        if (raw.includes(',')) {
            return raw.replace(/\./g, '').replace(',', '.');
        }

        return raw;
    }

    function formatQty(value) {
        const raw = String(value ?? '').trim();

        if (!raw) return '';

        const normalized = normalizeQty(raw);
        const number = Number(normalized);

        if (!Number.isFinite(number)) return '';

        return number.toLocaleString('id-ID', {
            useGrouping: true,
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function formatQtyInputs() {
        document.querySelectorAll('.qty-input').forEach(input => {
            input.value = formatQty(input.value);
        });
    }

    function prepareQtyForSubmit() {
        document.querySelectorAll('.qty-input').forEach(input => {
            input.value = normalizeQty(input.value);
        });
    }

    function updateOutputUnit() {
        const option = productSelect.options[productSelect.selectedIndex];
        outputUnit.textContent = option?.dataset.unit || '-';
    }

    function addMaterialRow(item = {}) {
        const fragment = template.content.cloneNode(true);
        const row = fragment.querySelector('.material-row');
        const product = row.querySelector('.material-product');
        const unit = row.querySelector('.material-unit');
        const qty = row.querySelector('.material-qty');

        product.value = item.product_id || '';
        unit.innerHTML = unitOptions(product.value, item.unit_id || '');
        unit.disabled = !product.value;
        const itemQty = Number(item.qty);
        qty.value = Number.isFinite(itemQty)
            ? itemQty.toLocaleString('id-ID', {
                useGrouping: true,
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            })
            : '';

        product.addEventListener('change', function () {
            unit.innerHTML = unitOptions(this.value);
            unit.disabled = !this.value;
        });

        rows.appendChild(fragment);
    }

    function resetForm() {
        form.action = routes.store;
        method.value = 'POST';
        title.textContent = 'Tambah BOM';
        submitText.textContent = 'Simpan BOM';
        form.reset();
        rows.innerHTML = '';
        addMaterialRow();
        updateOutputUnit();
    }

    form.addEventListener('submit', function () {
        prepareQtyForSubmit();
    });

    document.addEventListener('focusin', function (event) {
        if (event.target.matches('.qty-input')) {
            event.target.select();
        }
    });

    document.addEventListener('focusout', function (event) {
        if (event.target.matches('.qty-input')) {
            event.target.value = formatQty(event.target.value);
        }
    });

    document.getElementById('btnAddBom').addEventListener('click', function () {
        resetForm();
        bomModal.show();
    });

    productSelect.addEventListener('change', updateOutputUnit);

    document.getElementById('addMaterial').addEventListener('click', function () {
        addMaterialRow();
    });

    rows.addEventListener('click', function (event) {
        const button = event.target.closest('.remove-material');
        if (!button) return;

        const allRows = rows.querySelectorAll('.material-row');
        if (allRows.length === 1) {
            allRows[0].querySelector('.material-product').value = '';
            allRows[0].querySelector('.material-unit').innerHTML = '<option value="">Pilih material dulu</option>';
            allRows[0].querySelector('.material-unit').disabled = true;
            allRows[0].querySelector('.material-qty').value = '';
            return;
        }

        button.closest('.material-row').remove();
    });

    document.querySelectorAll('.btn-edit').forEach(button => {
        button.addEventListener('click', function () {
            const data = JSON.parse(atob(this.dataset.bom));

            form.action = routes.update.replace('__ID__', data.id);
            method.value = 'PUT';
            title.textContent = 'Edit BOM';
            submitText.textContent = 'Simpan Perubahan';

            productSelect.value = data.product_id;
            document.getElementById('bomCode').value = data.code;
            document.getElementById('bomName').value = data.name;
            document.getElementById('bomOutput').value = Number(data.output_qty).toLocaleString('id-ID', {
                useGrouping: true,
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
            rows.innerHTML = '';

            (data.items || []).forEach(item => addMaterialRow(item));
            if (!data.items?.length) addMaterialRow();

            updateOutputUnit();
            bomModal.show();
        });
    });

    document.querySelectorAll('.btn-delete').forEach(button => {
        button.addEventListener('click', async function () {
            const ok = typeof window.erpConfirm === 'function'
                ? await window.erpConfirm('BOM ini akan dihapus. Lanjutkan?', 'Hapus BOM')
                : confirm('Hapus BOM ini?');

            if (!ok) return;

            const deleteForm = document.getElementById('deleteBomForm');
            deleteForm.action = this.dataset.url;
            deleteForm.submit();
        });
    });

    @if($errors->any())
        resetForm();
        bomModal.show();
    @endif
});
</script>
@endpush
@endsection
