<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Penyesuaian Stok (STK-02)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #000; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body onload="window.print()">

<div class="container-fluid py-3">

    <div class="no-print mb-3 text-end">
        <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="bi bi-printer"></i> Cetak PDF</button>
    </div>

    <div class="text-center border-bottom border-2 pb-2 mb-3">
        <h4 class="fw-bold mb-0">LAPORAN REKAP PENYESUAIAN STOK (STK-02)</h4>
        <div class="text-muted small">Periode: {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}</div>
    </div>

    <table class="table table-bordered table-sm align-middle">
        <thead class="table-light text-center">
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>No. Adjustment</th>
                <th>Ref Opname</th>
                <th>Gudang</th>
                <th>Unit Bisnis</th>
                <th>Total Nilai (Rp)</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($adjustments as $idx => $adj)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td class="text-center">{{ \Carbon\Carbon::parse($adj->adjustment_date)->format('d/m/Y') }}</td>
                    <td class="font-monospace text-center fw-bold">{{ $adj->adjustment_no }}</td>
                    <td class="font-monospace text-center">{{ $adj->opname_no ?? 'Manual' }}</td>
                    <td>{{ $adj->warehouse_name }}</td>
                    <td>{{ $adj->business_unit_name ?? '-' }}</td>
                    <td class="text-end font-monospace">Rp {{ number_format($adj->total_amount, 0, ',', '.') }}</td>
                    <td class="text-center font-monospace">{{ strtoupper($adj->status) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

</div>

</body>
</html>