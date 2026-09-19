@extends('layouts.app')

@section('content')
<div class="mb-3">
    <h3 class="mb-1">Supplier</h3>
    <div class="text-secondary">Master data supplier.</div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <div class="col-lg-6">
            <form id="supplierForm" method="POST" action="{{ url('/master/supplier') }}" novalidate onsubmit="return window.submitSupplierForm ? window.submitSupplierForm(event) : false;">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Kode Supplier <span class="text-danger">*</span></label>
                    <input id="supplier-code" class="form-control" readonly value="Otomatis">
                    <div class="form-text">Kode dibuat otomatis.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nama Supplier <span class="text-danger">*</span></label>
                    <input id="supplier-name" name="name" class="form-control" maxlength="255">
                </div>
                <div class="mb-3">
                    <label class="form-label">Kategori</label>
                    <input id="supplier-category" name="category" class="form-control" maxlength="100">
                </div>
                <div class="mb-3">
                    <label class="form-label">No. Telepon</label>
                    <input id="supplier-phone" name="phone" class="form-control" maxlength="100">
                </div>
                <div class="mb-3">
                    <label class="form-label">WhatsApp</label>
                    <input id="supplier-whatsapp" name="whatsapp" class="form-control" maxlength="100">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input id="supplier-email" name="email" type="email" class="form-control" maxlength="255">
                </div>
                <div class="mb-3">
                    <label class="form-label">Website</label>
                    <input id="supplier-website" name="website" class="form-control" maxlength="255">
                </div>
                <div class="mb-3">
                    <label class="form-label">Alamat</label>
                    <textarea id="supplier-address" name="address" class="form-control" rows="3"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Negara</label>
                    <input id="supplier-country" name="country" class="form-control" maxlength="100">
                </div>
                <div class="mb-3">
                    <label class="form-label d-block">Status</label>
                    <div class="d-flex gap-4">
                        <div class="form-check"><input class="form-check-input" type="radio" name="is_active" value="1" id="supplier-active" checked><label class="form-check-label" for="supplier-active">Aktif</label></div>
                        <div class="form-check"><input class="form-check-input" type="radio" name="is_active" value="0" id="supplier-inactive"><label class="form-check-label" for="supplier-inactive">Nonaktif</label></div>
                    </div>
                </div>
                <div id="supplier-alert" class="mb-3"></div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary" id="supplier-submit">Simpan</button>
                    <button type="button" class="btn btn-light d-none" id="supplier-cancel">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body py-2 border-bottom"><div class="fw-semibold">Daftar Supplier</div></div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0" id="suppliers-table" style="width:100%">
            <thead><tr><th>Kode</th><th>Nama Supplier</th><th>Kategori</th><th>Telepon</th><th>WhatsApp</th><th>Email</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
            <tbody></tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
(function () {
    let editId = null;
    let table = null;
    const el = id => document.getElementById(id);

    function alertBox(message, kind = 'success') {
        const box = el('supplier-alert');
        if (box) box.innerHTML = '<div class="alert alert-' + kind + ' py-2 small mb-0">' + message + '</div>';
    }

    function resetForm() {
        el('supplierForm')?.reset();
        el('supplier-code').value = 'Otomatis';
        el('supplier-active').checked = true;
        editId = null;
        el('supplier-submit').textContent = 'Simpan';
        el('supplier-cancel').classList.add('d-none');
    }

    window.submitSupplierForm = async function (event) {
        event?.preventDefault();
        const name = el('supplier-name');
        if (!name.value.trim()) { name.classList.add('is-invalid'); alertBox('Nama Supplier wajib diisi.', 'danger'); name.focus(); return false; }

        const baseUrl = @json(url('/master/supplier'));
        const payload = new FormData(el('supplierForm'));
        if (editId) payload.append('_method', 'PUT');
        const submit = el('supplier-submit');
        submit.disabled = true;
        try {
            const response = await fetch(editId ? baseUrl + '/' + editId : baseUrl, {
                method: 'POST',
                headers: {'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token())},
                body: payload
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) { const errors = Object.values(data.errors || {}).flat(); alertBox(data.message || errors.join('<br>') || 'Gagal menyimpan supplier.', 'danger'); return false; }
            alertBox(data.message || 'Supplier berhasil disimpan.');
            resetForm();
            table?.ajax.reload(null, false);
        } catch (e) { console.error(e); alertBox('Terjadi kesalahan saat menyimpan supplier.', 'danger'); }
        finally { submit.disabled = false; }
        return false;
    };

    document.addEventListener('DOMContentLoaded', function () {
        el('supplier-cancel')?.addEventListener('click', resetForm);
        if (typeof DataTable === 'function') {
            table = new DataTable('#suppliers-table', {
                processing:true, serverSide:true, ordering:false, pageLength:15,
                ajax:{url:@json(url('/master/supplier')),type:'GET',dataSrc:'data'},
                columns:[
                    {data:'code'},{data:'name'},{data:'category',defaultContent:''},{data:'phone',defaultContent:''},
                    {data:'whatsapp',defaultContent:''},{data:'email',defaultContent:''},
                    {data:'is_active',render:v=>Number(v)===1?'<span class="badge text-bg-success">Aktif</span>':'<span class="badge text-bg-secondary">Nonaktif</span>'},
                    {data:null,className:'text-end text-nowrap',render:row=>'<button type="button" class="btn btn-outline-primary btn-sm btn-edit-supplier" data-id="'+row.id+'">Edit</button> <button type="button" class="btn btn-outline-danger btn-sm btn-delete-supplier" data-id="'+row.id+'">Hapus</button>'}
                ]
            });
        }
        document.addEventListener('click', function (event) {
            const edit = event.target.closest('.btn-edit-supplier');
            if (edit && table) {
                const row = table.row(edit.closest('tr')).data(); if (!row) return;
                editId=edit.dataset.id; el('supplier-code').value=row.code||''; el('supplier-name').value=row.name||'';
                el('supplier-category').value=row.category||''; el('supplier-phone').value=row.phone||'';
                el('supplier-whatsapp').value=row.whatsapp||''; el('supplier-email').value=row.email||'';
                el('supplier-website').value=row.website||''; el('supplier-address').value=row.address||'';
                el('supplier-country').value=row.country||''; el('supplier-active').checked=Number(row.is_active)===1; el('supplier-inactive').checked=Number(row.is_active)!==1;
                el('supplier-submit').textContent='Update'; el('supplier-cancel').classList.remove('d-none'); window.scrollTo({top:0,behavior:'smooth'}); return;
            }
            const del=event.target.closest('.btn-delete-supplier'); if(!del) return;
            if(!confirm('Hapus supplier ini? Jika sudah digunakan transaksi, nonaktifkan supplier.')) return;
            fetch(@json(url('/master/supplier'))+'/'+del.dataset.id,{method:'DELETE',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token())}})
                .then(async r=>{const d=await r.json().catch(()=>({})); if(!r.ok) {alertBox(d.message||'Supplier tidak dapat dihapus.','danger');return;} alertBox(d.message||'Supplier berhasil dihapus.'); table?.ajax.reload(null,false);})
                .catch(()=>alertBox('Terjadi kesalahan saat menghapus supplier.','danger'));
        });
        resetForm();
    });
})();
</script>
@endpush
@endsection
