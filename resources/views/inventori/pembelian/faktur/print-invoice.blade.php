<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Faktur Pembelian - {{ $p->invoice_no }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Courier New', Courier, monospace; font-size: 13px; color: #000; }
        .kop-header { border-bottom: 2px dashed #000; padding-bottom: 8px; margin-bottom: 12px; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body onload="window.print()">

<div class="container py-3">

    <div class="no-print mb-3 text-end">
        <button onclick="window.print()" class="btn btn-primary btn-sm">Cetak Faktur (Dotmatrix)</button>
    </div>

    <div class="row align-items-center kop-header">
        <div class="col-8">
            <h4 class="fw-bold mb-0">{{ $p->business_unit_name ?? 'MINI ERP SYSTEM' }}</h4>
            <div>FAKTUR PEMBELIAN BARANG / TAGIHAN SUPPLIER</div>
        </div>
        <div class="col-4 text-end">
            <div class="fw-bold font-monospace fs-6">NO: {{ $p->invoice_no }}</div>
            <div>Ref Supplier: {{ $p->supplier_invoice_no ?? '-' }}</div>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-6">
            <table class="table table-borderless table-sm mb-0">
                <tr><td style="width: 140px;">Supplier / Vendor</td><td>: <strong>{{ $p->supplier_name }}</strong></td></tr>
                <tr><td>Alamat Supplier</td><td>: {{ $p->supplier_address ?? '-' }}</td></tr>
                <tr><td>Cara Bayar</td><td>: <strong class="text-uppercase">{{ $p->payment_type }}</strong></td></tr>
            </table>
        </div>
        <div class="col-6">
            <table class="table table-borderless table-sm mb-0">
                <tr><td style="width: 140px;">Tanggal Faktur</td><td>: {{ \Carbon\Carbon::parse($p->purchase_date)->format('d/m/Y') }}</td></tr>
                <tr><td>Jatuh Tempo</td><td>: {{ $p->due_date ? \Carbon\Carbon::parse($p->due_date)->format('d/m/Y') : '-' }}</td></tr>
                <tr><td>Gudang Tujuan</td><td>: <strong>{{ $p->warehouse_name }}</strong></td></tr>
            </table>
        </div>
    </div>

    <table class="table table-bordered table-sm align-middle mb-3">
        <thead class="table-light text-center">
            <tr>
                <th style="width: 30px;">No</th>
                <th style="width: 120px;">Kode</th>
                <th>Nama Barang</th>
                <th style="width: 80px;">Qty</th>
                <th style="width: 120px;">Harga (Rp)</th>
                <th style="width: 100px;">Diskon (Rp)</th>
                <th style="width: 130px;">Subtotal (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $idx => $i)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td class="font-monospace text-center">{{ $i->product_code }}</td>
                    <td>{{ $i->product_name }}</td>
                    <td class="text-center fw-bold">{{ number_format($i->qty, 2, ',', '.') }} {{ $i->unit_name }}</td>
                    <td class="text-end font-monospace">{{ number_format($i->unit_price, 0, ',', '.') }}</td>
                    <td class="text-end font-monospace">{{ number_format($i->discount, 0, ',', '.') }}</td>
                    <td class="text-end font-monospace fw-bold">{{ number_format($i->total, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot class="fw-bold">
            <tr><td colspan="6" class="text-end">SUBTOTAL:</td><td class="text-end font-monospace">Rp {{ number_format($p->subtotal, 0, ',', '.') }}</td></tr>
            <tr><td colspan="6" class="text-end">PAJAK (PPN):</td><td class="text-end font-monospace">Rp {{ number_format($p->tax_amount, 0, ',', '.') }}</td></tr>
            <tr><td colspan="6" class="text-end fs-6">GRAND TOTAL TAGIHAN:</td><td class="text-end font-monospace fs-6">Rp {{ number_format($p->grand_total, 0, ',', '.') }}</td></tr>
        </tfoot>
    </table>

    <div class="row text-center mt-5">
        <div class="col-4"><p class="mb-5">Bagian Keuangan,</p><p class="fw-bold">( .................... )</p></div>
        <div class="col-4"><p class="mb-5">Penerima Gudang,</p><p class="fw-bold">( .................... )</p></div>
        <div class="col-4"><p class="mb-5">Pemasok / Supplier,</p><p class="fw-bold">( {{ $p->supplier_name }} )</p></div>
    </div>

</div>

</body>
</html>