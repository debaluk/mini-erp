@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1">Hasil Produksi</h4>
        <div class="text-secondary small">Rekap hasil produksi dan rincian per pekerja.</div>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#manualProductionModal">Input Produksi Manual</button>
        <a href="{{ route('produksi.work-order.hasil-report.export', request()->query()) }}" class="btn btn-success"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Tanggal awal</label>
                <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Tanggal akhir</label>
                <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
            </div>
            <div class="col-md-auto"><button class="btn btn-primary">Tampilkan</button></div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header fw-semibold bg-light">Rekap per Tanggal dan SPK</div>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
                <tr><th>Tanggal</th><th>No. SPK</th><th>Produk</th><th class="text-center">Pekerja</th><th class="text-end">Hasil Bagus</th><th class="text-end">Reject</th><th class="text-end">Biaya Upah Satuan</th><th>Status</th><th class="text-center">Aksi</th></tr>
            </thead>
            <tbody>
            @forelse($results as $result)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($result->production_date)->format('d/m/Y') }}</td>
                    <td class="fw-semibold">{{ $result->wo_no }}</td>
                    <td>{{ $result->product_name }}</td>
                    <td class="text-center">{{ $result->worker_count }}</td>
                    <td class="text-end">{{ \App\Helpers\FormatHelper::indo((float) $result->good_qty, 0) }}</td>
                    <td class="text-end">{{ \App\Helpers\FormatHelper::indo((float) $result->reject_qty, 0) }}</td>
                    <td class="text-end">Rp {{ number_format((float) $result->unit_labor_cost, 2, ',', '.') }}</td>
                    <td><span class="badge text-bg-{{ $result->status === 'posted' ? 'success' : 'warning' }}">{{ $result->status === 'posted' ? 'Sudah Closing' : 'Draft' }}</span></td>
                    <td class="text-center">@if(str_starts_with($result->wo_no, 'MAN-'))<form method="POST" action="{{ route('produksi.manual.destroy', $result->id) }}" class="d-inline" onsubmit="return confirm('Hapus produksi manual ini? Stok barang jadi dikurangi dan bahan baku dikembalikan. Jika sudah masuk voucher, penghapusan ditolak.');">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button></form>@else<span class="text-muted">-</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">Belum ada hasil produksi pada periode ini.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header fw-semibold bg-light">Rincian per Pekerja</div>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
                <tr><th>Tanggal</th><th>Pekerja</th><th>Dasar Upah</th><th class="text-end">Tarif</th><th class="text-end">Bagus</th><th class="text-end">Biaya Bagus</th><th class="text-end">Reject</th><th class="text-end">Biaya Reject</th><th>Status</th></tr>
            </thead>
            <tbody>
            @forelse($lines as $line)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($line->production_date)->format('d/m/Y') }}</td>
                    <td>{{ $line->worker_name }}</td>
                    <td>{{ $line->pay_type === 'satuan' ? 'Satuan' : 'Borongan' }}</td>
                    <td class="text-end">{{ $line->pay_type === 'satuan' ? 'Rp '.number_format((float) $line->unit_rate, 2, ',', '.') : 'Rp '.number_format((float) $line->unit_rate, 2, ',', '.') . ' / SPK' }}</td>
                    <td class="text-end">{{ \App\Helpers\FormatHelper::indo((float) $line->good_qty, 0) }}</td>
                    <td class="text-end">{{ $line->pay_type === 'satuan' ? 'Rp '.number_format((float) $line->good_cost, 2, ',', '.') : '-' }}</td>
                    <td class="text-end">{{ \App\Helpers\FormatHelper::indo((float) $line->reject_qty, 0) }}</td>
                    <td class="text-end">{{ $line->pay_type === 'satuan' ? 'Rp '.number_format((float) $line->reject_cost, 2, ',', '.') : '-' }}</td>
                    <td><span class="badge text-bg-{{ $line->status === 'posted' ? 'success' : 'warning' }}">{{ $line->status === 'posted' ? 'Sudah Closing' : 'Draft' }}</span></td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">Belum ada rincian hasil produksi.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="manualProductionModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><form method="POST" action="{{ route('produksi.manual.store') }}" class="modal-content">@csrf<div class="modal-header"><h5 class="modal-title">Input Produksi Manual</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><label class="form-label">Tanggal</label><input type="date" name="production_date" class="form-control mb-2" value="{{ now()->toDateString() }}" required><label class="form-label">BOM / Produk</label><select id="manualBomId" name="bom_id" class="form-select mb-2" required><option value="">Pilih BOM</option></select><div class="d-none"><label class="form-label">Gudang</label><select id="manualWarehouseId" name="warehouse_id" class="form-select mb-2" required><option value="">Pilih gudang</option></select></div><div class="border rounded p-2 mb-3"><div class="fw-semibold mb-2">Informasi Bahan BOM</div><div class="table-responsive"><table class="table table-sm table-hover align-middle mb-1"><thead class="table-light"><tr><th>Bahan</th><th class="text-end">Qty</th><th>Satuan</th><th class="text-end">Harga Satuan</th><th class="text-end">Estimasi Biaya</th></tr></thead><tbody id="manualBomMaterials"><tr><td colspan="5" class="text-center text-muted">Pilih BOM untuk melihat bahan.</td></tr></tbody><tfoot><tr class="fw-semibold"><td colspan="4" class="text-end">Total Estimasi Bahan</td><td id="manualBomMaterialTotal" class="text-end">Rp 0,00</td></tr></tfoot></table></div><div class="form-text">Qty mengikuti jumlah hasil bagus + reject. Harga satuan menggunakan biaya rata-rata persediaan di gudang BU terkait.</div></div><label class="form-label">Pekerja</label><select id="manualWorkerId" name="worker_id" class="form-select mb-2" required><option value="">Pilih pekerja</option></select><div class="row g-2 mb-2"><div class="col-5"><label class="form-label">Jenis Upah</label><select id="manualPayType" name="pay_type" class="form-select" required><option value="borongan">Borongan</option><option value="satuan">Satuan</option></select></div><div class="col-7"><label id="manualRateLabel" class="form-label">Nilai Upah Borongan (Rp)</label><input type="number" id="manualUnitRate" name="unit_rate" min="0" step="0.01" class="form-control" value="0" required></div></div><div class="row"><div class="col"><label class="form-label">Hasil bagus</label><input type="number" name="good_qty" min="0.001" step="0.001" class="form-control" required></div><div class="col"><label class="form-label">Reject</label><input type="number" name="reject_qty" min="0" step="0.001" value="0" class="form-control"></div></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary">Posting</button></div></form></div></div>
