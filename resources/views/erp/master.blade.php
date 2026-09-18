@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h3 class="mb-1">{{ $config['title'] }}</h3><div class="text-secondary">Master data</div></div>
    <button type="button" class="btn btn-primary btn-sm" id="btnAddMaster">+ Tambah</button>
</div>

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
                        <button type="button" class="btn btn-outline-primary btn-sm btn-edit-master" data-id="{{ $row->id }}" data-row='{{ json_encode($row, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) }}'>Edit</button>
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
            <form id="masterForm" novalidate>
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
                                <textarea name="{{ $key }}" class="form-control @if($field['required']??false) required-field @endif" rows="2" @if($field['required']??false) data-required="1" @endif></textarea>
                            @elseif($field['type']==='select')
                                <select name="{{ $key }}" class="form-select @if($field['required']??false) required-field @endif" @if($field['required']??false) data-required="1" @endif>
                                    @foreach($field['options'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                </select>
                            @else
                                <input name="{{ $key }}" type="{{ $field['type'] }}" step="{{ $field['step'] ?? 'any' }}" class="form-control @if($field['required']??false) required-field @endif" @if($field['required']??false) data-required="1" @endif>
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
    <div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content">
        <div class="modal-header py-2"><h5 class="modal-title" id="masterMessageTitle">Informasi</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" id="masterMessageBody"></div>
        <div class="modal-footer py-2"><button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">OK</button></div>
    </div></div>
</div>

<div class="modal fade" id="masterConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content">
        <div class="modal-header py-2"><h5 class="modal-title">Konfirmasi</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">Hapus data ini?</div>
        <div class="modal-footer py-2"><button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button><button type="button" class="btn btn-danger btn-sm" id="masterConfirmYes">Ya, Hapus</button></div>
    </div></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalEl=document.getElementById('masterModal'), modal=new bootstrap.Modal(modalEl);
    const messageModal=new bootstrap.Modal(document.getElementById('masterMessageModal'));
    const confirmModal=new bootstrap.Modal(document.getElementById('masterConfirmModal'));
    const form=document.getElementById('masterForm'), title=document.getElementById('masterModalTitle');
    const tableBox=document.getElementById('masterTableContainer'), messageTitle=document.getElementById('masterMessageTitle'), messageBody=document.getElementById('masterMessageBody');
    const baseUrl=@json(url('/master/'.$type)), csrf=document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
    let editId=null, deleteId=null;

    const showMessage=(message,type='success')=>{messageTitle.textContent=type==='danger'?'Gagal':(type==='warning'?'Peringatan':'Berhasil');messageBody.innerHTML=message;messageModal.show();};
    const reloadTable=async(url=window.location.href)=>{const response=await fetch(url,{headers:{'X-Requested-With':'XMLHttpRequest'}});const html=await response.text();const doc=new DOMParser().parseFromString(html,'text/html');const fresh=doc.querySelector('#masterTableContainer');if(fresh)tableBox.replaceWith(fresh);};
    const resetForm=()=>{form.reset();editId=null;form.querySelectorAll('.required-field').forEach(i=>i.classList.remove('is-invalid'));title.textContent='Tambah {{ $config['title'] }}';document.getElementById('masterSubmit').textContent='Simpan';};
    const validateRequired=()=>{const missing=[];form.querySelectorAll('[data-required="1"]').forEach(input=>{const empty=!String(input.value??'').trim();input.classList.toggle('is-invalid',empty);if(empty){const label=input.closest('.col-md-4')?.querySelector('.form-label')?.textContent?.trim()||input.name;missing.push(label);}});return missing;};

    document.getElementById('btnAddMaster').addEventListener('click',()=>{resetForm();modal.show();});
    form.addEventListener('input',e=>{if(e.target.matches('.required-field')&&String(e.target.value??'').trim())e.target.classList.remove('is-invalid');});
    form.addEventListener('change',e=>{if(e.target.matches('.required-field')&&String(e.target.value??'').trim())e.target.classList.remove('is-invalid');});

    form.addEventListener('submit',async e=>{
        e.preventDefault();
        const missing=validateRequired();
        if(missing.length){showMessage('Field wajib diisi:<br><strong>'+missing.join('<br>')+'</strong>','danger');return;}
        const url=editId?baseUrl+'/'+editId:baseUrl, method=editId?'PUT':'POST';
        try{
            const response=await fetch(url,{method,headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json','X-CSRF-TOKEN':csrf},body:new FormData(form)});
            const data=await response.json().catch(()=>({}));
            if(!response.ok){const errors=Object.values(data.errors||{}).flat();showMessage(data.message||errors.join('<br>')||'Gagal menyimpan data.','danger');return;}
            modal.hide();await reloadTable();showMessage(data.message||'Data berhasil disimpan.');
        }catch(error){showMessage('Terjadi kesalahan saat menyimpan data.','danger');}
    });

    document.addEventListener('click',async e=>{
        const edit=e.target.closest('.btn-edit-master');
        if(edit){const row=JSON.parse(edit.dataset.row);resetForm();editId=edit.dataset.id;Object.keys(row).forEach(key=>{const input=form.elements.namedItem(key);if(input)input.value=row[key]??'';});title.textContent='Edit {{ $config['title'] }}';document.getElementById('masterSubmit').textContent='Update';modal.show();return;}
        const del=e.target.closest('.btn-delete-master');if(del){deleteId=del.dataset.id;confirmModal.show();}
    });

    document.getElementById('masterConfirmYes').addEventListener('click',async()=>{
        if(!deleteId)return;confirmModal.hide();
        try{
            const response=await fetch(baseUrl+'/'+deleteId,{method:'DELETE',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json','X-CSRF-TOKEN':csrf}});
            const data=await response.json().catch(()=>({}));
            if(!response.ok){showMessage(data.message||'Data tidak dapat dihapus karena sudah digunakan oleh data lain.','danger');return;}
            await reloadTable();showMessage(data.message||'Data berhasil dihapus.');
        }catch(error){showMessage('Terjadi kesalahan saat menghapus data.','danger');}finally{deleteId=null;}
    });

    document.addEventListener('click',async e=>{const link=e.target.closest('#masterTableContainer .pagination a');if(!link)return;e.preventDefault();await reloadTable(link.href);});
});
</script>
@endsection