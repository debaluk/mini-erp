@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h3 class="mb-1">{{ $config['title'] }}</h3><div class="text-secondary">Master data</div></div>
    <button type="button" class="btn btn-primary btn-sm" id="btnAddMaster">+ Tambah</button>
</div>

<div id="masterAlert"></div>

<div class="card shadow-sm" id="masterTableContainer">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead><tr>
                @foreach($config['columns'] as $column)<th>{{ ucwords(str_replace('_',' ',$column)) }}</th>@endforeach
                <th class="text-end" style="width:130px">Aksi</th>
            </tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($config['columns'] as $column)<td>{{ $row->$column }}</td>@endforeach
                    <td class="text-end text-nowrap">
                        <button type="button" class="btn btn-outline-primary btn-sm btn-edit-master"
                            data-id="{{ $row->id }}"
                            data-row='@json($row)'>Edit</button>
                        <button type="button" class="btn btn-outline-danger btn-sm btn-delete-master" data-id="{{ $row->id }}">Hapus</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ count($config['columns']) + 1 }}" class="text-center text-secondary py-4">Belum ada data.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer py-2">{{ $rows->links() }}</div>
</div>

<div class="modal fade" id="masterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="masterForm">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="masterModalTitle">Tambah {{ $config['title'] }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="row g-2">
                    @foreach($config['fields'] as $key=>$field)
                        <div class="col-md-4">
                            <label class="form-label">{{ $field['label'] }}</label>
                            @if($field['type']==='textarea')
                                <textarea name="{{ $key }}" class="form-control" rows="2"></textarea>
                            @elseif($field['type']==='select')
                                <select name="{{ $key }}" class="form-select">
                                    @foreach($field['options'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                </select>
                            @else
                                <input name="{{ $key }}" type="{{ $field['type'] }}" step="{{ $field['step'] ?? 'any' }}" class="form-control" @if($field['required']??false) required @endif>
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

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalEl = document.getElementById('masterModal');
    const modal = new bootstrap.Modal(modalEl);
    const form = document.getElementById('masterForm');
    const title = document.getElementById('masterModalTitle');
    const alertBox = document.getElementById('masterAlert');
    const tableBox = document.getElementById('masterTableContainer');
    const baseUrl = @json(url('/master/'.$type));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
    let editId = null;

    const showAlert = (message, type='success') => {
        alertBox.innerHTML = '<div class="alert alert-' + type + ' py-2 mb-3">' + message + '</div>';
        setTimeout(() => { alertBox.innerHTML = ''; }, 3500);
    };

    const reloadTable = async (url = window.location.href) => {
        const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const html = await response.text();
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const fresh = doc.querySelector('#masterTableContainer');
        if (fresh) tableBox.replaceWith(fresh);
    };

    const resetForm = () => {
        form.reset();
        editId = null;
        title.textContent = 'Tambah {{ $config['title'] }}';
        document.getElementById('masterSubmit').textContent = 'Simpan';
    };

    document.getElementById('btnAddMaster').addEventListener('click', () => {
        resetForm();
        modal.show();
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const url = editId ? baseUrl + '/' + editId : baseUrl;
        const method = editId ? 'PUT' : 'POST';
        const response = await fetch(url, {
            method,
            headers: {'X-Requested-With':'XMLHttpRequest','Accept':'application/json','X-CSRF-TOKEN':csrf},
            body: new FormData(form)
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const message = data.message || Object.values(data.errors || {}).flat().join('<br>') || 'Gagal menyimpan data.';
            showAlert(message, 'danger');
            return;
        }
        modal.hide();
        showAlert(data.message || 'Berhasil.');
        await reloadTable();
    });

    document.addEventListener('click', async (e) => {
        const edit = e.target.closest('.btn-edit-master');
        if (edit) {
            const row = JSON.parse(edit.dataset.row);
            resetForm();
            editId = edit.dataset.id;
            Object.keys(row).forEach(key => {
                const input = form.elements.namedItem(key);
                if (input) input.value = row[key] ?? '';
            });
            title.textContent = 'Edit {{ $config['title'] }}';
            document.getElementById('masterSubmit').textContent = 'Update';
            modal.show();
            return;
        }
        const del = e.target.closest('.btn-delete-master');
        if (del) {
            if (!confirm('Hapus data ini?')) return;
            const response = await fetch(baseUrl + '/' + del.dataset.id, {
                method:'DELETE',
                headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json','X-CSRF-TOKEN':csrf}
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                showAlert(data.message || 'Data tidak dapat dihapus karena sudah digunakan.', 'danger');
                return;
            }
            showAlert(data.message || 'Data berhasil dihapus.');
            await reloadTable();
        }
    });

    document.addEventListener('click', async (e) => {
        const link = e.target.closest('#masterTableContainer .pagination a');
        if (!link) return;
        e.preventDefault();
        await reloadTable(link.href);
    });
});
</script>
@endsection
