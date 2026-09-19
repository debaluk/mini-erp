@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-2">
    <div><h3 class="mb-1">Customer</h3><div class="text-secondary">Master data customer.</div></div>
</div>
<div class="card shadow-sm mb-2">
    <div class="card-body py-3">
        <form id="customerForm" method="POST" action="{{ url('/master/customer') }}" novalidate>
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
document.addEventListener('DOMContentLoaded',()=>{
 const form=document.getElementById('customerForm'),code=document.getElementById('customer-code'),name=document.getElementById('customer-name'),type=document.getElementById('customer-type'),phone=document.getElementById('customer-phone'),address=document.getElementById('customer-address'),submit=document.getElementById('customer-submit'),cancel=document.getElementById('customer-cancel'),alertBox=document.getElementById('customer-alert');
 const baseUrl=@json(url('/master/customer')),csrf=document.querySelector('meta[name="csrf-token"]')?.content||@json(csrf_token());let editId=null;
 const labels={umum:'Umum',proyek:'Proyek',perusahaan:'Perusahaan'};
 const alert=(m,k='success')=>{alertBox.innerHTML='<div class="alert alert-'+k+' py-2 small mb-0">'+m+'</div>';setTimeout(()=>alertBox.innerHTML='',3500)};
 const reset=()=>{form.reset();document.getElementById('customer-active').checked=true;editId=null;code.value='Otomatis';submit.textContent='Simpan';cancel.classList.add('d-none');name.classList.remove('is-invalid');name.focus()};
 const table=new DataTable('#customers-table',{processing:true,serverSide:true,ordering:false,pageLength:15,lengthMenu:[[15,25,50,100],[15,25,50,100]],language:{lengthMenu:'Tampilkan _MENU_ data per halaman',search:'Cari:',info:'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',infoEmpty:'Tidak ada data',infoFiltered:'(disaring dari _MAX_ data)',zeroRecords:'Data tidak ditemukan',emptyTable:'Belum ada data',paginate:{first:'<<',last:'>>',next:'>',previous:'<'},processing:'Memuat...'},ajax:{url:baseUrl,type:'GET',dataSrc:'data'},columns:[
 {data:'code'},{data:'name'},{data:'customer_type',render:v=>labels[v]||v||''},{data:'phone',defaultContent:''},{data:'address',defaultContent:''},
 {data:'is_active',render:v=>Number(v)===1?'<span class="badge text-bg-success">Aktif</span>':'<span class="badge text-bg-secondary">Nonaktif</span>'},
 {data:null,orderable:false,searchable:false,className:'text-end text-nowrap',render:(d,t,r)=>'<button type="button" class="btn btn-outline-primary btn-sm btn-edit-customer" data-id="'+r.id+'">Edit</button> <button type="button" class="btn btn-outline-danger btn-sm btn-delete-customer" data-id="'+r.id+'">Hapus</button>'}
 ]});
 form.addEventListener('submit',async e=>{e.preventDefault();if(!name.value.trim()){name.classList.add('is-invalid');alert('Nama Customer wajib diisi.','danger');name.focus();return}const p=new FormData(form);if(editId)p.append('_method','PUT');try{const r=await fetch(editId?baseUrl+'/'+editId:baseUrl,{method:'POST',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf},body:p}),d=await r.json().catch(()=>({}));if(!r.ok){const es=Object.values(d.errors||{}).flat();alert(d.message||es.join('<br>')||'Gagal menyimpan customer.','danger');return}alert(d.message||'Customer berhasil disimpan.');reset();table.ajax.reload(null,false)}catch(err){console.error(err);alert('Terjadi kesalahan saat menyimpan customer.','danger')}});
 document.addEventListener('click',e=>{const b=e.target.closest('.btn-edit-customer');if(b){const r=table.row(b.closest('tr')).data();if(!r)return;editId=b.dataset.id;code.value=r.code||'';name.value=r.name||'';type.value=r.customer_type||'umum';phone.value=r.phone||'';address.value=r.address||'';document.getElementById('customer-active').checked=Number(r.is_active)===1;document.getElementById('customer-inactive').checked=Number(r.is_active)!==1;submit.textContent='Update';cancel.classList.remove('d-none');name.focus();window.scrollTo({top:0,behavior:'smooth'});return}const d=e.target.closest('.btn-delete-customer');if(d){if(!confirm('Hapus customer ini? Jika sudah digunakan transaksi, customer tidak dapat dihapus. Nonaktifkan jika masih diperlukan untuk histori.'))return;d.disabled=true;fetch(baseUrl+'/'+d.dataset.id,{method:'DELETE',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf}}).then(async r=>{const x=await r.json().catch(()=>({}));if(!r.ok){alert(x.message||'Customer tidak dapat dihapus.','danger');return}alert(x.message||'Customer berhasil dihapus.');if(editId===d.dataset.id)reset();table.ajax.reload(null,false)}).catch(err=>{console.error(err);alert('Terjadi kesalahan saat menghapus customer.','danger')}).finally(()=>d.disabled=false)}}});
 cancel.addEventListener('click',reset);reset();
});
</script>
@endpush

@endsection
