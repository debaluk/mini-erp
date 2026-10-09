<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Retur Pembelian - {{ $return->return_no }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #000; }
        .container { width: 94%; margin: 0 auto; }
        .kop { border-bottom: 3px double #000; padding-bottom: 8px; margin-bottom: 14px; }
        .row { display: flex; justify-content: space-between; gap: 20px; }
        .col { flex: 1; }
        .right { text-align: right; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 6px; }
        th { background: #eee; text-align: center; }
        .no-border td { border: 0; padding: 2px 0; }
        .total { font-weight: bold; }
        .no-print { margin-bottom: 12px; }
        @media print { .no-print { display:none !important; } }
    </style>
</head>
<body onload="window.print()">
<div class="container">
    <div class="no-print right">
        <button onclick="window.print()">Cetak</button>
    </div>

    <div class="kop row">
        <div class="col">
            <h2 style="margin:0 0 4px;">{{ $entity->name ?? 'ENTITAS' }}</h2>
            <div>{{ $entity->address ?? '-' }}</div>
            <div>Telp: {{ $entity->phone ?? '-' }} | Email: {{ $entity->email ?? '-' }}</div>
        </div>
        <div class="col right">
            <h2 style="margin:0;">RETUR PEMBELIAN</h2>
            <strong>No: {{ $return->return_no }}</strong>
        </div>
    </div>

    <div class="row" style="margin-bottom:14px;">
        <div class="col">
            <strong>Supplier</strong><br>
            {{ $return->supplier_name ?? '-' }}<br>
            {{ $return->supplier_address ?? '-' }}<br>
            Telp: {{ $return->supplier_phone ?? '-' }}
        </div>
        <div class="col">
            <table class="no-border">
                <tr><td style="width:130px;">Tanggal Retur</td><td>: {{ date('d/m/Y', strtotime($return->return_date)) }}</td></tr>
                <tr><td>Penerimaan Sumber</td><td>: {{ $return->source_receipt_no ?? '-' }}</td></tr>
                <tr><td>Unit Bisnis</td><td>: {{ $return->business_unit_name ?? '-' }}</td></tr>
                <tr><td>Gudang</td><td>: {{ $return->warehouse_name ?? '-' }}</td></tr>
                <tr><td>Status</td><td>: {{ $return->status === 'posted' ? 'Terposting' : ($return->status === 'cancelled' ? 'Dibatalkan' : 'Draft') }}</td></tr>
            </table>
        </div>
    </div>

    <table>
        <thead>
            <tr><th>No</th><th>Kode</th><th>Nama Barang</th><th>Qty</th><th>Kondisi</th><th>Harga Pokok Pembelian/Unit</th><th>Total</th></tr>
        </thead>
        <tbody>
        @foreach($items as $i => $item)
            <tr>
                <td style="text-align:center;">{{ $i + 1 }}</td>
                <td>{{ $item->product_code }}</td>
                <td>{{ $item->product_name }}</td>
                <td style="text-align:right;">{{ number_format($item->qty, 2, ',', '.') }} {{ $item->unit_name }}</td>
                <td style="text-align:center;">{{ $item->condition === 'reject' ? 'RUSAK' : 'BAGUS' }}</td>
                <td style="text-align:right;">Rp {{ number_format($item->unit_value, 0, ',', '.') }}</td>
                <td style="text-align:right;">Rp {{ number_format($item->return_value, 0, ',', '.') }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr class="total"><td colspan="6" style="text-align:right;">TOTAL RETUR</td><td style="text-align:right;">Rp {{ number_format($return->total, 0, ',', '.') }}</td></tr>
        </tfoot>
    </table>

    <div style="margin-top:12px;"><strong>Alasan Retur:</strong> {{ $return->reason ?: '-' }}</div>

    <div class="row" style="margin-top:55px; text-align:center;">
        <div class="col">Dibuat Oleh<br><br><br><strong>{{ $return->user_name ?? '-' }}</strong></div>
        <div class="col">Diterima / Dikonfirmasi Supplier<br><br><br><strong>{{ $return->supplier_name ?? '-' }}</strong></div>
    </div>
</div>
</body>
</html>