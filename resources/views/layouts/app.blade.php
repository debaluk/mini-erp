<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Mini ERP') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/2.3.3/css/dataTables.bootstrap5.css" rel="stylesheet">
    <style>
        .navbar .dropdown-menu { min-width: 230px; }
        .navbar .dropdown-menu .dropdown-header { font-weight: 700; color: var(--bs-primary); }
        main { min-height: calc(100vh - 56px); }
        /* Compact ERP forms: consistent Bootstrap sizing across all modules */
        main .form-label { font-size: .8rem; margin-bottom: .2rem; font-weight: 600; }
        main .form-control,
        main .form-select {
            min-height: 32px;
            height: 32px;
            padding: .2rem .5rem;
            font-size: .82rem;
            line-height: 1.2;
        }
        main textarea.form-control { height: auto; min-height: 52px; }
        main .btn:not(.btn-close) { padding: .3rem .65rem; font-size: .8rem; line-height: 1.2; }
        main .table { font-size: .82rem; }
        main .table thead th { font-size: .82rem; font-weight: 600; padding: .45rem .5rem; }
        main .table tbody td { font-size: .82rem; padding: .4rem .5rem; }
        main .dataTables_wrapper,
        main .dataTables_wrapper .dt-info,
        main .dataTables_wrapper .dt-paging,
        main .dataTables_wrapper .dt-length,
        main .dataTables_wrapper .dt-search { font-size: .82rem; }
        main .dataTables_wrapper .dt-length select,
        main .dataTables_wrapper .dt-search input { font-size: .82rem; padding: .2rem .45rem; min-height: 30px; }
        main .dataTables_wrapper .dt-paging .pagination {
            margin: 0 !important;
            gap: 1px !important;
        }
        main .dataTables_wrapper .dt-paging .pagination .page-item,
        main .dataTables_wrapper .dt-paging .pagination .page-link {
            margin: 0 !important;
            padding: 0 !important;
            width: 22px !important;
            min-width: 22px !important;
            max-width: 22px !important;
            height: 22px !important;
            min-height: 22px !important;
            font-size: .65rem !important;
            line-height: 20px !important;
            text-align: center !important;
        }
        main .dataTables_wrapper .dt-paging .pagination .page-link {
            display: block !important;
        }
        main .card-body { padding: .75rem; }
        main .card-header { padding: .5rem .75rem; }
        main form.row { --bs-gutter-x: .6rem; --bs-gutter-y: .45rem; }
    </style>
