@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1">Pemakaian Bahan Baku</h4>
        <div class="text-secondary small">Pemakaian aktual berdasarkan SPK On Progress</div>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Dari</label>
                <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Sampai</label>
                <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Semua</option>
                    @foreach(['draft'=>'Draft','pending'=>'Menunggu Approval','approved'=>'Disetujui','rejected'=>'Ditolak'] as $key => $label)
                        <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary">Tampilkan</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-primary bg-opacity-10 text-primary border-bottom d-flex justify-content-between align-items-center py-3">
        <div class="fw-semibold"><i class="bi bi-clipboard-check me-2"></i>SPK On Progress</div>
        <span class="badge text-bg-primary">{{ $workOrders->count() }} SPK</span>
    </div>
    <div class="card-body">
        <div id="materialUsageControls" class="d-flex justify-content-between align-items-center flex-nowrap gap-3 mb-3">
            <div class="d-flex align-items-center gap-2 flex-nowrap">
                <label for="materialUsagePageLength" class="mb-0 text-nowrap">Tampilkan</label>
                <select id="materialUsagePageLength" class="form-select form-select-sm" style="width:80px;">
                    <option value="15">15</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span class="text-nowrap">baris</span>
            </div>
            <div class="d-flex align-items-center gap-2 flex-nowrap ms-auto">
                <label for="materialUsageSearch" class="mb-0 text-nowrap">Cari:</label>
                <input type="search" id="materialUsageSearch" class="form-control form-control-sm" style="width:240px;" placeholder="Cari...">
            </div>
        </div>
        <div class="table-responsive">
            <table id="materialUsageTable" class="table table-hover align-middle mb-0 w-100">
                <thead class="table-primary">
                    <tr>
                        <th>No. SPK</th>
                        <th>Tanggal</th>
                        <th>Produk</th>
                        <th>Gudang</th>
                        <th class="text-end">Target</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($workOrders as $wo)
                    <tr>
                        <td class="fw-semibold">{{ $wo->wo_no }}</td>
                        <td>{{ \Carbon\Carbon::parse($wo->wo_date)->format('d/m/Y') }}</td>
                        <td>{{ $wo->product_name }}</td>
                        <td>{{ $wo->warehouse_name }}</td>
                        <td class="text-end">{{ number_format((float) $wo->target_output_qty, 2, ',', '.') }}</td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-primary btn-open-material-usage" data-id="{{ $wo->id }}">
                                Pemakaian
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada SPK On Progress yang belum memiliki pemakaian bahan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-primary bg-opacity-10 text-primary border-bottom d-flex justify-content-between align-items-center py-3">
        <div class="fw-semibold"><i class="bi bi-box-arrow-up me-2"></i>Daftar Pemakaian Bahan Baku</div>
        @if($rows->count() > 0)
            <span class="text-secondary small">{{ $rows->count() }} data</span>
        @endif
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-primary">
                    <tr>
                        <th>No. Pengeluaran</th>
                        <th>Tanggal</th>
                        <th>No. SPK</th>
                        <th>Produk</th>
                        <th>Gudang</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    @php
                        $statusLabel = ['draft'=>'Draft','pending'=>'Menunggu Approval','approved'=>'Disetujui','rejected'=>'Ditolak'][$row->status] ?? $row->status;
                        $badge = ['draft'=>'secondary','pending'=>'warning','approved'=>'success','rejected'=>'danger'][$row->status] ?? 'secondary';
                    @endphp
                    <tr>
                        <td class="fw-semibold">{{ $row->usage_no }}</td>
                        <td>{{ \Carbon\Carbon::parse($row->usage_date)->format('d/m/Y') }}</td>
                        <td>{{ $row->wo_no }}</td>
                        <td>{{ $row->product_name }}</td>
                        <td>{{ $row->warehouse_name }}</td>
                        <td><span class="badge text-bg-{{ $badge }}">{{ $statusLabel }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('produksi.pemakaian-bahan.show', $row->id) }}" class="btn btn-sm btn-outline-primary">Lihat</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Belum ada pemakaian bahan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($rows->hasPages())
    <div class="card-footer bg-body border-0 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 py-3">
        <div class="text-secondary small">
            Menampilkan {{ $rows->firstItem() }}–{{ $rows->lastItem() }} dari {{ $rows->total() }} data
        </div>
        <nav aria-label="Navigasi halaman">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item {{ $rows->onFirstPage() ? 'disabled' : '' }}">
                    <a class="page-link" href="{{ $rows->previousPageUrl() ?? '#' }}" aria-label="Sebelumnya">&laquo;</a>
                </li>
                @foreach($rows->getUrlRange(max(1, $rows->currentPage() - 2), min($rows->lastPage(), $rows->currentPage() + 2)) as $page => $url)
                    <li class="page-item {{ $page == $rows->currentPage() ? 'active' : '' }}">
                        <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                    </li>
                @endforeach
                <li class="page-item {{ $rows->currentPage() == $rows->lastPage() ? 'disabled' : '' }}">
                    <a class="page-link" href="{{ $rows->nextPageUrl() ?? '#' }}" aria-label="Berikutnya">&raquo;</a>
                </li>
            </ul>
        </nav>
    </div>
    @endif
