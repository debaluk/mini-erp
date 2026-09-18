@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-1">Konversi Satuan</h3>
        <div class="text-secondary">Konversi satuan transaksi per produk</div>
    </div>
    <button class="btn btn-primary" type="button" onclick="openConversionModal()">+ Tambah</button>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" id="conversionDataTable" style="width:100%">
                <thead><tr>
                    <th>Produk</th><th>Dasar</th><th>Satuan</th><th>Faktor</th><th>Default</th><th class="text-end">Aksi</th>
                </tr></thead>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="conversionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="conversionForm">
                @csrf
                <input type="hidden" id="conversionId">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="conversionModalTitle">Tambah Konversi Satuan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label">Produk</label>
                        <select name="product_id" id="product_id" class="form-select" required>
                            <option value="">Pilih produk</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Satuan</label>
                        <select name="unit_id" id="unit_id" class="form-select" required>
                            <option value="">Pilih satuan</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->code }} - {{ $unit->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Faktor ke satuan dasar</label>
                        <input name="conversion_factor" id="conversion_factor" type="number" step="0.000001" min="0.000001" class="form-control" value="1" required>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_default" id="is_default" value="1">
                        <label class="form-check-label" for="is_default">Jadikan satuan dasar produk</label>
                    </div>
                    <div class="small text-secondary mt-2">Contoh: 1 SAK Semen = 50 KG, maka faktor = 50.</div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-primary" type="submit">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="messageModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content">
        <div class="modal-header py-2"><h5 class="modal-title">Informasi</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" id="messageText"></div>
        <div class="modal-footer py-2"><button class="btn btn-primary" data-bs-dismiss="modal">OK</button></div>
    </div></div>
</div>

<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content">
        <div class="modal-header py-2"><h5 class="modal-title">Konfirmasi</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">Hapus konversi satuan ini?</div>
        <div class="modal-footer py-2"><button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-danger" id="confirmDelete">Hapus</button></div>
    </div></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = new DataTable('#conversionDataTable', {
        processing: true,
        serverSide: true,
        ajax: '{{ route('master.unit-conversions') }}',
        pageLength: 15,
        lengthMenu: [[15,25,50,100],[15,25,50,100]],
        order: [[0,'asc']],
        columns: [
            {data:'product_name'},
            {data:'default_unit_code', render:d => d || '-'},
            {data:'unit_code', render:(d,t,r) => d + ' - ' + r.unit_name},
            {data:'conversion_factor', render:d => {
                const n=Number(d);
                return Number.isInteger(n) ? n : n.toFixed(6).replace(/0+$/,'').replace(/.$/,'');
            }},
            {data:'is_default', render:d => d ? '<span class="badge text-bg-primary">Ya</span>' : 'Tidak'},
            {data:null, orderable:false, searchable:false, className:'text-end', render:(d,t,r) =>
                '<button type="button" class="btn btn-outline-secondary btn-sm me-1" onclick="editConversion('+r.id+', '+r.product_id+', '+r.unit_id+', '+r.conversion_factor+', '+(r.is_default?1:0)+')">Edit</button>'+
                '<button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteConversion('+r.id+')">Hapus</button>'
            }
        ],
        language: {
            lengthMenu: 'Tampilkan _MENU_ data per halaman',
            search: 'Cari:',
            info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            infoFiltered: '(disaring dari _MAX_ data)',
            zeroRecords: 'Data tidak ditemukan',
            emptyTable: 'Belum ada data',
            paginate: { first:'<<', last:'>>', next:'>', previous:'<' },
            processing: 'Memuat...'
        }
    });

    window.openConversionModal = function () {
        document.getElementById('conversionForm').reset();
        document.getElementById('conversionId').value='';
        document.getElementById('conversionModalTitle').textContent='Tambah Konversi Satuan';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('conversionModal')).show();
    };

    window.editConversion = function(id, productId, unitId, factor, isDefault) {
        document.getElementById('conversionForm').reset();
        document.getElementById('conversionId').value=id;
        document.getElementById('product_id').value=productId;
        document.getElementById('unit_id').value=unitId;
        document.getElementById('conversion_factor').value=factor;
        document.getElementById('is_default').checked=!!isDefault;
        document.getElementById('conversionModalTitle').textContent='Edit Konversi Satuan';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('conversionModal')).show();
    };

    document.getElementById('conversionForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const id=document.getElementById('conversionId').value;
        const url=id ? '{{ url('/master/unit-conversions') }}/'+id : '{{ route('master.unit-conversions.store') }}';
        const form=new FormData(this);
        if(id) form.append('_method','PUT');
        try {
            const res=await fetch(url,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},body:form});
            const json=await res.json();
            if(!res.ok) throw new Error(json.message || Object.values(json.errors||{}).flat().join('\n') || 'Gagal menyimpan data.');
            bootstrap.Modal.getInstance(document.getElementById('conversionModal')).hide();
            table.ajax.reload(null,false);
            showMessage(json.message);
        } catch(err) { showMessage(err.message); }
    });

    let deleteId=null;
    window.deleteConversion=function(id){
        deleteId=id;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmModal')).show();
    };
    document.getElementById('confirmDelete').addEventListener('click', async function(){
        if(!deleteId)return;
        try{
            const res=await fetch('{{ url('/master/unit-conversions') }}/'+deleteId,{method:'DELETE',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','X-Requested-With':'XMLHttpRequest','Accept':'application/json'}});
            const json=await res.json();
            if(!res.ok) throw new Error(json.message||'Gagal menghapus data.');
            bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
            table.ajax.reload(null,false);
            showMessage(json.message);
        }catch(err){showMessage(err.message);}
    });

    window.showMessage=function(message){
        document.getElementById('messageText').textContent=message;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('messageModal')).show();
    };
});
</script>
@endsection