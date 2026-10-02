<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>BOM {{ $bom->code }}</title>
    <style>
        @page { size: A4; margin: 16mm; }
        body { font-family: Arial, sans-serif; color: #222; font-size: 12px; margin: 0; }
        .toolbar { margin-bottom: 16px; }
        .toolbar button { padding: 7px 12px; border: 1px solid #999; background: #fff; cursor: pointer; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #222; padding-bottom: 10px; margin-bottom: 16px; }
        .entity { font-size: 15px; font-weight: 700; }
        .entity small { display: block; font-size: 11px; font-weight: 400; margin-top: 3px; }
        .title { text-align: right; }
        .title h1 { margin: 0 0 4px; font-size: 20px; }
        .title div { font-size: 12px; }
        .meta { display: grid; grid-template-columns: 110px 1fr 110px 1fr; gap: 7px 10px; margin-bottom: 18px; }
        .label { font-weight: 700; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 7px 8px; }
        th { background: #f1f1f1; text-align: left; }
        .text-right { text-align: right; }
        .note { margin-top: 14px; font-size: 10px; color: #666; }
        .signatures { display: grid; grid-template-columns: repeat(2, 1fr); gap: 50px; margin-top: 70px; text-align: center; }
        .signature-line { margin-top: 55px; border-top: 1px solid #777; padding-top: 5px; }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
<div class="toolbar">
    <button onclick="window.print()">Cetak</button>
    <button onclick="window.close()">Tutup</button>
</div>

<div class="header">
    <div class="entity">
        {{ $entity->name ?? 'Entitas' }}
        @if(!empty($entity->address))
            <small>{{ $entity->address }}</small>
        @endif
        @if(!empty($entity->phone))
            <small>{{ $entity->phone }}</small>
        @endif
    </div>
    <div class="title">
        <h1>MASTER BOM</h1>
        <div>{{ $bom->code }}</div>
    </div>
</div>

<div class="meta">
    <div class="label">Nama Formula</div>
    <div>{{ $bom->name }}</div>
    <div class="label">Status</div>
    <div>{{ $bom->is_active ? 'Aktif' : 'Nonaktif' }}</div>

    <div class="label">Produk Jadi</div>
    <div>{{ $bom->product_sku }} — {{ $bom->product_name }}</div>
    <div class="label">Output Standar</div>
    <div>{{ FormatIndo::indo($bom->output_qty, 2) }} {{ $bom->output_unit_code }}</div>
</div>

<table>
    <thead>
        <tr>
            <th style="width: 50px;">No</th>
            <th>Kode Material</th>
            <th>Material / Bahan</th>
            <th style="width: 150px;">Satuan</th>
            <th style="width: 130px;" class="text-right">Qty</th>
        </tr>
    </thead>
    <tbody>
        @forelse($items as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item->sku }}</td>
                <td>{{ $item->product_name }}</td>
                <td>{{ $item->unit_code }} — {{ $item->unit_name }}</td>
                <td class="text-right">{{ FormatIndo::indo($item->qty, 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="5" style="text-align:center;">Tidak ada material.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="note">
    Dokumen ini adalah master formula produksi. Biaya aktual BUASO dicatat pada transaksi produksi/SPK, bukan pada BOM.
</div>

<div class="signatures">
    <div>
        Dibuat oleh
        <div class="signature-line">________________________</div>
    </div>
    <div>
        Disetujui oleh
        <div class="signature-line">________________________</div>
    </div>
</div>
</body>
</html>
