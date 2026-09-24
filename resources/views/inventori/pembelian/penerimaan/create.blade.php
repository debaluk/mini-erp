@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h4 class="mb-1">Penerimaan Barang</h4><div class="text-secondary small">Input barang yang diterima secara fisik di gudang</div></div>
    <a href="{{ route('inventori.penerimaan') }}" class="btn btn-outline-secondary">← Kembali</a>
</div>

<div class="card shadow-sm mb-3">
 <div class="card-header fw-semibold">Informasi Penerimaan</div>
 <div class="card-body">
  <div class="row g-3">
   <div class="col-md-3"><label class="form-label">Tanggal</label><input type="date" class="form-control" value="{{ now()->toDateString() }}" readonly></div>
   <div class="col-md-3"><label class="form-label">Nomor Penerimaan</label><input class="form-control" value="Otomatis" readonly></div>
   <div class="col-md-6">
    <label class="form-label">Cari PO</label>
    <div class="input-group"><input class="form-control" placeholder="Pilih PO (fitur PO akan diaktifkan)" readonly><button type="button" class="btn btn-outline-primary" disabled>🔍 Pilih PO</button></div>
    <div class="form-text">PO menjadi sumber penerimaan; integrasi PO akan diaktifkan pada tahap berikutnya.</div>
   </div>
   <div class="col-md-6"><label class="form-label">Supplier</label><div class="input-group"><input id="supplierName" class="form-control" placeholder="Supplier dari PO" readonly><button type="button" class="btn btn-outline-secondary" disabled>🔍 Cari Supplier</button></div></div>
   <div class="col-md-3"><label class="form-label">Gudang</label><select class="form-select" id="warehouse_id"><option value="">Pilih Gudang</option>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->code }} - {{ $w->name }}</option>@endforeach</select></div>
   <div class="col-md-3"><label class="form-label">No. Surat Jalan</label><input class="form-control" placeholder="Nomor surat jalan"></div>
   <div class="col-md-3"><label class="form-label">Tanggal Surat Jalan</label><input type="date" class="form-control"></div>
  </div>
 </div>
</div>

<div class="card shadow-sm mb-3">
 <div class="card-header d-flex justify-content-between align-items-center"><span class="fw-semibold">Detail Barang</span><button type="button" class="btn btn-sm btn-outline-primary" id="addRow">+ Tambah Item</button></div>
 <div class="table-responsive">
  <table class="table table-bordered align-middle mb-0" id="receiptTable">
   <thead class="table-light"><tr><th style="min-width:260px">Item</th><th>Satuan</th><th class="text-end">Qty PO</th><th class="text-end">Qty Terima</th><th class="text-end">Selisih</th><th style="width:70px">Aksi</th></tr></thead>
   <tbody id="receiptItems"></tbody>
  </table>
 </div>
</div>

<div class="card shadow-sm mb-3">
 <div class="card-header fw-semibold">Catatan</div>
 <div class="card-body"><textarea class="form-control" rows="3" placeholder="Catatan penerimaan..."></textarea></div>
</div>

<div class="d-flex justify-content-end gap-2">
 <a href="{{ route('inventori.penerimaan') }}" class="btn btn-outline-secondary">Batal</a>
 <button type="button" class="btn btn-primary" id="saveReceipt">Simpan Penerimaan</button>
</div>

<div class="modal fade" id="itemModal" tabindex="-1" aria-hidden="true">
 <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Pilih Item</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
   <input id="itemSearch" class="form-control mb-3" placeholder="Cari kode / nama item...">
   <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Kode</th><th>Item</th><th>Satuan</th><th></th></tr></thead><tbody id="itemRows">
   @foreach($products as $p)<tr data-search="{{ strtolower($p->code.' '.$p->name) }}"><td>{{ $p->code }}</td><td>{{ $p->name }}</td><td>{{ $p->unit_code ?? '-' }}</td><td class="text-end"><button type="button" class="btn btn-sm btn-primary choose-item" data-id="{{ $p->id }}" data-name="{{ $p->name }}" data-unit="{{ $p->unit_code ?? '-' }}">Pilih</button></td></tr>@endforeach
   </tbody></table></div>
  </div>
 </div></div>
</div>
@endsection

@push('scripts')
<script>
(() => {
 const modal = new bootstrap.Modal(document.getElementById('itemModal'));
 let activeRow = null;

 function addRow(product={}) {
   const tr=document.createElement('tr');
   tr.innerHTML='<td><div class="input-group"><input class="form-control item-name" value="'+(product.name||'')+'" readonly><button type="button" class="btn btn-outline-primary choose-btn">🔍</button></div><input type="hidden" class="item-id" value="'+(product.id||'')+'"></td>'
     +'<td class="item-unit">'+(product.unit||'-')+'</td>'
     +'<td><input type="number" min="0" step="0.001" class="form-control text-end qty-po" value="0"></td>'
     +'<td><input type="number" min="0" step="0.001" class="form-control text-end qty-received" value="0"></td>'
     +'<td class="text-end selisih">0</td>'
     +'<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row">×</button></td>';
   document.getElementById('receiptItems').appendChild(tr);
   bindRow(tr);
   return tr;
 }
 function bindRow(tr){
   tr.querySelector('.choose-btn').onclick=()=>{activeRow=tr; modal.show();};
   tr.querySelector('.remove-row').onclick=()=>tr.remove();
   tr.querySelectorAll('.qty-po,.qty-received').forEach(el=>el.addEventListener('input',()=>{
     const po=Number(tr.querySelector('.qty-po').value||0), rec=Number(tr.querySelector('.qty-received').value||0);
     tr.querySelector('.selisih').textContent=(rec-po).toLocaleString('id-ID',{maximumFractionDigits:3});
   }));
 }
 document.getElementById('addRow').onclick=()=>addRow();
 document.querySelectorAll('.choose-item').forEach(btn=>btn.onclick=()=>{
   if(!activeRow) return;
   activeRow.querySelector('.item-id').value=btn.dataset.id;
   activeRow.querySelector('.item-name').value=btn.dataset.name;
   activeRow.querySelector('.item-unit').textContent=btn.dataset.unit;
   modal.hide();
 });
 document.getElementById('itemSearch').addEventListener('input',e=>{
   const q=e.target.value.toLowerCase();
   document.querySelectorAll('#itemRows tr').forEach(tr=>tr.style.display=tr.dataset.search.includes(q)?'':'none');
 });
 document.getElementById('saveReceipt').onclick=()=>alert('UI Penerimaan Barang sudah siap. Penyimpanan dan posting stok akan diaktifkan setelah schema Penerimaan dikunci.');
 addRow();
})();
</script>
@endpush
