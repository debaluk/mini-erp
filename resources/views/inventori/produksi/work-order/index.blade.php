@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1">Work Order / SPK</h4>
        <div class="text-secondary small">Perintah produksi berdasarkan BOM dan penetapan biaya produksi.</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('produksi.work-order.export', request()->query()) }}" class="btn btn-outline-success">
            <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
        </a>
        <a href="{{ route('produksi.work-order.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Buat SPK</a>
    </div>
</div>

@if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
@if($errors->any())
<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-primary bg-opacity-10 text-primary border-bottom py-3">
        <div class="fw-semibold"><i class="bi bi-funnel me-2"></i>Filter Work Order / SPK</div>
    </div>
    <div class="card-body">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-md-2"><label class="form-label small">Dari</label><input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}"></div>
            <div class="col-md-2"><label class="form-label small">Sampai</label><input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}"></div>
            <div class="col-md-2">
                <label class="form-label small">Status</label>
                <select name="status" class="form-select">
                    <option value="">Semua</option>
                    @foreach(['draft'=>'Draft','open'=>'Open','in_progress'=>'Proses','completed'=>'Selesai','cancelled'=>'Batal'] as $key => $label)
                        <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-auto"><button class="btn btn-outline-primary">Terapkan</button> <a href="{{ route('produksi.work-order') }}" class="btn btn-outline-secondary">Reset</a></div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-primary bg-opacity-10 text-primary border-bottom d-flex justify-content-between align-items-center py-3">
        <div class="fw-semibold"><i class="bi bi-clipboard-check me-2"></i>Daftar Work Order / SPK</div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="workOrderTable" class="table table-hover align-middle mb-0 w-100">
                <thead class="table-primary">
                    <tr>
                        <th>No. SPK</th><th>Tanggal</th><th>Produk</th><th>BOM</th><th>Gudang</th>
                        <th class="text-end">Target</th><th class="text-center">Pekerja</th><th>Status</th><th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($rows as $row)
                    @php
                        $statusLabels=['draft'=>'Draft','open'=>'Open','in_progress'=>'Proses','completed'=>'Selesai','cancelled'=>'Batal'];
                        $statusClasses=['draft'=>'secondary','open'=>'primary','in_progress'=>'warning','completed'=>'success','cancelled'=>'danger'];
                    @endphp
                    <tr>
                        <td class="fw-semibold">{{ $row->wo_no }}</td>
                        <td data-order="{{ $row->wo_date }}">{{ CarbonCarbon::parse($row->wo_date)->format('d/m/Y') }}</td>
                        <td>{{ $row->product_name }}</td>
                        <td><span class="badge text-bg-light border">{{ $row->bom_code }}</span></td>
                        <td>{{ $row->warehouse_name }}</td>
                        <td class="text-end">{{ number_format($row->target_output_qty,3,',','.') }}</td>
                        <td class="text-center">{{ $workerCounts[$row->id] ?? 0 }}</td>
                        <td><span class="badge text-bg-{{ $statusClasses[$row->status] ?? 'secondary' }}">{{ $statusLabels[$row->status] ?? $row->status }}</span></td>
                        <td class="text-end"><a href="{{ route('produksi.work-order.print',$row->id) }}" target="_blank" class="btn btn-sm btn-outline-dark" title="Cetak"><i class="bi bi-printer"></i></a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="createWoModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
