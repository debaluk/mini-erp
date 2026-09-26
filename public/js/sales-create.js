<?php

(function () {
    const dataNode = document.getElementById('salesCreateData');
    const config = dataNode ? JSON.parse(dataNode.textContent || '{}') : {};
    const productCatalog = config.products || [];
    const rows = [];
    const body = document.getElementById('detailBody');
    const unitSelect = document.getElementById('unitSelect');
    const customerModal = document.getElementById('customerModal');
    const customerFilter = document.getElementById('customerFilter');
    const paymentMethod = document.getElementById('paymentMethod');
    const dueDate = document.getElementById('dueDate');
    const discountInput = document.getElementById('discountInput');

    let selectedCustomerId = null;

    const rupiah = (value) => 'Rp ' + Number(value || 0).toLocaleString('id-ID');

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    function getPrice(product, unitId) {
        const row = (product.prices || []).find((price) =>
            Number(price.business_unit_id) === Number(unitSelect.value) &&
            Number(price.unit_id) === Number(unitId)
        );

        return row ? Number(row.selling_price) : 0;
    }

    function getUnits(product) {
        const base = [{
            id: Number(product.base_unit_id),
            code: product.base_unit_code || '-',
            name: product.base_unit_name || product.base_unit_code || '-',
            factor: 1,
            defaultSale: false
        }];

        const conversions = (product.conversions || []).map((unit) => ({
            id: Number(unit.unit_id),
            code: unit.code,
            name: unit.name,
            factor: Number(unit.conversion_factor),
            defaultSale: Boolean(Number(unit.is_default_sale))
        }));

        const seen = new Set();
        return [...base, ...conversions].filter((unit) => {
            if (seen.has(unit.id)) return false;
            seen.add(unit.id);
            return true;
        });
    }

    function defaultUnit(product) {
        const units = getUnits(product);
        return units.find((unit) => unit.defaultSale) || units[0];
    }

    function lineTotal(row) {
        return Math.max(
            0,
            (Number(row.qty) * Number(row.unitPrice)) - Number(row.discount || 0)
        );
    }

    function calculateTotals() {
        const subtotal = rows
            .filter((row) => row.product)
            .reduce((sum, row) => sum + lineTotal(row), 0);

        const discount = Math.min(
            Math.max(Number(discountInput.value || 0), 0),
            subtotal
        );

        document.getElementById('subtotalAmount').textContent = rupiah(subtotal);
        document.getElementById('totalAmount').textContent = rupiah(subtotal - discount);

        return { subtotal, discount, total: subtotal - discount };
    }

    function renderProductOptions(row) {
        const list = row.element.querySelector('.product-results');
        const query = row.search.value.trim().toLowerCase();

        list.innerHTML = '';

        if (!query) {
            list.classList.add('d-none');
            return;
        }

        const matches = productCatalog
            .filter((product) => {
                const text = [
                    product.code,
                    product.sku,
                    product.name
                ].filter(Boolean).join(' ').toLowerCase();

                return text.includes(query);
            })
            .slice(0, 12);

        if (!matches.length) {
            const empty = document.createElement('div');
            empty.className = 'list-group-item text-secondary small';
            empty.textContent = 'Barang tidak ditemukan.';
            list.appendChild(empty);
            list.classList.remove('d-none');
            return;
        }

        matches.forEach((product) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action';
            button.innerHTML =
                '<div class="fw-semibold">' + escapeHtml(product.code || product.sku || '-') + '</div>' +
                '<div class="small text-secondary">' + escapeHtml(product.name) + '</div>';

            button.addEventListener('click', () => selectProduct(row, product));
            list.appendChild(button);
        });

        list.classList.remove('d-none');
    }

    function selectProduct(row, product) {
        const unit = defaultUnit(product);

        row.product = product;
        row.unitId = unit.id;
        row.qty = 1;
        row.unitPrice = getPrice(product, unit.id);
        row.discount = 0;

        row.search.value = product.name;
        row.code.textContent = product.code || product.sku || '-';
        row.list.classList.add('d-none');

        renderRow(row);
        ensureBlankRow();
        calculateTotals();

        if (row.unitPrice <= 0 && unitSelect.value) {
            row.price.classList.add('is-invalid');
        } else {
            row.price.classList.remove('is-invalid');
        }
    }

    function renderRow(row) {
        if (!row.product) {
            row.code.textContent = '-';
            row.unit.innerHTML = '<option value="">-</option>';
            row.qty.value = '';
            row.price.value = '';
            row.discountInput.value = '';
            row.subtotal.textContent = 'Rp 0';
            row.price.disabled = true;
            row.unit.disabled = true;
            row.qty.disabled = true;
            row.discountInput.disabled = true;
            return;
        }

        const units = getUnits(row.product);

        row.unit.innerHTML = units.map((unit) =>
            '<option value="' + unit.id + '">' + escapeHtml(unit.code) + '</option>'
        ).join('');

        row.unit.value = String(row.unitId);
        row.qty.value = row.qty;
        row.price.value = row.unitPrice;
        row.discountInput.value = row.discount;
        row.subtotal.textContent = rupiah(lineTotal(row));

        row.price.disabled = false;
        row.unit.disabled = false;
        row.qty.disabled = false;
        row.discountInput.disabled = false;
    }

    function createRow() {
        const row = {
            product: null,
            unitId: null,
            qty: 0,
            unitPrice: 0,
            discount: 0,
            element: null
        };

        row.element = document.createElement('tr');
        row.element.innerHTML = `
            <td class="align-top" style="min-width:280px">
                <div class="position-relative">
                    <input type="search" class="form-control form-control-sm product-search" placeholder="Cari kode / nama barang..." autocomplete="off">
                    <div class="list-group position-absolute top-100 start-0 w-100 shadow-sm product-results d-none" style="z-index:1050;max-height:280px;overflow:auto"></div>
                </div>
            </td>
            <td class="align-top text-nowrap"><span class="fw-semibold product-code">-</span></td>
            <td class="align-top" style="width:120px">
                <select class="form-select form-select-sm item-unit" disabled><option value="">-</option></select>
            </td>
            <td class="align-top" style="width:110px">
                <input type="number" min="0.001" step="0.001" class="form-control form-control-sm text-end item-qty" disabled>
            </td>
            <td class="align-top" style="width:160px">
                <input type="number" min="0" step="0.0001" class="form-control form-control-sm text-end item-price" disabled readonly>
            </td>
            <td class="align-top" style="width:140px">
                <input type="number" min="0" step="0.01" class="form-control form-control-sm text-end item-discount" disabled value="0">
            </td>
            <td class="align-top text-end text-nowrap">
                <span class="fw-semibold item-subtotal">Rp 0</span>
            </td>
        `;

        row.search = row.element.querySelector('.product-search');
        row.code = row.element.querySelector('.product-code');
        row.list = row.element.querySelector('.product-results');
        row.unit = row.element.querySelector('.item-unit');
        row.qtyInput = row.element.querySelector('.item-qty');
        row.price = row.element.querySelector('.item-price');
        row.discountInput = row.element.querySelector('.item-discount');
        row.subtotal = row.element.querySelector('.item-subtotal');

        row.search.addEventListener('input', () => {
            if (row.product) {
                row.product = null;
                row.unitId = null;
                row.qty = 0;
                row.unitPrice = 0;
                row.discount = 0;
                renderRow(row);
            }
            renderProductOptions(row);
            calculateTotals();
        });

        row.search.addEventListener('focus', () => {
            if (row.search.value.trim()) renderProductOptions(row);
        });

        row.unit.addEventListener('change', () => {
            row.unitId = Number(row.unit.value);
            row.unitPrice = getPrice(row.product, row.unitId);
            row.price.value = row.unitPrice;
            row.subtotal.textContent = rupiah(lineTotal(row));

            if (row.unitPrice <= 0) {
                row.price.classList.add('is-invalid');
            } else {
                row.price.classList.remove('is-invalid');
            }

            calculateTotals();
        });

        row.qtyInput.addEventListener('input', () => {
            row.qty = Number(row.qtyInput.value || 0);
            row.subtotal.textContent = rupiah(lineTotal(row));
            calculateTotals();
        });

        row.discountInput.addEventListener('input', () => {
            row.discount = Math.max(Number(row.discountInput.value || 0), 0);
            row.discount = Math.min(row.discount, Number(row.qty) * Number(row.unitPrice));
            row.discountInput.value = row.discount;
            row.subtotal.textContent = rupiah(lineTotal(row));
            calculateTotals();
        });

        body.appendChild(row.element);
        rows.push(row);
        renderRow(row);

        return row;
    }

    function ensureBlankRow() {
        if (!rows.length || rows[rows.length - 1].product) {
            createRow();
        }
    }

    function customerChoices() {
        return document.querySelectorAll('.customer-choice');
    }

    customerChoices().forEach((button) => {
        button.addEventListener('click', () => {
            selectedCustomerId = button.dataset.id;
            document.getElementById('customerSearch').value = button.dataset.name;
            document.getElementById('customerInfo').textContent = button.dataset.name;
            document.getElementById('customerBalance').textContent = rupiah(button.dataset.balance);
            document.getElementById('arrears').classList.toggle(
                'd-none',
                Number(button.dataset.balance || 0) <= 0
            );

            bootstrap.Modal.getOrCreateInstance(customerModal).hide();
        });
    });

    customerFilter.addEventListener('input', (event) => {
        const query = event.target.value.toLowerCase();

        customerChoices().forEach((button) => {
            button.classList.toggle(
                'd-none',
                !button.textContent.toLowerCase().includes(query)
            );
        });
    });

    document.addEventListener('click', (event) => {
        rows.forEach((row) => {
            if (!row.element.contains(event.target)) {
                row.list.classList.add('d-none');
            }
        });
    });

    unitSelect.addEventListener('change', () => {
        rows.filter((row) => row.product).forEach((row) => {
            row.unitPrice = getPrice(row.product, row.unitId);
            row.price.value = row.unitPrice;
            row.price.classList.toggle('is-invalid', row.unitPrice <= 0);
            row.subtotal.textContent = rupiah(lineTotal(row));
        });

        calculateTotals();
    });

    discountInput.addEventListener('input', calculateTotals);

    paymentMethod.addEventListener('change', (event) => {
        dueDate.classList.toggle('d-none', event.target.value !== 'Kredit / Bon');
    });

    document.getElementById('saveSales').addEventListener('click', async () => {
        try {
            if (!unitSelect.value) {
                throw new Error('Business Unit wajib dipilih.');
            }

            const selectedRows = rows.filter((row) => row.product);

            if (!selectedRows.length) {
                throw new Error('Pilih minimal satu barang.');
            }

            const invalidRow = selectedRows.find((row) =>
                Number(row.qty) <= 0 || Number(row.unitPrice) <= 0
            );

            if (invalidRow) {
                throw new Error('Pastikan qty dan harga semua barang sudah tersedia.');
            }

            const totals = calculateTotals();

            const payload = {
                customer_id: selectedCustomerId ? Number(selectedCustomerId) : null,
                unit_id: Number(unitSelect.value),
                payment_method: paymentMethod.value,
                due_date: dueDate.querySelector('input').value || null,
                memo: document.getElementById('memoInput').value || null,
                discount: totals.discount,
                items: selectedRows.map((row) => ({
                    product_id: Number(row.product.id),
                    unit_id: Number(row.unitId),
                    qty: Number(row.qty),
                    unit_price: Number(row.unitPrice),
                    discount: Number(row.discount || 0)
                }))
            };

            const response = await fetch(config.storeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify(payload)
            });

            const json = await response.json();

            if (!response.ok) {
                throw new Error(
                    json.message ||
                    Object.values(json.errors || {}).flat().join(' ') ||
                    'Gagal menyimpan penjualan.'
                );
            }

            const doPrint = window.confirm('Penjualan berhasil disimpan. Cetak penjualan sekarang?');
            window.location.href = doPrint
                ? (json.redirect || config.indexUrl) + '?print=1'
                : config.createUrl;
        } catch (error) {
            alert(error.message);
        }
    });

    createRow();
    calculateTotals();
})();
