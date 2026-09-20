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

<div class="card shadow-sm mb-3">
    <div class="card-header fw-semibold">Tambah Unit Bisnis</div>
    <div class="card-body">
        <form method="POST" action="{{ route('pengaturan.unit-bisnis.store') }}" class="row align-items-end">
            @csrf
            <div class="col-md-2">
                <label class="form-label">Kode Unit *</label>
                <input type="text" name="code" class="form-control" value="{{ old('code') }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Nama Unit *</label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tipe Usaha *</label>
                <select name="business_type" class="form-select" required>
                    <option value="">Pilih</option>
                    <option value="retail" @selected(old('business_type') === 'retail')>Retail</option>
                    <option value="production" @selected(old('business_type') === 'production')>Produksi</option>
                    <option value="service" @selected(old('business_type') === 'service')>Jasa</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Metode HPP *</label>
                <select name="hpp_method" class="form-select" required>
                    <option value="">Pilih</option>
                    <option value="perpetual" @selected(old('hpp_method') === 'perpetual')>Perpetual</option>
                    <option value="periodic" @selected(old('hpp_method') === 'periodic')>Periodik</option>
                </select>
            </div>
            <div class="col-md-1">
                <div class="form-check mb-2">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="new-active" checked>
                    <label class="form-check-label" for="new-active">Aktif</label>
                </div>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100">Simpan</button>
            </div>
        </form>
    </div>
</div>

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
                            <td>{{ $unit->hpp_method === 'perpetual' ? 'Perpetual' : 'Periodik' }}</td>
                            <td>
                                <span class="badge {{ $unit->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    {{ $unit->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editUnit{{ $unit->id }}">Edit</button>
                                <form method="POST" action="{{ route('pengaturan.unit-bisnis.destroy', $unit->id) }}" class="d-inline" onsubmit="return confirm('Hapus unit bisnis ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Hapus</button>
                                </form>
                            </td>
                        </tr>

                        <div class="modal fade" id="editUnit{{ $unit->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <form method="POST" action="{{ route('pengaturan.unit-bisnis.update', $unit->id) }}" class="modal-content">
                                    @csrf
                                    @method('PUT')
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Unit Bisnis</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-2">
                                            <label class="form-label">Kode Unit *</label>
                                            <input type="text" name="code" class="form-control" value="{{ $unit->code }}" required>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label">Nama Unit *</label>
                                            <input type="text" name="name" class="form-control" value="{{ $unit->name }}" required>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label">Tipe Usaha *</label>
                                            <select name="business_type" class="form-select" required>
                                                <option value="retail" @selected($unit->business_type === 'retail')>Retail</option>
                                                <option value="production" @selected($unit->business_type === 'production')>Produksi</option>
                                                <option value="service" @selected($unit->business_type === 'service')>Jasa</option>
                                            </select>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label">Metode HPP *</label>
                                            <select name="hpp_method" class="form-select" required>
                                                <option value="perpetual" @selected($unit->hpp_method === 'perpetual')>Perpetual</option>
                                                <option value="periodic" @selected($unit->hpp_method === 'periodic')>Periodik</option>
                                            </select>
                                        </div>
                                        <div class="form-check">
                                            <input type="hidden" name="is_active" value="0">
                                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="active{{ $unit->id }}" @checked($unit->is_active)>
                                            <label class="form-check-label" for="active{{ $unit->id }}">Aktif</label>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                                        <button class="btn btn-primary">Update</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @empty
                        <tr><td colspan="6" class="text-center text-secondary py-4">Belum ada unit bisnis.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

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