<div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
<form method="POST" action="{{ route('produksi.work-order.store') }}">
@csrf
<input type="hidden" name="batch_qty" value="1">
<div class="modal-header bg-primary bg-opacity-10">
    <div><h5 class="modal-title text-primary"><i class="bi bi-clipboard-plus me-2"></i>Buat Work Order / SPK</h5><div class="small text-secondary">Pilih BOM, cek target, lalu isi biaya produksi.</div></div>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <div class="card border-0 bg-light mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><label class="form-label">Tanggal SPK</label><input type="date" name="wo_date" class="form-control" value="{{ old('wo_date',date('Y-m-d')) }}" required></div>
                <div class="col-md-4"><label class="form-label">Business Unit</label>
                    <select name="business_unit_id" id="wo_bu" class="form-select" required><option value="">Pilih BU</option>
                        @foreach($businessUnits as $bu)<option value="{{ $bu->id }}" @selected(old('business_unit_id') == $bu->id)>{{ $bu->code }} — {{ $bu->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-5"><label class="form-label">Gudang Produksi</label>
                    <select name="warehouse_id" id="wo_warehouse" class="form-select" required><option value="">Pilih gudang</option>
                        @foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" data-bu="{{ $warehouse->business_unit_id }}">{{ $warehouse->code }} — {{ $warehouse->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-12"><label class="form-label">BOM / Formula</label>
                    <select name="bom_id" id="wo_bom" class="form-select" required><option value="">Pilih BOM</option>
                        @foreach($boms as $bom)<option value="{{ $bom->id }}" data-bu="{{ $bom->business_unit_id }}">{{ $bom->code }} — {{ $bom->name }} ({{ $bom->product_name }})</option>@endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div id="bom-info" class="card border-primary mb-4 d-none">
        <div class="card-header bg-primary bg-opacity-10 text-primary fw-semibold"><i class="bi bi-info-circle me-2"></i>Informasi BOM</div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-3"><div class="small text-secondary">Produk</div><div class="fw-semibold" id="bom-product">-</div></div>
                <div class="col-md-3"><div class="small text-secondary">Kode BOM</div><div class="fw-semibold" id="bom-code">-</div></div>
                <div class="col-md-3"><div class="small text-secondary">Target Produksi</div><div class="fw-bold text-primary fs-5" id="bom-target">0</div></div>
                <div class="col-md-3"><div class="small text-secondary">Estimasi Material</div><div class="fw-bold text-success fs-5" id="material-total">Rp 0</div></div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead><tr><th>Material</th><th class="text-end">Qty</th><th>Satuan</th><th class="text-end">Harga</th><th class="text-end">Subtotal</th></tr></thead>
                    <tbody id="bom-material-rows"><tr><td colspan="5" class="text-center text-secondary">Pilih BOM.</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-primary bg-opacity-10 text-primary d-flex justify-content-between align-items-center">
            <div><div class="fw-semibold"><i class="bi bi-person-workspace me-2"></i>Tenaga</div><div class="small text-secondary">Pilih pekerja dan isi biaya tenaga.</div></div>
            <button type="button" class="btn btn-outline-primary btn-sm" id="add-worker"><i class="bi bi-plus-lg me-1"></i>Tambah</button>
        </div>
        <div class="card-body" id="worker-rows">
            <div class="row g-2 mb-2 worker-row">
                <div class="col-md-7"><select name="worker_id[]" class="form-select worker-select" required><option value="">Pilih pekerja</option>
                    @foreach($workers as $worker)<option value="{{ $worker->id }}">{{ $worker->code }} — {{ $worker->name }}</option>@endforeach
                </select></div>
                <div class="col-md-3"><input type="text" name="worker_amount[]" class="form-control cost-input text-end" inputmode="decimal" placeholder="Biaya" required></div>
                <div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-worker"><i class="bi bi-trash"></i></button></div>
            </div>
        </div>
    </div>

    @foreach(['A'=>['Alat','Equipment'],'S'=>['Sewa','Rent'],'O'=>['Overhead','Overhead']] as $group => [$title,$label])
    <div class="card border-0 shadow-sm mb-3 cost-section" data-group="{{ $group }}">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <div class="fw-semibold">{{ $title }} <span class="text-secondary fw-normal">({{ $label }})</span></div>
            <button type="button" class="btn btn-outline-secondary btn-sm add-cost"><i class="bi bi-plus-lg me-1"></i>Tambah</button>
        </div>
        <div class="card-body cost-rows">
            <div class="row g-2 mb-2 cost-row">
                <div class="col-md-7"><input type="text" name="cost_description[]" class="form-control" placeholder="Nama {{ strtolower($title) }}"></div>
                <div class="col-md-3"><input type="text" name="cost_amount[]" class="form-control cost-input text-end" inputmode="decimal" placeholder="Biaya"></div>
                <div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-cost"><i class="bi bi-trash"></i></button></div>
                <input type="hidden" name="cost_group[]" value="{{ $group }}">
            </div>
        </div>
    </div>
    @endforeach

    <div class="card border-success">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div><div class="small text-secondary">TOTAL BIAYA WO/SPK</div><div class="small text-secondary">Material + Tenaga + Equipment + Rent + Overhead</div></div>
                <div class="fw-bold text-success fs-4" id="wo-total">Rp 0</div>
            </div>
        </div>
    </div>

    <div class="mt-3"><label class="form-label">Catatan SPK</label><textarea name="notes" class="form-control" rows="3" placeholder="Instruksi atau catatan produksi...">{{ old('notes') }}</textarea></div>
</div>
<div class="modal-footer bg-light"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Simpan & Terbitkan SPK</button></div>
</form>
</div></div></div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
@endpush

@push('scripts')
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery) $('#workOrderTable').DataTable({
        pageLength: 15,
        lengthMenu: [[15,25,50,100],[15,25,50,100]],
        order: [[1,'desc']],
        language: { search: 'Cari:', lengthMenu: 'Tampil _MENU_', info: 'Menampilkan _START_–_END_ dari _TOTAL_ SPK', infoEmpty: 'Tidak ada SPK', zeroRecords: 'Data tidak ditemukan', paginate: { previous: '‹', next: '›' } },
        columnDefs: [{ targets: [5,6,8], orderable: false }]
    });
});
</script>
@endpush
