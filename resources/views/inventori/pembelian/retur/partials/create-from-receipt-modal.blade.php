@php($returnDate = now()->toDateString())
<input type="hidden" name="return_date" value="{{ $returnDate }}">
<div class="row g-2 mb-3">
    <div class="col-md-4">
        <div class="small text-secondary">No. Penerimaan</div>
        <div class="fw-bold">{{ $receipt->receipt_no }}</div>
    </div>
    <div class="col-md-4">
        <div class="small text-secondary">Supplier</div>
        <div class="fw-semibold">{{ $receipt->supplier_name ?? '-' }}</div>
    </div>
    <div class="col-md-4">
        <div class="small text-secondary">Gudang</div>
        <div class="fw-semibold">{{ $receipt->warehouse_name ?? '-' }}</div>
    </div>
</div>
<div class="table-responsive">
    <table class="table table-sm table-bordered align-middle mb-2">
        <thead class="table-dark text-center">
            <tr><th>Kode</th><th>Barang</th><th>Satuan</th><th>Qty Penerimaan</th><th>Sisa Bisa Diretur</th><th style="width:150px">Qty Retur</th></tr>
        </thead>
        <tbody>
        @foreach($items as $item)
            <tr>
                <td>{{ $item->product_code }}</td>
                <td>{{ $item->product_name }}</td>
                <td>{{ $item->unit_name ?? '-' }}</td>
                <td class="text-end">{{ number_format((float) $item->qty, 3, ',', '.') }}</td>
                <td class="text-end fw-semibold">{{ number_format((float) $item->returnable_qty, 3, ',', '.') }}</td>
                <td>
                    @if($item->returnable_qty > 0)
                        <input type="number" name="items[{{ $item->id }}][qty]" class="form-control form-control-sm text-end" min="0.001" max="{{ $item->returnable_qty }}" step="0.001" placeholder="0" data-remaining="{{ $item->returnable_qty }}">
                    @else
                        <span class="text-muted small">Sudah diretur</span>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="small text-secondary">Isi qty yang diretur saja. Qty tidak boleh melebihi sisa. Harga pokok pembelian mengikuti penerimaan sumber.</div>
<script>
$('#return-receipt-body input[type="number"]').on('input', function () {
    const max = Number($(this).attr('max') || 0);
    const value = Number($(this).val() || 0);
    if (value > max) $(this).val(max);
    if (value < 0) $(this).val('');
});
</script>
