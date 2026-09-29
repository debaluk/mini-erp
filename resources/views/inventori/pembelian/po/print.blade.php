<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak PO - {{ $po->po_no }}</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 25px;
            background: #fff;
        }
        .kop-table {
            width: 100%;
            border-bottom: 2px solid #1a365d;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }
        .kop-title {
            font-size: 16pt;
            font-weight: bold;
            color: #1a365d;
            text-transform: uppercase;
        }
        .kop-address {
            font-size: 9pt;
            color: #666;
            margin-top: 3px;
        }
        .po-header-table {
            width: 100%;
            margin-bottom: 20px;
        }
        .po-header-table td {
            vertical-align: top;
            font-size: 10pt;
        }
        .doc-title-box {
            background-color: #1a365d;
            color: #fff;
            padding: 6px 12px;
            font-size: 13pt;
            font-weight: bold;
            text-align: right;
            border-radius: 3px;
            margin-bottom: 10px;
        }
        .info-label {
            font-weight: bold;
            color: #4a5568;
            width: 110px;
            display: inline-block;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            background-color: #f1f5f9;
            color: #1e293b;
            font-weight: bold;
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            font-size: 10pt;
            text-transform: uppercase;
        }
        .items-table td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            font-size: 10pt;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .fw-bold { font-weight: bold; }
        
        .terbilang-box {
            background-color: #f8fafc;
            border: 1px dashed #cbd5e1;
            padding: 8px 12px;
            font-style: italic;
            font-size: 9.5pt;
            margin-bottom: 20px;
        }

        .summary-table {
            width: 100%;
            margin-bottom: 25px;
        }
        
        .signature-table {
            width: 100%;
            margin-top: 30px;
            text-align: center;
        }
        .signature-table td {
            width: 33%;
            vertical-align: top;
            font-size: 10pt;
        }
        .signature-space {
            height: 70px;
        }

        @media print {
            body { padding: 0; background: #fff; }
            .container { border: none; padding: 0; max-width: 100%; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="no-print" style="max-width: 800px; margin: 0 auto 15px auto; text-align: right;">
    <button onclick="window.print()" style="background: #2563eb; color: white; border: none; padding: 8px 16px; font-weight: bold; border-radius: 4px; cursor: pointer;">
        🖨️ Cetak / Print Document PO
    </button>
</div>

<div class="container">
    <!-- Kop Perusahaan / Entitas -->
    <table class="kop-table">
        <tr>
            <td>
                <div class="kop-title">{{ $po->entity->name ?? 'PERUSAHAAN ERP' }}</div>
                <div class="kop-address">
                    Unit Bisnis: {{ $po->businessUnit->name ?? '-' }}<br>
                    Alamat / Kontak: {{ $po->businessUnit->address ?? 'Gedung Utama ERP, Jakarta' }}
                </div>
            </td>
            <td class="text-right" style="vertical-align: top;">
                <div class="doc-title-box">PURCHASE ORDER (PO)</div>
                <strong style="font-size: 11pt; color: #1a365d;">{{ $po->po_no }}</strong>
            </td>
        </tr>
    </table>

    <!-- Header Informasi PO & Supplier -->
    <table class="po-header-table">
        <tr>
            <td style="width: 50%;">
                <strong style="color: #1a365d; border-bottom: 1px solid #ccc; padding-bottom: 2px; display: inline-block; margin-bottom: 6px;">
                    DIBELI DARI (PEMASOK):
                </strong><br>
                <strong style="font-size: 11pt;">{{ $po->supplier->name ?? '-' }}</strong><br>
                Alamat: {{ $po->supplier->address ?? '-' }}<br>
                Telepon: {{ $po->supplier->phone ?? '-' }}<br>
                Email: {{ $po->supplier->email ?? '-' }}
            </td>
            <td style="width: 50%; padding-left: 20px;">
                <strong style="color: #1a365d; border-bottom: 1px solid #ccc; padding-bottom: 2px; display: inline-block; margin-bottom: 6px;">
                    INFORMASI PENGIRIMAN & DETAIL:
                </strong><br>
                <span class="info-label">Tanggal PO:</span> {{ date('d/m/Y', strtotime($po->po_date)) }}<br>
                <span class="info-label">Gudang Tujuan:</span> {{ $po->warehouse->name ?? '-' }}<br>
                <span class="info-label">Status PO:</span> <strong style="text-transform: uppercase;">{{ $po->status }}</strong><br>
                <span class="info-label">Tgl Cetak:</span> {{ date('d/m/Y H:i') }}
            </td>
        </tr>
    </table>

    <!-- Tabel Rincian Produk Item PO -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 15%;">Kode</th>
                <th>Nama Produk / Barang</th>
                <th style="width: 10%;" class="text-center">Qty</th>
                <th style="width: 15%;" class="text-right">Harga Satuan</th>
                <th style="width: 12%;" class="text-right">Diskon</th>
                <th style="width: 18%;" class="text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @php $subtotalTotal = 0; @endphp
            @foreach($items as $idx => $item)
                @php $subtotalTotal += $item->total; @endphp
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ $item->product->code ?? '-' }}</td>
                    <td>
                        <strong>{{ $item->product->name ?? '-' }}</strong>
                    </td>
                    <td class="text-center">{{ number_format($item->qty, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($item->discount, 2, ',', '.') }}</td>
                    <td class="text-right fw-bold">Rp {{ number_format($item->total, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" class="text-right fw-bold" style="background: #f8fafc;">TOTAL NILAI PEMESANAN (PO):</td>
                <td class="text-right fw-bold" style="background: #f8fafc; font-size: 11pt; color: #1a365d;">
                    Rp {{ number_format($items->sum('total'), 2, ',', '.') }}
                </td>
            </tr>
        </tfoot>
    </table>

    <!-- Terbilang Box -->
    <div class="terbilang-box">
        <strong>Terbilang:</strong> # {{ $terbilang }} #
    </div>

    <!-- Catatan Tambahan -->
    @if($po->memo)
        <div style="font-size: 9.5pt; margin-bottom: 20px; background: #fffbe0; border: 1px solid #ffe58f; padding: 8px 12px; border-radius: 3px;">
            <strong>Catatan / Keterangan Pemesanan:</strong><br>
            {{ $po->memo }}
        </div>
    @endif

    <!-- Kolom Tanda Tangan 3 Pihak -->
    <table class="signature-table">
        <tr>
            <td>
                Dibuat Oleh,<br>
                <div class="signature-space"></div>
                <strong>({{ $po->creator->name ?? 'Bagian Purchasing' }})</strong><br>
                <small class="text-muted">Purchasing Staff</small>
            </td>
            <td>
                Disetujui Oleh,<br>
                <div class="signature-space"></div>
                <strong>({{ $po->approver->name ?? 'Manager Purchasing / Finance' }})</strong><br>
                <small class="text-muted">Manager / Direksi</small>
            </td>
            <td>
                Konfirmasi Pemasok,<br>
                <div class="signature-space"></div>
                <strong>( ________________________ )</strong><br>
                <small class="text-muted">Supplier Representative</small>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
