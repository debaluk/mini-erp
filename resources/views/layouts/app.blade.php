<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Mini ERP') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/2.3.3/css/dataTables.bootstrap5.css" rel="stylesheet">
    <style>
        .navbar .dropdown-menu { min-width: 230px; }
        .navbar .dropdown-menu .dropdown-header { font-weight: 700; color: var(--bs-primary); }
        .navbar .dropdown-menu .dropdown-submenu { position: relative; }
        .navbar .dropdown-menu .dropdown-submenu > .dropdown-menu { top: 0; left: 100%; margin-top: -.35rem; display: none; }
        .navbar .dropdown-menu .dropdown-submenu.show > .dropdown-menu { display: block; }
        .navbar .dropdown-menu .dropdown-submenu > .dropdown-toggle::after { margin-left: auto; }
        @media (max-width: 1199.98px) {
            .navbar .dropdown-menu .dropdown-submenu > .dropdown-menu { position: static; margin: 0; border: 0; box-shadow: none; }
        }
        main { min-height: calc(100vh - 56px); }
        main .form-label { font-size: .8rem; margin-bottom: .2rem; font-weight: 600; }
        main .form-control, main .form-select { min-height: 32px; height: 32px; padding: .2rem .5rem; font-size: .82rem; line-height: 1.2; }
        main textarea.form-control { height: auto; min-height: 52px; }
        main .btn:not(.btn-close) { padding: .3rem .65rem; font-size: .8rem; line-height: 1.2; }
        main .table { font-size: .82rem; }
        main .table thead th { font-size: .82rem; font-weight: 600; padding: .45rem .5rem; }
        main .table tbody td { font-size: .82rem; padding: .4rem .5rem; }
        main .dataTables_wrapper, main .dataTables_wrapper .dt-info, main .dataTables_wrapper .dt-paging, main .dataTables_wrapper .dt-length, main .dataTables_wrapper .dt-search { font-size: .82rem; }
        main .dataTables_wrapper .dt-length select, main .dataTables_wrapper .dt-search input { font-size: .82rem; padding: .2rem .45rem; min-height: 30px; }
        main .dataTables_wrapper .dt-paging .pagination { margin: 0 !important; gap: 1px !important; }
        main .dataTables_wrapper .dt-paging .pagination .page-item, main .dataTables_wrapper .dt-paging .pagination .page-link { margin: 0 !important; padding: 0 !important; width: 22px !important; min-width: 22px !important; max-width: 22px !important; height: 22px !important; min-height: 22px !important; font-size: .65rem !important; line-height: 20px !important; text-align: center !important; }
        main .dataTables_wrapper .dt-paging .pagination .page-link { display: block !important; }
        main .card-body { padding: .75rem; }
        main .card-header { padding: .5rem .75rem; }
        main form.row { --bs-gutter-x: .6rem; --bs-gutter-y: .45rem; }
    </style>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-xl navbar-dark bg-dark sticky-top shadow-sm">
    <div class="container-fluid px-3">
        <a class="navbar-brand fw-bold" href="{{ route('dashboard') }}">Mini ERP</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topMenu" aria-controls="topMenu" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="topMenu">
            <ul class="navbar-nav me-auto mb-2 mb-xl-0">
                <li class="nav-item"><a class="nav-link" href="{{ route('dashboard') }}">Dashboard</a></li>
                @if(auth()->user()->hasModuleAccess('master_data'))
                <li class="nav-item dropdown"><a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">MASTER DATA</a><ul class="dropdown-menu">
                    @foreach([
    ['route' => 'pengaturan.unit-bisnis', 'label' => 'Data Unit Bisnis'],
    ['route' => 'master.menu.gudang', 'label' => 'Gudang'],
    ['route' => 'master.menu.satuan', 'label' => 'Satuan'],
    ['route' => 'master.menu.produk', 'label' => 'Item Barang'],
    ['route' => 'master.menu.harga-jual', 'label' => 'Daftar Harga Jual'],
    ['route' => 'master.menu.customer', 'label' => 'Pelanggan'],
    ['route' => 'master.menu.supplier', 'label' => 'Supplier'],
    ['route' => 'master.menu.pekerja', 'label' => 'Pekerja'],
    ['route' => 'erp.bom', 'label' => 'BOM'],
    ['route' => 'akuntansi.akun', 'label' => 'COA/Akun'],
] as $menu)
    <li>
        <a class="dropdown-item" href="{{ route($menu['route']) }}">
            {{ $menu['label'] }}
        </a>
    </li>
