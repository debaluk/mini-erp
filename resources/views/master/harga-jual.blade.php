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
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-secondary">Filter</button>
            <button type="button" id="priceReset" class="btn btn-light">Reset</button>
        </div>
    </form>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Daftar Harga Jual</span>
            <button type="button" id="priceExport" class="btn btn-sm btn-outline-success">Export</button>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Unit Bisnis</th>
                        <th>Kode</th>
                        <th>Item</th>
                        <th>Satuan</th>
                        <th class="text-end">Harga Jual</th>
                        <th class="text-center">Aksi</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody id="priceTableBody">
                    <tr><td colspan="7" class="text-center text-muted py-4">Memuat data...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="priceSetupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <form id="priceSetupForm">
            @csrf
            <input type="hidden" name="business_unit_id" id="priceBusinessUnitId">
            <input type="hidden" name="product_id" id="priceProductId">
            <input type="hidden" name="unit_id" id="priceUnitId">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Setup Harga Jual</h5>
                    <button type="button" class="btn-close btn-close-modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Barang</label><input class="form-control" id="priceProductLabel" disabled></div>
                    <div class="mt-3"><label class="form-label">Satuan</label><input class="form-control" id="priceUnitLabel" disabled></div>
                    <div class="mt-3"><label class="form-label">Tanggal Update</label><input type="date" name="change_date" id="priceChangeDate" class="form-control" required></div>
                    <div class="mt-3"><label class="form-label">Harga Lama</label><input type="text" id="oldPriceDisplay" class="form-control" disabled></div>
                    <div class="mt-3"><label class="form-label">Harga Baru</label><input type="number" name="selling_price" id="newPrice" class="form-control" min="0.01" step="0.01" required></div>
                    <div class="mt-3"><label class="form-label">% Selisih</label><input type="text" id="changePercent" class="form-control" value="-" disabled></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-close-modal">Batal</button>
                    <button class="btn btn-primary" id="priceSaveButton">Simpan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="priceHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1">History Harga Jual</h5>
                    <div class="small text-secondary" id="historySubtitle"></div>
                </div>
                <button type="button" class="btn-close btn-close-modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div id="historyLoading" class="text-center text-muted py-3 d-none">Memuat history...</div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead><tr><th>Tanggal</th><th class="text-end">Harga Lama</th><th class="text-end">Harga Baru</th><th class="text-end">% Selisih</th><th>Diubah Oleh</th></tr></thead>
                        <tbody id="historyBody"></tbody>
                    </table>
                </div>
                <div id="historyEmpty" class="text-center text-muted py-3 d-none">Belum ada history harga.</div>
            </div>
        </div>
    </div>
</div>

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

    const loadPrices = async () => {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Memuat data...</td></tr>';

        const params = new URLSearchParams();
        if (businessUnitFilter.value) params.set('business_unit_id', businessUnitFilter.value);
        if (search.value.trim()) params.set('search', search.value.trim());

        try {
            const response = await fetch('{{ route('master.menu.harga-jual') }}?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) throw new Error(await apiError(response));
            const result = await response.json();
            renderPrices(result.data || []);
        } catch (error) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">' + escapeHtml(error.message) + '</td></tr>';
        }
    };

    const renderPrices = rows => {
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Tidak ada item yang sesuai dengan filter.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(row => {
            return '<tr>' +
                '<td>' + escapeHtml(row.business_unit_name) + '</td>' +
                '<td class="fw-semibold">' + escapeHtml(row.product_code) + '</td>' +
                '<td>' + escapeHtml(row.product_name) + '</td>' +
                '<td>' + escapeHtml(row.unit_name) + '</td>' +
                '<td class="text-end">' + (row.price_id ? '<span class="fw-semibold">' + formatRupiah(row.selling_price) + '</span>' : '<span class="text-muted">Belum Setup</span>') + '</td>' +
                '<td class="text-center text-nowrap">' +
                    '<button type="button" class="btn btn-sm btn-outline-primary btn-setup-price" data-row="' + encodeURIComponent(JSON.stringify(row)) + '">Setup / Edit</button> ' +
                    '<button type="button" class="btn btn-sm btn-outline-secondary btn-history" data-row="' + encodeURIComponent(JSON.stringify(row)) + '">History</button>' +
                '</td>' +
                '<td class="text-center">' + (row.price_id ? '<span class="badge bg-success">Sudah Setup</span>' : '<span class="badge bg-secondary">Belum Setup</span>') + '</td>' +
            '</tr>';
        }).join('');

        tbody.querySelectorAll('.btn-setup-price').forEach(button => {
            button.addEventListener('click', () => openSetup(JSON.parse(decodeURIComponent(button.dataset.row))));
        });
        tbody.querySelectorAll('.btn-history').forEach(button => {
            button.addEventListener('click', () => openHistory(JSON.parse(decodeURIComponent(button.dataset.row))));
        });
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
        document.getElementById('priceProductLabel').value = row.product_code + ' - ' + row.product_name;
        document.getElementById('priceUnitLabel').value = row.unit_name;
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
            await loadPrices();
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
                    : Number(row.change_percent).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';

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

    filterForm.addEventListener('submit', event => {
        event.preventDefault();
        loadPrices();
    });

    businessUnitFilter.addEventListener('change', loadPrices);

    document.querySelectorAll('.btn-close-modal').forEach(button => button.addEventListener('click', () => {
        hideModal(button.closest('.modal'));
    }));

    document.getElementById('priceExport').addEventListener('click', () => {
        const params = new URLSearchParams();
        if (businessUnitFilter.value) params.set('business_unit_id', businessUnitFilter.value);
        if (search.value.trim()) params.set('search', search.value.trim());
        window.location.href = '{{ route('master.harga-jual.export') }}?' + params.toString();
    });

    document.getElementById('priceReset').addEventListener('click', () => {
        businessUnitFilter.value = '';
        search.value = '';
        loadPrices();
    });

    loadPrices();
})();
</script>
@endsection
