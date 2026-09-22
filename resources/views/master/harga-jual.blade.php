@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Daftar Harga Jual</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#priceModal">
            Tambah Harga
        </button>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control"
                   placeholder="Cari kode / nama item..."
                   value="{{ request('search') }}">
        </div>

        <div class="col-md-3">
            <select name="business_unit_id" class="form-select">
                <option value="">Semua Unit Bisnis</option>
                @foreach($businessUnits as $bu)
                    <option value="{{ $bu->id }}" @selected(request('business_unit_id') == $bu->id)>
                        {{ $bu->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-md-2">
            <select name="price_type" class="form-select">
                <option value="">Semua Tipe</option>
                <option value="retail" @selected(request('price_type') === 'retail')>Retail</option>
                <option value="grosir" @selected(request('price_type') === 'grosir')>Grosir</option>
            </select>
        </div>

        <div class="col-md-2">
            <button class="btn btn-secondary">Filter</button>
            <a href="{{ route('master.menu.harga-jual') }}" class="btn btn-light">Reset</a>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Unit Bisnis</th>
                        <th>Satuan</th>
                        <th>Tipe</th>
                        <th class="text-end">Harga Jual</th>
                        <th width="150">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($prices as $price)
                        <tr>
                            <td>
                                <strong>{{ $price->product_code }}</strong><br>
                                {{ $price->product_name }}
                            </td>
                            <td>{{ $price->business_unit_name }}</td>
                            <td>{{ $price->unit_name }}</td>
                            <td>{{ ucfirst($price->price_type) }}</td>
                            <td class="text-end">
                                Rp {{ number_format($price->selling_price, 0, ',', '.') }}
                            </td>
                            <td>
                                <button type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editPrice{{ $price->id }}">
                                    Edit
                                </button>

                                <form method="POST"
                                      action="{{ route('master.harga-jual.destroy', $price->id) }}"
                                      class="d-inline"
                                      onsubmit="return confirm('Hapus harga jual ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Hapus</button>
                                </form>
                            </td>
                        </tr>

                        <div class="modal fade" id="editPrice{{ $price->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <form method="POST" action="{{ route('master.harga-jual.update', $price->id) }}">
                                    @csrf
                                    @method('PUT')
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Harga Jual</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label">Item</label>
                                                <input class="form-control"
                                                       value="{{ $price->product_code }} - {{ $price->product_name }}"
                                                       disabled>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Unit Bisnis</label>
                                                <input class="form-control"
                                                       value="{{ $price->business_unit_name }}"
                                                       disabled>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Satuan</label>
                                                <input class="form-control"
                                                       value="{{ $price->unit_name }}"
                                                       disabled>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Tipe</label>
                                                <input class="form-control"
                                                       value="{{ ucfirst($price->price_type) }}"
                                                       disabled>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Harga Jual</label>
                                                <input type="number"
                                                       name="selling_price"
                                                       class="form-control"
                                                       value="{{ $price->selling_price }}"
                                                       min="0.01"
                                                       step="0.01"
                                                       required>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                            <button class="btn btn-primary">Simpan</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                Belum ada harga jual.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="priceModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('master.harga-jual.store') }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Harga Jual</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Item</label>
                        <select name="product_id" id="priceProduct" class="form-select" required>
                            <option value="">Pilih Item</option>
                            @foreach($products->unique('id') as $product)
                                <option value="{{ $product->id }}">
                                    {{ $product->code }} - {{ $product->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Unit Bisnis</label>
                        <select name="business_unit_id" id="priceBusinessUnit" class="form-select" required>
                            <option value="">Pilih Unit Bisnis</option>
                            @foreach($businessUnits as $bu)
                                <option value="{{ $bu->id }}">{{ $bu->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Satuan</label>
                        <select name="unit_id" id="priceUnit" class="form-select" required>
                            <option value="">Pilih Item terlebih dahulu</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tipe Harga</label>
                        <select name="price_type" class="form-select" required>
                            <option value="retail">Retail</option>
                            <option value="grosir">Grosir</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Harga Jual</label>
                        <input type="number" name="selling_price" class="form-control"
                               min="0.01" step="0.01" required>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-primary">Simpan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
const priceProducts = @json($products);

document.getElementById('priceProduct').addEventListener('change', function () {
    const productId = Number(this.value);
    const unitSelect = document.getElementById('priceUnit');

    unitSelect.innerHTML = '<option value="">Pilih Satuan</option>';

    priceProducts
        .filter(p => Number(p.id) === productId)
        .forEach(p => {
            unitSelect.innerHTML += `<option value="${p.base_unit_id}">
                ${p.unit_code} - ${p.unit_name}
            </option>`;
        });
});
</script>
@endsection
