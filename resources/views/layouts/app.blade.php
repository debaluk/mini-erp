<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ config('app.name','Mini ERP') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="{{ route('dashboard') }}">Mini ERP</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topMenu"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="topMenu">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="{{ route('dashboard') }}">Dashboard</a></li>
                <li class="nav-item dropdown"><a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">MASTER</a><ul class="dropdown-menu">
                    @foreach(['products'=>'Produk','customers'=>'Customer','suppliers'=>'Supplier','warehouses'=>'Gudang','units'=>'Satuan','tariffs'=>'Tarif','vehicles'=>'Kendaraan','drivers'=>'Driver'] as $k=>$v)<li><a class="dropdown-item" href="{{ route('master.module',$k) }}">{{ $v }}</a></li>@endforeach
                </ul></li>
                <li class="nav-item dropdown"><a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">PENJUALAN / POS</a><ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="{{ route('erp.module','pos') }}">POS</a></li><li><a class="dropdown-item" href="{{ route('erp.module','sales') }}">Transaksi Penjualan</a></li><li><a class="dropdown-item" href="{{ route('erp.module','payments') }}">Pembayaran</a></li><li><a class="dropdown-item" href="{{ route('erp.module','shifts') }}">Shift Kasir</a></li>
                </ul></li>
                <li class="nav-item dropdown"><a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">PEMBELIAN</a><ul class="dropdown-menu"><li><a class="dropdown-item" href="{{ route('erp.module','purchases') }}">Pembelian</a></li><li><a class="dropdown-item" href="{{ route('erp.module','receipts') }}">Penerimaan Barang</a></li><li><a class="dropdown-item" href="{{ route('erp.module','payables') }}">Hutang</a></li></ul></li>
                <li class="nav-item dropdown"><a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">INVENTORI</a><ul class="dropdown-menu"><li><a class="dropdown-item" href="{{ route('erp.module','stock') }}">Stok</a></li><li><a class="dropdown-item" href="{{ route('erp.module','movements') }}">Mutasi Stok</a></li><li><a class="dropdown-item" href="{{ route('erp.module','opname') }}">Stock Opname</a></li></ul></li>
                <li class="nav-item dropdown"><a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">PRODUKSI BATAKO</a><ul class="dropdown-menu"><li><a class="dropdown-item" href="{{ route('erp.module','bom') }}">Formula / BOM</a></li><li><a class="dropdown-item" href="{{ route('erp.module','production') }}">Produksi</a></li><li><a class="dropdown-item" href="{{ route('erp.module','production-results') }}">Hasil Produksi</a></li><li><a class="dropdown-item" href="{{ route('erp.module','material-usage') }}">Pemakaian Bahan</a></li><li><a class="dropdown-item" href="{{ route('erp.module','production-cost') }}">HPP Produksi</a></li></ul></li>
                <li class="nav-item dropdown"><a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">ARMADA</a><ul class="dropdown-menu"><li><a class="dropdown-item" href="{{ route('erp.module','fleet') }}">Kendaraan</a></li><li><a class="dropdown-item" href="{{ route('erp.module','deliveries') }}">Pengiriman</a></li><li><a class="dropdown-item" href="{{ route('erp.module','operations') }}">Operasional Armada</a></li><li><a class="dropdown-item" href="{{ route('erp.module','fleet-costs') }}">Biaya Armada</a></li></ul></li>
                <li class="nav-item dropdown"><a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">AKUNTANSI</a><ul class="dropdown-menu"><li><a class="dropdown-item" href="{{ route('erp.module','journals') }}">Jurnal</a></li><li><a class="dropdown-item" href="{{ route('erp.module','ledger') }}">Buku Besar</a></li><li><a class="dropdown-item" href="{{ route('erp.module','payables') }}">Hutang</a></li><li><a class="dropdown-item" href="{{ route('erp.module','receivables') }}">Piutang</a></li><li><a class="dropdown-item" href="{{ route('erp.module','cashbank') }}">Kas & Bank</a></li><li><a class="dropdown-item" href="{{ route('erp.module','cogs') }}">HPP</a></li><li><a class="dropdown-item" href="{{ route('erp.module','profit-loss') }}">Laba Rugi</a></li><li><a class="dropdown-item" href="{{ route('erp.module','balance-sheet') }}">Neraca</a></li><li><a class="dropdown-item" href="{{ route('erp.module','cash-flow') }}">Arus Kas</a></li></ul></li>
            </ul>
            <div class="dropdown"><button class="btn btn-outline-light btn-sm dropdown-toggle" data-bs-toggle="dropdown">{{ auth()->user()->name }} · {{ ucfirst(auth()->user()->role) }}</button><ul class="dropdown-menu dropdown-menu-end"><li><span class="dropdown-item-text">Entitas Utama</span></li><li><hr class="dropdown-divider"></li><li><form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item" type="submit">Logout</button></form></li></ul></div>
        </div>
    </div>
</nav>
<main class="container-fluid p-4">
    @if(session('success'))<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
