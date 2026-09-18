<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Mini ERP') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/2.3.3/css/dataTables.bootstrap5.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --erp-primary: #0f766e;
            --erp-primary-dark: #115e59;
            --erp-primary-soft: #ccfbf1;
            --erp-sidebar: #f0fdfa;
            --erp-border: #d1fae5;
        }

        body { background: #f8fafc; }

        .erp-sidebar {
            width: 270px;
            min-height: 100vh;
            background: linear-gradient(180deg, #ecfdf5 0%, #f0fdfa 55%, #ffffff 100%);
            border-right: 1px solid var(--erp-border);
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 1040;
            overflow-y: auto;
        }

        .erp-brand {
            height: 64px;
            display: flex;
            align-items: center;
            gap: .65rem;
            padding: 0 1.15rem;
            color: var(--erp-primary-dark);
            text-decoration: none;
            border-bottom: 1px solid var(--erp-border);
        }

        .erp-brand-mark {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: var(--erp-primary);
            color: #fff;
            box-shadow: 0 4px 12px rgba(15, 118, 110, .18);
        }

        .erp-brand-title { font-weight: 700; letter-spacing: .1px; }
        .erp-sidebar-body { padding: .7rem .65rem 1rem; }

        .erp-section-title {
            padding: .7rem .7rem .35rem;
            color: #64748b;
            font-size: .68rem;
            font-weight: 800;
            letter-spacing: .08em;
        }

        .erp-menu {
            display: flex;
            align-items: center;
            gap: .65rem;
            min-height: 38px;
            margin: 2px 0;
            padding: .5rem .7rem;
            border-radius: 9px;
            color: #334155;
            text-decoration: none;
            font-size: .84rem;
            transition: .15s ease;
        }

        .erp-menu i { width: 18px; text-align: center; color: #64748b; font-size: .95rem; }
        .erp-menu:hover { background: #dff7ef; color: var(--erp-primary-dark); }
        .erp-menu:hover i { color: var(--erp-primary); }
        .erp-menu.active {
            background: var(--erp-primary);
            color: #fff;
            box-shadow: 0 3px 9px rgba(15, 118, 110, .18);
        }
        .erp-menu.active i { color: #fff; }

        .erp-menu.disabled {
            color: #94a3b8;
            cursor: default;
        }
        .erp-menu.disabled i { color: #a8b5c2; }
        .erp-menu.disabled:hover { background: transparent; color: #94a3b8; }

        .erp-content {
            margin-left: 270px;
            min-height: 100vh;
        }

        .erp-topbar {
            min-height: 64px;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 1030;
        }

        .erp-user-btn {
            color: #334155;
            border-color: #cbd5e1;
            background: #fff;
        }
        .erp-user-btn:hover { background: var(--erp-primary-soft); border-color: #99f6e4; color: var(--erp-primary-dark); }

        .navbar .dropdown-menu { min-width: 230px; }
        .navbar .dropdown-menu .dropdown-header { font-weight: 700; color: var(--bs-primary); }
        main { min-height: calc(100vh - 64px); }
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

        @media (max-width: 991.98px) {
            .erp-sidebar {
                transform: translateX(-100%);
                transition: transform .2s ease;
            }
            .erp-sidebar.show { transform: translateX(0); }
            .erp-content { margin-left: 0; }
            .erp-sidebar-backdrop {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(15, 23, 42, .28);
                z-index: 1035;
            }
            .erp-sidebar-backdrop.show { display: block; }
        }
    </style>
<script>
function openPosWindow(url){
    const w = window.screen.availWidth;
    const h = window.screen.availHeight;
    window.open(url,'miniErpPos','width='+w+',height='+h+',left=0,top=0,toolbar=no,location=no,menubar=no,status=no,resizable=yes,scrollbars=yes');
    return false;
}
</script>
</head>
<body>
@if(!request()->routeIs('erp.pos'))
<aside id="erpSidebar" class="erp-sidebar">
    <a class="erp-brand" href="{{ route('dashboard') }}">
        <span class="erp-brand-mark"><i class="bi bi-buildings-fill"></i></span>
        <span class="erp-brand-title">Mini ERP</span>
    </a>

    <div class="erp-sidebar-body">
        <a class="erp-menu {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
            <i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span>
        </a>

        @if(in_array(auth()->user()->role, ['owner','admin']))
        <div class="erp-section-title">MASTER DATA</div>
        @foreach([
            ['products','Produk','bi-box-seam'],
            ['customers','Customer','bi-people'],
            ['suppliers','Supplier','bi-truck'],
            ['warehouses','Gudang','bi-house'],
            ['units','Satuan','bi-rulers'],
            ['unit-conversions','Konversi Satuan','bi-arrow-left-right'],
            ['tariffs','Tarif','bi-tags'],
            ['vehicles','Kendaraan','bi-truck-front'],
            ['drivers','Driver','bi-person-badge']
        ] as $item)
        <a class="erp-menu {{ request()->routeIs('master.'.$item[0]) ? 'active' : '' }}" href="{{ route('master.'.$item[0]) }}">
            <i class="bi {{ $item[2] }}"></i><span>{{ $item[1] }}</span>
        </a>
        @endforeach
        @endif

        @if(in_array(auth()->user()->role, ['owner','kasir']))
        <div class="erp-section-title">POS RETAIL</div>
        <a class="erp-menu {{ request()->routeIs('erp.sales') ? 'active' : '' }}" href="{{ route('erp.sales') }}"><i class="bi bi-cart3"></i><span>Penjualan</span></a>
        <a class="erp-menu {{ request()->routeIs('erp.payments') ? 'active' : '' }}" href="{{ route('erp.payments') }}"><i class="bi bi-credit-card"></i><span>Pembayaran</span></a>
        <a class="erp-menu disabled" href="javascript:void(0)" title="Modul belum tersedia"><i class="bi bi-arrow-return-left"></i><span>Retur</span></a>
        <a class="erp-menu {{ request()->routeIs('erp.shifts') ? 'active' : '' }}" href="{{ route('erp.shifts') }}"><i class="bi bi-clock-history"></i><span>Kasir / Shift</span></a>
        @endif

        @if(in_array(auth()->user()->role, ['owner','inventori']))
        <div class="erp-section-title">PRODUKSI</div>
        <a class="erp-menu {{ request()->routeIs('erp.bom') ? 'active' : '' }}" href="{{ route('erp.bom') }}"><i class="bi bi-diagram-3"></i><span>BOM / Formula</span></a>
        <a class="erp-menu {{ request()->routeIs('erp.production') ? 'active' : '' }}" href="{{ route('erp.production') }}"><i class="bi bi-gear"></i><span>Produksi</span></a>
        <a class="erp-menu {{ request()->routeIs('erp.material-usage') ? 'active' : '' }}" href="{{ route('erp.material-usage') }}"><i class="bi bi-box-arrow-up"></i><span>Pemakaian Bahan</span></a>
        <a class="erp-menu {{ request()->routeIs('erp.production-results') ? 'active' : '' }}" href="{{ route('erp.production-results') }}"><i class="bi bi-box-arrow-in-down"></i><span>Hasil Produksi</span></a>
        <a class="erp-menu disabled" href="javascript:void(0)" title="Modul belum tersedia"><i class="bi bi-x-octagon"></i><span>Reject</span></a>
        <a class="erp-menu {{ request()->routeIs('erp.production-cost') ? 'active' : '' }}" href="{{ route('erp.production-cost') }}"><i class="bi bi-calculator"></i><span>HPP Produksi</span></a>
        @endif

        @if(in_array(auth()->user()->role, ['owner','inventori']))
        <div class="erp-section-title">ARMADA &amp; JASA</div>
        <a class="erp-menu disabled" href="javascript:void(0)" title="Modul belum tersedia"><i class="bi bi-clipboard-check"></i><span>Order Jasa</span></a>
        <a class="erp-menu disabled" href="javascript:void(0)" title="Modul belum tersedia"><i class="bi bi-file-earmark-text"></i><span>Surat Jalan</span></a>
        <a class="erp-menu disabled" href="javascript:void(0)" title="Modul belum tersedia"><i class="bi bi-sign-turn-right"></i><span>Perjalanan</span></a>
        @endif

        @if(in_array(auth()->user()->role, ['owner','inventori']))
        <div class="erp-section-title">INVENTORI</div>
        <a class="erp-menu {{ request()->routeIs('erp.purchases') ? 'active' : '' }}" href="{{ route('erp.purchases') }}"><i class="bi bi-bag-plus"></i><span>Pembelian</span></a>
        <a class="erp-menu {{ request()->routeIs('erp.receipts') ? 'active' : '' }}" href="{{ route('erp.receipts') }}"><i class="bi bi-box-seam"></i><span>Penerimaan</span></a>
        <a class="erp-menu {{ request()->routeIs('erp.stock') ? 'active' : '' }}" href="{{ route('erp.stock') }}"><i class="bi bi-boxes"></i><span>Stok</span></a>
        <a class="erp-menu disabled" href="javascript:void(0)" title="Modul belum tersedia"><i class="bi bi-arrow-left-right"></i><span>Transfer</span></a>
        <a class="erp-menu disabled" href="javascript:void(0)" title="Modul belum tersedia"><i class="bi bi-sliders"></i><span>Adjustment</span></a>
        <a class="erp-menu {{ request()->routeIs('erp.opname') ? 'active' : '' }}" href="{{ route('erp.opname') }}"><i class="bi bi-clipboard2-check"></i><span>Stock Opname</span></a>
        @endif

        @if(in_array(auth()->user()->role, ['owner','akuntansi']))
        <div class="erp-section-title">AKUNTANSI</div>
        @foreach([
            ['journals','Jurnal','bi-journal-text'],
            ['ledger','Buku Besar','bi-book'],
            ['cashbank','Kas & Bank','bi-bank'],
            ['receivables','Piutang','bi-person-lines-fill'],
            ['payables','Hutang','bi-receipt'],
            ['cogs','HPP','bi-calculator'],
            ['profit-loss','Laba Rugi','bi-graph-up-arrow'],
            ['balance-sheet','Neraca','bi-bar-chart'],
            ['cash-flow','Arus Kas','bi-cash-stack']
        ] as $item)
        <a class="erp-menu {{ request()->routeIs('erp.'.$item[0]) ? 'active' : '' }}" href="{{ route('erp.'.$item[0]) }}">
            <i class="bi {{ $item[2] }}"></i><span>{{ $item[1] }}</span>
        </a>
        @endforeach
        @endif

        <div class="erp-section-title">LAPORAN</div>
        @foreach(['Penjualan','Pembelian','Persediaan','Produksi','Armada & Jasa','Piutang','Hutang','Keuangan'] as $label)
        <a class="erp-menu disabled" href="javascript:void(0)" title="Modul laporan belum tersedia">
            <i class="bi bi-file-earmark-bar-graph"></i><span>{{ $label }}</span>
        </a>
        @endforeach

        @if(auth()->user()->role === 'owner' || auth()->user()->role === 'admin')
        <div class="erp-section-title">PENGATURAN</div>
        @foreach(['User','Role & Hak Akses','Entitas','Konfigurasi'] as $label)
        <a class="erp-menu disabled" href="javascript:void(0)" title="Modul pengaturan belum tersedia">
            <i class="bi bi-gear"></i><span>{{ $label }}</span>
        </a>
        @endforeach
        @endif
    </div>
</aside>
<div id="erpSidebarBackdrop" class="erp-sidebar-backdrop"></div>

<div class="erp-content">
    <nav class="erp-topbar navbar navbar-expand-lg px-3 px-lg-4">
        <div class="container-fluid p-0">
            <button id="sidebarToggle" class="btn btn-outline-secondary d-lg-none me-2" type="button" aria-label="Buka menu">
                <i class="bi bi-list fs-5"></i>
            </button>
            <div class="small text-secondary d-none d-md-block">{{ config('app.name', 'Mini ERP') }}</div>
            <div class="dropdown ms-auto">
                <button class="btn btn-sm erp-user-btn dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-1"></i>{{ auth()->user()->name }} · {{ ucfirst(auth()->user()->role) }}
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text fw-semibold">{{ auth()->user()->name }}</span></li>
                    <li><span class="dropdown-item-text text-secondary">Role: {{ ucfirst(auth()->user()->role) }}</span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item" type="submit">Logout</button></form></li>
                </ul>
            </div>
        </div>
    </nav>
    <main class="container-fluid p-3 p-lg-4">
        @if(session('success'))<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif
        @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('content')
    </main>
</div>
@else
<main class="p-0">
    @yield('content')
</main>
@endif

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.3.3/js/dataTables.js"></script>
<script src="https://cdn.datatables.net/2.3.3/js/dataTables.bootstrap5.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('erpSidebar');
    const backdrop = document.getElementById('erpSidebarBackdrop');
    const toggle = document.getElementById('sidebarToggle');

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('show');
        if (backdrop) backdrop.classList.remove('show');
    }

    if (toggle) toggle.addEventListener('click', function () {
        sidebar.classList.toggle('show');
        backdrop.classList.toggle('show');
    });

    if (backdrop) backdrop.addEventListener('click', closeSidebar);
});
</script>
</body>
</html>