<script>
document.getElementById('manualPayType')?.addEventListener('change',function(){document.getElementById('manualRateLabel').textContent=this.value==='satuan'?'Tarif per Unit (Rp)':'Nilai Upah Borongan (Rp)'});
document.getElementById('manualProductionModal')?.addEventListener('show.bs.modal', async function(){
    const b=document.getElementById('manualBomId');
    const w=document.getElementById('manualWarehouseId');
    const worker=document.getElementById('manualWorkerId');
    if(b.dataset.loaded)return;
    try {
        const r=await fetch(@json(route('produksi.manual.options')),{headers:{Accept:'application/json'}});
        if(!r.ok)throw new Error();
        const d=await r.json();
        d.boms.forEach(x=>b.add(new Option(x.code+' - '+x.product_name,x.id)));
        d.warehouses.forEach(x=>w.add(new Option(x.name,x.id)));
        d.workers.forEach(x=>worker.add(new Option((x.code?x.code+' - ':'')+x.name,x.id)));
        b.dataset.loaded='1';

        const fmtQty=value=>new Intl.NumberFormat('id-ID',{maximumFractionDigits:3}).format(value||0);
        const fmtRp=value=>'Rp '+new Intl.NumberFormat('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2}).format(value||0);
        const good=document.querySelector('[name="good_qty"]');
        const reject=document.querySelector('[name="reject_qty"]');
        const body=document.getElementById('manualBomMaterials');
        const total=document.getElementById('manualBomMaterialTotal');

        const renderMaterials=()=>{
            const selected=d.boms.find(x=>String(x.id)===b.value);
            if(!selected)return;
            const items=d.materials[String(selected.id)]||[];
            const produced=parseFloat(good.value||0)+parseFloat(reject.value||0);
            const factor=produced>0?produced/Math.max(parseFloat(selected.output_qty||0),0.001):1;
            let sum=0;
            if(!items.length){
                body.innerHTML='<tr><td colspan="5" class="text-center text-muted">BOM ini belum memiliki bahan.</td></tr>';
                total.textContent=fmtRp(0);
                return;
            }
            body.innerHTML='';
            items.forEach(item=>{
                const qty=parseFloat(item.qty||0)*factor;
                const unitCost=parseFloat(item.unit_cost||0);
                const lineCost=qty*unitCost;
                sum+=lineCost;
                const tr=document.createElement('tr');
                [item.name,fmtQty(qty),item.unit,fmtRp(unitCost),fmtRp(lineCost)].forEach((value,index)=>{
                    const td=document.createElement('td');
                    td.textContent=value;
                    if(index===1||index>=3)td.className='text-end';
                    tr.appendChild(td);
                });
                body.appendChild(tr);
            });
            total.textContent=fmtRp(sum);
        };
        const syncBom=()=>{
            const selected=d.boms.find(x=>String(x.id)===b.value);
            if(!selected){
                w.value='';
                body.innerHTML='<tr><td colspan="5" class="text-center text-muted">Pilih BOM untuk melihat bahan.</td></tr>';
                total.textContent=fmtRp(0);
                return;
            }
            const matchingWarehouses=d.warehouses.filter(x=>String(x.business_unit_id)===String(selected.business_unit_id));
            w.value=matchingWarehouses.length===1?String(matchingWarehouses[0].id):'';
            if(!w.value){
                body.innerHTML='<tr><td colspan="5" class="text-center text-danger">Gudang untuk BU BOM belum terpetakan secara tunggal.</td></tr>';
                total.textContent=fmtRp(0);
                return;
            }
            renderMaterials();
        };
        b.addEventListener('change',syncBom);
        good.addEventListener('input',renderMaterials);
        reject.addEventListener('input',renderMaterials);
        syncBom();
    } catch(e) {
        alert('Gagal memuat BOM, bahan, dan gudang. Muat ulang halaman lalu coba lagi.');
    }
});
</script>
@endsection
