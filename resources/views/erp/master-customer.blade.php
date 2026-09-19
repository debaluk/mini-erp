@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-2">
    <div><h3 class="mb-1">Customer</h3><div class="text-secondary">Master data customer.</div></div>
</div>
<div class="card shadow-sm mb-2">
    <div class="card-body py-3">
        <form id="customerForm" method="POST" action="{{ url('/master/customer') }}" novalidate onsubmit="return window.submitCustomerForm ? window.submitCustomerForm(event) : false;">
            @csrf
            <div class="row g-2 align-items-end">
                <div class="col-md-2"><label class="form-label mb-1">Kode Customer</label><input id="customer-code" class="form-control form-control-sm" readonly value="Otomatis"></div>
                <div class="col-md-3"><label class="form-label mb-1">Nama Customer <span class="text-danger">*</span></label><input id="customer-name" name="name" class="form-control form-control-sm" maxlength="255"></div>
                <div class="col-md-2"><label class="form-label mb-1">Jenis Customer <span class="text-danger">*</span></label><select id="customer-type" name="customer_type" class="form-select form-select-sm"><option value="umum">Umum</option><option value="proyek">Proyek</option><option value="perusahaan">Perusahaan</option></select></div>
                <div class="col-md-2"><label class="form-label mb-1">No. Telepon</label><input id="customer-phone" name="phone" class="form-control form-control-sm" maxlength="100"></div>
                <div class="col-md-3"><label class="form-label mb-1">Alamat</label><input id="customer-address" name="address" class="form-control form-control-sm"></div>
                <div class="col-md-2"><label class="form-label mb-1">Status</label><div class="d-flex gap-3 align-items-center" style="min-height:31px"><div class="form-check"><input class="form-check-input" type="radio" name="is_active" value="1" id="customer-active" checked><label class="form-check-label small" for="customer-active">Aktif</label></div><div class="form-check"><input class="form-check-input" type="radio" name="is_active" value="0" id="customer-inactive"><label class="form-check-label small" for="customer-inactive">Nonaktif</label></div></div></div>
                <div class="col-md-8 d-flex align-items-end"><div id="customer-alert" class="w-100"></div></div>
                <div class="col-md-2 d-flex justify-content-end gap-1"><button type="submit" class="btn btn-primary btn-sm" id="customer-submit">Simpan</button><button type="button" class="btn btn-light btn-sm d-none" id="customer-cancel">Batal</button></div>
            </div>
        </form>
    </div>
</div>
<div class="card shadow-sm">
    <div class="card-body py-2 border-bottom"><div class="fw-semibold">Daftar Customer</div></div>
    <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0" id="customers-table" style="width:100%"><thead><tr><th>Kode</th><th>Nama Customer</th><th>Jenis</th><th>Telepon</th><th>Alamat</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody></tbody></table></div>
</div>

