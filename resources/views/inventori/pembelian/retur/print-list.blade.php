<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Retur Pembelian</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color:#000; }
        .container { width: 96%; margin: 0 auto; }
        .kop { border-bottom: 3px double #000; padding-bottom: 8px; margin-bottom: 15px; }
        .center { text-align:center; }
        table { width:100%; border-collapse:collapse; }
        th, td { border:1px solid #000; padding:5px; }
        th { background:#eee; text-align:center; }
        .right { text-align:right; }
        .no-print { margin-bottom:12px; text-align:right; }
        @media print { .no-print { display:none !important; } }
    </style>
</head>
<body onload="window.print()">
<div class="container">
    <div class="no-print"><button onclick="window.print()">Cetak</button></div>

    <div class="kop center">
        <h2 style="margin:0 0 4px;">{{ $entity->name ?? 'ENTITAS' }}</h2>
        <div>{{ $entity->address ?? '-' }}</div>
        <div>Telp: {{ $entity->phone ?? '-' }} | Email: {{ $entity->email ?? '-' }}</div>
    </div>

    <div class="center">
        <h2 style="margin:0 0 8px;">LAPORAN RETUR PEMBELIAN</h2>
        <div>Unit Bisnis: <strong>{{ $businessUnitName ?: 'Semua Unit Bisnis' }}</strong></div>
        <div>Periode: {{ date('d/m/Y', strtotime($startDate)) }} s/d {{ date('d/m/Y', strtotime($endDate)) }}</div>
        <div>Tanggal Cetak: {{ date('d/m/Y H:i') }}</div>
    </div>

    <table style="margin-top:15px;">
        <thead>
            <tr>
                <th>No</th><th>No. Retur</th><th>Tanggal</th><th>No. Faktur</th><th>Unit Bisnis</th><th>Supplier</th><th>Gudang</th><th>Qty</th><th>Total</th><th>Status</th>
            </tr>
        </thead>
        <tbody>
        @forelse($returns as $i => $row)
            <tr>
                <td class="center">{{ $i + 1 }}</td>
                <td>{{ $row->return_no }}</td>
                <td class="center">{{ date('d/m/Y', strtotime($row->return_date)) }}</td>
                <td>{{ $row->invoice_no ?? '-' }}</td>
                <td>{{ $row->business_unit_name ?? '-' }}</td>
                <td>{{ $row->supplier_name ?? '-' }}</td>
                <td>{{ $row->warehouse_name ?? '-' }}</td>
                <td class="right">{{ number_format($row->return_qty, 2, ',', '.') }}</td>
                <td class="right">Rp {{ number_format($row->total, 0, ',', '.') }}</td>
                <td class="center">{{ strtoupper($row->status) }}</td>
            </tr>
        @empty
            <tr><td colspan="10" class="center">Tidak ada data retur pembelian.</td></tr>
        @endforelse
        </tbody>
        <tfoot>
            <tr><th colspan="8" class="right">TOTAL</th><th class="right">Rp {{ number_format($returns->sum('total'), 0, ',', '.') }}</th><th></th></tr>
        </tfoot>
    </table>
</div>
</body>
</html>