<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Nota {{ $sale->invoice_no }}</title>

    <link rel="stylesheet" href="{{ asset('css/receipt-thermal.css') }}">
</head>

<body>

@if(request()->boolean('print'))

    @include('components.print.nota-pos', [
        'entityName' => $sale->entity_name ?? 'UD Sinar Jaya Lestari',
        'entityAddress' => $sale->entity_address ?? null,
        'documentNo' => $sale->invoice_no,
        'date' => $sale->sale_date,
        'customerName' => $sale->customer_name ?? 'Umum',
        'unitName' => $sale->unit_name ?? '-',
        'items' => $items,
        'subtotal' => $sale->subtotal,
        'discount' => $sale->discount,
        'total' => $sale->total,
        'payment' => $payments->pluck('method')
            ->unique()
            ->map(fn($m) => $m === 'credit' ? 'Kredit / Bon' : $m)
            ->implode(', ') ?: '-',
        'memo' => $sale->memo,
    ])

    <script>
        window.addEventListener('load', function () {
            window.print();
        });

        window.addEventListener('afterprint', function () {
            window.close();
        });
    </script>

@else

    <div class="no-print">
        <a href="{{ route('pos') }}">← Kembali ke POS</a>
    </div>

@endif

</body>
</html>
