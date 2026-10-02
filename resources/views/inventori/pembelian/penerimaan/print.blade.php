<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Penerimaan {{ $receipt->receipt_no }}</title>
    <style>
        body{font-family:Arial,sans-serif;font-size:12px;color:#222;margin:30px}
        h2{margin:0 0 4px}.muted{color:#666}.meta{display:grid;grid-template-columns:130px 1fr 130px 1fr;gap:6px 12px;margin:20px 0}
        table{width:100%;border-collapse:collapse;margin-top:15px}th,td{border:1px solid #ccc;padding:7px}th{background:#f2f2f2}
        .right{text-align:right}.center{text-align:center}.total{font-weight:bold}
        @media print{body{margin:10mm}.no-print{display:none}}
    </style>
</head>
<body onload="window.print()">
    <div class="no-print" style="text-align:right;margin-bottom:15px"><button onclick="window.print()">Print</button></div>
    <h2>BUKTI PENERIMAAN BARANG</h2>
    <div class="muted">MINI ERP ENTERPRISE</div>
    <div class="meta">
        <div>No. Penerimaan</div><div><strong>{{ $receipt->receipt_no }}</strong></div>
        <div>Tanggal</div><div>{{ $receipt->formatted_date }}</div>
        <div>No. PO</div><div>{{ $receipt->po_no ?? '-' }}</div>
        <div>Status</div><div>{{ strtoupper($receipt->status ?? '-') }}</div>
        <div>Supplier</div><div>{{ $receipt->supplier_name ?? '-' }}</div>
        <div>Gudang</div><div>{{ $receipt->warehouse_name ?? '-' }}</div>
        <div>Unit Bisnis</div><div>{{ $receipt->business_unit_name ?? '-' }}</div>
        <div>Catatan</div><div>{{ $receipt->memo ?? '-' }}</div>
    </div>
    <table>
        <thead><tr><th width="40">No</th><th>Barang</th><th width="90">Qty</th><th width="100">Satuan</th><th width="130">HPP</th><th width="140">Nilai</th></tr></thead>
        <tbody>
        @php $grandTotal = 0; @endphp
        @foreach($items as $i => $item)
            @php $line = (float)$item->line_value; $grandTotal += $line; @endphp
            <tr>
                <td class="center">{{ $i + 1 }}</td>
                <td>{{ $item->product_code }} - {{ $item->product_name }}</td>
                <td class="right">{{ $item->qty }}</td>
                <td>{{ $item->unit_name ?? '-' }}</td>
                <td class="right">Rp {{ number_format($item->base_unit_cost ?? 0, 0, ',', '.') }}</td>
                <td class="right">Rp {{ number_format($line, 0, ',', '.') }}</td>
            </tr>
        @endforeach
        <tr class="total"><td colspan="5" class="right">TOTAL</td><td class="right">Rp {{ number_format($grandTotal, 0, ',', '.') }}</td></tr>
        </tbody>
    </table>
</body>
</html>