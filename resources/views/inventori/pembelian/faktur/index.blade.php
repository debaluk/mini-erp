@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3">

    <!-- Header & Actions -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1 fw-bold text-dark"><i class="bi bi-receipt me-2 text-primary"></i> Faktur Pembelian</h3>
            <div class="text-secondary small">Pencatatan Tagihan Supplier, Pengakuan Hutang Usaha</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('inventori.pembelian.create') }}" class="btn btn-primary btn-sm px-3 fw-semibold shadow-sm">
                <i class="bi bi-plus-circle me-1"></i> + Buat Faktur Pembelian
            </a>
            <a href="{{ route('inventori.pembelian.export-excel', request()->all()) }}" class="btn btn-success btn-sm px-3 fw-semibold shadow-sm">
                <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
            </a>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card shadow-sm border-0 mb-2">
        <div class="card-body p-3">
            <form id="form-filter" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label mb-1 small fw-bold">Mulai Tanggal</label>
                    <input type="date" id="filter-start-date" class="form-control form-control-sm" value="{{ $startDate }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1 small fw-bold">Sampai Tanggal</label>
                    <input type="date" id="filter-end-date" class="form-control form-control-sm" value="{{ $endDate }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1 small fw-bold">Unit Bisnis</label>
                    <select id="filter-bu" class="form-select form-select-sm">
                        <option value="">Semua Unit Bisnis</option>
                       @foreach($businessUnits as $bu)
                                    <option value="{{ $bu->id }}">{{ $bu->code }} - {{ $bu->name }}</option>
                                @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1 small fw-bold">Supplier</label>
                    <select id="filter-supplier" class="form-select form-select-sm">
                        <option value="">Semua Supplier</option>
                        @foreach($suppliers as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1 small fw-bold">Cara Bayar</label>
                    <select id="filter-payment" class="form-select form-select-sm">
                        <option value="">Semua Cara Bayar</option>
                        <option value="cash">Tunai / Cash</option>
                        <option value="credit">Kredit (Tempo)</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="button" id="btn-apply-filter" class="btn btn-primary btn-sm w-100"><i class="bi bi-search me-1"></i> Filter</button>
                    <button type="button" id="btn-reset-filter" class="btn btn-outline-secondary btn-sm">Reset</button>
                </div>
            </form>
        </div>
    </div>

    <!-- DataTables Table -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="table-pembelian" class="table table-hover table-striped align-middle w-100" style="font-size: 0.88rem;">
                    <thead class="table-dark text-center">
                        <tr>
                            <th style="width: 140px;">No. Bukti</th>
                            <th style="width: 85px;">Tanggal</th>
                            <th>Supplier</th>
                            <th style="width: 140px;">Faktur Supplier</th>
                            <th style="width: 110px;">Cara Bayar</th>
                            <th style="width: 90px;">Stok</th>
                            <th style="width: 130px;">Jumlah</th>
                            <th style="width: 90px;">Status</th>
                            <th style="width: 130px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    @if(session('swal_success'))
        Swal.fire({ icon: 'success', title: 'Berhasil!', text: "{{ session('swal_success') }}", timer: 2500, showConfirmButton: false });
    @endif
    @if(session('swal_error'))
        Swal.fire({ icon: 'error', title: 'Gagal!', text: "{{ session('swal_error') }}", confirmButtonColor: '#d33' });
    @endif

    const table = $('#table-pembelian').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: "{{ route('inventori.pembelian.data') }}",
            data: function (d) {
                d.start_date       = $('#filter-start-date').val();
                d.end_date         = $('#filter-end-date').val();
                d.business_unit_id = $('#filter-bu').val();
                d.supplier_id      = $('#filter-supplier').val();
                d.payment_type     = $('#filter-payment').val();
            }
        },
        columns: [
            { data: 'invoice_no', className: 'text-center font-monospace fw-bold text-primary' },
            { data: 'formatted_date', className: 'text-center' },
            { data: 'supplier_name', className: 'fw-semibold text-dark' },
            { data: 'supplier_invoice_no', className: 'text-center font-monospace' },
            {
                data: 'payment_type',
                className: 'text-center',
                render: function (data, type, row) {
                    return data === 'cash'
                        ? '<span class="badge bg-success">CASH</span>'
                        : `<span class="badge bg-warning text-dark">KREDIT</span><div class="small text-muted font-monospace">${row.formatted_due}</div>`;
                }
            },
            {
                data: 'goods_received',
                className: 'text-center',
                render: function (data) {
                    return data == 1
                        ? '<span class="badge bg-info text-dark"><i class="bi bi-box-seam me-1"></i> YA</span>'
                        : '<span class="badge bg-secondary">TIDAK</span>';
                }
            },
            { data: 'formatted_grand', className: 'text-end font-monospace fw-bold' },
            {
                data: 'status',
                className: 'text-center',
                render: function (data) {
                    return data === 'draft'
                        ? '<span class="badge bg-secondary">DRAFT</span>'
                        : '<span class="badge bg-success"><i class="bi bi-check-all me-1"></i> POSTED</span>';
                }
            },
            {
                data: null,
                className: 'text-center',
                orderable: false,
                render: function (data, type, row) {
                    let actions = `
                        <div class="btn-group btn-group-sm">
                            <a href="{{ url('/inventori/pembelian') }}/${row.id}" class="btn btn-outline-info" title="Detail"><i class="bi bi-eye"></i></a>
                            <a href="{{ url('/inventori/pembelian') }}/${row.id}/print-invoice" target="_blank" class="btn btn-outline-secondary" title="Cetak Dotmatrix"><i class="bi bi-printer"></i></a>
                    `;

                    if (row.status === 'draft') {
                        actions += `
                            <a href="{{ url('/inventori/pembelian') }}/${row.id}/edit" class="btn btn-outline-warning" title="Edit Draft"><i class="bi bi-pencil"></i></a>
                            <button type="button" class="btn btn-success btn-post-item" data-id="${row.id}" data-no="${row.invoice_no}" data-goods-received="${row.goods_received}" title="Post"><i class="bi bi-check-circle"></i></button>
                            <button type="button" class="btn btn-outline-danger btn-delete-item" data-id="${row.id}" data-no="${row.invoice_no}" title="Hapus"><i class="bi bi-trash"></i></button>
                        `;
                    }

                    actions += `</div>`;
                    return actions;
                }
            }
        ]
    });

     $('#btn-apply-filter').on('click', function () { table.ajax.reload(); });
     $('#btn-reset-filter').on('click', function () {
         $('#form-filter').reset();
        table.ajax.reload();
    });

     $(document).on('click', '.btn-post-item', function () {
        const id = $(this).data('id');
        const no = $(this).data('no');
        const goodsReceived = Number($(this).data('goods-received')) === 1;

        let warehouseField = '';
        if (goodsReceived) {
            warehouseField = `
                <select id="post-warehouse-id" class="form-select text-start">
                    <option value="">-- Pilih Gudang --</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                    @endforeach
                </select>`;
        }

        Swal.fire({
            title: 'Posting Faktur Pembelian?',
            html: goodsReceived
                ? `Posting [<strong>${no}</strong>] akan menerima barang ke gudang.<br><div class="mt-3">${warehouseField}</div>`
                : `Posting [<strong>${no}</strong>] akan menerbitkan Jurnal Keuangan GL.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            confirmButtonText: 'Ya, Post Sekarang!',
            cancelButtonText: 'Batal',
            preConfirm: () => {
                if (goodsReceived) {
                    const warehouseId = document.getElementById('post-warehouse-id')?.value;
                    if (!warehouseId) {
                        Swal.showValidationMessage('Gudang wajib dipilih.');
                        return false;
                    }
                    return warehouseId;
                }
                return null;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `{{ url('/inventori/pembelian') }}/${id}/post`;
                form.innerHTML = `@csrf`;
                if (result.value) {
                    form.insertAdjacentHTML('beforeend', `<input type="hidden" name="warehouse_id" value="${result.value}">`);
                }
                document.body.appendChild(form);
                form.submit();
            }
        });
    });

     $(document).on('click', '.btn-delete-item', function () {
        const id =  $(this).data('id');
        const no =  $(this).data('no');

        Swal.fire({
            title: 'Hapus Draft Faktur?',
            text: `Draft Faktur [${no}] akan dihapus!`,
            icon: 'error',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `{{ url('/inventori/pembelian') }}/${id}`;
                form.innerHTML = `@csrf @method('DELETE')`;
                document.body.appendChild(form);
                form.submit();
            }
        });
    });

});
</script>
@endsection