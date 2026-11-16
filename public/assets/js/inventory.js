(() => {
    const notice = document.getElementById('notice');
    const noticeKey = 'superstore.inventory.notice';

    function displayNotice(element, message, isError) {
        window.setLocalizedText(element, message);
        element.classList.toggle('error', isError);
        element.hidden = false;
        element.focus();
    }

    try {
        const message = sessionStorage.getItem(noticeKey);
        if (message) {
            sessionStorage.removeItem(noticeKey);
            displayNotice(notice, message, false);
        }
    } catch {
        // Inventory still works when browser storage is disabled.
    }

    for (const form of document.querySelectorAll('[data-action-form]')) {
        const fields = form.querySelectorAll('[data-error]');
        const feedback = form.querySelector('.notice') || notice;
        const submit = form.querySelector('button[type="submit"]');
        for (const slot of fields) {
            const input = form.elements.namedItem(slot.dataset.error);
            slot.id = 'error-' + slot.dataset.error;
            input.setAttribute('aria-describedby', slot.id);
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (submit.disabled) return;

            submit.disabled = true;
            feedback.hidden = true;
            for (const slot of fields) {
                window.setLocalizedText(slot, '');
                form.elements.namedItem(slot.dataset.error).removeAttribute('aria-invalid');
            }

            let navigating = false;
            try {
                const result = await postForm(form.getAttribute('action').slice(1), form);
                if (result.status !== 'success') {
                    displayNotice(feedback, result.message || 'Could not save your changes.', true);
                    let firstError;
                    for (const slot of fields) {
                        const message = result.errors?.[slot.dataset.error];
                        if (!message) continue;
                        const input = form.elements.namedItem(slot.dataset.error);
                        window.setLocalizedText(slot, message);
                        input.setAttribute('aria-invalid', 'true');
                        firstError ??= input;
                    }
                    firstError?.focus();
                    return;
                }

                let destination = form.dataset.next;
                if ('newProduct' in form.dataset) {
                    destination = '/products/show?id=' + result.product.id;
                }
                if ('reception' in form.dataset) {
                    destination = '/products/show?id=' + result.reception.product_id;
                }

                try {
                    sessionStorage.setItem(noticeKey, result.message);
                } catch {
                    window.alert(result.message);
                }
                navigating = true;
                window.location.assign(destination);
            } catch {
                displayNotice(feedback, 'Could not confirm the result. Check the inventory before trying again.', true);
            } finally {
                if (!navigating) submit.disabled = false;
            }
        });
    }

    const deleteDialog = document.getElementById('delete-product');
    document.querySelector('[data-open-delete]')?.addEventListener('click', () => deleteDialog.showModal());
    document.querySelector('[data-cancel-delete]')?.addEventListener('click', () => deleteDialog.close());

    const upcEntry = document.querySelector('[data-upc-entry]');
    upcEntry?.addEventListener('focus', () => upcEntry.select());
    upcEntry?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') event.preventDefault();
    });

    const search = document.getElementById('search');
    const stockFilter = document.getElementById('stock-filter');
    const rows = [...document.querySelectorAll('[data-product]')];

    function filterProducts() {
        const query = search.value.trim().toLowerCase();
        let visible = 0;
        for (const row of rows) {
            const matches = row.dataset.search.toLowerCase().includes(query);
            row.hidden = !matches || (stockFilter.checked && row.dataset.low !== 'true');
            if (!row.hidden) visible++;
        }
        const noResults = document.getElementById('no-results');
        if (noResults) noResults.hidden = visible > 0;
        window.setLocalizedText(document.getElementById('product-count'), visible + (visible === 1 ? ' product' : ' products'));
    }

    search?.addEventListener('input', filterProducts);
    stockFilter?.addEventListener('change', filterProducts);

    const scanToggle = document.querySelector('.scan-toggle');
    scanToggle?.addEventListener('click', () => {
        const scan = document.getElementById('scan-product');
        scan.hidden = !scan.hidden;
        scanToggle.setAttribute('aria-expanded', String(!scan.hidden));
        if (!scan.hidden) scan.querySelector('input').focus();
    });

    const productSelect = document.getElementById('received-product');
    const receptionSummary = document.getElementById('latest-reception');
    const receivingBarcode = document.getElementById('receive-upc');
    let receptionRequest;

    async function updateReceptionSummary() {
        receptionRequest?.abort();
        const option = productSelect.selectedOptions[0];
        const code = option?.dataset.upc || option?.dataset.epc;
        document.getElementById('receiving-checkout').href = code ? '/checkout?code=' + encodeURIComponent(code) : '/checkout';
        receptionSummary.hidden = !option?.value;
        if (receptionSummary.hidden) return;

        receivingBarcode.value = option.dataset.upc || '';
        const request = new AbortController();
        receptionRequest = request;
        window.setLocalizedText(receptionSummary, 'Loading last delivery...');
        try {
            const response = await fetch('/api/receptions/latest?id=' + encodeURIComponent(option.value), {
                signal: request.signal,
            });
            if (!response.ok) throw new Error('Could not load the last delivery.');
            const { reception } = await response.json();
            if (request.signal.aborted) return;
            window.setLocalizedText(receptionSummary, reception
                ? 'Last delivery: ' + reception.quantity + ' on ' + reception.received_at
                : 'No previous deliveries.');
        } catch (error) {
            if (!request.signal.aborted) {
                window.setLocalizedText(receptionSummary, 'Could not load the last delivery.');
            }
        }
    }

    const demoDelivery = document.getElementById('demo-delivery');
    demoDelivery?.addEventListener('click', () => {
        if (!productSelect.value) {
            productSelect.reportValidity();
            productSelect.focus();
            return;
        }
        const quantity = productSelect.form.elements.namedItem('quantity');
        const date = productSelect.form.elements.namedItem('received_at');
        quantity.value = '12';
        date.value = date.max;
        quantity.focus();
    });

    if (productSelect) {
        productSelect.addEventListener('change', () => {
            receivingBarcode.value = '';
            updateReceptionSummary();
        });
        updateReceptionSummary();
    }


    for (const scan of document.querySelectorAll('[data-barcode-form]')) {
        const input = scan.querySelector('input');
        const status = scan.querySelector('.scan-status');
        const addProduct = scan.querySelector('.scan-add');

        function showScanStatus(message, isError = false) {
            window.setLocalizedText(status, message);
            status.classList.toggle('error', isError);
        }

        scan.addEventListener('submit', (event) => {
            event.preventDefault();
            const code = input.value.trim();
            addProduct.hidden = true;
            if (productSelect) {
                productSelect.value = '';
                updateReceptionSummary();
            }

            if ((!code || [...code].length > 13 || /[\x00-\x1F\x7F]/.test(code))) {
                showScanStatus('Enter a barcode with 1 to 13 printable characters.', true);
                input.select();
                return;
            }

            if (productSelect) {
                const match = [...productSelect.options].find((option) => option.dataset.upc === code);
                if (match) {
                    productSelect.value = match.value;
                    updateReceptionSummary();
                    showScanStatus('Product selected.');
                    productSelect.form.elements.namedItem('quantity').focus();
                    return;
                }
            } else {
                const match = rows.find((row) => row.dataset.upc === code);
                if (match) {
                    window.location.assign(match.querySelector('.product-name').href);
                    return;
                }
            }

            showScanStatus('No product found for ' + code + '.', true);
            addProduct.href = '/products/create?upc=' + encodeURIComponent(code);
            addProduct.hidden = false;
            input.select();
        });
        input.addEventListener('input', () => {
            showScanStatus('');
            addProduct.hidden = true;
        });
    }

    const capture = document.getElementById('capture-epc');
    capture?.addEventListener('click', async () => {
        const status = document.getElementById('epc-status');
        capture.disabled = true;
        try {
            let response = await fetch('/api/scans/latest');
            if (!response.ok) throw new Error('Reader feed unavailable.');
            const initial = await response.json();
            window.setLocalizedText(status, 'Place one tag on the reader. Waiting up to 20 seconds.');
            const deadline = Date.now() + 20000;
            while (Date.now() < deadline) {
                await new Promise(resolve => setTimeout(resolve, 1000));
                response = await fetch('/api/scans/latest?after=' + initial.last_id);
                if (!response.ok) throw new Error('Reader feed unavailable.');
                const result = await response.json();
                const epcs = [...new Set(result.scans.map(scan => scan.epc))];
                if (epcs.length > 1) throw new Error('Multiple tags detected. Leave only one tag and try again.');
                if (epcs.length === 1) {
                    document.querySelector('[name=epc]').value = epcs[0];
                    window.setLocalizedText(status, 'EPC captured. Save the product to assign it.');
                    return;
                }
            }
            window.setLocalizedText(status, 'No tag received. Check that the RFID bridge is running and try again.');
        } catch (error) {
            window.setLocalizedText(status, error.message);
        } finally {
            capture.disabled = false;
        }
    });
})();