@push('scripts')
<script>
(function () {
    let editId = null;
    let table = null;

    function getEl(id) { return document.getElementById(id); }
    function showAlert(message, kind) {
        const box = getEl('customer-alert');
        if (!box) return;
        box.innerHTML = '<div class="alert alert-' + (kind || 'success') + ' py-2 small mb-0">' + message + '</div>';
        setTimeout(function () { box.innerHTML = ''; }, 3500);
    }
    function resetForm() {
        const form = getEl('customerForm');
        if (!form) return;
        form.reset();
        getEl('customer-active').checked = true;
        editId = null;
        getEl('customer-code').value = 'Otomatis';
        getEl('customer-submit').textContent = 'Simpan';
        getEl('customer-cancel').classList.add('d-none');
        getEl('customer-name').classList.remove('is-invalid');
    }

    window.submitCustomerForm = async function (event) {
        if (event) event.preventDefault();

        const form = getEl('customerForm');
        const name = getEl('customer-name');
        const submit = getEl('customer-submit');
        if (!form || !name || !submit) return false;

        if (!name.value.trim()) {
            name.classList.add('is-invalid');
            showAlert('Nama Customer wajib diisi.', 'danger');
            name.focus();
            return false;
        }

        const baseUrl = @json(url('/master/customer'));
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
        const data = new FormData(form);
        if (editId) data.append('_method', 'PUT');

        submit.disabled = true;
        try {
            const response = await fetch(editId ? baseUrl + '/' + editId : baseUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf
                },
                body: data
            });
            const result = await response.json().catch(function () { return {}; });

            if (!response.ok) {
                const errors = Object.values(result.errors || {}).flat();
                showAlert(result.message || errors.join('<br>') || 'Gagal menyimpan customer.', 'danger');
                return false;
            }

            showAlert(result.message || 'Customer berhasil disimpan.');
            resetForm();
            if (table) {
                table.ajax.reload(null, false);
            } else {
                setTimeout(function () { window.location.reload(); }, 300);
            }
        } catch (error) {
            console.error(error);
            showAlert('Terjadi kesalahan saat menyimpan customer.', 'danger');
        } finally {
            submit.disabled = false;
        }
        return false;
    };

    document.addEventListener('DOMContentLoaded', function () {
        const cancel = getEl('customer-cancel');

        if (cancel) cancel.addEventListener('click', resetForm);

        if (typeof DataTable === 'function') {
            table = new DataTable('#customers-table', {
                processing: true,
                serverSide: true,
                ordering: false,
                pageLength: 15,
                lengthMenu: [[15, 25, 50, 100], [15, 25, 50, 100]],
                ajax: { url: @json(url('/master/customer')), type: 'GET', dataSrc: 'data' },
                columns: [
                    { data: 'code' },
                    { data: 'name' },
                    { data: 'customer_type', render: function (v) { return ({umum:'Umum',proyek:'Proyek',perusahaan:'Perusahaan'})[v] || v || ''; } },
                    { data: 'phone', defaultContent: '' },
                    { data: 'address', defaultContent: '' },
                    { data: 'is_active', render: function (v) { return Number(v) === 1 ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>'; } },
                    { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: function (d,t,r) {
                        return '<button type="button" class="btn btn-outline-primary btn-sm btn-edit-customer" data-id="' + r.id + '">Edit</button> <button type="button" class="btn btn-outline-danger btn-sm btn-delete-customer" data-id="' + r.id + '">Hapus</button>';
                    }}
                ]
            });
        }

        document.addEventListener('click', function (event) {
            const edit = event.target.closest('.btn-edit-customer');
            if (edit && table) {
                const row = table.row(edit.closest('tr')).data();
                if (!row) return;
                editId = edit.dataset.id;
                getEl('customer-code').value = row.code || '';
                getEl('customer-name').value = row.name || '';
                getEl('customer-type').value = row.customer_type || 'umum';
                getEl('customer-phone').value = row.phone || '';
                getEl('customer-address').value = row.address || '';
                getEl('customer-active').checked = Number(row.is_active) === 1;
                getEl('customer-inactive').checked = Number(row.is_active) !== 1;
                getEl('customer-submit').textContent = 'Update';
                getEl('customer-cancel').classList.remove('d-none');
                getEl('customer-name').focus();
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return;
            }

            const del = event.target.closest('.btn-delete-customer');
            if (!del) return;
            if (!confirm('Hapus customer ini? Jika sudah digunakan transaksi, customer tidak dapat dihapus. Nonaktifkan jika masih diperlukan untuk histori.')) return;

            del.disabled = true;
            fetch(@json(url('/master/customer')) + '/' + del.dataset.id, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token())
                }
            }).then(async function (response) {
                const result = await response.json().catch(function () { return {}; });
                if (!response.ok) {
                    showAlert(result.message || 'Customer tidak dapat dihapus.', 'danger');
                    return;
                }
                showAlert(result.message || 'Customer berhasil dihapus.');
                if (editId === del.dataset.id) resetForm();
                if (table) table.ajax.reload(null, false);
            }).catch(function (error) {
                console.error(error);
                showAlert('Terjadi kesalahan saat menghapus customer.', 'danger');
            }).finally(function () {
                del.disabled = false;
            });
        });

        resetForm();
    });
})();
</script>
@endpush
@endsection
