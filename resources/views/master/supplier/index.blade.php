@extends('layouts.app')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h4 class="mb-1">Master Data Supplier</h4>
        <div class="text-secondary small">Data pemasok barang dan jasa untuk kebutuhan pembelian.</div>
    </div>
    <button type="button" class="btn btn-primary" id="btnAddMaster">
        <i class="bi bi-plus-lg me-1"></i> Tambah Supplier
    </button>
</div>


@if($errors->any())
    <div class="alert alert-danger py-2">
        <div class="fw-semibold mb-1">Data supplier belum tersimpan.</div>
        <ul class="mb-0 ps-3">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-header bg-primary bg-opacity-10 text-primary border-bottom d-flex justify-content-between align-items-center py-3">
        <div class="fw-semibold">
            <i class="bi bi-truck me-2"></i>Daftar Supplier
        </div>
        <span class="badge rounded-pill bg-white text-primary border border-primary-subtle" id="supplierCount">Data supplier</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="masterDataTable" style="width:100%">
            <thead class="table-primary">
                <tr>
                    @foreach($config['columns'] as $column)
                        <th>{{ $config['column_labels'][$column] ?? ucwords(str_replace('_', ' ', $column)) }}</th>
                    @endforeach
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="masterModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-md modal-dialog-scrollable" style="max-height: calc(100vh - 1rem);">
        <div class="modal-content" style="max-height: calc(100vh - 1rem);">
            <form id="masterForm" method="POST" action="{{ url('/master/suppliers') }}" novalidate>
                @csrf
                <input type="hidden" name="_method" id="masterFormMethod" value="PUT" disabled>
                <div class="modal-header py-2">
                    <div>
                        <h5 class="modal-title mb-1" id="masterModalTitle">Tambah Supplier</h5>
                        <div class="text-secondary small">Lengkapi data supplier dengan benar.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-3 py-2" style="overflow-y: auto; max-height: calc(100vh - 170px);">
                    <div class="row g-2">
                        @foreach($config['fields'] as $key => $field)
                            <div class="{{ in_array($type, ['customers', 'suppliers'], true) ? 'col-12' : ($type === 'warehouses' ? 'col-md-6' : 'col-md-4') }}">
                                <label class="form-label fw-medium mb-1">{{ $field['label'] }}@if($field['required'] ?? false) <span class="text-danger">*</span>@endif</label>
                                @if($field['type'] === 'textarea')
                                    <textarea name="{{ $key }}" class="form-control @if($field['required'] ?? false) required-field @endif" rows="2" @if($field['required'] ?? false) data-required="1" @endif></textarea>
                                @elseif($field['type'] === 'select')
                                    <select name="{{ $key }}" class="form-select @if($field['required'] ?? false) required-field @endif" @if($field['required'] ?? false) data-required="1" @endif>
                                        @foreach($field['options'] as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input name="{{ $key }}" type="{{ $field['type'] }}" step="{{ $field['step'] ?? 'any' }}" class="form-control @if($field['required'] ?? false) required-field @endif" placeholder="{{ $field['placeholder'] ?? ($key === 'email' ? 'nama@domain.com' : '') }}" @if($field['readonly'] ?? false) readonly @endif @if($field['required'] ?? false) data-required="1" @endif @if($type === 'suppliers' && $key === 'email') autocomplete="email" aria-describedby="supplierEmailError" @endif>
                                    @if($type === 'suppliers' && $key === 'email')
                                        <div class="invalid-feedback" id="supplierEmailError">Format email tidak valid. Contoh: nama@domain.com.</div>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <small class="text-secondary me-auto"><span class="text-danger">*</span> Wajib diisi</small>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success px-4" id="masterSubmit">
                        <i class="bi bi-check-lg me-1"></i>Simpan Supplier
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="masterConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title">Konfirmasi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">Hapus data supplier ini?</div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger btn-sm" id="masterConfirmYes">
                    <i class="bi bi-trash me-1"></i>Ya, Hapus
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const tableEl = document.getElementById('masterDataTable');
    if (!tableEl || typeof DataTable === 'undefined') {
        console.error('DataTables belum termuat.');
        return;
    }

    const modal = new bootstrap.Modal(document.getElementById('masterModal'));
    const confirmModal = new bootstrap.Modal(document.getElementById('masterConfirmModal'));
    const form = document.getElementById('masterForm');
    const title = document.getElementById('masterModalTitle');
    const submitButton = document.getElementById('masterSubmit');
    const countBadge = document.getElementById('supplierCount');
    const baseUrl = @json(url('/master/suppliers'));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());

    let editId = null;
    let deleteId = null;

    const dataTable = new DataTable('#masterDataTable', {
        processing: true,
        serverSide: true,
        pageLength: 15,
        lengthMenu: [[15, 25, 50, 100], [15, 25, 50, 100]],
        language: {
            lengthMenu: 'Tampilkan _MENU_ data per halaman',
            search: 'Cari:',
            info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            infoFiltered: '(disaring dari _MAX_ data)',
            zeroRecords: 'Data tidak ditemukan',
            emptyTable: 'Belum ada data',
            paginate: {
                first: '<<',
                last: '>>',
                next: '>',
                previous: '<'
            },
            processing: 'Memuat...'
        },
        order: [[0, 'desc']],
        ajax: {
            url: baseUrl,
            type: 'GET',
            dataSrc: json => {
                countBadge.textContent = (json.recordsFiltered ?? json.recordsTotal ?? 0) + ' supplier';
                return json.data || [];
            }
        },
        columns: [
            @foreach($config['columns'] as $column)
                { data: @json($column), defaultContent: '', render: (data) => {
                    if (@json($type) === 'customers' && @json($column) === 'customer_type') return ({umum:'Umum',proyek:'Proyek',perusahaan:'Perusahaan'}[data] || data || '');
                    if ((@json($type) === 'customers' || @json($type) === 'suppliers' || @json($type) === 'workers' || @json($type) === 'warehouses') && @json($column) === 'is_active') return Number(data) === 1 ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>';
                    return data ?? '';
                } },
            @endforeach
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-end text-nowrap',
                render: (data, type, row) => {
                    const json = encodeURIComponent(JSON.stringify(row));
                    return '<button type="button" class="btn btn-outline-primary btn-sm btn-edit-master" data-id="' + row.id + '" data-row="' + json + '">Edit</button> ' +
                           '<button type="button" class="btn btn-outline-danger btn-sm btn-delete-master" data-id="' + row.id + '">Hapus</button>';
                }
            }
        ]
    });

    const methodInput = document.getElementById('masterFormMethod');

    const resetForm = () => {
        form.reset();
        form.action = baseUrl;
        methodInput.disabled = true;
        editId = null;
        form.querySelectorAll('.is-invalid').forEach(input => {
            input.classList.remove('is-invalid');
            input.removeAttribute('aria-invalid');
        });
        const emailFeedback = document.getElementById('supplierEmailError');
        if (emailFeedback) emailFeedback.textContent = 'Format email tidak valid. Contoh: nama@domain.com.';
        title.textContent = 'Tambah Supplier';
        submitButton.innerHTML = '<i class="bi bi-check-lg me-1"></i>Simpan Supplier';
    };

    const validateRequired = () => {
        const missing = [];
        form.querySelectorAll('[data-required="1"]').forEach(input => {
            const empty = !String(input.value ?? '').trim();
            input.classList.toggle('is-invalid', empty);
            if (empty) {
                const label = input.closest('[class*="col-"]')?.querySelector('.form-label')?.textContent?.trim() || input.name;
                missing.push(label);
            }
        });
        return missing;
    };

    document.getElementById('btnAddMaster').addEventListener('click', () => {
        resetForm();
        modal.show();
    });

    const emailInput = form.elements.namedItem('email');
    const validateEmail = () => {
        if (!emailInput || @json($type) !== 'suppliers') return true;
        const value = String(emailInput.value ?? '').trim();
        const valid = value === '' || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        emailInput.classList.toggle('is-invalid', !valid);
        emailInput.setAttribute('aria-invalid', valid ? 'false' : 'true');
        return valid;
    };

    form.addEventListener('input', e => {
        if (e.target.matches('.required-field') && String(e.target.value ?? '').trim()) {
            e.target.classList.remove('is-invalid');
        }
        if (e.target === emailInput) validateEmail();
    });

    form.addEventListener('change', e => {
        if (e.target.matches('.required-field') && String(e.target.value ?? '').trim()) {
            e.target.classList.remove('is-invalid');
        }
        if (e.target === emailInput) validateEmail();
    });

    form.addEventListener('submit', async e => {
        e.preventDefault();

        if (validateRequired().length) {
            form.querySelector('[data-required="1"].is-invalid')?.focus();
            return;
        }

        if (!validateEmail()) {
            emailInput.focus();
            return;
        }

        const url = editId ? baseUrl + '/' + editId : baseUrl;
        const payload = new FormData(form);

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                body: payload
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                const errors = data.errors || {};
                const emailError = Array.isArray(errors.email) ? errors.email[0] : null;

                if (emailError && emailInput) {
                    emailInput.classList.add('is-invalid');
                    emailInput.setAttribute('aria-invalid', 'true');
                    const feedback = document.getElementById('supplierEmailError');
                    if (feedback) feedback.textContent = emailError;
                    emailInput.focus();
                } else {
                    const firstError = Object.values(errors).flat().find(Boolean);
                    window.erpNotify(firstError || data.message || 'Data supplier gagal disimpan. Periksa kembali isian.', 'danger');
                }
                return;
            }

            modal.hide();
            dataTable.ajax.reload(null, false);
            window.erpNotify(data.message || 'Data berhasil disimpan.', 'success');
        } catch (error) {
            console.error(error);
            window.erpNotify(error.message || 'Terjadi kesalahan saat menyimpan data.', 'danger');
        }
    });

    document.addEventListener('click', e => {
        const edit = e.target.closest('.btn-edit-master');
        if (edit) {
            const row = JSON.parse(decodeURIComponent(edit.dataset.row));
            resetForm();
            editId = edit.dataset.id;
            form.action = baseUrl + '/' + editId;
            methodInput.disabled = false;

            Object.keys(row).forEach(key => {
                const input = form.elements.namedItem(key);
                if (input) input.value = row[key] ?? '';
            });

            title.textContent = 'Edit Supplier';
            submitButton.innerHTML = '<i class="bi bi-check-lg me-1"></i>Update Supplier';
            modal.show();
            return;
        }

        const del = e.target.closest('.btn-delete-master');
        if (del) {
            deleteId = del.dataset.id;
            confirmModal.show();
        }
    });

    document.getElementById('masterConfirmYes').addEventListener('click', async () => {
        if (!deleteId) return;

        confirmModal.hide();

        try {
            const response = await fetch(baseUrl + '/' + deleteId, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf
                }
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                window.erpNotify(data.message || 'Data tidak dapat dihapus karena sudah digunakan oleh data lain.', 'danger');
                return;
            }

            dataTable.ajax.reload(null, false);
            window.erpNotify(data.message || 'Data berhasil dihapus.', 'success');
        } catch (error) {
            console.error(error);
            window.erpNotify(error.message || 'Terjadi kesalahan saat menghapus data.', 'danger');
        } finally {
            deleteId = null;
        }
    });
});
</script>
@endsection
