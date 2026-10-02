<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order - {{ $po->po_no }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: sans-serif; font-size: 13px; color: #000; }
        .kop-header { border-bottom: 3px double #000; padding-bottom: 8px; margin-bottom: 15px; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body onload="window.print()">

<div class="container py-3">

    <div class="no-print mb-3 text-end">
        <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="bi bi-printer"></i> Cetak Faktur PO</button>
    </div>

    @php($entity = current_entity())

    <!-- KOP ENTITAS -->
    <div class="row align-items-center kop-header">
        <div class="col-8">
            <h3 class="fw-bold mb-0 text-uppercase">{{ $entity?->name ?? 'ENTITAS' }}</h3>
            <div class="small">{{ $entity?->address ?? '-' }}</div>
            <div class="small">
                Telp: {{ $entity?->phone ?? '-' }} | Email: {{ $entity?->email ?? '-' }}
            </div>
        </div>
        <div class="col-4 text-end">
            <h4 class="fw-bold text-decoration-underline mb-0">PURCHASE ORDER</h4>
            <div class="font-monospace fw-bold fs-6">NO: {{ $po->po_no }}</div>
        </div>
    </div>

    <!-- METADATA PO & SUPPLIER -->
    <div class="row g-2 mb-3">
        <div class="col-6">
            <div class="p-2 border rounded">
                <div class="fw-bold text-decoration-underline mb-1">Pemasok / Vendor:</div>
                <div class="fw-bold fs-6">{{ $po->supplier_name }}</div>
                <div>{{ $po->supplier_address ?? 'Alamat tidak tersedia' }}</div>
                <div>Telp: {{ $po->supplier_phone ?? '-' }}</div>
            </div>
        </div>
        <div class="col-6">
            <div class="p-2 border rounded">
                <table class="table table-borderless table-sm mb-0">
                    <tr><td style="width: 130px;">Tanggal PO</td><td>: {{ \Carbon\Carbon::parse($po->po_date)->format('d/m/Y') }}</td></tr>
                    <tr><td>Gudang Tujuan</td><td>: <strong>{{ $po->warehouse_name }}</strong></td></tr>
                    <tr><td>Status PO</td><td>: <strong class="text-uppercase">{{ $po->status }}</strong></td></tr>
                </table>
            </div>
        </div>
    </div>

    <!-- TABEL ITEM PO -->
    <table class="table table-bordered table-sm align-middle mb-3">
        <thead class="table-light text-center">
            <tr>
                <th style="width: 35px;">No</th>
                <th style="width: 120px;">Kode</th>
                <th>Deskripsi Barang</th>
                <th style="width: 90px;">Qty</th>
                <th style="width: 120px;">Harga (Rp)</th>
                <th style="width: 100px;">Diskon (Rp)</th>
                <th style="width: 130px;">Total (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @php $grandTotal = 0; @endphp
            @foreach($items as $idx => $i)
                @php $grandTotal += $i->total; @endphp
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
        <tfoot class="table-light fw-bold">
            <tr>
                <td colspan="6" class="text-end">GRAND TOTAL PEMBELIAN:</td>
                <td class="text-end font-monospace fs-6">Rp {{ number_format($grandTotal, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="mb-4">
        <div class="small fw-bold">Catatan / Instuksi Pengiriman:</div>
        <div class="p-2 border bg-light small">{{ $po->memo ?? 'Harap melampirkan Surat Jalan resmi saat pengiriman barang ke gudang tujuan.' }}</div>
    </div>

    <!-- TANDA TANGAN BUKTI APPROVAL -->
    <div class="row text-center mt-5">
        <div class="col-4">
            <p class="mb-5">Dibuat Oleh (Purchasing),</p>
            <p class="fw-bold text-decoration-underline">( {{ $po->creator_name }} )</p>
        </div>
        <div class="col-4">
            <p class="mb-5">Disetujui Oleh (Manager),</p>
            <p class="fw-bold text-decoration-underline">( .................... )</p>
        </div>
        <div class="col-4">
            <p class="mb-5">Konfirmasi Konfirmasi Vendor,</p>
            <p class="fw-bold text-decoration-underline">( {{ $po->supplier_name }} )</p>
        </div>
    </div>

</div>

</body>
</html>