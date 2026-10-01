@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1 fw-bold text-dark"><i class="bi bi-cart-plus me-2 text-primary"></i> Input Faktur Pembelian Baru</h3>
            <div class="text-secondary small">Dapat merujuk Ref PO, Opsi Otomatis Tambah Stok & Pengaturan Jatuh Tempo Hutang.</div>
        </div>
        <a href="{{ route('inventori.pembelian.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke List
        </a>
    </div>

    <form action="{{ route('inventori.pembelian.store') }}" method="POST">
        @csrf
        
        <!-- Option Header Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">No. Faktur Sistem</label>
                        <input type="text" class="form-control font-monospace fw-bold bg-light" value="{{  $autoInvNo }}" readonly>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">No. Faktur Supplier</label>
                        <input type="text" name="supplier_invoice_no" class="form-control font-monospace" placeholder="Nomor Nota dari Supplier">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Tarik Dari Ref PO (Interaktif)</label>
                        <select name="purchase_order_id" id="select-ref-po" class="form-select">
                            <option value="">-- Tanpa PO (Direct Purchase) --</option>
                            @foreach((approvedPos as )po)
                                <option value="{{  $po->id }}" {{ (string) (fromPoId === (string) )po->id ? 'selected' : '' }}>
                                    {{ (po->po_no }} - {{ )po->supplier_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Tanggal Pembelian <span class="text-danger">*</span></label>
                        <input type="date" name="purchase_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Unit Bisnis <span class="text-danger">*</span></label>
                        <select name="business_unit_id" class="form-select" required>
                            <option value="">-- Pilih Unit Bisnis --</option>
                            @foreach((businessUnits as )bu)
                                <option value="{{  $bu->id }}">{{ (bu->code }} - {{ )bu->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Supplier / Vendor <span class="text-danger">*</span></label>
                        <select name="supplier_id" id="supplier-id" class="form-select" required>
                            <option value="">-- Pilih Supplier --</option>
                            @foreach((suppliers as )s)
                                <option value="{{ (s->id }}">{{ )s->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Gudang Tujuan <span class="text-danger">*</span></label>
                        <select name="warehouse_id" class="form-select" required>
                            <option value="">-- Pilih Gudang --</option>
                            @foreach((warehouses as )w)
                                <option value="{{ (w->id }}">{{ )w->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Cara Pembayaran <span class="text-danger">*</span></label>
                        <select name="payment_type" id="select-payment-type" class="form-select fw-bold" required>
                            <option value="cash" class="text-success">Tunai / Cash</option>
                            <option value="credit" class="text-danger">Kredit / Tempo (Hutang Usaha)</option>
                        </select>
                    </div>

                    <div class="col-md-3 d-none" id="container-due-date">
                        <label class="form-label fw-bold text-danger">Tanggal Jatuh Tempo <span class="text-danger">*</span></label>
                        <input type="date" name="due_date" id="due-date" class="form-control border-danger" value="{{ date('Y-m-d', strtotime('+30 days')) }}">
                    </div>

                    <!-- ATURAN 7: CHECKBOX BARANG DITERIMA -->
                    <div class="col-md-9 d-flex align-items-center">
                        <div class="form-check form-switch bg-light p-3 rounded border w-100">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="goods_received" id="check-goods-received" value="1" checked style="transform: scale(1.3);">
                            <label class="form-check-label fw-bold text-dark" for="check-goods-received">
                                <i class="bi bi-box-seam me-1 text-info"></i> Barang Langsung Diterima di Gudang
                                <span class="d-block text-muted small fw-normal">Jika dicentang, stok fisik di `warehouses_stocks` langsung bertambah & Moving Average HPP dihitung ulang saat di-Posting. Jika tidak dicentang, hanya pengakuan kewajiban/keuangan.</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Items Table Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-dark text-white py-2 d-flex justify-content-between align-items-center">
                <span class="fw-bold small"><i class="bi bi-cart me-1"></i> Detail Rincian Item Barang Pembelian</span>
                <button type="button" class="btn btn-sm btn-light fw-bold" id="btn-add-row">+ Tambah Item Manual</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0" id="table-invoice-items" style="font-size: 0.88rem;">
                        <thead class="table-light text-center">
                            <tr>
                                <th>Produk / Barang</th>
                                <th style="width: 120px;">Qty Beli</th>
                                <th style="width: 140px;">Harga Satuan</th>
                                <th style="width: 120px;">Diskon</th>
                                <th style="width: 160px;">Subtotal (Rp)</th>
                                <th style="width: 50px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-items">
                            @forelse((poItems as )item)
                                <tr>
                                    <td>
                                        <input type="hidden" name="products[]" value="{{  $item->product_id }}">
                                        <div class="fw-semibold text-dark">{{  $item->product_name }}</div>
                                        <div class="small font-monospace text-muted">{{  $item->product_code }}</div>
                                    </td>
                                    <td><input type="number" name="qty[]" class="form-control form-control-sm text-center input-qty" step="0.01" value="{{ (float) $item->qty }}" required></td>
                                    <td><input type="number" name="unit_price[]" class="form-control form-control-sm text-end input-price" step="0.01" value="{{ (float) $item->unit_price }}" required></td>
                                    <td><input type="number" name="discount[]" class="form-control form-control-sm text-end input-discount" step="0.01" value="{{ (float) $item->discount }}"></td>
                                    <td class="text-end font-monospace fw-bold cell-subtotal">Rp {{ number_format( $item->total, 0, ',', '.') }}</td>
                                    <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-row"><i class="bi bi-x"></i></button></td>
                                </tr>
                            @empty
                            @endforelse
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="4" class="text-end">SUBTOTAL:</td>
                                <td class="text-end font-monospace" id="footer-subtotal">Rp 0</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="4" class="text-end">PAJAK (PPN/Lainnya):</td>
                                <td class="text-end">
                                    <input type="number" name="tax_amount" id="input-tax" class="form-control form-control-sm text-end font-monospace" value="0">
                                </td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="4" class="text-end text-primary fs-6">GRAND TOTAL PEMBELIAN:</td>
                                <td class="text-end font-monospace fs-6 text-primary" id="footer-grand-total">Rp 0</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light p-3 text-end">
                <a href="{{ route('inventori.pembelian.index') }}" class="btn btn-secondary me-2">Batal</a>
                <button type="submit" name="save_draft" value="1" class="btn btn-outline-primary me-2">
                    <i class="bi bi-save me-1"></i> Simpan Draft
                </button>
                <button type="submit" name="post_now" value="1" class="btn btn-success px-4 fw-bold" onclick="return confirm('Posting langsung Faktur Pembelian ini? Jurnal Keuangan & Stok akan diperbarui.')">
                    <i class="bi bi-check-circle me-1"></i> Simpan & Posting
                </button>
            </div>
        </div>
    </form>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // Toggle Tanggal Jatuh Tempo jika Kredit
     $('#select-payment-type').on('change', function () {
        if (((this).val() === 'credit') {
            )('#container-due-date').removeClass('d-none');
        } else {
             $('#container-due-date').addClass('d-none');
        }
    });

    // Pull Item PO Interaktif
     $('#select-ref-po').on('change', function () {
        const poId = ((this).value || )(this).val();
        if (poId) {
             $.get(`{{ url('/inventori/pembelian/po-items') }} ${poId}`, function (res) {
                if (res.success) {
                     $('#supplier-id').val(res.po.supplier_id);
                     $('#tbody-items').empty();
                    res.items.forEach(i => {
                        addRow(i.product_id, i.product_name, i.product_code, i.qty, i.unit_price, i.discount);
                    });
                }
            });
        }
    });

     $('#btn-add-row').on('click', function () { addRow(); });

    function addRow(prodId = '', name = '', code = '', qty = 1, price = 0, disc = 0) {
        const rowId = Date.now();
        let prodOptions = `@foreach($products as $p)<option value="{{ $p->id }}" data-price="{{ $p->purchase_price ?? 0 }}">{{ $p->code }} - {{ $p->name }}</option>@endforeach`;
        
        const html = `
            <tr id="row-${rowId}">
                <td>
                    ${prodId ? `<input type="hidden" name="products[]" value=" ${prodId}"><div class="fw-semibold text-dark"> ${name}</div><div class="small font-monospace text-muted"> ${code}</div>` : 
                    `<select name="products[]" class="form-select form-select-sm select-prod" required><option value="">-- Pilih Barang --</option> ${prodOptions}</select>`}
                </td>
                <td><input type="number" name="qty[]" class="form-control form-control-sm text-center input-qty" step="0.01" value="${qty}" required></td>
                <td><input type="number" name="unit_price[]" class="form-control form-control-sm text-end input-price" step="0.01" value="${price}" required></td>
                <td><input type="number" name="discount[]" class="form-control form-control-sm text-end input-discount" step="0.01" value="${disc}"></td>
                <td class="text-end font-monospace fw-bold cell-subtotal">Rp 0</td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-row"><i class="bi bi-x"></i></button></td>
            </tr>
        `;
         $('#tbody-items').append(html);
        calcTotals();
    }

    ((document).on('click', '.btn-remove-row', function () {
        )(this).closest('tr').remove();
        calcTotals();
    });

     $(document).on('change', '.select-prod', function () {
        const price =  $(this).find(':selected').data('price') || 0;
         $(this).closest('tr').find('.input-price').val(price);
        calcTotals();
    });

     $(document).on('input', '.input-qty, .input-price, .input-discount, #input-tax', function () {
        calcTotals();
    });

    function calcTotals() {
        let subtotal = 0;
         $('#tbody-items tr').each(function () {
            const q = parseFloat( $(this).find('.input-qty').val()) || 0;
            const p = parseFloat( $(this).find('.input-price').val()) || 0;
            const d = parseFloat( $(this).find('.input-discount').val()) || 0;
            const sub = (q * p) - d;
             $(this).find('.cell-subtotal').text('Rp ' + sub.toLocaleString('id-ID'));
            subtotal += sub;
        });

        const tax = parseFloat( $('#input-tax').val()) || 0;
        const grand = subtotal + tax;

         $('#footer-subtotal').text('Rp ' + subtotal.toLocaleString('id-ID'));
         $('#footer-grand-total').text('Rp ' + grand.toLocaleString('id-ID'));
    }

    calcTotals();
});
</script>
@endsection