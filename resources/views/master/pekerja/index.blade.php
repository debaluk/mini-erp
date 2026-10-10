@extends('layouts.app')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h4 class="mb-1">Master Data Pekerja</h4>
        <div class="text-secondary small">Data tenaga kerja yang dapat ditetapkan pada Work Order / SPK produksi.</div>
    </div>
    <button type="button" class="btn btn-primary" id="btnAddMaster">
        <i class="bi bi-plus-lg me-1"></i> Tambah Pekerja
    </button>
</div>


@if($errors->any())
    <div class="alert alert-danger py-2">
        <div class="fw-semibold mb-1">Data pekerja belum tersimpan.</div>
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
            <i class="bi bi-people me-2"></i>Daftar Pekerja
        </div>
        <span class="badge rounded-pill bg-white text-primary border border-primary-subtle" id="workerCount">Data pekerja</span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 text-nowrap" id="masterDataTable" style="width:100%; min-width:900px;">
            <thead class="table-primary">
                <tr>
                    <th style="width:50px;">No</th>
                    <th>Kode Pekerja</th>
                    <th>Nama Pekerja</th>
                    <th>No. Telepon</th>
                    <th>Alamat</th>
                    <th class="text-center">Status</th>
                    <th class="text-end" style="width:110px;">Aksi</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<div class="modal fade" id="masterModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="masterForm" novalidate>
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-1" id="masterModalTitle">Tambah Pekerja</h5>
                        <div class="text-secondary small">Kode pekerja dibuat otomatis oleh sistem.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-2">
                        @foreach($config['fields'] as $key => $field)
                            @continue($key === 'code')
                            <div class="{{ $key === 'address' ? 'col-12' : 'col-md-6' }}">
                                <label class="form-label">{{ $field['label'] }}@if($field['required'] ?? false) <span class="text-danger">*</span>@endif</label>

                                @if($field['type'] === 'textarea')
                                    <textarea
                                        name="{{ $key }}"
                                        class="form-control @if($field['required'] ?? false) required-field @endif"
                                        rows="3"
                                        @if($field['required'] ?? false) data-required="1" @endif
                                    ></textarea>
                                @elseif($field['type'] === 'select')
                                    <select
                                        name="{{ $key }}"
                                        class="form-select @if($field['required'] ?? false) required-field @endif"
                                        @if($field['required'] ?? false) data-required="1" @endif
                                    >
                                        @foreach($field['options'] as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input
                                        name="{{ $key }}"
                                        type="{{ $field['type'] }}"
                                        class="form-control @if($field['required'] ?? false) required-field @endif"
                                        placeholder="{{ $field['placeholder'] ?? '' }}"
                                        @if($field['readonly'] ?? false) readonly @endif
                                        @if($field['required'] ?? false) data-required="1" @endif
                                    >
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <small class="text-secondary me-auto"><span class="text-danger">*</span> Wajib diisi</small>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success px-4" id="masterSubmit">
                        <i class="bi bi-check-lg me-1"></i>Simpan Pekerja
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
            <div class="modal-body">Hapus data pekerja ini?</div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger btn-sm" id="masterConfirmYes">
                    <i class="bi bi-trash me-1"></i>Ya, Hapus
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
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
    const countBadge = document.getElementById('workerCount');
    const baseUrl = @json(url('/master/pekerja'));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());

    let editId = null;
    let deleteId = null;

    const dataTable = new DataTable('#masterDataTable', {
        processing: true,
        serverSide: true,
        pageLength: 15,
        lengthMenu: [[15, 25, 50, 100], [15, 25, 50, 100]],
        scrollX: true,
        autoWidth: false,
        order: [[1, 'asc']],
        language: {
            lengthMenu: 'Tampilkan _MENU_ data',
            search: 'Cari:',
            info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
            infoEmpty: 'Menampilkan 0 sampai 0 dari _TOTAL_ data',
            infoFiltered: '(disaring dari _MAX_ total data)',
            zeroRecords: 'Data pekerja tidak ditemukan',
            emptyTable: 'Belum ada data pekerja',
            paginate: {
                first: '<<',
                last: '>>',
                next: '>',
                previous: '<'
            },
            processing: 'Memuat...'
        },
        ajax: {
            url: baseUrl,
            type: 'GET',
            dataSrc: json => {
                countBadge.textContent = (json.recordsFiltered ?? json.recordsTotal ?? 0) + ' pekerja';
                return json.data || [];
            }
        },
        columns: [
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-secondary',
                render: (data, type, row, meta) =>
                    meta.settings._iDisplayStart + meta.row + 1
            },
            {
                data: 'code',
                defaultContent: '',
                className: 'fw-semibold'
            },
            {
                data: 'name',
                defaultContent: ''
            },
            {
                data: 'phone',
                defaultContent: '',
                render: data => data || '-'
            },
            {
                data: 'address',
                defaultContent: '',
                render: data => data || '-'
            },
            {
                data: 'is_active',
                className: 'text-center',
                render: data =>
                    Number(data) === 1
                        ? '<span class="badge rounded-pill text-bg-success"><i class="bi bi-check-circle-fill me-1"></i>Aktif</span>'
                        : '<span class="badge rounded-pill text-bg-secondary">Nonaktif</span>'
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-end text-nowrap',
                render: (data, type, row) => {
                    const json = encodeURIComponent(JSON.stringify(row));

                    return '<div class="btn-group btn-group-sm" role="group">' +
                        '<button type="button" class="btn btn-outline-primary btn-edit-master" data-id="' + row.id + '" data-row="' + json + '" title="Edit">' +
                            '<i class="bi bi-pencil-square"></i>' +
                        '</button>' +
                        '<button type="button" class="btn btn-outline-danger btn-delete-master" data-id="' + row.id + '" title="Hapus">' +
                            '<i class="bi bi-trash"></i>' +
                        '</button>' +
                    '</div>';
                }
            }
        ]
    });

    const resetForm = () => {
        form.reset();
        editId = null;

        form.querySelectorAll('.required-field').forEach(input => {
            input.classList.remove('is-invalid');
        });

        title.textContent = 'Tambah Pekerja';
        submitButton.innerHTML = '<i class="bi bi-check-lg me-1"></i>Simpan Pekerja';
    };

    const validateRequired = () => {
        const missing = [];

        form.querySelectorAll('[data-required="1"]').forEach(input => {
            const empty = !String(input.value ?? '').trim();

            input.classList.toggle('is-invalid', empty);

            if (empty) {
                const label = input.closest('[class*="col-"]')
                    ?.querySelector('.form-label')
                    ?.textContent
                    ?.trim() || input.name;

                missing.push(label);
            }
        });

        return missing;
    };

    document.getElementById('btnAddMaster').addEventListener('click', () => {
        resetForm();
        modal.show();
    });

    form.addEventListener('input', event => {
        if (event.target.matches('.required-field') && String(event.target.value ?? '').trim()) {
            event.target.classList.remove('is-invalid');
        }
    });

    form.addEventListener('change', event => {
        if (event.target.matches('.required-field') && String(event.target.value ?? '').trim()) {
            event.target.classList.remove('is-invalid');
        }
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();

        if (validateRequired().length) {
            form.querySelector('[data-required="1"].is-invalid')?.focus();
            return;
        }

        const url = editId ? baseUrl + '/' + editId : baseUrl;
        const payload = new FormData(form);

        if (editId) {
            payload.append('_method', 'PUT');
        }

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
                const errors = Object.values(data.errors || {}).flat();
                window.erpNotify(
                    data.message || (errors.length ? errors : 'Gagal menyimpan data pekerja.'),
                    'danger'
                );
                return;
            }

            modal.hide();
            dataTable.ajax.reload(null, false);
            window.erpNotify(data.message || 'Data pekerja berhasil disimpan.', 'success');
        } catch (error) {
            console.error(error);
            window.erpNotify(error.message || 'Terjadi kesalahan saat menyimpan data pekerja.', 'danger');
        }
    });

    document.addEventListener('click', event => {
        const edit = event.target.closest('.btn-edit-master');

        if (edit) {
            const row = JSON.parse(decodeURIComponent(edit.dataset.row));

            resetForm();
            editId = edit.dataset.id;

            Object.keys(row).forEach(key => {
                const input = form.elements.namedItem(key);

                if (input) {
                    input.value = row[key] ?? '';
                }
            });

            title.textContent = 'Edit Pekerja';
            submitButton.innerHTML = '<i class="bi bi-check-lg me-1"></i>Update Pekerja';
            modal.show();
            return;
        }

        const del = event.target.closest('.btn-delete-master');

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
                window.erpNotify(
                    data.message || 'Pekerja tidak dapat dihapus karena sudah digunakan.',
                    'danger'
                );
                return;
            }

            dataTable.ajax.reload(null, false);
            window.erpNotify(data.message || 'Pekerja berhasil dihapus.', 'success');
        } catch (error) {
            console.error(error);
            window.erpNotify(error.message || 'Terjadi kesalahan saat menghapus data pekerja.', 'danger');
        } finally {
            deleteId = null;
        }
    });
});
</script>
@endpush