</head>
<body class="bg-light">
@if(!request()->routeIs('erp.pos'))
<nav class="navbar navbar-expand-xl navbar-dark bg-dark sticky-top shadow-sm">
    <div class="container-fluid px-3">
        <a class="navbar-brand fw-bold" href="{{ route('dashboard') }}">Mini ERP</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topMenu" aria-controls="topMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="topMenu">
            <ul class="navbar-nav me-auto mb-2 mb-xl-0">
                <li class="nav-item"><a class="nav-link" href="{{ route('dashboard') }}">Dashboard</a></li>

                @if(in_array(auth()->user()->role, ['owner','admin']))
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">MASTER</a>
                    <ul class="dropdown-menu">
                        @foreach(['products'=>'Produk','customers'=>'Customer','suppliers'=>'Supplier','warehouses'=>'Gudang','units'=>'Satuan','unit-conversions'=>'Konversi Satuan','tariffs'=>'Tarif','vehicles'=>'Kendaraan','drivers'=>'Driver'] as $route=>$label)
                            <li><a class="dropdown-item" href="{{ route('master.'.$route) }}">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </li>
                @endif

                @if(in_array(auth()->user()->role, ['owner','kasir']))
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">PENJUALAN / POS</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('erp.pos') }}">POS</a></li>
                        <li><a class="dropdown-item" href="{{ route('erp.sales') }}">Transaksi Penjualan</a></li>
                        <li><a class="dropdown-item" href="{{ route('erp.payments') }}">Pembayaran</a></li>
                        <li><a class="dropdown-item" href="{{ route('erp.shifts') }}">Shift Kasir</a></li>
                    </ul>
                </li>
                @endif

                @if(in_array(auth()->user()->role, ['owner','inventori','akuntansi']))
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">PEMBELIAN</a>
                    <ul class="dropdown-menu">
                        @if(in_array(auth()->user()->role, ['owner','inventori']))
                            <li><a class="dropdown-item" href="{{ route('erp.purchases') }}">Pembelian</a></li>
                            <li><a class="dropdown-item" href="{{ route('erp.receipts') }}">Penerimaan Barang</a></li>
                        @endif
                        <li><a class="dropdown-item" href="{{ route('erp.payables') }}">Hutang</a></li>
                    </ul>
                </li>
                @endif

                @if(in_array(auth()->user()->role, ['owner','inventori']))
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">INVENTORI</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('erp.stock') }}">Stok</a></li>
                        <li><a class="dropdown-item" href="{{ route('erp.movements') }}">Mutasi Stok</a></li>
                        <li><a class="dropdown-item" href="{{ route('erp.opname') }}">Stock Opname</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">PRODUKSI BATAKO</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('erp.bom') }}">Formula / BOM</a></li>
                        <li><a class="dropdown-item" href="{{ route('erp.production') }}">Produksi</a></li>
                        <li><a class="dropdown-item" href="{{ route('erp.production-results') }}">Hasil Produksi</a></li>
                        <li><a class="dropdown-item" href="{{ route('erp.material-usage') }}">Pemakaian Bahan</a></li>
                        <li><a class="dropdown-item" href="{{ route('erp.production-cost') }}">HPP Produksi</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">ARMADA</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('erp.fleet') }}">Kendaraan</a></li>
                        <li><a class="dropdown-item" href="{{ route('erp.deliveries') }}">Pengiriman</a></li>
                        <li><a class="dropdown-item" href="{{ route('erp.operations') }}">Operasional Armada</a></li>
                        <li><a class="dropdown-item" href="{{ route('erp.fleet-costs') }}">Biaya Armada</a></li>
                    </ul>
                </li>
                @endif

                @if(in_array(auth()->user()->role, ['owner','akuntansi']))
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">AKUNTANSI</a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('erp.journals') }}">Jurnal</a></li>
                        <li><a class="dropdown-item" href="{{ route('erp.ledger') }}">Buku Besar</a></li>
                        <li><a class="dropdown-item" href="{{ route('erp.payables') }}">Hutang</a></li>
                        <li><a class="dropdown-item" href="{{ route('erp.receivables') }}">Piutang</a></li>
                        <li><a class="dropdown-item" href="{{ route('erp.cashbank') }}">Kas & Bank</a></li>
                        <li><a class="dropdown-item" href="{{ route('erp.cogs') }}">HPP</a></li>
                        <li><a class="dropdown-item" href="{{ route('erp.profit-loss') }}">Laba Rugi</a></li>
                        <li><a class="dropdown-item" href="{{ route('erp.balance-sheet') }}">Neraca</a></li>
                        <li><a class="dropdown-item" href="{{ route('erp.cash-flow') }}">Arus Kas</a></li>
                    </ul>
                </li>
                @endif
            </ul>

            <div class="dropdown">
                <button class="btn btn-outline-light btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                    {{ auth()->user()->name }} · {{ ucfirst(auth()->user()->role) }}
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text fw-semibold">{{ auth()->user()->name }}</span></li>
                    <li><span class="dropdown-item-text text-secondary">Role: {{ ucfirst(auth()->user()->role) }}</span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item" type="submit">Logout</button></form></li>
                </ul>
            </div>
        </div>
    </div>
</nav>
@endif

<main class="{{ request()->routeIs('erp.pos') ? 'p-0' : 'container-fluid p-3 p-lg-4' }}">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @yield('content')
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.3.3/js/dataTables.js"></script>
<script src="https://cdn.datatables.net/2.3.3/js/dataTables.bootstrap5.js"></script>
</body>
</html>
