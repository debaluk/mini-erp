@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-1">Satuan</h3>
        <div class="text-secondary">Master satuan yang digunakan dalam Item dan transaksi.</div>
    </div>
</div>

<div id="unit-alert"></div>

<div class="card shadow-sm mb-3">
    <div class="card-body py-3">
        <form id="unitForm" novalidate>
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="unit-code" class="form-label mb-1">Kode Satuan <span class="text-danger">*</span></label>
                    <input type="text" id="unit-code" name="code" class="form-control form-control-sm" maxlength="50" autocomplete="off">
                </div>
                <div class="col-md-5">
                    <label for="unit-name" class="form-label mb-1">Nama Satuan <span class="text-danger">*</span></label>
                    <input type="text" id="unit-name" name="name" class="form-control form-control-sm" maxlength="100" autocomplete="off">
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Status</label>
                    <div class="d-flex gap-3 align-items-center" style="min-height:31px">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="is_active" id="unit-active" value="1" checked>
                            <label class="form-check-label small" for="unit-active">Aktif</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="is_active" id="unit-inactive" value="0">
                            <label class="form-check-label small" for="unit-inactive">Nonaktif</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm" id="unit-submit">Simpan</button>
                    <button type="button" class="btn btn-light btn-sm d-none" id="unit-cancel">Batal</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body py-2 border-bottom">
        <div class="d-flex justify-content-between align-items-center">
            <div class="fw-semibold">Daftar Satuan</div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0" id="units-table" style="width:100%">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama Satuan</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('unitForm');
    const code = document.getElementById('unit-code');
    const name = document.getElementById('unit-name');
    const submit = document.getElementById('unit-submit');
    const cancel = document.getElementById('unit-cancel');
    const alertBox = document.getElementById('unit-alert');
    const baseUrl = @json(url('/master/units'));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
    let editId = null;

    const showAlert = (message, type = 'success') => {
        alertBox.innerHTML = '<div class="alert alert-' + type + ' py-2 small">' + message + '</div>';
        window.setTimeout(() => { alertBox.innerHTML = ''; }, 3500);
    };

    const resetForm = () => {
        form.reset();
        document.getElementById('unit-active').checked = true;
        editId = null;
        submit.textContent = 'Simpan';
        cancel.classList.add('d-none');
        code.classList.remove('is-invalid');
        name.classList.remove('is-invalid');
        code.focus();
    };

    const validate = () => {
        let ok = true;
        [code, name].forEach(input => {
            const invalid = !input.value.trim();
            input.classList.toggle('is-invalid', invalid);
            if (invalid) ok = false;
        });
        if (!ok) showAlert('Kode dan Nama Satuan wajib diisi.', 'danger');
        return ok;
    };

    const table = new DataTable('#units-table', {
        processing: true,
        serverSide: true,
        ordering: false,
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
            paginate: { first: '<<', last: '>>', next: '>', previous: '<' },
            processing: 'Memuat...'
        },
        ajax: { url: baseUrl, type: 'GET', dataSrc: 'data' },
        columns: [
            { data: 'code', defaultContent: '' },
            { data: 'name', defaultContent: '' },
            {
                data: 'is_active',
                render: value => Number(value) === 1
                    ? '<span class="badge text-bg-success">Aktif</span>'
                    : '<span class="badge text-bg-secondary">Nonaktif</span>'
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-end text-nowrap',
                render: (data, type, row) =>
                    '<button type="button" class="btn btn-outline-primary btn-sm btn-edit-unit" data-id="' + row.id + '">Edit</button> ' +
                    '<button type="button" class="btn btn-outline-danger btn-sm btn-delete-unit" data-id="' + row.id + '">Hapus</button>'
            }
        ]
    });

    const getUnit = async id => {
        const response = await fetch(baseUrl + '?lookup=' + encodeURIComponent(id), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        return response.json();
    };

    form.addEventListener('input', event => {
        if (event.target.value.trim()) event.target.classList.remove('is-invalid');
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!validate()) return;

        const payload = new FormData(form);
        if (editId) payload.append('_method', 'PUT');

        try {
            const response = await fetch(editId ? baseUrl + '/' + editId : baseUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf
                },
                body: payload
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                const errors = Object.values(data.errors || {}).flat();
                showAlert(data.message || errors.join('<br>') || 'Gagal menyimpan satuan.', 'danger');
                return;
            }
            showAlert(data.message || 'Satuan berhasil disimpan.');
            resetForm();
            table.ajax.reload(null, false);
        } catch (error) {
            console.error(error);
            showAlert('Terjadi kesalahan saat menyimpan satuan.', 'danger');
        }
    });

    document.addEventListener('click', async event => {
        const editButton = event.target.closest('.btn-edit-unit');
        if (editButton) {
            const row = table.row(editButton.closest('tr')).data();
            if (!row) return;
            editId = editButton.dataset.id;
            code.value = row.code ?? '';
            name.value = row.name ?? '';
            document.getElementById('unit-active').checked = Number(row.is_active) === 1;
            document.getElementById('unit-inactive').checked = Number(row.is_active) !== 1;
            submit.textContent = 'Update';
            cancel.classList.remove('d-none');
            code.focus();
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        }

        const deleteButton = event.target.closest('.btn-delete-unit');
        if (deleteButton) {
            const id = deleteButton.dataset.id;
            if (!confirm('Hapus satuan ini? Jika sudah digunakan Item atau konversi, satuan tidak dapat dihapus. Nonaktifkan jika masih diperlukan untuk histori.')) return;

            deleteButton.disabled = true;
            try {
                const response = await fetch(baseUrl + '/' + id, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf
                    }
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    showAlert(data.message || 'Satuan tidak dapat dihapus.', 'danger');
                    return;
                }
                showAlert(data.message || 'Satuan berhasil dihapus.');
                if (editId === id) resetForm();
                table.ajax.reload(null, false);
            } catch (error) {
                console.error(error);
                showAlert('Terjadi kesalahan saat menghapus satuan.', 'danger');
            } finally {
                deleteButton.disabled = false;
            }
        }
    });

    cancel.addEventListener('click', resetForm);
    resetForm();
});
</script>
@endsection
