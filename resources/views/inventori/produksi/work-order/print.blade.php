<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>{{ $wo->wo_no }} - SPK</title>
<style>
@page{size:A4;margin:14mm}
body{font-family:Arial,sans-serif;font-size:12px;color:#111}
.header{display:flex;justify-content:space-between;border-bottom:2px solid #111;padding-bottom:10px;margin-bottom:20px}
.title{font-size:20px;font-weight:700}.no{font-size:18px;font-weight:700;text-align:right}
.grid{display:grid;grid-template-columns:130px 1fr;gap:9px 12px}
.label{font-weight:700}
.box{border:1px solid #aaa;padding:12px;margin-top:20px}
table{width:100%;border-collapse:collapse}th,td{border:1px solid #888;padding:7px}th{background:#eee;text-align:left}
@media print{.no-print{display:none}}
</style>
</head>
<body>
<div class="no-print" style="text-align:right;margin-bottom:10px"><button onclick="window.print()">Cetak</button></div>

<div class="header">
    <div>
        <div class="title">{{ $entity->name ?? 'Perusahaan' }}</div>
        <div>{{ $entity->address ?? '' }}</div>
    </div>
    <div>
        <div class="title">WORK ORDER / SPK</div>
        <div class="no">{{ $wo->wo_no }}</div>
    </div>
</div>

<div class="grid">
    <div class="label">Nomor SPK</div><div>{{ $wo->wo_no }}</div>
    <div class="label">Tanggal</div><div>{{ \Carbon\Carbon::parse($wo->wo_date)->format('d/m/Y') }}</div>
    <div class="label">Produk</div><div>{{ $wo->product_code }} — {{ $wo->product_name }}</div>
    <div class="label">BOM</div><div>{{ $wo->bom_code }} — {{ $wo->bom_name }}</div>
    <div class="label">Target</div><div>{{ \App\Helpers\FormatHelper::indo($wo->target_output_qty, 2) }}</div>
    <div class="label">Gudang</div><div>{{ $wo->warehouse_code }} — {{ $wo->warehouse_name }}</div>
    <div class="label">Status</div>
    <div>{{ $wo->status === 'in_progress' ? 'On Progress' : ($wo->status === 'completed' ? 'Selesai' : 'Open') }}</div>
</div>

<div class="box">
    <h3>Nama Pekerja</h3>
    <table style="margin-top:8px">
        <thead><tr><th>No.</th><th>Kode</th><th>Nama</th></tr></thead>
        <tbody>
        @forelse($workers as $i => $worker)
            <tr><td>{{ $i + 1 }}</td><td>{{ $worker->code }}</td><td>{{ $worker->name }}</td></tr>
        @empty
            <tr><td colspan="3">Belum ada pekerja.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
</body>
</html>
