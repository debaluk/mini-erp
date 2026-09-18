@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-1">Konversi Satuan</h3>
        <div class="text-secondary">Satuan transaksi per produk terhadap satuan dasar</div>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header fw-semibold">Tambah Konversi</div>
    <div class="card-body">
        <form method="POST" action="{{ route('master.unit-conversions.store') }}" class="row align-items-end">
            @csrf
            <div class="col-md-4">
                <label class="form-label">Produk</label>
                <select name="product_id" class="form-select" required>
                    <option value="">Pilih produk</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Satuan</label>
                <select name="unit_id" class="form-select" required>
                    <option value="">Pilih satuan</option>
                    @foreach($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->code }} - {{ $unit->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Faktor ke dasar</label>
                <input name="conversion_factor" type="number" step="0.000001" min="0.000001" class="form-control" value="1" required>
            </div>
            <div class="col-md-2">
                <div class="form-check mb-1">
                    <input class="form-check-input" type="checkbox" name="is_default" value="1" id="isDefault">
                    <label class="form-check-label" for="isDefault">Jadikan dasar</label>
                </div>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit">Simpan</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Produk</th>
                    <th>Dasar</th>
                    <th>Satuan</th>
                    <th>Faktor</th>
                    <th>Default</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row->product_name }}</td>
                        <td>{{ $row->default_unit_code ?: '-' }}</td>
                        <td>{{ $row->unit_code }}</td>
                        <td>{{ rtrim(rtrim(number_format($row->conversion_factor, 6, '.', ''), '0'), '.') }}</td>
                        <td>{!! $row->is_default ? '<span class="badge text-bg-primary">Ya</span>' : 'Tidak' !!}</td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('master.unit-conversions.delete',$row->id) }}" onsubmit="return confirm('Hapus konversi ini?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-outline-danger btn-sm" type="submit">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-3">Belum ada konversi satuan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection