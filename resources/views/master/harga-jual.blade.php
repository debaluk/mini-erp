@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="mb-3">
        <h4 class="mb-1">Daftar Harga Jual</h4>
        <div class="text-secondary">Harga jual berdasarkan alokasi Unit Bisnis pada Master Item.</div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-4">
            <select name="business_unit_id" class="form-select" required>
                <option value="">Pilih Unit Bisnis</option>
                @foreach($businessUnits as $bu)
                    <option value="{{ $bu->id }}" @selected(request('business_unit_id') == $bu->id)>{{ $bu->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <input type="text" name="search" class="form-control" placeholder="Cari kode / nama item..." value="{{ request('search') }}">
        </div>
        <div class="col-md-2">
            <select name="price_type" class="form-select">
                <option value="">Semua Tipe</option>
                <option value="retail" @selected(request('price_type') === 'retail')>Retail</option>
                <option value="grosir" @selected(request('price_type') === 'grosir')>Grosir</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-secondary">Filter</button>
            <a href="{{ route('master.menu.harga-jual') }}" class="btn btn-light">Reset</a>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Item</th>
                        <th>Satuan</th>
                        <th class="text-end">Retail</th>
                        <th class="text-end">Grosir</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @if(!request('business_unit_id'))
                        <tr><td colspan="6" class="text-center text-muted py-4">Silakan pilih Unit Bisnis terlebih dahulu.</td></tr>
                    @elseif($prices->isEmpty())
                        <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada item yang sesuai dengan filter.</td></tr>
                    @else
                        @foreach($prices as $price)
                            <tr>
                                <td class="fw-semibold">{{ $price->product_code }}</td>
                                <td>{{ $price->product_name }}</td>
                                <td>{{ $price->unit_name }}</td>
                                <td class="text-end">
                                    @if($price->retail_price_id)
                                        <span class="fw-semibold">Rp {{ number_format((float) $price->retail_price, 0, ',', '.') }}</span>
                                    @else
                                        <span class="text-muted">Belum Setup</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($price->grosir_price_id)
                                        <span class="fw-semibold">Rp {{ number_format((float) $price->grosir_price, 0, ',', '.') }}</span>
                                    @else
                                        <span class="text-muted">Belum Setup</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-setup-price"
                                        data-product-id="{{ $price->product_id }}"
                                        data-product-code="{{ $price->product_code }}"
                                        data-product-name="{{ $price->product_name }}"
                                        data-unit-id="{{ $price->unit_id }}"
                                        data-unit-name="{{ $price->unit_name }}"
                                        data-business-unit-id="{{ request('business_unit_id') }}"
                                        data-retail-id="{{ $price->retail_price_id ?? '' }}"
                                        data-retail-price="{{ $price->retail_price ?? '' }}"
                                        data-grosir-id="{{ $price->grosir_price_id ?? '' }}"
                                        data-grosir-price="{{ $price->grosir_price ?? '' }}">Setup / Edit</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-history"
                                        data-product-id="{{ $price->product_id }}"
                                        data-unit-id="{{ $price->unit_id }}"
                                        data-business-unit-id="{{ request('business_unit_id') }}"
                                        data-product-name="{{ $price->product_name }}"
                                        data-unit-name="{{ $price->unit_name }}">History</button>
                                </td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="priceSetupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" id="priceSetupForm">
            @csrf
            <input type="hidden" name="_method" id="priceMethod" value="POST">
            <input type="hidden" name="business_unit_id" id="priceBusinessUnitId">
            <input type="hidden" name="product_id" id="priceProductId">
            <input type="hidden" name="unit_id" id="priceUnitId">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Setup Harga Jual</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Barang</label><input class="form-control" id="priceProductLabel" disabled></div>
                    <div class="row g-2">
                        <div class="col-md-7"><label class="form-label">Satuan</label><input class="form-control" id="priceUnitLabel" disabled></div>
                        <div class="col-md-5"><label class="form-label">Jenis Harga</label>
                            <select name="price_type" id="priceType" class="form-select" required>
                                <option value="retail">Retail</option><option value="grosir">Grosir</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3"><label class="form-label">Tanggal Update</label><input type="date" name="change_date" id="priceChangeDate" class="form-control" required></div>
                    <div class="mt-3"><label class="form-label">Harga Lama</label><input type="text" id="oldPriceDisplay" class="form-control" disabled></div>
                    <div class="mt-3"><label class="form-label">Harga Baru</label><input type="number" name="selling_price" id="newPrice" class="form-control" min="0.01" step="0.01" required></div>
                    <div class="mt-3"><label class="form-label">% Selisih</label><input type="text" id="changePercent" class="form-control" value="-" disabled></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary">Simpan</button></div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="priceHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><div><h5 class="modal-title mb-1">History Harga Jual</h5><div class="small text-secondary" id="historySubtitle"></div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div id="historyLoading" class="text-center text-muted py-3 d-none">Memuat history...</div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead><tr><th>Tanggal</th><th>Jenis</th><th class="text-end">Harga Lama</th><th class="text-end">Harga Baru</th><th class="text-end">% Selisih</th><th>Diubah Oleh</th></tr></thead>
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
    const setupModal = new bootstrap.Modal(document.getElementById('priceSetupModal'));
    const historyModal = new bootstrap.Modal(document.getElementById('priceHistoryModal'));
    const form = document.getElementById('priceSetupForm');
    const method = document.getElementById('priceMethod');
    const type = document.getElementById('priceType');
    const newPrice = document.getElementById('newPrice');
    const oldDisplay = document.getElementById('oldPriceDisplay');
    const percent = document.getElementById('changePercent');
    const today = new Date().toISOString().slice(0, 10);
    let current = null;

    const formatRupiah = value => {
        if (value === null || value === undefined || value === '') return '-';
        return 'Rp ' + Number(value).toLocaleString('id-ID', { maximumFractionDigits: 2 });
    };

    const refreshOldPrice = () => {
        if (!current) return;
        const id = type.value === 'retail' ? current.retailId : current.grosirId;
        const old = type.value === 'retail' ? current.retailPrice : current.grosirPrice;
        const exists = !!id;
        oldDisplay.value = exists ? formatRupiah(old) : '-';
        newPrice.value = exists ? old : '';
        method.value = exists ? 'PUT' : 'POST';
        form.action = exists ? '{{ url('/master/harga-jual') }}/' + id : '{{ route('master.harga-jual.store') }}';
        const calculate = () => {
            const n = Number(newPrice.value), o = Number(old);
            percent.value = (!exists || !o || !n) ? '-' : (((n - o) / o) * 100).toFixed(2).replace('.', ',') + '%';
        };
        calculate();
    };

    document.querySelectorAll('.btn-setup-price').forEach(button => {
        button.addEventListener('click', () => {
            current = {
                retailId: button.dataset.retailId, retailPrice: button.dataset.retailPrice,
                grosirId: button.dataset.grosirId, grosirPrice: button.dataset.grosirPrice
            };
            document.getElementById('priceBusinessUnitId').value = button.dataset.businessUnitId;
            document.getElementById('priceProductId').value = button.dataset.productId;
            document.getElementById('priceUnitId').value = button.dataset.unitId;
            document.getElementById('priceProductLabel').value = button.dataset.productCode + ' - ' + button.dataset.productName;
            document.getElementById('priceUnitLabel').value = button.dataset.unitName;
            document.getElementById('priceChangeDate').value = today;
            type.value = current.retailId ? 'retail' : 'grosir';
            refreshOldPrice();
            setupModal.show();
        });
    });

    type.addEventListener('change', refreshOldPrice);
    newPrice.addEventListener('input', () => {
        if (!current) return;
        const old = type.value === 'retail' ? Number(current.retailPrice) : Number(current.grosirPrice);
        const n = Number(newPrice.value);
        percent.value = old && n ? (((n - old) / old) * 100).toFixed(2).replace('.', ',') + '%' : '-';
    });

    document.querySelectorAll('.btn-history').forEach(button => {
        button.addEventListener('click', async () => {
            document.getElementById('historySubtitle').textContent = button.dataset.productName + ' · ' + button.dataset.unitName;
            const body = document.getElementById('historyBody');
            const loading = document.getElementById('historyLoading');
            const empty = document.getElementById('historyEmpty');
            body.innerHTML = '';
            loading.classList.remove('d-none');
            empty.classList.add('d-none');
            historyModal.show();

            const params = new URLSearchParams({
                business_unit_id: button.dataset.businessUnitId,
                product: button.dataset.productId,
                unit: button.dataset.unitId
            });

            try {
                const response = await fetch('{{ route('master.harga-jual.history') }}?' + params.toString(), { headers: { 'Accept': 'application/json' } });
                if (!response.ok) throw new Error('Gagal memuat history.');
                const rows = await response.json();
                if (!rows.length) {
                    empty.classList.remove('d-none');
                    return;
                }
                body.innerHTML = rows.map(row => {
                    const oldPrice = row.old_price === null ? '-' : formatRupiah(row.old_price);
                    const pct = row.change_percent === null ? '-' : Number(row.change_percent).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';
                    return '<tr><td>' + new Date(row.change_date).toLocaleDateString('id-ID') + '</td><td>' + (row.price_type === 'retail' ? 'Retail' : 'Grosir') + '</td><td class="text-end">' + oldPrice + '</td><td class="text-end">' + formatRupiah(row.new_price) + '</td><td class="text-end">' + pct + '</td><td>' + (row.changed_by_name || '-') + '</td></tr>';
                }).join('');
            } catch (error) {
                empty.textContent = error.message;
                empty.classList.remove('d-none');
            } finally {
                loading.classList.add('d-none');
            }
        });
    });
})();
</script>
@endsection
