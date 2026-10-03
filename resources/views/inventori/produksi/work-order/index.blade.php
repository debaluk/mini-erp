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
    <div class="card-body">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-md-2"><label class="form-label small">Dari</label><input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}"></div>
            <div class="col-md-2"><label class="form-label small">Sampai</label><input type="date" name="date_to" class="form-control" value="{{ $dateTo }}"></div>
            <div class="col-md-2">
                <label class="form-label small">Status</label>
                <select name="status" class="form-select">
                    <option value="">Semua</option>
                    @foreach(['open'=>'Open','in_progress'=>'On Progress','completed'=>'Selesai'] as $key => $label)
                        <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-auto"><button class="btn btn-primary">Tampilkan</button> <a href="{{ route('produksi.work-order') }}" class="btn btn-outline-secondary">Reset</a></div>
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
                        $statusLabels=['open'=>'Open','in_progress'=>'On Progress','completed'=>'Selesai'];
                        $statusClasses=['open'=>'primary','in_progress'=>'warning','completed'=>'success'];
                    @endphp
                    <tr>
                        <td class="fw-semibold">{{ $row->wo_no }}</td>
                        <td data-order="{{ $row->wo_date }}">{{ \Carbon\Carbon::parse($row->wo_date)->format('d/m/Y') }}</td>
                        <td>{{ $row->product_name }}</td>
                        <td><span class="badge text-bg-light border">{{ $row->bom_code }}</span></td>
                        <td>{{ $row->warehouse_name }}</td>
                        <td class="text-end">{{ \App\Helpers\FormatHelper::indo($row->target_output_qty, 3) }}</td>
                        <td class="text-center">{{ $workerCounts[$row->id] ?? 0 }}</td>
                        <td><span class="badge text-bg-{{ $statusClasses[$row->status] ?? 'secondary' }}">{{ $statusLabels[$row->status] ?? $row->status }}</span></td>
                        <td class="text-end text-nowrap">
                            @if($row->status === 'open')
                                <a href="{{ route('produksi.work-order.edit', $row->id) }}" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="{{ route('produksi.work-order.destroy', $row->id) }}" class="d-inline" onsubmit="return confirm('Hapus SPK {{ $row->wo_no }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                                </form>
                            @else
                                <a href="{{ route('produksi.work-order.print',$row->id) }}" target="_blank" class="btn btn-sm btn-outline-dark" title="View"><i class="bi bi-eye"></i></a>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<style>
    #workOrderTable_wrapper .dataTables_length,
    #workOrderTable_wrapper .dataTables_filter { white-space: nowrap; }
    #workOrderTable_wrapper .dataTables_filter { display:flex; align-items:center; justify-content:flex-end; gap:.5rem; }
    #workOrderTable_wrapper .dataTables_filter label { margin:0; display:flex; align-items:center; gap:.5rem; }
    #workOrderTable_wrapper .dataTables_filter input { margin:0; width:240px; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery) $('#workOrderTable').DataTable({
        dom: '<"row align-items-center px-3 py-3"<"col-sm-6"l><"col-sm-6 d-flex justify-content-end"f>>t<"row align-items-center px-3 py-3"<"col-sm-5"i><"col-sm-7 d-flex justify-content-end"p>>',
        pageLength: 15,
        lengthMenu: [[15,25,50,100],[15,25,50,100]],
        autoWidth: false,
        order: [[1,'desc']],
        language: { search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ baris', info: 'Menampilkan _START_–_END_ dari _TOTAL_ SPK', infoEmpty: 'Tidak ada SPK', zeroRecords: 'Data tidak ditemukan', paginate: { previous: '‹', next: '›' } },
        columnDefs: [{ targets: [5,6,8], orderable: false }]
    });
});
</script>
@endpush
