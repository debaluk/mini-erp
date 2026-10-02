<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bukti Mutasi Barang - {{ $transfer->transfer_no }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Courier New', Courier, monospace; font-size: 13px; color: #000; }
        .border-dashed { border-style: dashed !important; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body onload="window.print()">

<div class="container py-3">

    <div class="no-print mb-3 text-end">
        <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="bi bi-printer"></i> Cetak Dokumen</button>
    </div>

    <!-- Header Surat Jalan Mutasi -->
    <div class="text-center border-bottom border-2 pb-2 mb-3">
        <h4 class="fw-bold mb-0">SURAT JALAN / BUKTI MUTASI BARANG</h4>
        <div class="text-uppercase fw-semibold">{{ $transfer->business_unit_name ?? 'MINI ERP SYSTEM' }}</div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-6">
            <table class="table table-borderless table-sm mb-0">
                <tr><td style="width: 130px;">No. Mutasi</td><td>: <strong>{{ $transfer->transfer_no }}</strong></td></tr>
                <tr><td>Tanggal Mutasi</td><td>: {{ \Carbon\Carbon::parse($transfer->transfer_date)->format('d/m/Y') }}</td></tr>
                <tr><td>Petunjuk/Memo</td><td>: {{ $transfer->memo ?? '-' }}</td></tr>
            </table>
        </div>
        <div class="col-6">
            <table class="table table-borderless table-sm mb-0">
                <tr><td style="width: 130px;">Gudang Asal</td><td>: <strong>{{ $transfer->from_warehouse_name }}</strong></td></tr>
                <tr><td>Gudang Tujuan</td><td>: <strong>{{ $transfer->to_warehouse_name }}</strong></td></tr>
                <tr><td>Status Barang</td><td>: <span class="text-uppercase fw-bold">{{ $transfer->status }}</span></td></tr>
            </table>
        </div>
    </div>

    <!-- Table Items -->
    <table class="table table-bordered table-sm align-middle mb-4">
        <thead class="table-light text-center">
            <tr>
                <th style="width: 40px;">No</th>
                <th style="width: 140px;">Kode Barang</th>
                <th>Deskripsi Produk / Nama Barang</th>
                <th style="width: 120px;">Qty Kirim</th>
                <th style="width: 100px;">Satuan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $idx => $i)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td class="font-monospace text-center">{{ $i->product_code }}</td>
                    <td>{{ $i->product_name }}</td>
                    <td class="text-center fw-bold">{{ number_format($i->quantity, 2, ',', '.') }}</td>
                    <td class="text-center">{{ $i->unit_name }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Tanda Tangan Dual Approval -->
    <div class="row text-center mt-5">
        <div class="col-4">
            <p class="mb-5">Pengaju / Pembuat,</p>
            <p class="fw-bold mb-0">({{ $transfer->creator_name }})</p>
            <small class="text-muted">Admin System</small>
        </div>
        <div class="col-4">
            <p class="mb-5">Gudang Pengirim,</p>
            <p class="fw-bold mb-0">({{ $transfer->sender_approver_name ?? '....................' }})</p>
            <small class="text-muted">Yang Menyerahkan</small>
        </div>
        <div class="col-4">
            <p class="mb-5">Gudang Penerima,</p>
            <p class="fw-bold mb-0">({{ $transfer->receiver_approver_name ?? '....................' }})</p>
            <small class="text-muted">Yang Menerima Fisik</small>
        </div>
    </div>

</div>

</body>
</html>