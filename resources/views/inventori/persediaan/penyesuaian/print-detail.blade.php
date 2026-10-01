<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Voucher Penyesuaian Stok - {{ $adj->adjustment_no }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Courier New', Courier, monospace; font-size: 13px; color: #000; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body onload="window.print()">

<div class="container py-3">

    <div class="no-print mb-3 text-end">
        <button onclick="window.print()" class="btn btn-primary btn-sm">Cetak Voucher</button>
    </div>

    <div class="text-center border-bottom border-2 pb-2 mb-3">
        <h4 class="fw-bold mb-0">BUKTI PENYESUAIAN STOK (STOCK ADJUSTMENT)</h4>
        <div>{{ $adj->business_unit_name ?? 'MINI ERP SYSTEM' }}</div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-6">
            <table class="table table-borderless table-sm mb-0">
                <tr><td style="width: 140px;">No. Adjustment</td><td>: <strong>{{ $adj->adjustment_no }}</strong></td></tr>
                <tr><td>Ref Stock Opname</td><td>: {{ $adj->opname_no ?? 'Manual / Insidental' }}</td></tr>
                <tr><td>Tanggal</td><td>: {{ \Carbon\Carbon::parse($adj->adjustment_date)->format('d/m/Y') }}</td></tr>
            </table>
        </div>
        <div class="col-6">
            <table class="table table-borderless table-sm mb-0">
                <tr><td style="width: 140px;">Lokasi Gudang</td><td>: <strong>{{ $adj->warehouse_name }}</strong></td></tr>
                <tr><td>Petugas Pembuat</td><td>: {{ $adj->creator_name }}</td></tr>
                <tr><td>Status</td><td>: <strong>{{ strtoupper($adj->status) }}</strong></td></tr>
            </table>
        </div>
    </div>

    <table class="table table-bordered table-sm align-middle mb-4">
        <thead class="table-light text-center">
            <tr>
                <th style="width: 30px;">No</th>
                <th style="width: 120px;">Kode</th>
                <th>Nama Barang</th>
                <th style="width: 90px;">System</th>
                <th style="width: 90px;">Adjust</th>
                <th style="width: 90px;">Final</th>
                <th style="width: 110px;">HPP (Rp)</th>
                <th style="width: 120px;">Total (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $idx => $i)
                @php $qty = (float) $i->adjustment_qty; @endphp
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td class="font-monospace text-center">{{ $i->product_code }}</td>
                    <td>{{ $i->product_name }}</td>
                    <td class="text-center">{{ number_format($i->system_qty, 2, ',', '.') }}</td>
                    <td class="text-center fw-bold">{{ $qty > 0 ? '+' . number_format($qty, 2, ',', '.') : number_format($qty, 2, ',', '.') }}</td>
                    <td class="text-center fw-bold">{{ number_format($i->final_qty, 2, ',', '.') }}</td>
                    <td class="text-end font-monospace">{{ number_format($i->unit_cost, 0, ',', '.') }}</td>
                    <td class="text-end font-monospace fw-bold">{{ number_format($i->total_cost, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="row text-center mt-5">
        <div class="col-4">
            <p class="mb-5">Petugas Gudang,</p>
            <p class="fw-bold">( {{ $adj->creator_name }} )</p>
        </div>
        <div class="col-4">
            <p class="mb-5">Supervisor,</p>
            <p class="fw-bold">( .................... )</p>
        </div>
        <div class="col-4">
            <p class="mb-5">Accounting / Owner,</p>
            <p class="fw-bold">( .................... )</p>
        </div>
    </div>

</div>

</body>
</html>