</div>

{{-- Modal input pemakaian bahan --}}
<div class="modal fade" id="materialUsageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="materialUsageForm">
                @csrf
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-1">Pemakaian Bahan</h5>
                        <div class="small text-secondary" id="materialUsageWoInfo"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Tanggal Pemakaian</label>
                            <input type="date" name="usage_date" class="form-control" value="{{ now()->toDateString() }}" required>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-2">
                            <thead class="table-primary">
                                <tr><th>Kode</th><th>Material</th><th class="text-end">Rencana</th><th>Satuan</th><th style="width:180px">Aktual Dipakai</th></tr>
                            </thead>
                            <tbody id="materialUsageRows"></tbody>
                        </table>
                    </div>
                    <div class="small text-secondary">Rencana dihitung dari kuantitas BOM × jumlah batch SPK. Periksa jumlah aktual sebelum disimpan.</div>
                    <div class="mt-3">
                        <label class="form-label">Catatan</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Draft</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const workOrders = @json($workOrders->keyBy('id'));
    const modalElement = document.getElementById('materialUsageModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const form = document.getElementById('materialUsageForm');
    const rows = document.getElementById('materialUsageRows');
    const info = document.getElementById('materialUsageWoInfo');
    const storeUrlTemplate = @json(route('produksi.pemakaian-bahan.store', ['workOrderId' => '__WO_ID__']));
    const formatQty = value => Number(value || 0).toLocaleString('id-ID', {maximumFractionDigits: 3});

    document.querySelectorAll('.btn-open-material-usage').forEach(button => {
        button.addEventListener('click', function () {
            const wo = workOrders[this.dataset.id];
            if (!wo) return;
            form.action = storeUrlTemplate.replace('__WO_ID__', wo.id);
            info.textContent = wo.wo_no + ' · ' + wo.product_name + ' · Target ' + formatQty(wo.target_output_qty);
            rows.replaceChildren();

            (wo.materials || []).forEach((item, index) => {
                const tr = document.createElement('tr');
                const values = [item.sku || '-', item.name || '-', formatQty(item.planned_qty), item.unit || '-'];
                values.forEach((value, cellIndex) => {
                    const td = document.createElement('td');
                    td.textContent = value;
                    if (cellIndex === 2) td.className = 'text-end';
                    tr.appendChild(td);
                });
                const actualCell = document.createElement('td');
                const productInput = document.createElement('input');
                productInput.type = 'hidden';
                productInput.name = 'product_id[]';
                productInput.value = item.product_id;
                const actualInput = document.createElement('input');
                actualInput.type = 'number';
                actualInput.name = 'actual_qty[]';
                actualInput.className = 'form-control form-control-sm text-end';
                actualInput.min = '0.001';
                actualInput.step = '0.001';
                actualInput.required = true;
                actualInput.value = item.planned_qty;
                actualCell.append(productInput, actualInput);
                tr.appendChild(actualCell);
                rows.appendChild(tr);
            });

            if (!(wo.materials || []).length) {
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = 5;
                td.className = 'text-center text-muted py-3';
                td.textContent = 'BOM SPK belum memiliki material.';
                tr.appendChild(td);
                rows.appendChild(tr);
            }
            modal.show();
        });
    });
});
</script>
@endpush

@endsection
