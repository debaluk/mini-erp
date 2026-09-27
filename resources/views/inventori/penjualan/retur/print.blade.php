<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota Kredit Retur - {{ $return->return_no }}</title>
    <link rel="stylesheet" href="{{ asset('css/invoice-dotmatrix.css') }}">
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 9pt;
            line-height: 1.25;
        }

        .invoice-container {
            font-size: 9pt;
        }

        .header-table,
        .meta-table,
        .item-table,
        .footer-table,
        .sig-table {
            width: 100%;
            border-collapse: collapse;
        }

        .company-name {
            font-size: 14pt;
            font-weight: bold;
            letter-spacing: 0.3px;
        }

        .doc-title {
            font-size: 13pt;
            font-weight: bold;
        }

        .doc-number {
            font-size: 10pt;
            font-weight: bold;
        }

        .meta-table {
            font-size: 9pt;
        }

        .item-table {
            font-size: 8.5pt;
        }

        .item-table th {
            font-size: 8pt;
            font-weight: bold;
        }

        .total-box {
            font-size: 9pt;
        }

        .total-amount {
            font-size: 11pt;
            font-weight: bold;
        }

        .header-table td,
        .meta-table td,
        .footer-table td,
        .sig-table td {
            vertical-align: top;
        }

        .item-table th,
        .item-table td {
            border: 1px solid #000;
            padding: 3px 4px;
        }

        .item-table th {
            text-align: center;
        }

        .divider-double {
            border-top: 3px double #000;
            margin: 5px 0;
        }

        .divider-single {
            border-top: 1px solid #000;
            margin: 5px 0;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .total-box {
            border: 1px solid #000;
            padding: 5px;
            font-weight: bold;
        }

        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<div class="no-print" style="margin-bottom:15px;text-align:center;">
    <button onclick="window.print()"
        style="padding:8px 18px;font-weight:bold;background:#0284c7;color:#fff;border:none;border-radius:4px;cursor:pointer;">
        🖨️ CETAK NOTA KREDIT RETUR
    </button>
</div>

<div class="invoice-container">

    <table class="header-table">
        <tr>
            <td style="width:58%;">
                <div class="company-name">
                    {{ $entityData->name }}
                </div>
                <div>{{ $entityData->address ?? '-' }}</div>
                @if(!empty($entityData->phone))
                    <div>Telp/WA: {{ $entityData->phone }}</div>
                @endif
            </td>
            <td style="width:42%;text-align:right;">
                <div class="doc-title">NOTA KREDIT (RETUR)</div>
                <div class="doc-number">
                    No: {{ $return->return_no }}
                </div>
                <div>
                    Tgl Retur:
                    <strong>{{ \Carbon\Carbon::parse($return->return_date)->format('d/m/Y H:i') }}</strong>
                </div>
            </td>
        </tr>
    </table>

    <div class="divider-double"></div>

    <table class="meta-table">
        <tr>
            <td style="width:50%;">
                <strong>Kepada Yth:</strong><br>
                <span style="font-size:10pt;font-weight:bold;">
                    {{ $return->customer_name }}
                </span><br>
                Alamat: {{ $return->customer_address ?? '-' }}<br>
                Telp: {{ $return->customer_phone ?? '-' }}
            </td>

            <td style="width:50%;">
                <strong>Ref. Faktur Asal :</strong>
                {{ $return->invoice_no }}<br>

                <strong>Tgl Faktur Asal :</strong>
                {{ \Carbon\Carbon::parse($return->sale_created_at)->format('d/m/Y') }}<br>

                <strong>Dipotongkan Ke :</strong>
                <span style="font-weight:bold;">{{ $refundTo }}</span><br>

                <strong>Petugas Admin :</strong>
                {{ $return->user_name }}
            </td>
        </tr>
    </table>

    <div class="divider-single"></div>

    <table class="item-table">
        <thead>
            <tr>
                <th style="width:4%;">NO</th>
                <th style="width:11%;">KODE</th>
                <th>NAMA BARANG &amp; SPESIFIKASI</th>
                <th style="width:15%;">KONDISI</th>
                <th style="width:7%;">QTY</th>
                <th style="width:7%;">SAT</th>
                <th style="width:13%;">HARGA</th>
                <th style="width:14%;">SUBTOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $item->product_code }}</td>
                    <td>{{ $item->product_name }}</td>
                    <td class="text-center">
                        @if($item->condition === 'good')
                            BAGUS (STOK)
                        @else
                            <u>RUSAK (AFKIR)</u>
                        @endif
                    </td>
                    <td class="text-right">
                        {{ number_format($item->qty, 0, ',', '.') }}
                    </td>
                    <td class="text-center">{{ $item->unit_name }}</td>
                    <td class="text-right">
                        {{ number_format($item->unit_price, 0, ',', '.') }}
                    </td>
                    <td class="text-right">
                        {{ number_format($item->return_value, 0, ',', '.') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider-single"></div>

    <table class="footer-table">
        <tr>
            <td style="width:58%;padding-right:10px;">
                <div style="border:1px solid #000;padding:5px;">
                    <strong>Terbilang:</strong><br>
                    <i style="font-weight:bold;">"# {{ $terbilang }} Rupiah #"</i>
                </div>

                <div style="margin-top:5px;">
                    * Nota Kredit ini merupakan bukti sah penyesuaian
                    kewajiban/pengembalian barang.
                </div>
            </td>

            <td style="width:42%;">
                <table style="width:100%;border-collapse:collapse;">
                    <tr>
                        <td style="border:1px solid #000;padding:6px;font-weight:bold;">
                            TOTAL RETUR
                        </td>
                        <td class="total-amount" style="border:1px solid #000;padding:6px;text-align:right;">
                            Rp {{ number_format($return->total, 0, ',', '.') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <br>

    <table class="sig-table">
        <tr>
            <td style="width:33%;text-align:center;">
                Pelanggan / Pembeli,<br><br><br><br>
                ( .................................... )
            </td>
            <td style="width:33%;text-align:center;">
                Penerima / Gudang,<br><br><br><br>
                ( .................................... )
            </td>
            <td style="width:33%;text-align:center;">
                Finance / Kasir,<br><br><br><br>
                ( .................................... )
            </td>
        </tr>
    </table>

</div>

<script>
window.addEventListener('load', function () {
    setTimeout(function () {
        window.print();
    }, 300);
});
</script>

</body>
</html>
