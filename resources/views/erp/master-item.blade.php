@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-1">Item</h3>
        <div class="text-secondary">Master item</div>
    </div>
    <a href="{{ route('master.item.create') }}" class="btn btn-primary btn-sm">+ Tambah Item</a>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Kode Item</th>
                    <th>Aksi</th>
                    <th>Barcode</th>
                    <th>Nama Item</th>
                    <th>Jenis</th>
                    <th>Satuan Dasar</th>
                    <th class="text-end">Minimum Stok</th>
                    <th class="text-center">Persediaan</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                    <tr>
                        <td class="fw-semibold">{{ $item->code }}</td>
                        <td><a href="{{ route('master.item.edit', $item->id) }}" class="btn btn-outline-primary btn-sm">Edit</a></td>
                        <td>{{ $item->barcode ?: '-' }}</td>
                        <td>{{ $item->name }}</td>
                        <td>{{ ucfirst($item->item_type) }}</td>
                        <td>{{ $item->base_unit_name ?: '-' }}</td>
                        <td class="text-end">{{ rtrim(rtrim(number_format((float) $item->minimum_stock, 3, ',', '.'), '0'), ',') }}</td>
                        <td class="text-center">{{ $item->manage_stock ? 'Ya' : 'Tidak' }}</td>
                        <td class="text-center">
                            @if($item->is_active)
                                <span class="badge text-bg-success">Aktif</span>
                            @else
                                <span class="badge text-bg-secondary">Nonaktif</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-secondary py-4">Belum ada item.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
