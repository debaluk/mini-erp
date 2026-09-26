(function () {
    const dataNode = document.getElementById('salesCreateData');
    if (!dataNode) return;

    const config = JSON.parse(dataNode.textContent || '{}');

    const products = Array.isArray(config.products) ? config.products : [];
    const body = document.getElementById('detailBody');
    const unitSelect = document.getElementById('unitSelect');
    const customerSearch = document.getElementById('customerSearch');
    const customerId = document.getElementById('customerId');
    const customerFilter = document.getElementById('customerFilter');
    const customerModal = document.getElementById('customerModal');
    const paymentMethod = document.getElementById('paymentMethod');
    const dueDateBox = document.getElementById('dueDate');
    const dueDate = dueDateBox?.querySelector('input[type="date"]');
    const discountInput = document.getElementById('discountInput');
    const memoInput = document.getElementById('memo');

    const productModalEl = document.getElementById('productModal');
    const productFilter = document.getElementById('productFilter');
    const productList = document.getElementById('productList');

    const mode = config.mode || 'tempo';
    const requireCustomer = config.requireCustomer !== false;
    const allowCredit = config.allowCredit !== false;

    let rows = [];
    let activeRowIndex = null;
    let selectedCustomerId = customerId ? customerId.value : '';

    function money(value) {
        return new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        }).format(Number(value || 0));
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function currentBusinessUnit() {
        return Number(unitSelect?.value || 0);
    }

    function findProduct(value) {
        const needle = String(value ?? '').trim().toLowerCase();
        if (!needle) return null;

        return products.find(product =>
            String(product.barcode ?? '').trim().toLowerCase() === needle
        ) || null;
    }

    function getPrice(product) {
        const buId = currentBusinessUnit();

        const prices = Array.isArray(product?.prices) ? product.prices : [];

        const price = prices.find(item =>
            Number(item.business_unit_id) === buId &&
            Number(item.unit_id) === Number(product.base_unit_id)
        );

        return Number(price?.selling_price || 0);
    }

    function createBlankRow() {
        return {
            product_id: '',
            code: '',
            name: '',
            barcode: '',
            unit_id: '',
            unit_code: '',
            unit_name: '',
            qty: 1,
            price: 0,
            discount: 0
        };
    }

    function ensureBlankRow() {
        if (!rows.length || rows[rows.length - 1].product_id) {
            rows.push(createBlankRow());
        }
    }

    function fillRow(index, product) {
        if (!product) return;

        rows[index] = {
            product_id: Number(product.id),
            code: product.code || '',
            name: product.name || '',
            barcode: product.barcode || '',
            unit_id: Number(product.base_unit_id),
            unit_code: product.base_unit_code || '',
            unit_name: product.base_unit_name || '',
            qty: 1,
            price: getPrice(product),
            discount: 0
        };

        ensureBlankRow();
        renderRows();
        calculateTotals();
    }

    function rowSubtotal(row) {
        const gross = Number(row.qty || 0) * Number(row.price || 0);
        const discount = Number(row.discount || 0);
        return Math.max(0, gross - discount);
    }

    function renderRows() {
        if (!body) return;

        body.innerHTML = '';

        rows.forEach((row, index) => {
            const blank = !row.product_id;

            const tr = document.createElement('tr');

            tr.innerHTML = `
                <td style="min-width:220px">
                    ${
                        blank
                        ? `
                            <div class="input-group input-group-sm">
                                <input type="text"
                                       class="form-control barcode-input"
                                       data-index="${index}"
                                       placeholder="Scan / ketik barcode">
                                <button type="button"
                                        class="btn btn-outline-secondary choose-product"
                                        data-index="${index}">
                                    Pilih
                                </button>
                            </div>
                        `
                        : `
                            <div class="fw-semibold">${escapeHtml(row.code)}</div>
                            <div class="small text-muted">${escapeHtml(row.barcode)}</div>
                        `
                    }
                </td>

                <td style="min-width:260px">
                    ${blank ? '<span class="text-muted">Belum dipilih</span>' : escapeHtml(row.name)}
                </td>

                <td>
                    ${blank ? '-' : escapeHtml(row.unit_code || row.unit_name)}
                </td>

                <td style="width:85px;max-width:85px">
                    ${
                        blank
                        ? '<span class="text-muted">-</span>'
                        : `<input type="number"
                                  min="1"
                                  step="1"
                                  class="form-control form-control-sm qty-input text-end"
                                  data-index="${index}"
                                  value="${Number(row.qty || 1)}">`
                    }
                </td>

                <td style="min-width:145px">
                    ${
                        blank
                        ? '<span class="text-muted">-</span>'
                        : `<input type="text"
                                  inputmode="numeric"
                                  class="form-control form-control-sm price-input text-end"
                                  data-index="${index}"
                                  value="${money(row.price)}">`
                    }
                </td>

                <td style="width:105px;max-width:105px">
                    ${
                        blank
                        ? '<span class="text-muted">-</span>'
                        : `<input type="text"
                                  inputmode="numeric"
                                  class="form-control form-control-sm discount-row-input text-end"
                                  data-index="${index}"
                                  value="${money(row.discount)}"
                                  disabled>`
                    }
                </td>

                <td class="text-end subtotal-cell" style="width:115px;max-width:115px">
                    ${blank ? '-' : money(rowSubtotal(row))}
                </td>

                <td class="text-center" style="width:42px;max-width:42px;padding-left:2px;padding-right:2px">
                    ${
                        blank
                        ? ''
                        : `<button type="button"
                                   class="btn btn-sm btn-outline-danger delete-row px-2"
                                   data-index="${index}"
                                   title="Hapus barang"
                                   aria-label="Hapus barang">
                               ×
                           </button>`
                    }
                </td>
            `;

            body.appendChild(tr);
        });
    }

    function renderProductList(keyword = '') {
        if (!productList) return;

        const needle = String(keyword).trim().toLowerCase();
        const buId = currentBusinessUnit();

        const filtered = products.filter(product => {
            if (!needle) return true;

            return [
                product.code,
                product.name,
                product.barcode,
                product.sku
            ].some(value =>
                String(value ?? '').toLowerCase().includes(needle)
            );
        });

        productList.innerHTML = filtered.map(product => {
            const price = getPrice(product);

            return `
                <tr>
                    <td>${escapeHtml(product.code)}</td>
                    <td>${escapeHtml(product.name)}</td>
                    <td>${escapeHtml(product.barcode || '-')}</td>
                    <td>${escapeHtml(product.base_unit_code || product.base_unit_name || '-')}</td>
                    <td class="text-end">${money(price)}</td>
                    <td class="text-center">
                        <button type="button"
                                class="btn btn-sm btn-primary choose-product-modal"
                                data-product-id="${product.id}">
                            Pilih
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        if (!filtered.length) {
            productList.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center text-muted py-3">
                        Barang tidak ditemukan.
                    </td>
                </tr>
            `;
        }
    }

    function openProductModal(index) {
        activeRowIndex = index;

        if (!productModalEl) return;

        if (productFilter) {
            productFilter.value = '';
        }

        renderProductList('');

        bootstrap.Modal.getOrCreateInstance(productModalEl).show();

        setTimeout(() => productFilter?.focus(), 150);
    }

    function selectProductFromModal(productId) {
        const product = products.find(item => Number(item.id) === Number(productId));
        if (!product || activeRowIndex === null) return;

        fillRow(activeRowIndex, product);

        if (productModalEl) {
            bootstrap.Modal.getOrCreateInstance(productModalEl).hide();
        }

        activeRowIndex = null;
    }

    discountInput?.addEventListener('input', calculateTotals);

    function calculateTotals() {
        const detailRows = rows.filter(row => row.product_id);

        const subtotal = detailRows
            .reduce((sum, row) => sum + rowSubtotal(row), 0);

        if (detailRows.length === 0 && discountInput) {
            discountInput.value = 0;
        }

        const discount = parseInt(discountInput?.value || 0, 10) || 0;
        const total = Math.max(0, subtotal - discount);

        const subtotalElement = document.getElementById('subtotalAmount');
        const totalElement = document.getElementById('totalAmount');

        if (subtotalElement) {
            subtotalElement.textContent = money(subtotal);
        }

        if (totalElement) {
            totalElement.textContent = money(total);
        }

        return total;
    }

    function removeRow(index) {
        if (!rows[index]?.product_id) return;

        rows.splice(index, 1);
        ensureBlankRow();
        renderRows();
        calculateTotals();
    }

    function handleBarcode(index, input) {
        const value = input.value.trim();
        if (!value) return;

        const product = findProduct(value);

        if (!product) {
            input.classList.add('is-invalid');
            return;
        }

        input.classList.remove('is-invalid');
        fillRow(index, product);
    }

    body?.addEventListener('click', event => {
        const choose = event.target.closest('.choose-product');
        if (choose) {
            openProductModal(Number(choose.dataset.index));
            return;
        }

        const remove = event.target.closest('.delete-row');
        if (remove) {
            removeRow(Number(remove.dataset.index));
        }
    });

    body?.addEventListener('change', event => {
        const index = Number(event.target.dataset.index);

        if (event.target.classList.contains('qty-input')) {
            rows[index].qty = Math.max(1, Number(event.target.value || 1));
        }

        if (event.target.classList.contains('price-input')) {
            const value = event.target.value.replace(/[^0-9]/g, '');
            rows[index].price = Math.max(0, Number(value || 0));
        }

        if (event.target.classList.contains('discount-row-input')) {
            const value = event.target.value.replace(/[^0-9]/g, '');
            rows[index].discount = Math.max(0, Number(value || 0));
        }

        renderRows();
        calculateTotals();
    });

    body?.addEventListener('keydown', event => {
        if (!event.target.classList.contains('barcode-input')) return;

        if (event.key === 'Enter') {
            event.preventDefault();
            handleBarcode(Number(event.target.dataset.index), event.target);
        }
    });

    body?.addEventListener('blur', event => {
        if (!event.target.classList.contains('barcode-input')) return;

        handleBarcode(Number(event.target.dataset.index), event.target);
    }, true);

    productList?.addEventListener('click', event => {
        const chooseModal = event.target.closest('.choose-product-modal');
        if (!chooseModal) return;

        selectProductFromModal(Number(chooseModal.dataset.productId));
    });

    productFilter?.addEventListener('input', () => {
        renderProductList(productFilter.value);
    });

    unitSelect?.addEventListener('change', () => {
        rows = rows.map(row => {
            if (!row.product_id) return row;

            const product = products.find(item => Number(item.id) === Number(row.product_id));
            if (!product) return row;

            return {
                ...row,
                price: getPrice(product)
            };
        });

        renderRows();
        calculateTotals();
    });

    customerFilter?.addEventListener('input', () => {
        const keyword = customerFilter.value.toLowerCase();

        document.querySelectorAll('#customerList .customer-choice').forEach(row => {
            row.style.display =
                row.textContent.toLowerCase().includes(keyword)
                    ? ''
                    : 'none';
        });
    });

    document.addEventListener('click', event => {
        const customerButton = event.target.closest('.customer-choice');
        if (!customerButton) return;

        selectedCustomerId = customerButton.dataset.id || '';

        if (customerId) {
            customerId.value = selectedCustomerId;
        }

        if (customerSearch) {
            customerSearch.value =
                customerButton.dataset.name ||
                customerButton.textContent.trim();
        }

        if (customerModal) {
            bootstrap.Modal.getOrCreateInstance(customerModal).hide();
        }
    });

    function updateDueDateState() {
        if (!paymentMethod || !dueDate) return;

        const method = String(paymentMethod.value || '').toLowerCase();
        const isCredit = method.includes('kredit') || method.includes('bon');

        dueDate.required = isCredit && allowCredit;
        dueDate.disabled = !isCredit || !allowCredit;

        if (dueDateBox) {
            dueDateBox.classList.toggle(
                'd-none',
                !isCredit || !allowCredit
            );
        }

        if (!isCredit || !allowCredit) {
            dueDate.value = '';
        }
    }

    paymentMethod?.addEventListener('change', updateDueDateState);

    function markInvalid(element) {
        element?.classList.add('is-invalid');
    }

    function clearInvalid(element) {
        element?.classList.remove('is-invalid');
    }

    unitSelect?.addEventListener('change', () => clearInvalid(unitSelect));
    customerSearch?.addEventListener('change', () => clearInvalid(customerSearch));
    paymentMethod?.addEventListener('change', () => clearInvalid(dueDate));

    body?.addEventListener('input', event => {
        if (
            event.target.matches('.qty-input') ||
            event.target.matches('.price-input')
        ) {
            clearInvalid(event.target);
        }
    });

    dueDate?.addEventListener('change', () => clearInvalid(dueDate));

    async function submitSale() {
        const buId = currentBusinessUnit();

        if (!buId) {
            markInvalid(unitSelect);
            unitSelect?.focus();
            return;
        }

        clearInvalid(unitSelect);

        if (requireCustomer && !selectedCustomerId && !customerId?.value) {
            markInvalid(customerSearch);
            customerSearch?.focus();
            return;
        }

        clearInvalid(customerSearch);

        const detailRows = rows.filter(row => row.product_id);

        if (!detailRows.length) {
            const barcodeInput = body?.querySelector('.barcode-input');
            markInvalid(barcodeInput);
            barcodeInput?.focus();
            return;
        }

        for (const row of detailRows) {
            const rowElement = body?.querySelector(`tr:nth-child(${rows.indexOf(row) + 1})`);
            const qtyInput = rowElement?.querySelector('.qty-input');
            const priceInput = rowElement?.querySelector('.price-input');

            if (Number(row.qty) <= 0) {
                markInvalid(qtyInput);
                qtyInput?.focus();
                return;
            }

            clearInvalid(qtyInput);

            if (Number(row.price) < 0) {
                markInvalid(priceInput);
                priceInput?.focus();
                return;
            }

            clearInvalid(priceInput);

            if (Number(row.discount) < 0) {
                return;
            }
        }

        const method = String(paymentMethod?.value || '').toLowerCase();
        const isCredit = method.includes('kredit') || method.includes('bon');

        if (isCredit && allowCredit && !dueDate?.value) {
            markInvalid(dueDate);
            dueDate?.focus();
            return;
        }

        clearInvalid(dueDate);

        if (!isCredit || !allowCredit) {
            if (dueDate) {
                dueDate.value = '';
            }
        }

        const payload = {
            customer_id: Number(selectedCustomerId || customerId?.value || 0) || null,
            business_unit_id: buId,
            payment_method: paymentMethod?.value || 'tunai',
            due_date: isCredit && allowCredit ? dueDate?.value || null : null,
            discount: Number(discountInput?.value || 0),
            memo: memoInput?.value || '',
            items: detailRows.map(row => ({
                product_id: Number(row.product_id),
                unit_id: Number(row.unit_id),
                qty: Number(row.qty),
                selling_price: Number(row.price),
                discount: Number(row.discount)
            }))
        };

        const csrf =
            document.querySelector('meta[name="csrf-token"]')?.content ||
            document.querySelector('input[name="_token"]')?.value;

        try {
            const response = await fetch(config.storeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                body: JSON.stringify(payload)
            });

            const result = await response.json().catch(() => ({}));

            if (!response.ok) {
                const message =
                    result.message ||
                    Object.values(result.errors || {}).flat().join('\n') ||
                    'Penjualan gagal disimpan.';

                alert(message);
                return;
            }

            const modalElement = document.getElementById('saleSavedModal');
            const noButton = document.getElementById('saleSavedNo');
            const yesButton = document.getElementById('saleSavedYes');

            if (!modalElement || typeof bootstrap === 'undefined') {
                window.location.href = config.createUrl || window.location.href;
                return;
            }

            const modal = bootstrap.Modal.getOrCreateInstance(modalElement);

            noButton.onclick = () => {
                modal.hide();
                window.location.href = config.createUrl || window.location.href;
            };

            yesButton.onclick = () => {
                modal.hide();

                const printUrl = `/inventori/penjualan/${result.sale_id}/print?print=1`;

                window.open(printUrl, '_blank');
                window.location.href = config.createUrl || window.location.href;
            };

            modal.show();
        } catch (error) {
            console.error(error);
            alert('Terjadi kesalahan saat menyimpan penjualan.');
        }
    }

    const saveButton =
        document.getElementById('saveSales') ||
        document.getElementById('saveSale') ||
        document.getElementById('btnSave') ||
        document.querySelector('[data-action="save-sale"]');

    saveButton?.addEventListener('click', submitSale);

    ensureBlankRow();
    renderRows();
    calculateTotals();
    updateDueDateState();
})();
