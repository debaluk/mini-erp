@props([
    'entityName' => 'UD Sinar Jaya Lestari',
    'entityAddress' => null,
    'documentNo' => '-',
    'date' => null,
    'customerName' => 'Umum',
    'unitName' => '-',
    'items' => collect(),
    'subtotal' => 0,
    'discount' => 0,
    'total' => 0,
    'payment' => '-',
    'memo' => null,
])

<div class="nota-pos-print">

    {{-- KOP --}}
    <div class="receipt-header">
        <div class="receipt-store-name">
            {{ $entityName }}
        </div>

        @if(!empty($entityAddress))
            <div class="receipt-store-address">
                {{ $entityAddress }}
            </div>
        @endif
    </div>

    <div class="receipt-document-no">
        {{ $documentNo }}
    </div>

    <div class="receipt-divider"></div>

    {{-- INFORMASI TRANSAKSI --}}
    <table class="receipt-table receipt-meta">
        <tr>
            <td>Tgl / Jam</td>
            <td>
                {{ $date
                    ? \Carbon\Carbon::parse($date)->format('d/m/Y H:i')
                    : '-'
                }}
            </td>
        </tr>

        <tr>
            <td>Pelanggan</td>
            <td>{{ $customerName ?: 'Umum' }}</td>
        </tr>
    </table>

    <div class="receipt-divider"></div>

    {{-- DETAIL BARANG --}}
    <table class="receipt-table receipt-items">
        <tbody>
        @forelse($items as $item)

            <tr>
                <td colspan="2" class="receipt-item-name">
                    {{ $item->name }}
                </td>
            </tr>

            <tr>
                <td class="receipt-item-calc">
                    {{ rtrim(rtrim(number_format((float) $item->qty, 3, ',', '.'), '0'), ',') }}
                    {{ $item->unit_code ?? '' }}
                    x
                    {{ number_format((float) $item->unit_price, 0, ',', '.') }}
                </td>

                <td class="receipt-number">
                    {{ number_format((float) $item->total, 0, ',', '.') }}
                </td>
            </tr>

            @if((float) $item->discount > 0)
                <tr>
                    <td class="receipt-item-calc">
                        Diskon
                    </td>

                    <td class="receipt-number">
                        -{{ number_format((float) $item->discount, 0, ',', '.') }}
                    </td>
                </tr>
            @endif

        @empty

            <tr>
                <td colspan="2">
                    Tidak ada item.
                </td>
            </tr>

        @endforelse
        </tbody>
    </table>

    <div class="receipt-divider"></div>

    {{-- RINGKASAN --}}
    <table class="receipt-table receipt-summary">

        <tr>
            <td>Subtotal</td>
            <td class="receipt-number">
                {{ number_format((float) $subtotal, 0, ',', '.') }}
            </td>
        </tr>

        <tr>
            <td>Diskon Global</td>
            <td class="receipt-number">
                {{ number_format((float) $discount, 0, ',', '.') }}
            </td>
        </tr>

    </table>

    <div class="receipt-divider"></div>

    {{-- TOTAL --}}
    <table class="receipt-table receipt-summary receipt-total">

        <tr>
            <td>TOTAL</td>
            <td class="receipt-number">
                {{ number_format((float) $total, 0, ',', '.') }}
            </td>
        </tr>

    </table>

    <div class="receipt-divider"></div>

    {{-- PEMBAYARAN --}}
    <table class="receipt-table receipt-payment">

        <tr>
            <td>Bayar</td>
            <td class="receipt-number">
                {{ strtoupper($payment) }}
            </td>
        </tr>

    </table>

    @if(!empty($memo))
        <div class="receipt-divider"></div>

        <div class="receipt-memo">
            {{ $memo }}
        </div>
    @endif

    <div class="receipt-divider"></div>

    {{-- FOOTER --}}
    <div class="receipt-footer">

        <div class="receipt-thanks">
            *** TERIMA KASIH ***
        </div>

        <div class="receipt-policy">
            Barang yang sudah dibeli tidak<br>
            dapat ditukar / dikembalikan
        </div>

    </div>

</div>
