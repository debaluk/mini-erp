@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3"><div><h4 class="mb-1">Penjualan</h4><div class="text-secondary small">Daftar transaksi penjualan</div></div><div class="d-flex gap-2"><button type="button" class="btn btn-success" id="btn-export-sales">↓ Export Excel</button><a href="{{ route('inventori.penjualan.create') }}" class="btn btn-primary">+ Tambah Penjualan</a></div></div>
<div class="card shadow-sm"><div class="card-body border-bottom"><form method="GET" action="{{ route('inventori.penjualan') }}" class="row g-2 align-items-end"><div class="col-md-2"><label class="form-label">Tanggal Mulai</label><input type="date" name="start_date" value="{{ request('start_date', now()->startOfMonth()->toDateString()) }}" class="form-control"></div><div class="col-md-2"><label class="form-label">Tanggal Akhir</label><input type="date" name="end_date" value="{{ request('end_date', now()->endOfMonth()->toDateString()) }}" class="form-control"></div><div class="col-md-3"><label class="form-label">Customer</label><input name="customer" value="{{ request('customer') }}" class="form-control" placeholder="Cari customer..."></div><div class="col-md-2"><label class="form-label">Unit</label><select class="form-select" disabled><option>Semua Unit</option>@foreach($units as $u)<option>{{ $u->name }}</option>@endforeach</select></div><div class="col-md-2"><label class="form-label">Cara Bayar</label><select name="payment_method" class="form-select"><option value="">Semua</option><option value="cash" @selected(request('payment_method') === 'cash')>Tunai</option><option value="credit" @selected(request('payment_method') === 'credit')>Kredit / Bon</option><option value="transfer" @selected(request('payment_method') === 'transfer')>Transfer</option><option value="qris" @selected(request('payment_method') === 'qris')>QRIS</option></select></div><div class="col-md-1"><button type="submit" class="btn btn-outline-primary w-100">Cari</button></div></form></div>
<div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>No. Penjualan</th><th>Tanggal</th><th>Customer</th><th>Unit</th><th>Cara Bayar</th><th>Jatuh Tempo</th><th class="text-end">Subtotal</th><th class="text-end">Diskon</th><th class="text-end">Total</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>@forelse($rows as $r)<tr><td class="fw-semibold">{{ $r->invoice_no }}</td><td>{{ \Carbon\Carbon::parse($r->sale_date)->format('d/m/Y') }}</td><td>{{ $r->customer_name ?? 'Umum' }}</td><td>{{ $r->unit_name ?? '-' }}</td><td>{{ $r->payment_methods ?? '-' }}</td><td>-</td><td class="text-end">Rp {{ number_format((float)$r->subtotal,0,',','.') }}</td><td class="text-end">Rp {{ number_format((float)$r->discount,0,',','.') }}</td><td class="text-end">Rp {{ number_format((float)$r->total,0,',','.') }}</td><td><span class="badge text-bg-secondary">{{ $r->status }}</span></td><td class="text-end"><a href="{{ route('inventori.penjualan.show', $r->id) }}" class="btn btn-sm btn-outline-secondary">Lihat</a></td></tr>@empty<tr><td colspan="11" class="text-center text-secondary py-4">Belum ada transaksi penjualan.</td></tr>@endforelse</tbody></table></div>
<div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="small text-secondary">
        @if($rows->total() > 0)
            Menampilkan {{ $rows->firstItem() }}–{{ $rows->lastItem() }} dari {{ $rows->total() }} transaksi
        @else
            Tidak ada transaksi
        @endif
    </div>
    @if($rows->lastPage() > 1)
    <nav aria-label="Pagination Penjualan">
        <ul class="pagination pagination-sm mb-0">
            <li class="page-item {{ $rows->onFirstPage() ? 'disabled' : '' }}">
                <a class="page-link" href="{{ $rows->previousPageUrl() ?? '#' }}">‹</a>
            </li>
            @foreach($rows->getUrlRange(max(1, $rows->currentPage()-2), min($rows->lastPage(), $rows->currentPage()+2)) as $page => $url)
                <li class="page-item {{ $page == $rows->currentPage() ? 'active' : '' }}">
                    <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                </li>
            @endforeach
            <li class="page-item {{ $rows->currentPage() >= $rows->lastPage() ? 'disabled' : '' }}">
                <a class="page-link" href="{{ $rows->nextPageUrl() ?? '#' }}">›</a>
            </li>
        </ul>
    </nav>
    @endif
</div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js"></script>
<script>
document.getElementById('btn-export-sales')?.addEventListener('click', async function () {
    if (typeof XLSX === 'undefined') {
        alert('Library Excel belum termuat. Silakan refresh halaman lalu coba lagi.');
        return;
    }

    const btn = this;
    btn.disabled = true;
    const params = new URLSearchParams({
        start_date: document.querySelector('[name="start_date"]')?.value || '',
        end_date: document.querySelector('[name="end_date"]')?.value || '',
        customer: document.querySelector('[name="customer"]')?.value || '',
        payment_method: document.querySelector('[name="payment_method"]')?.value || ''
    });

    try {
        const response = await fetch('{{ route('inventori.penjualan.export-data') }}?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || 'Gagal mengambil data export.');

        const formatDate = value => {
            if (!value) return '';
            const d = new Date(String(value).replace(' ', 'T'));
            return Number.isNaN(d.getTime()) ? String(value).slice(0,10) : new Intl.DateTimeFormat('id-ID').format(d);
        };

        const data = payload.rows.map(r => [
            String(r.invoice_no || ''),
            formatDate(r.sale_date),
            String(r.customer_name || 'Umum'),
            String(r.unit_name || '-'),
            String(r.payment_methods || '-'),
            String(r.due_date || '-'),
            Number(r.subtotal ?? 0),
            Number(r.discount ?? 0),
            Number(r.total ?? 0),
            String(r.status || '')
        ]);

        const periode = formatDate(payload.start_date) + ' s/d ' + formatDate(payload.end_date);
        const exportDate = new Intl.DateTimeFormat('id-ID', {
            day: '2-digit', month: '2-digit', year: 'numeric'
        }).format(new Date());

        const ws = XLSX.utils.aoa_to_sheet([
            [payload.entity_name || 'NAMA ENTITAS'],
            ['Laporan Transaksi Penjualan'],
            ['Periode : ' + periode],
            ['Tgl Export : ' + exportDate],
            [],
            ['No. Penjualan','Tanggal','Customer','Unit','Cara Bayar','Jatuh Tempo','Subtotal','Diskon','Total','Status'],
            ...data
        ]);

        ws['!merges'] = [
            {s:{r:0,c:0},e:{r:0,c:9}},
            {s:{r:1,c:0},e:{r:1,c:9}},
            {s:{r:2,c:0},e:{r:2,c:9}},
            {s:{r:3,c:0},e:{r:3,c:9}}
        ];
        ws['!cols'] = [
            {wch:28},{wch:14},{wch:24},{wch:16},{wch:16},
            {wch:16},{wch:18},{wch:18},{wch:18},{wch:14}
        ];

        data.forEach((row, i) => {
            const excelRow = i + 7;
            ws['G'+excelRow].z = '#,##0';
            ws['H'+excelRow].z = '#,##0';
            ws['I'+excelRow].z = '#,##0';
        });

        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Penjualan');
        XLSX.writeFile(wb, 'laporan-penjualan-' + new Date().toISOString().slice(0,10) + '.xlsx');
    } catch (err) {
        alert(err.message || 'Export gagal.');
    } finally {
        btn.disabled = false;
    }
});
</script>
@endpush