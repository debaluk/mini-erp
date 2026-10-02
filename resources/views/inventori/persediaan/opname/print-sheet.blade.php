<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Lembar Hitung Fisik - {{ $opname->opname_no }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
        }
        h2 {
            margin: 0 0 15px;
            text-align: center;
        }
        .header {
            margin-bottom: 20px;
        }
        .header table {
            width: 100%;
        }
        .header td {
            padding: 3px 0;
        }
        .count-table {
            width: 100%;
            border-collapse: collapse;
        }
        .count-table th,
        .count-table td {
            border: 1px solid #000;
            padding: 6px;
        }
        .count-table th {
            text-align: center;
        }
        .number {
            width: 40px;
            text-align: center;
        }
        .code {
            width: 110px;
        }
        .unit {
            width: 80px;
        }
        .count {
            width: 140px;
        }
        .notes {
            width: 180px;
        }
        .signature {
            margin-top: 50px;
            width: 100%;
        }
        .signature td {
            width: 50%;
            text-align: center;
            vertical-align: top;
        }
        @media print {
            body {
                margin: 10mm;
            }
        }
    </style>
</head>
<body>

    <h2>LEMBAR HITUNG FISIK STOCK OPNAME</h2>

    <div class="header">
        <table>
            <tr>
                <td width="150"><strong>No. SO</strong></td>
                <td>: {{ $opname->opname_no }}</td>
            </tr>
            <tr>
                <td><strong>Tanggal</strong></td>
                <td>: {{ $opname->opname_date }}</td>
            </tr>
            <tr>
                <td><strong>Unit Bisnis</strong></td>
                <td>: {{ $opname->business_unit_name }}</td>
            </tr>
            <tr>
                <td><strong>Gudang</strong></td>
                <td>: {{ $opname->warehouse_name }}</td>
            </tr>
        </table>
    </div>

    <table class="count-table">
        <thead>
            <tr>
                <th class="number">No</th>
                <th class="code">Kode Barang</th>
                <th>Nama Barang</th>
                <th class="unit">Satuan</th>
                <th class="count">Hasil Hitung Fisik</th>
                <th class="notes">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $i => $item)
                <tr>
                    <td class="number">{{ $i + 1 }}</td>
                    <td>{{ $item->product_code }}</td>
                    <td>{{ $item->product_name }}</td>
                    <td>{{ $item->unit_name ?? '-' }}</td>
                    <td style="height: 30px;"></td>
                    <td></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="signature">
        <tr>
            <td>
                Dihitung Oleh,<br><br><br><br>
                (________________________)
            </td>
            <td>
                Diverifikasi Oleh,<br><br><br><br>
                (________________________)
            </td>
        </tr>
    </table>

    <script>
        window.onload = function () {
            window.print();
        };
    </script>

</body>
</html>