@endforeach
                </ul></li>
                @endif
                {{-- POS RETAIL: intentionally hidden from the top menu; routes/features remain intact. --}}
                <li class="nav-item dropdown d-none">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">POS RETAIL</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('pos.pos') }}">POS</a></li>
                        <li><a class="dropdown-item" href="{{ route('pos.penjualan') }}">Penjualan</a></li>
                        <li><a class="dropdown-item" href="{{ route('pos.pembayaran') }}">Pembayaran</a></li>
                        <li><a class="dropdown-item" href="{{ route('pos.retur') }}">Retur</a></li>
                        <li><a class="dropdown-item" href="{{ route('pos.shift') }}">Kasir / Shift</a></li>
                    </ul>
                </li>

                @if(auth()->user()->hasAnyModuleAccess(['produksi','inventori']))
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">INVENTORI &amp; OPERASIONAL</a>
                    <ul class="dropdown-menu">
                        <li class="dropdown-submenu">
                            <a class="dropdown-item dropdown-toggle" href="#">1. PENJUALAN</a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('pos.pos') }}" onclick="window.open(this.href, 'POSKasir', 'width=1400,height=900,resizable=yes,scrollbars=yes'); return false;">POS Kasir (Tunai/Retail)</a></li>
                                <li><a class="dropdown-item" href="{{ route('inventori.penjualan') }}">Penjualan Tempo/Invoice</a></li>
                                <li><a class="dropdown-item" href="{{ route('pos.retur') }}">Retur Penjualan</a></li>
                            </ul>
                        </li>
                        <li class="dropdown-submenu">
                            <a class="dropdown-item dropdown-toggle" href="#">2. PEMBELIAN</a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('inventori.pembelian-po') }}">Purchase Order (PO)</a></li>
                                <li><a class="dropdown-item" href="{{ route('inventori.penerimaan') }}">Penerimaan Barang</a></li>
                                <li><a class="dropdown-item" href="{{ route('inventori.pembelian') }}">Faktur Pembelian</a></li>
                                <li><a class="dropdown-item" href="{{ route('inventori.retur-pembelian') }}">Retur Pembelian</a></li>
                            </ul>
                        </li>
                        <li class="dropdown-submenu">
                            <a class="dropdown-item dropdown-toggle" href="#">3. PRODUKSI (BUASO)</a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('produksi.work-order') }}">Work Order (SPK)</a></li>
                                <li><a class="dropdown-item" href="{{ route('produksi.pemakaian-bahan') }}">Pemakaian Bahan Baku</a></li>
                                <li><a class="dropdown-item" href="{{ route('produksi.hasil-produksi') }}">Hasil Barang Jadi &amp; Scrap</a></li>
                            </ul>
                        </li>
                        <li class="dropdown-submenu">
                            <a class="dropdown-item dropdown-toggle" href="#">4. PERSEDIAAN</a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('inventory.initial-setup') }}">Setup Stok Awal</a></li>
                                <li><a class="dropdown-item" href="{{ route('erp.movements') }}">Mutasi Barang</a></li>
                                <li><a class="dropdown-item" href="{{ route('inventori.adjustment') }}">Penyesuaian Stok</a></li>
                                <li><a class="dropdown-item" href="{{ route('inventori.stock-opname') }}">Stok Opname</a></li>
                            </ul>
                        </li>
                        <li class="dropdown-submenu">
                            <a class="dropdown-item dropdown-toggle" href="#">5. LAPORAN OPERASIONAL</a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('laporan.penjualan') }}">Laporan Penjualan</a></li>
                                <li><a class="dropdown-item" href="{{ route('laporan.pembelian') }}">Laporan Pembelian</a></li>
                                <li><a class="dropdown-item" href="{{ route('laporan.persediaan') }}">Laporan Persediaan</a></li>
                                <li><a class="dropdown-item" href="{{ route('laporan.produksi') }}">Laporan Produksi</a></li>
                                <li><a class="dropdown-item" href="{{ route('inventori.stok') }}">Kartu Stok &amp; Tracking HPP</a></li>
                                <li><a class="dropdown-item" href="{{ route('inventori.margin-control') }}">Analisa Margin &amp; Kontrol Harga</a></li>
                            </ul>
                        </li>
                    </ul>
                </li>
                @endif

                @if(auth()->user()->hasAnyModuleAccess(['laporan','akuntansi']))
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">KEUANGAN &amp; AKUNTANSI</a>
                    <ul class="dropdown-menu">
                        @if(auth()->user()->hasModuleAccess('akuntansi'))
                        <li class="dropdown-submenu">
                            <a class="dropdown-item dropdown-toggle" href="#">1. KAS &amp; BANK</a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('akuntansi.kas-bank') }}">Kas / Bank Masuk</a></li>
                                <li><a class="dropdown-item" href="{{ route('akuntansi.kas-bank') }}">Kas / Bank Keluar</a></li>
                                <li><a class="dropdown-item" href="{{ route('akuntansi.kas-bank') }}">Transfer / Mutasi</a></li>
                            </ul>
                        </li>
                        <li class="dropdown-submenu">
                            <a class="dropdown-item dropdown-toggle" href="#">2. AKUNTANSI</a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('akuntansi.jurnal') }}">Jurnal Umum</a></li>
                                <li><a class="dropdown-item" href="{{ route('akuntansi.buku-besar') }}">Buku Besar</a></li>
                                <li><a class="dropdown-item" href="{{ route('akuntansi.closing-periode') }}">Closing Periode</a></li>
                            </ul>
                        </li>
                        @endif
                        @if(auth()->user()->hasModuleAccess('laporan'))
                        <li class="dropdown-submenu">
                            <a class="dropdown-item dropdown-toggle" href="#">3. LAPORAN KEUANGAN</a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('akuntansi.laba-rugi') }}">Laporan Laba Rugi</a></li>
                                <li><a class="dropdown-item" href="{{ route('akuntansi.neraca-saldo') }}">Laporan Neraca Saldo</a></li>
                                <li><a class="dropdown-item" href="{{ route('akuntansi.neraca') }}">Laporan Neraca</a></li>
                                <li><a class="dropdown-item" href="{{ route('akuntansi.arus-kas') }}">Laporan Arus Kas</a></li>
                                <li><a class="dropdown-item" href="{{ route('laporan.piutang') }}">Laporan Aging Piutang</a></li>
                                <li><a class="dropdown-item" href="{{ route('laporan.hutang') }}">Laporan Aging Hutang</a></li>
                            </ul>
                        </li>
                        @endif
                    </ul>
                </li>
                @endif

                @if(in_array(auth()->user()->role, ['superadmin','owner','admin']))
                <li class="nav-item dropdown"><a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">PENGATURAN</a><ul class="dropdown-menu">
                    @if(in_array(auth()->user()->role, ['owner']))<li><a class="dropdown-item" href="{{ route('pengaturan.user') }}">User</a></li>@endif
                    @if(in_array(auth()->user()->role, ['superadmin','owner']))<li><a class="dropdown-item" href="{{ route('pengaturan.entitas') }}">Entitas</a></li>@endif
                    @if(in_array(auth()->user()->role, ['superadmin','owner','admin']))<li><a class="dropdown-item" href="{{ route('pengaturan.konfigurasi') }}">Konfigurasi</a></li>@endif
                </ul></li>
                @endif
            </ul>
            <div class="dropdown"><button class="btn btn-outline-light btn-sm dropdown-toggle" data-bs-toggle="dropdown">{{ auth()->user()->name }} · {{ ucfirst(auth()->user()->role) }}</button><ul class="dropdown-menu dropdown-menu-end"><li><span class="dropdown-item-text fw-semibold">{{ auth()->user()->name }}</span></li><li><span class="dropdown-item-text text-secondary">Role: {{ ucfirst(auth()->user()->role) }}</span></li><li><hr class="dropdown-divider"></li><li><form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item" type="submit">Logout</button></form></li></ul></div>
        </div>
    </div>
