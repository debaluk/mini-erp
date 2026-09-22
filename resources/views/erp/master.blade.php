@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-1">{{ $config['title'] }}</h3>
        <div class="text-secondary">Master data</div>
    </div>
    <button type="button" class="btn btn-primary btn-sm" id="btnAddMaster">+ Tambah</button>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0" id="masterDataTable" style="width:100%">
            <thead>
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

<div class="modal fade" id="masterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="masterForm" novalidate>
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="masterModalTitle">Tambah {{ $config['title'] }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="row g-3">
                        @foreach($config['fields'] as $key => $field)
                            <div class="{{ $type === 'warehouses' ? 'col-md-6' : (in_array($type, ['customers','suppliers'], true) ? 'col-md-6' : 'col-md-4') }}">
                                <label class="form-label mb-1">{{ $field['label'] }}</label>
                                @if($field['type'] === 'textarea')
                                    <textarea name="{{ $key }}" class="form-control @if($field['required'] ?? false) required-field @endif" rows="2" @if($field['required'] ?? false) data-required="1" @endif></textarea>
                                @elseif($field['type'] === 'select')
                                    <select name="{{ $key }}" class="form-select @if($field['required'] ?? false) required-field @endif" @if($field['required'] ?? false) data-required="1" @endif>
                                        @foreach($field['options'] as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input name="{{ $key }}" type="{{ $field['type'] }}" step="{{ $field['step'] ?? 'any' }}" class="form-control @if($field['required'] ?? false) required-field @endif" placeholder="{{ $field['placeholder'] ?? '' }}" @if($field['readonly'] ?? false) readonly @endif @if($field['required'] ?? false) data-required="1" @endif>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="masterSubmit">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="masterMessageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="masterMessageTitle">Informasi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="masterMessageBody"></div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">OK</button>
            </div>
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
            <div class="modal-body">Hapus data ini?</div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger btn-sm" id="masterConfirmYes">Ya, Hapus</button>
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
    const messageModal = new bootstrap.Modal(document.getElementById('masterMessageModal'));
    const confirmModal = new bootstrap.Modal(document.getElementById('masterConfirmModal'));
    const form = document.getElementById('masterForm');
    const title = document.getElementById('masterModalTitle');
    const messageTitle = document.getElementById('masterMessageTitle');
    const messageBody = document.getElementById('masterMessageBody');
    const submitButton = document.getElementById('masterSubmit');
    const baseUrl = @json(url('/master/'.$type));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());

    let editId = null;
    let deleteId = null;

    const showMessage = (message, type = 'success') => {
        messageTitle.textContent = type === 'danger' ? 'Gagal' : (type === 'warning' ? 'Peringatan' : 'Berhasil');
        messageBody.innerHTML = message;
        messageModal.show();
    };

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
            dataSrc: 'data'
        },
        columns: [
            @foreach($config['columns'] as $column)
                { data: @json($column), defaultContent: '', render: (data) => {
                    if (@json($type) === 'customers' && @json($column) === 'customer_type') return ({umum:'Umum',proyek:'Proyek',perusahaan:'Perusahaan'}[data] || data || '');
                    if ((@json($type) === 'customers' || @json($type) === 'suppliers' || @json($type) === 'warehouses') && @json($column) === 'is_active') return Number(data) === 1 ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>';
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

    const resetForm = () => {
        form.reset();
        editId = null;
        form.querySelectorAll('.required-field').forEach(input => input.classList.remove('is-invalid'));
        title.textContent = 'Tambah {{ $config['title'] }}';
        submitButton.textContent = 'Simpan';
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

    form.addEventListener('input', e => {
        if (e.target.matches('.required-field') && String(e.target.value ?? '').trim()) {
            e.target.classList.remove('is-invalid');
        }
    });

    form.addEventListener('change', e => {
        if (e.target.matches('.required-field') && String(e.target.value ?? '').trim()) {
            e.target.classList.remove('is-invalid');
        }
    });

    form.addEventListener('submit', async e => {
        e.preventDefault();

        if (validateRequired().length) {
            form.querySelector('[data-required="1"].is-invalid')?.focus();
            return;
        }

        const url = editId ? baseUrl + '/' + editId : baseUrl;
        const payload = new FormData(form);
        if (editId) payload.append('_method', 'PUT');

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
                showMessage(data.message || errors.join('<br>') || 'Gagal menyimpan data.', 'danger');
                return;
            }

            modal.hide();
            dataTable.ajax.reload(null, false);
            showMessage(data.message || 'Data berhasil disimpan.');
        } catch (error) {
            console.error(error);
            showMessage('Terjadi kesalahan saat menyimpan data.', 'danger');
        }
    });

    document.addEventListener('click', e => {
        const edit = e.target.closest('.btn-edit-master');
        if (edit) {
            const row = JSON.parse(decodeURIComponent(edit.dataset.row));
            resetForm();
            editId = edit.dataset.id;

            Object.keys(row).forEach(key => {
                const input = form.elements.namedItem(key);
                if (input) input.value = row[key] ?? '';
            });

            title.textContent = 'Edit {{ $config['title'] }}';
            submitButton.textContent = 'Update';
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
                showMessage(data.message || 'Data tidak dapat dihapus karena sudah digunakan oleh data lain.', 'danger');
                return;
            }

            dataTable.ajax.reload(null, false);
            showMessage(data.message || 'Data berhasil dihapus.');
        } catch (error) {
            console.error(error);
            showMessage('Terjadi kesalahan saat menghapus data.', 'danger');
        } finally {
            deleteId = null;
        }
    });
});
</script>
@endsection
