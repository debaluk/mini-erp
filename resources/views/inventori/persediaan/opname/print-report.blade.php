<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Hasil Stock Opname - {{ $opname->opname_no }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Courier New', Courier, monospace; font-size: 13px; color: #000; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body onload="window.print()">

<div class="container py-3">

    <div class="no-print mb-3 text-end">
        <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="bi bi-printer"></i> Cetak Laporan</button>
    </div>

    <div class="text-center border-bottom border-2 pb-2 mb-3">
        <h4 class="fw-bold mb-0">LAPORAN HASIL STOCK OPNAME (VARIANCE REPORT)</h4>
        <div class="text-uppercase fw-semibold">{{ $opname->business_unit_name ?? 'MINI ERP SYSTEM' }}</div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-6">
            <table class="table table-borderless table-sm mb-0">
                <tr><td style="width: 130px;">No. Opname</td><td>: <strong>{{ $opname->opname_no }}</strong></td></tr>
                <tr><td>Tanggal Opname</td><td>: {{ \Carbon\Carbon::parse($opname->opname_date)->format('d/m/Y') }}</td></tr>
            </table>
        </div>
        <div class="col-6">
            <table class="table table-borderless table-sm mb-0">
                <tr><td style="width: 130px;">Lokasi Gudang</td><td>: <strong>{{ $opname->warehouse_name }}</strong></td></tr>
                <tr><td>Petugas Opname</td><td>: {{ $opname->creator_name }}</td></tr>
            </table>
        </div>
    </div>

    <table class="table table-bordered table-sm align-middle mb-4">
        <thead class="table-light text-center">
            <tr>
                <th style="width: 40px;">No</th>
                <th style="width: 130px;">Kode Barang</th>
                <th>Nama Produk</th>
                <th style="width: 110px;">Stok Sistem</th>
                <th style="width: 110px;">Stok Fisik</th>
                <th style="width: 110px;">Selisih</th>
                <th style="width: 80px;">Satuan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $idx => $i)
                @php $diff = (float) $i->difference; @endphp
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td class="font-monospace text-center">{{ $i->product_code }}</td>
                    <td>{{ $i->product_name }}</td>
                    <td class="text-center">{{ number_format($i->system_qty, 2, ',', '.') }}</td>
                    <td class="text-center fw-bold">{{ number_format($i->actual_qty, 2, ',', '.') }}</td>
                    <td class="text-center fw-bold {{ $diff < 0 ? 'text-danger' : ($diff > 0 ? 'text-warning' : '') }}">
                        {{ $diff > 0 ? '+' . number_format($diff, 2, ',', '.') : number_format($diff, 2, ',', '.') }}
                    </td>
                    <td class="text-center">{{ $i->unit_name }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="row text-center mt-5">
        <div class="col-6">
            <p class="mb-5">Petugas Opname Gudang,</p>
            <p class="fw-bold mb-0">( {{ $opname->creator_name }} )</p>
        </div>
        <div class="col-6">
            <p class="mb-5">Mengetahui / Supervisor,</p>
            <p class="fw-bold mb-0">( .................... )</p>
        </div>
    </div>

</div>

</body>
</html>