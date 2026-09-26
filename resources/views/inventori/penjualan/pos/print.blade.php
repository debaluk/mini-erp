@extends('layouts.app')

@section('content')
@if(request()->boolean('print'))
    @include('components.print.nota-pos', [
        'entityName' => $sale->entity_name ?? config('app.name'),
        'documentNo' => $sale->invoice_no,
        'date' => $sale->sale_date,
        'customerName' => $sale->customer_name ?? 'Umum',
        'unitName' => $sale->unit_name ?? '-',
        'items' => $items,
        'subtotal' => $sale->subtotal,
        'discount' => $sale->discount,
        'total' => $sale->total,
        'payment' => $payments->pluck('method')->unique()->map(fn($m) => $m === 'credit' ? 'Kredit / Bon' : $m)->implode(', ') ?: '-',
        'memo' => $sale->memo,
    ])
@else
    <div class="container py-4">
        <a href="{{ route('pos') }}" class="btn btn-outline-secondary">← Kembali ke POS</a>
    </div>
@endif
@endsection

@if(request()->boolean('print'))
@push('scripts')
<script>
window.addEventListener('load', function () {
    window.print();
});

window.addEventListener('afterprint', function () {
    window.close();
});
</script>
@endpush
@endif
