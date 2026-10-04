@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="mb-3">
        <h4 class="mb-1">Daftar Harga Jual</h4>
        <div class="text-secondary">Harga jual berdasarkan alokasi Unit Bisnis pada Master Item.</div>
    </div>

    <div id="priceAlert"></div>

    <form id="priceFilterForm" class="row g-2 mb-3">
        <div class="col-md-4">
            <select name="business_unit_id" id="businessUnitFilter" class="form-select">
                <option value="">Semua Unit Bisnis</option>
                @foreach($businessUnits as $bu)
                    <option value="{{ $bu->id }}">{{ $bu->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <input type="text" name="search" id="priceSearch" class="form-control" placeholder="Cari kode / nama item...">
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-secondary">Filter</button>
            <button type="button" id="priceReset" class="btn btn-light">Reset</button>
            <button type="button" id="priceExport" class="btn btn-outline-success">Export Excel</button>
        </div>
    </form>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Daftar Harga Jual</span>
            <button type="button" id="priceSyncInitialSetup" class="btn btn-sm btn-outline-primary">Sync Setup Awal</button>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0 align-middle" id="priceDataTable" style="width:100%">
                <thead>
                    <tr>
                        <th>Kode Barang</th>
                        <th>Nama Barang</th>
                        <th>Satuan</th>
                        <th>Unit Bisnis</th>
                        <th class="text-end">Harga Jual</th>
                        <th class="text-center">Tgl. Update</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody id="priceTableBody">
                    <tr><td colspan="7" class="text-center text-muted py-4">Memuat data...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer"></div>
    </div>
</div>

<div class="modal fade" id="priceSetupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form id="priceSetupForm" class="w-100">
            @csrf
            <input type="hidden" name="business_unit_id" id="priceBusinessUnitId">
            <input type="hidden" name="product_id" id="priceProductId">
            <input type="hidden" name="unit_id" id="priceUnitId">

            <div class="modal-content border-0 shadow">
                <div class="modal-header px-4 py-3">
                    <div>
                        <h5 class="modal-title fw-semibold mb-1">Setup Harga Jual</h5>
                        <div class="small text-secondary">Atur harga jual item untuk Unit Bisnis yang dipilih.</div>
                    </div>
                    <button type="button" class="btn-close btn-close-modal" aria-label="Tutup"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="border rounded-3 bg-light p-3 mb-4">
                        <div class="row g-3">
                            <div class="col-md-7">
                                <div class="small text-secondary mb-1">Barang</div>
                                <div class="fw-semibold" id="priceProductLabel">-</div>
                            </div>
                            <div class="col-md-3">
                                <div class="small text-secondary mb-1">Unit Bisnis</div>
                                <div class="fw-semibold" id="priceBusinessUnitLabel">-</div>
                            </div>
                            <div class="col-md-2">
                                <div class="small text-secondary mb-1">Satuan</div>
                                <div class="fw-semibold" id="priceUnitLabel">-</div>
                            </div>
                        </div>
                    </div>

                    <div class="fw-semibold mb-3">Perubahan Harga</div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small text-secondary">Harga Lama</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="text" id="oldPriceDisplay" class="form-control text-end fw-semibold bg-light" disabled>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="newPrice" class="form-label small text-secondary">Harga Baru</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" name="selling_price" id="newPrice" class="form-control text-end fw-semibold" min="0.01" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-secondary">% Selisih</label>
                            <div class="input-group">
                                <input type="text" id="changePercent" class="form-control text-end fw-semibold bg-light" value="-" disabled>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label for="priceChangeDate" class="form-label small text-secondary">Tanggal Update</label>
                        <input type="date" name="change_date" id="priceChangeDate" class="form-control" required>
                    </div>
                </div>

                <div class="modal-footer px-4 py-3">
                    <button type="button" class="btn btn-light border btn-close-modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4" id="priceSaveButton">Simpan Harga</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="priceSyncModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="priceSyncTitle">Perhatian</h5>
                <button type="button" class="btn-close btn-close-sync" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div id="priceSyncAttention">
                    <p class="mb-0">Proses ini akan mengisi harga awal pertama kali dari Setup Saldo Awal dan mencatat ke dalam histori. Item Barang yang sudah ada Harga Jual tidak akan diproses.</p>
                </div>
                <div id="priceSyncProgress" class="d-none">
                    <div class="progress" style="height: 22px;">
                        <div id="priceSyncProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%">0%</div>
                    </div>
                    <div class="text-center text-muted small mt-2">Sedang memproses...</div>
                </div>
                <div id="priceSyncDone" class="d-none">
                    <p class="mb-0">Proses sudah selesai, silahkan cek kembali Daftar Harga Jual</p>
                </div>
            </div>
            <div class="modal-footer" id="priceSyncFooter">
                <button type="button" class="btn btn-secondary btn-close-sync">Batal</button>
                <button type="button" class="btn btn-primary" id="priceSyncConfirm">Proses</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="priceHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header px-4 py-3">
                <div>
                    <h5 class="modal-title fw-semibold mb-1">History Harga Jual</h5>
                    <div class="small text-secondary" id="historySubtitle"></div>
                </div>
                <button type="button" class="btn-close btn-close-modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-4">
                <div id="historyLoading" class="text-center text-muted py-4 d-none">
                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                    Memuat history...
                </div>
                <div class="table-responsive border rounded-3">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal</th>
                                <th class="text-end">Harga Lama</th>
                                <th class="text-end">Harga Baru</th>
                                <th class="text-end">% Selisih</th>
                                <th>Diubah Oleh</th>
                            </tr>
                        </thead>
                        <tbody id="historyBody"></tbody>
                    </table>
                </div>
                <div id="historyEmpty" class="text-center text-muted py-4 d-none">Belum ada history harga.</div>
            </div>
            <div class="modal-footer px-4 py-3">
                <button type="button" class="btn btn-light border btn-close-modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js"></script>
<script>
(() => {
    const showModal = element => { element.classList.add("show"); element.style.display = "block"; element.removeAttribute("aria-hidden"); document.body.classList.add("modal-open"); };
    const hideModal = element => { element.classList.remove("show"); element.style.display = "none"; element.setAttribute("aria-hidden", "true"); document.body.classList.remove("modal-open"); };
    const setupModal = document.getElementById('priceSetupModal');
    const historyModal = document.getElementById('priceHistoryModal');
    const filterForm = document.getElementById('priceFilterForm');
    const businessUnitFilter = document.getElementById('businessUnitFilter');
    const search = document.getElementById('priceSearch');
    const tbody = document.getElementById('priceTableBody');
    const alertBox = document.getElementById('priceAlert');
    const form = document.getElementById('priceSetupForm');
    const newPrice = document.getElementById('newPrice');
    const oldDisplay = document.getElementById('oldPriceDisplay');
    const percent = document.getElementById('changePercent');
    const saveButton = document.getElementById('priceSaveButton');
    const today = new Date().toISOString().slice(0, 10);
    let current = null;
        const formatRupiah = value => {
        if (value === null || value === undefined || value === '') return '-';
        return 'Rp ' + Number(value).toLocaleString('id-ID', { maximumFractionDigits: 2 });
    };

    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;'
    }[char]));

    const showAlert = (message, type = 'success') => {
        alertBox.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show">' +
            escapeHtml(message) + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    };

    const apiError = async response => {
        let message = 'Terjadi kesalahan.';
        try {
            const data = await response.json();
            if (data.message) message = data.message;
            if (data.errors) message = Object.values(data.errors).flat()[0] || message;
        } catch (_) {}
        return message;
    };

    const refreshOldPrice = () => {
        if (!current) return;
        const exists = !!current.price_id;
        const old = current.selling_price;

        oldDisplay.value = exists ? formatRupiah(old) : '-';
        newPrice.value = exists ? Number(old).toString() : '';

        const calculate = () => {
            const n = Number(newPrice.value), o = Number(old);
            percent.value = (!exists || !o || !n) ? '-' : (((n - o) / o) * 100).toFixed(2).replace('.', ',') + '%';
        };
        calculate();
    };

    const openSetup = row => {
        current = row;
        document.getElementById('priceBusinessUnitId').value = row.business_unit_id;
        document.getElementById('priceProductId').value = row.product_id;
        document.getElementById('priceUnitId').value = row.unit_id;
        document.getElementById('priceProductLabel').textContent = row.product_code + ' - ' + row.product_name;
        document.getElementById('priceBusinessUnitLabel').textContent = row.business_unit_name || '-';
        document.getElementById('priceUnitLabel').textContent = row.unit_name || '-';
        document.getElementById('priceChangeDate').value = today;
        refreshOldPrice();
        showModal(setupModal);
    };

    newPrice.addEventListener('input', () => {
        if (!current) return;
        const old = Number(current.selling_price);
        const n = Number(newPrice.value);
        percent.value = old && n ? (((n - old) / old) * 100).toFixed(2).replace('.', ',') + '%' : '-';
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        saveButton.disabled = true;

        const isEdit = !!current.price_id;
        const priceId = current.price_id;
        const url = isEdit
            ? '{{ url('/master/harga-jual') }}/' + priceId
            : '{{ route('master.harga-jual.store') }}';

        const payload = new FormData(form);
        if (isEdit) payload.append('_method', 'PUT');

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: payload
            });

            if (!response.ok) throw new Error(await apiError(response));

            const result = await response.json();
            hideModal(setupModal);
            showAlert(result.message || 'Harga jual berhasil disimpan.');
            dataTable.ajax.reload(null, false);
        } catch (error) {
            showAlert(error.message, 'danger');
        } finally {
            saveButton.disabled = false;
        }
    });

    const openHistory = async row => {
        document.getElementById('historySubtitle').textContent =
            row.business_unit_name + ' · ' + row.product_name + ' · ' + row.unit_name;

        const body = document.getElementById('historyBody');
        const loading = document.getElementById('historyLoading');
        const empty = document.getElementById('historyEmpty');
        body.innerHTML = '';
        empty.classList.add('d-none');
        loading.classList.remove('d-none');
        showModal(historyModal);

        const params = new URLSearchParams({
            business_unit_id: row.business_unit_id,
            product: row.product_id,
            unit: row.unit_id
        });

        try {
            const response = await fetch('{{ route('master.harga-jual.history') }}?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) throw new Error(await apiError(response));
            const rows = await response.json();

            if (!rows.length) {
                empty.textContent = 'Belum ada history harga.';
                empty.classList.remove('d-none');
                return;
            }

            body.innerHTML = rows.map(row => {
                const oldPrice = row.old_price === null ? '-' : formatRupiah(row.old_price);
                const pct = row.change_percent === null
                    ? '-'
                    : Number(row.change_percent).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                return '<tr><td>' + new Date(row.change_date).toLocaleDateString('id-ID') +
                    '</td><td class="text-end">' + oldPrice +
                    '</td><td class="text-end">' + formatRupiah(row.new_price) +
                    '</td><td class="text-end">' + pct +
                    '</td><td>' + escapeHtml(row.changed_by_name || '-') + '</td></tr>';
            }).join('');
        } catch (error) {
            empty.textContent = error.message;
            empty.classList.remove('d-none');
        } finally {
            loading.classList.add('d-none');
        }
    };

    const dataTable = new DataTable('#priceDataTable', {
        processing: true,
        serverSide: true,
        ordering: false,
        pageLength: 15,
        lengthMenu: [[15, 25, 50, 100], [15, 25, 50, 100]],
        language: {
            lengthMenu: 'Tampilkan _MENU_ data per halaman',
            search: 'Cari:',
            info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            infoFiltered: '(disaring dari _MAX_ data)',
            zeroRecords: 'Data tidak ditemukan',
            emptyTable: 'Belum ada data',
            paginate: { first: '<<', last: '>>', next: '>', previous: '<' },
            processing: 'Memuat...'
        },
        ajax: {
            url: '{{ route('master.menu.harga-jual') }}',
            type: 'GET',
            data: function (d) {
                d.business_unit_id = businessUnitFilter.value || '';
            },
            dataSrc: 'data'
        },
        columns: [
            { data: 'product_code', defaultContent: '', className: 'fw-semibold' },
            { data: 'product_name', defaultContent: '' },
            { data: 'unit_name', defaultContent: '' },
            { data: 'business_unit_name', defaultContent: '' },
            {
                data: 'selling_price',
                className: 'text-end',
                render: (data, type, row) => row.price_id
                    ? '<span class="fw-semibold">' + formatRupiah(data) + '</span>'
                    : '<span class="text-muted">Belum Setup</span>'
            },
            {
                data: 'updated_price_date',
                className: 'text-center',
                render: data => data ? new Date(data).toLocaleDateString('id-ID') : '-'
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center text-nowrap',
                render: () =>
                    '<button type="button" class="btn btn-sm btn-outline-primary btn-setup-price">Setup / Edit</button> ' +
                    '<button type="button" class="btn btn-sm btn-outline-secondary btn-history">History</button>'
            }
        ],
        createdRow: (row, data) => {
            row.querySelector('.btn-setup-price')?.addEventListener('click', () => openSetup(data));
            row.querySelector('.btn-history')?.addEventListener('click', () => openHistory(data));
        }
    });


    filterForm.addEventListener('submit', event => {
        event.preventDefault();
        dataTable.ajax.reload();
    });

    businessUnitFilter.addEventListener('change', () => dataTable.ajax.reload());

    document.querySelectorAll('.btn-close-modal').forEach(button => button.addEventListener('click', () => {
        hideModal(button.closest('.modal'));
    }));

    const syncModal = document.getElementById('priceSyncModal');
    const syncAttention = document.getElementById('priceSyncAttention');
    const syncProgress = document.getElementById('priceSyncProgress');
    const syncDone = document.getElementById('priceSyncDone');
    const syncProgressBar = document.getElementById('priceSyncProgressBar');
    const syncConfirm = document.getElementById('priceSyncConfirm');
    const syncFooter = document.getElementById('priceSyncFooter');
    let syncReloadOnClose = false;

    const resetSyncModal = () => {
        syncAttention.classList.remove('d-none');
        syncProgress.classList.add('d-none');
        syncDone.classList.add('d-none');
        syncProgressBar.style.width = '0%';
        syncProgressBar.textContent = '0%';
        syncConfirm.classList.remove('d-none');
        syncConfirm.disabled = false;
        syncConfirm.textContent = 'Proses';
        syncFooter.querySelectorAll('.btn-close-sync').forEach(button => button.classList.remove('d-none'));
    };

    const closeSyncModal = () => {
        hideModal(syncModal);
        if (syncReloadOnClose) {
            syncReloadOnClose = false;
            dataTable.ajax.reload(null, false);
        }
    };

    document.getElementById('priceSyncInitialSetup').addEventListener('click', () => {
        resetSyncModal();
        showModal(syncModal);
    });

    syncFooter.querySelectorAll('.btn-close-sync').forEach(button => button.addEventListener('click', closeSyncModal));
    syncModal.querySelector('.btn-close-sync').addEventListener('click', closeSyncModal);

    syncConfirm.addEventListener('click', async () => {
        syncConfirm.disabled = true;
        syncAttention.classList.add('d-none');
        syncProgress.classList.remove('d-none');
        syncProgressBar.style.width = '15%';
        syncProgressBar.textContent = '15%';

        const params = new URLSearchParams();
        if (businessUnitFilter.value) params.set('business_unit_id', businessUnitFilter.value);

        try {
            await new Promise(resolve => setTimeout(resolve, 250));
            syncProgressBar.style.width = '45%';
            syncProgressBar.textContent = '45%';

            const response = await fetch('{{ route('master.harga-jual.sync-initial-setup') }}?' + params.toString(), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Sync Setup Awal gagal.');

            syncProgressBar.style.width = '100%';
            syncProgressBar.textContent = '100%';
            await new Promise(resolve => setTimeout(resolve, 350));
            syncProgress.classList.add('d-none');
            syncDone.classList.remove('d-none');
            syncConfirm.textContent = 'OK';
            syncConfirm.disabled = false;
            syncReloadOnClose = true;
            syncConfirm.onclick = closeSyncModal;
        } catch (error) {
            syncProgress.classList.add('d-none');
            syncAttention.classList.remove('d-none');
            syncConfirm.disabled = false;
            showAlert(error.message || 'Sync Setup Awal gagal.', 'danger');
            closeSyncModal();
        }
    });

    document.getElementById('priceExport').addEventListener('click', async () => {
        if (typeof XLSX === 'undefined') {
            showAlert('Library Excel belum termuat. Silakan refresh halaman lalu coba lagi.', 'danger');
            return;
        }

        const button = document.getElementById('priceExport');
        button.disabled = true;

        const params = new URLSearchParams();
        if (businessUnitFilter.value) params.set('business_unit_id', businessUnitFilter.value);
        if (search.value.trim()) params.set('search', search.value.trim());

        try {
            const response = await fetch('{{ route('master.harga-jual.export') }}?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Export gagal.');

            const todayText = new Intl.DateTimeFormat('id-ID', {
                day: '2-digit', month: '2-digit', year: 'numeric'
            }).format(new Date());

            const rows = payload.rows || [];
            const data = rows.map(row => [
                String(row.product_code || ''),
                String(row.product_name || ''),
                String(row.unit_name || ''),
                String(row.business_unit_name || ''),
                row.selling_price === null ? 'Belum Setup' : Number(row.selling_price),
                row.updated_price_date ? new Date(row.updated_price_date).toLocaleDateString('id-ID') : '-'
            ]);

            const ws = XLSX.utils.aoa_to_sheet([
                [payload.entity_name || 'NAMA ENTITAS'],
                ['DAFTAR HARGA JUAL'],
                ['Unit Bisnis : ' + (payload.business_unit_name || 'Semua Unit Bisnis')],
                ['Tgl Export : ' + todayText],
                [],
                ['Kode Barang', 'Nama Barang', 'Satuan', 'Unit Bisnis', 'Harga Jual', 'Tgl. Update'],
                ...data
            ]);

            ws['!merges'] = [
                {s:{r:0,c:0},e:{r:0,c:5}},
                {s:{r:1,c:0},e:{r:1,c:5}},
                {s:{r:2,c:0},e:{r:2,c:5}},
                {s:{r:3,c:0},e:{r:3,c:5}}
            ];
            ws['!cols'] = [
                {wch: 20}, {wch: 18}, {wch: 34},
                {wch: 14}, {wch: 20}, {wch: 16}
            ];

            data.forEach((row, index) => {
                const excelRow = index + 7;
                if (typeof row[4] === 'number') ws['E' + excelRow].z = '#,##0.##';
            });

            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, ws, 'Harga Jual');
            XLSX.writeFile(
                workbook,
                'daftar-harga-jual-' + new Date().toISOString().slice(0, 10) + '.xlsx'
            );
        } catch (error) {
            showAlert(error.message || 'Export gagal.', 'danger');
        } finally {
            button.disabled = false;
        }
    });

    document.getElementById('priceReset').addEventListener('click', () => {
        businessUnitFilter.value = '';
        search.value = '';
        dataTable.ajax.reload();
    });

    dataTable.ajax.reload();
})();
</script>
@endpush
@endsection
