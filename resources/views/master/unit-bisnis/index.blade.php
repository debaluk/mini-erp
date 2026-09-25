@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-1">Setup Unit Bisnis</h3>
        <div class="text-secondary">Atur unit usaha dan metode HPP yang digunakan oleh masing-masing unit.</div>
    </div>
    <a href="{{ route('pengaturan.konfigurasi') }}" class="btn btn-outline-secondary">Kembali</a>
</div>

@if(session('success'))
    <div class="alert alert-success py-2">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger py-2">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@include('master.unit-bisnis._form', ['editUnit' => $editUnit])

<div class="card shadow-sm">
    <div class="card-header fw-semibold">Daftar Unit Bisnis</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 12%">Kode Unit</th>
                        <th>Nama Unit</th>
                        <th style="width: 15%">Tipe Usaha</th>
                        <th style="width: 15%">Metode HPP</th>
                        <th style="width: 10%">Status</th>
                        <th style="width: 170px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($units as $unit)
                        <tr>
                            <td>{{ $unit->code }}</td>
                            <td>{{ $unit->name }}</td>
                            <td>{{ ['retail' => 'Retail', 'production' => 'Produksi', 'service' => 'Jasa'][$unit->business_type] }}</td>
                            <td>
                                {{ [
                                    'perpetual' => 'Perpetual',
                                    'periodic' => 'Periodik',
                                    'direct_cost' => 'Direct Cost',
                                ][$unit->hpp_method] ?? $unit->hpp_method }}
                            </td>
                            <td>
                                <span class="badge {{ $unit->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    {{ $unit->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('pengaturan.unit-bisnis', ['edit' => Crypt::encryptString((string) $unit->id)]) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="POST" action="{{ route('pengaturan.unit-bisnis.destroy', $unit->id) }}" class="d-inline" onsubmit="return confirm('Hapus unit bisnis ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-secondary py-4">Belum ada unit bisnis.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header fw-semibold">Edukasi Metode HPP</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="border rounded p-2 h-100">
                    <div class="fw-semibold mb-1">PERPETUAL — MOVING AVERAGE</div>
                    <div class="text-secondary small">
                        HPP dihitung dan diperbarui secara berjalan setiap terjadi transaksi persediaan
                        yang memengaruhi nilai stok. Metode yang digunakan adalah Moving Average
                        (rata-rata bergerak). Penerimaan/pembelian membentuk rata-rata biaya persediaan
                        yang menjadi dasar HPP saat barang dijual atau digunakan.
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="border rounded p-3 h-100">
                    <div class="fw-semibold mb-1">PERIODIK</div>
                    <div class="text-secondary small">
                        HPP dihitung pada akhir periode berdasarkan:
                        <strong>Persediaan Awal + Pembelian Bersih - Persediaan Akhir = HPP</strong>.
                        Persediaan akhir ditentukan berdasarkan Stock Opname (SO), kemudian HPP
                        difinalisasi melalui proses Tutup Buku / Closing.
                    </div>
                </div>
            </div>
        </div>
        <div class="alert alert-light border mt-2 mb-0 py-1 small">
            <strong>Catatan:</strong> Tipe Usaha menentukan engine HPP (Retail, Produksi, atau Jasa).
            Metode HPP menentukan cara perhitungan untuk Retail/Produksi. Jasa menggunakan Direct Cost
            dan bukan HPP persediaan.
        </div>
    </div>
</div>

@endsection