</nav>

<div class="modal fade" id="erpMessageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="erpMessageTitle">Informasi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="erpMessageBody"></div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="erpConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="erpConfirmTitle">Konfirmasi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="erpConfirmBody">Apakah Anda yakin?</div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-light btn-sm" id="erpConfirmNo">Batal</button>
                <button type="button" class="btn btn-danger btn-sm" id="erpConfirmYes">Ya, Lanjutkan</button>
            </div>
        </div>
    </div>
</div>

<main class="container-fluid p-3 p-lg-4">
    @yield('content')
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.3.3/js/dataTables.js"></script>
<script src="https://cdn.datatables.net/2.3.3/js/dataTables.bootstrap5.js"></script>
<script>
    DataTable.defaults.language = {
        ...DataTable.defaults.language,
        processing: 'Memproses...', search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ data', info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data', infoEmpty: 'Menampilkan 0 sampai 0 dari _TOTAL_ data', infoFiltered: '(disaring dari _MAX_ total data)', loadingRecords: 'Memuat...', zeroRecords: 'Data tidak ditemukan', emptyTable: 'Belum ada data', paginate: { first: '<<', previous: '<', next: '>', last: '>>' }
    };
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const messageEl = document.getElementById('erpMessageModal');
    const confirmEl = document.getElementById('erpConfirmModal');
    if (!messageEl || !confirmEl || typeof bootstrap === 'undefined') return;

    const messageModal = new bootstrap.Modal(messageEl);
    const confirmModal = new bootstrap.Modal(confirmEl);
    const messageTitle = document.getElementById('erpMessageTitle');
    const messageBody = document.getElementById('erpMessageBody');
    const confirmTitle = document.getElementById('erpConfirmTitle');
    const confirmBody = document.getElementById('erpConfirmBody');
    const confirmYes = document.getElementById('erpConfirmYes');
    const confirmNo = document.getElementById('erpConfirmNo');
    let confirmResolve = null;

    window.erpNotify = (message, type = 'success') => {
        const titles = {success:'Berhasil', danger:'Gagal', warning:'Peringatan', info:'Informasi'};
        const buttons = {success:'btn-primary', danger:'btn-danger', warning:'btn-warning', info:'btn-primary'};
        messageTitle.textContent = titles[type] || titles.info;
        messageBody.innerHTML = '';
        if (Array.isArray(message)) {
            const ul = document.createElement('ul');
            ul.className = 'mb-0 ps-3';
            message.forEach(item => {
                const li = document.createElement('li');
                li.textContent = item;
                ul.appendChild(li);
            });
            messageBody.appendChild(ul);
        } else {
            messageBody.textContent = String(message ?? '');
        }
        const okButton = messageEl.querySelector('.modal-footer button');
        okButton.className = 'btn btn-sm ' + (buttons[type] || buttons.info);
        messageModal.show();
    };

    window.erpConfirm = (message = 'Apakah Anda yakin?', title = 'Konfirmasi') => new Promise(resolve => {
        confirmResolve = resolve;
        confirmTitle.textContent = title;
        confirmBody.textContent = message;
        confirmModal.show();
    });

    confirmYes.addEventListener('click', () => {
        confirmModal.hide();
        if (confirmResolve) confirmResolve(true);
        confirmResolve = null;
    });
    confirmNo.addEventListener('click', () => {
        confirmModal.hide();
        if (confirmResolve) confirmResolve(false);
        confirmResolve = null;
    });
    confirmEl.addEventListener('hidden.bs.modal', () => {
        if (confirmResolve) confirmResolve(false);
        confirmResolve = null;
    });

    window.erpFetchJson = async (url, options = {}) => {
        const headers = {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            ...(options.headers || {})
        };
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        if (csrf && !headers['X-CSRF-TOKEN']) headers['X-CSRF-TOKEN'] = csrf;
        const response = await fetch(url, {...options, headers});
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const errors = Object.values(data.errors || {}).flat().filter(Boolean);
            const message = data.message || (errors.length ? errors : 'Terjadi kesalahan pada server.');
            const error = new Error(Array.isArray(message) ? message.join(' ') : message);
            error.status = response.status;
            error.data = data;
            throw error;
        }
        return data;
    };
});
    document.querySelectorAll('.dropdown-submenu > .dropdown-toggle').forEach(toggle => {
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const parent = this.parentElement;
            document.querySelectorAll('.dropdown-submenu.show').forEach(el => {
                if (el !== parent) el.classList.remove('show');
            });
            parent.classList.toggle('show');
        });
    });

    document.querySelectorAll('.dropdown').forEach(dropdown => {
        dropdown.addEventListener('hide.bs.dropdown', () => {
            dropdown.querySelectorAll('.dropdown-submenu.show').forEach(el => el.classList.remove('show'));
        });
    });
</script>

@stack('scripts')
</body>
</html>
