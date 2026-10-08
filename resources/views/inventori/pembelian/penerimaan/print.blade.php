<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Bukti Penerimaan Barang - {{ $receipt->receipt_no }}</title>
    <style>
        body{font-family:Arial,sans-serif;font-size:12px;color:#222;margin:28px}
        h2{margin:0 0 4px;font-size:20px}.muted{color:#666}.meta{display:grid;grid-template-columns:130px 1fr 130px 1fr;gap:7px 12px;margin:18px 0}
        .meta-label{color:#666}.meta-value{font-weight:600}
        table{width:100%;border-collapse:collapse;margin-top:18px}th,td{border:1px solid #bbb;padding:7px}th{background:#f2f2f2}
        .right{text-align:right}.center{text-align:center}.total{font-weight:bold}.status{font-weight:bold}
        .footer{margin-top:35px;display:flex;justify-content:space-between}.signature{width:180px;text-align:center}
        @media print{body{margin:10mm}.no-print{display:none!important}}
    </style>
</head>
<body onload="window.print()">
    <div class="no-print" style="text-align:right;margin-bottom:15px">
        <button onclick="window.print()">Cetak</button>
    </div>

    <h2>BUKTI PENERIMAAN BARANG</h2>
    <div class="muted">MINI ERP ENTERPRISE</div>

    <div class="meta">
        <div class="meta-label">No. Penerimaan</div><div class="meta-value">{{ $receipt->receipt_no }}</div>
        <div class="meta-label">Tanggal</div><div class="meta-value">{{ CarbonCarbon::parse($receipt->receipt_date)->locale('id')->translatedFormat('d F Y, H:i') }}</div>

        <div class="meta-label">No. PO</div><div class="meta-value">{{ $receipt->po_no ?? '-' }}</div>
        <div class="meta-label">Status</div>
        <div class="meta-value status">
            {{ $receipt->status === 'posted' ? 'TERPOSTING' : ($receipt->status === 'cancelled' ? 'DIBATALKAN' : strtoupper($receipt->status ?? '-')) }}
        </div>

        <div class="meta-label">Supplier</div><div class="meta-value">{{ $receipt->supplier_name ?? '-' }}</div>
        <div class="meta-label">Gudang</div><div class="meta-value">{{ $receipt->warehouse_name ?? '-' }}</div>

        <div class="meta-label">Unit Bisnis</div><div class="meta-value">{{ $receipt->business_unit_name ?? '-' }}</div>
        <div class="meta-label">Catatan</div><div class="meta-value">{{ $receipt->memo ?? '-' }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th width="40">No</th>
                <th>Barang</th>
                <th width="90">Qty</th>
                <th width="100">Satuan</th>
                <th width="130">HPP</th>
                <th width="140">Nilai</th>
            </tr>
        </thead>
        <tbody>
        @php $grandTotal = 0; @endphp
        @foreach($items as $i => $item)
            @php
                $line = (float) $item->line_value;
                $grandTotal += $line;
            @endphp
            <tr>
                <td class="center">{{ $i + 1 }}</td>
                <td>{{ $item->product_code }} - {{ $item->product_name }}</td>
                <td class="right">{{ number_format((float) $item->qty, 3, ',', '.') }}</td>
                <td>{{ $item->unit_name ?? '-' }}</td>
                <td class="right">Rp {{ number_format((float) ($item->base_unit_cost ?? 0), 0, ',', '.') }}</td>
                <td class="right">Rp {{ number_format($line, 0, ',', '.') }}</td>
            </tr>
        @endforeach
        <tr class="total">
            <td colspan="5" class="right">TOTAL</td>
            <td class="right">Rp {{ number_format($grandTotal, 0, ',', '.') }}</td>
        </tr>
        </tbody>
    </table>

    <div class="footer">
        <div class="signature">Penerima<br><br><br><br>(________________)</div>
        <div class="signature">Mengetahui<br><br><br><br>(________________)</div>
    </div>
</body>
</html>