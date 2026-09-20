@extends('layouts.app')
@section('content')
@php($isEdit = isset($purchase))
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h4 class="mb-1">{{ $isEdit ? 'Edit Pembelian' : 'Tambah Pembelian' }}</h4><div class="text-secondary small">{{ $isEdit ? 'Perbarui dokumen transaksi pembelian' : 'Buat dokumen transaksi pembelian' }}</div></div>
    <a href="{{ route('inventori.pembelian') }}" class="btn btn-outline-secondary">← Kembali ke Pembelian</a>
</div>

<div class="card shadow-sm">
<div class="card-body">
<div class="row g-3">
    <div class="col-md-6"><label class="form-label">Tanggal</label><input type="date" class="form-control" value="{{ isset($purchase) ? \Carbon\Carbon::parse($purchase->purchase_date)->toDateString() : now()->toDateString() }}" readonly></div>
    <div class="col-md-6"><label class="form-label">Nomor Pembelian</label><input class="form-control" value="{{ $purchase->purchase_no ?? 'Otomatis' }}" readonly></div>

    <div class="col-12">
        <label class="form-label">Cari PO</label>
        <div class="input-group">
            <input id="poSearch" class="form-control" placeholder="Pilih PO..." readonly>
            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#poModal">🔍</button>
        </div>
        <div class="form-text">PO belum diimplementasikan. Field disiapkan untuk integrasi berikutnya.</div>
    </div>

    <div class="col-md-6">
        <label class="form-label">Supplier</label>
        <div class="input-group">
            <input id="supplierSearch" class="form-control" value="{{ $purchase->supplier_name ?? '' }}" placeholder="Cari supplier..." readonly>
            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#supplierModal">🔍</button>
        </div>
    </div>
    <div class="col-md-6"><label class="form-label">Pilih Unit</label><select id="unitSelect" class="form-select" required><option value="">Pilih Unit</option>@foreach($units as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>

    <div class="col-md-6"><label class="form-label">No. Faktur Supplier</label><input class="form-control" placeholder="Nomor faktur supplier"></div>
    <div class="col-md-6"><label class="form-label">Tanggal Faktur</label><input type="date" class="form-control"></div>
</div>

<hr class="my-4">
<div class="d-flex justify-content-between align-items-center mb-2"><h6 class="mb-0">Detail Pembelian</h6><button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#itemModal">+ Tambah Item</button></div>
<div class="table-responsive"><table class="table align-middle"><thead class="table-light"><tr><th>Item</th><th>Satuan</th><th class="text-end">Qty</th><th class="text-end">Harga Beli</th><th class="text-end">Diskon</th><th class="text-end">Subtotal</th><th></th></tr></thead>
<tbody id="detailBody">
@forelse(($items ?? []) as $i=>$item)
<tr><td><b>{{ $item->code ?? $item->sku }}</b><div class="small text-secondary">{{ $item->name }}</div></td><td>{{ $item->unit_code ?? '-' }}</td><td class="text-end">{{ number_format((float)$item->qty,3,',','.') }}</td><td class="text-end">Rp {{ number_format((float)$item->unit_cost,0,',','.') }}</td><td class="text-end">Rp 0</td><td class="text-end">Rp {{ number_format((float)$item->total,0,',','.') }}</td><td></td></tr>
@empty
<tr id="emptyRow"><td colspan="7" class="text-center text-secondary py-4">Belum ada item.</td></tr>
@endforelse
</tbody></table></div>

<hr class="my-4">
<div class="row g-4">
    <div class="col-md-6"><h6>Informasi Supplier</h6><div class="small text-secondary">Supplier</div><div id="supplierInfo" class="fw-semibold mb-3">{{ $purchase->supplier_name ?? '-' }}</div><div class="small text-secondary">Hutang Sebelumnya</div><div class="fw-semibold mb-3">Rp 0</div><label class="form-label">Memo</label><textarea id="memo" class="form-control" rows="3" placeholder="Catatan transaksi..."></textarea></div>
    <div class="col-md-6"><h6>Informasi Transaksi</h6><div class="d-flex justify-content-between py-1"><span>Subtotal</span><strong id="subtotalAmount">Rp 0</strong></div><div class="d-flex justify-content-between align-items-center py-1"><span>Diskon (Rp)</span><input id="discountInput" type="number" min="0" class="form-control text-end" style="max-width:160px" value="{{ $purchase->discount ?? 0 }}"></div><div class="d-flex justify-content-between border-top mt-2 pt-2 fs-5"><strong>TOTAL</strong><strong id="totalAmount">Rp 0</strong></div>
    <div class="mt-3"><label class="form-label">Cara Bayar</label><select id="paymentMethod" class="form-select"><option>Tunai</option><option>Transfer</option><option>QRIS</option><option>Kredit / Bon</option></select></div>
    <div id="dueDateWrap" class="mt-3 d-none"><label class="form-label">Jatuh Tempo</label><input id="dueDate" type="date" class="form-control"></div></div>
</div>
</div>
<div class="card-footer d-flex justify-content-end gap-2"><a href="{{ route('inventori.pembelian') }}" class="btn btn-outline-secondary">Batal</a><button type="button" id="savePurchase" class="btn btn-primary">{{ $isEdit ? 'Update Pembelian' : 'Simpan Pembelian' }}</button></div>
</div>

<div class="modal fade" id="poModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h6 class="modal-title">Pilih PO</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="alert alert-info mb-0">PO belum diimplementasikan. Pilihan PO akan diaktifkan setelah modul PO tersedia.</div></div></div></div></div>
<div class="modal fade" id="supplierModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h6 class="modal-title">Cari Supplier</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input id="supplierFilter" class="form-control mb-3" placeholder="Ketik nama supplier..."><div class="list-group">@foreach($suppliers as $s)<button type="button" class="list-group-item list-group-item-action supplier-choice" data-id="{{ $s->id }}" data-name="{{ $s->name }}" data-phone="{{ $s->phone ?? '' }}">{{ $s->name }}<div class="small text-secondary">{{ $s->code }} @if($s->phone) · {{ $s->phone }} @endif</div></button>@endforeach</div></div></div></div></div>
<div class="modal fade" id="itemModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h6 class="modal-title">Cari Item</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input id="itemFilter" class="form-control mb-3" placeholder="Ketik kode / nama item..."><div class="list-group">@foreach($products as $p)<button type="button" class="list-group-item list-group-item-action item-choice" data-id="{{ $p->id }}" data-code="{{ $p->code ?? $p->sku }}" data-name="{{ $p->name }}" data-unit="{{ $p->unit_code ?? '-' }}" data-cost="{{ $p->cost_price ?? 0 }}">{{ $p->code ?? $p->sku }} — {{ $p->name }}</button>@endforeach</div></div></div></div></div>
@endsection
@push('scripts')
<script>
(function(){
    let selectedSupplier = null;
    let rows = [];

    document.querySelectorAll('.supplier-choice').forEach(b=>b.addEventListener('click',()=>{
        selectedSupplier={id:b.dataset.id,name:b.dataset.name};
        document.getElementById('supplierSearch').value=b.dataset.name;
        document.getElementById('supplierInfo').textContent=b.dataset.name;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('supplierModal')).hide();
    }));
    document.getElementById('supplierFilter').addEventListener('input',e=>{
        const q=e.target.value.toLowerCase();
        document.querySelectorAll('.supplier-choice').forEach(b=>b.classList.toggle('d-none',!b.textContent.toLowerCase().includes(q)));
    });
    document.getElementById('itemFilter').addEventListener('input',e=>{
        const q=e.target.value.toLowerCase();
        document.querySelectorAll('.item-choice').forEach(b=>b.classList.toggle('d-none',!b.textContent.toLowerCase().includes(q)));
    });
    document.querySelectorAll('.item-choice').forEach(b=>b.addEventListener('click',()=>{
        const body=document.getElementById('detailBody');
        document.getElementById('emptyRow')?.remove();
        const tr=document.createElement('tr');
        const cost=Number(b.dataset.cost||0);
        tr.innerHTML='<td><b>'+b.dataset.code+'</b><div class="small text-secondary">'+b.dataset.name+'</div></td><td>'+b.dataset.unit+'</td><td class="text-end"><input class="form-control form-control-sm text-end qty" type="number" min="0.001" step="0.001" value="1"></td><td class="text-end"><input class="form-control form-control-sm text-end cost" type="number" min="0" step="0.01" value="'+cost+'"></td><td class="text-end"><input class="form-control form-control-sm text-end line-discount" type="number" min="0" step="0.01" value="0"></td><td class="text-end line-total">Rp 0</td><td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger remove">×</button></td>';
        body.appendChild(tr);
        tr.querySelectorAll('input').forEach(i=>i.addEventListener('input',calc));
        tr.querySelector('.remove').addEventListener('click',()=>{tr.remove();calc();});
        calc();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('itemModal')).hide();
    }));
    document.getElementById('discountInput').addEventListener('input',calc);
    document.getElementById('paymentMethod').addEventListener('change',e=>document.getElementById('dueDateWrap').classList.toggle('d-none',e.target.value!=='Kredit / Bon'));

    function rupiah(n){return 'Rp '+Number(n||0).toLocaleString('id-ID');}
    function calc(){
        let subtotal=0;
        document.querySelectorAll('#detailBody tr').forEach(tr=>{
            const q=Number(tr.querySelector('.qty')?.value||0), c=Number(tr.querySelector('.cost')?.value||0), d=Number(tr.querySelector('.line-discount')?.value||0);
            const total=Math.max(q*c-d,0); subtotal+=total;
            const cell=tr.querySelector('.line-total'); if(cell) cell.textContent=rupiah(total);
        });
        const discount=Math.min(Math.max(Number(document.getElementById('discountInput').value||0),0),subtotal);
        document.getElementById('subtotalAmount').textContent=rupiah(subtotal);
        document.getElementById('totalAmount').textContent=rupiah(subtotal-discount);
    }
    document.getElementById('savePurchase').addEventListener('click',()=>alert('{{ $isEdit ? 'Update' : 'Simpan' }} Pembelian akan diaktifkan setelah alur Hutang/Penerimaan dikunci. UI sudah siap.'));
})();
</script>
@endpush
