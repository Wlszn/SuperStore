(() => {
    const payment = document.getElementById('payment');
    const checkoutKey = payment.elements.namedItem('key').value;
    const storageKey = 'superstore.basket';
    const cart = new Map();
    try {
        const saved = JSON.parse(sessionStorage.getItem(storageKey));
        if (saved?.key === checkoutKey) {
            for (const item of saved.items) cart.set(item.id, item);
        }
    } catch {}

    function saveBasket() {
        try {
            sessionStorage.setItem(storageKey, JSON.stringify({key: checkoutKey, items: [...cart.values()]}));
        } catch {}
    }

    const notice = document.getElementById('notice');
    const basket = document.getElementById('basket');
    const pay = document.getElementById('pay');
    let busy = false;
    let scanCursor = null;
    let readerSession;
    let pollTimer;
    const seenTags = new Set();
    const reader = document.getElementById('reader');
    const readerStatus = document.getElementById('reader-status');

    async function pollReader(session) {
        let changed = false;
        try {
            if (busy) return;

            const query = scanCursor === null ? '' : '?after=' + scanCursor;
            const options = { signal: session.signal, cache: 'no-store' };
            const response = await fetch('/api/scans/latest' + query, options);
            if (!response.ok) throw new Error('Reader feed unavailable. Retrying.');
            const result = await response.json();
            if (session.signal.aborted || busy) return;

            if (scanCursor === null) {
                scanCursor = result.last_id;
            } else {
                for (const scan of result.scans) {
                    if (seenTags.has(scan.epc)) {
                        scanCursor = scan.id;
                        continue;
                    }
                    const lookup = await fetch('/api/products/lookup?type=epc&code=' + encodeURIComponent(scan.epc), options);
                    if (!lookup.ok && lookup.status !== 404) throw new Error('Product lookup unavailable. Retrying.');
                    const found = await lookup.json();
                    if (session.signal.aborted || busy) return;

                    if (lookup.ok) {
                        const product = found.product;
                        const quantity = (cart.get(product.id)?.quantity || 0) + 1;
                        if (quantity <= Math.min(product.in_stock, 1000)) {
                            cart.set(product.id, {...product, quantity});
                            changed = true;
                        } else {
                            message('Not enough stock for ' + product.name, true);
                        }
                    } else {
                        message('Unknown RFID tag: ' + scan.epc, true);
                    }
                    seenTags.add(scan.epc);
                    scanCursor = scan.id;
                }
            }
            window.setLocalizedText(readerStatus, 'Waiting for RFID tags. Place products on the reader.');
        } catch (error) {
            if (!session.signal.aborted) {
                window.setLocalizedText(readerStatus, error instanceof TypeError
                    ? 'Reader feed unavailable. Retrying.'
                    : error.message);
            }
        } finally {
            if (changed && !busy) {
                render();
                if (notice.hidden || !notice.classList.contains('error')) message('RFID items added to your basket.');
            }
            if (!session.signal.aborted) pollTimer = setTimeout(() => pollReader(session), 1000);
        }
    }

    function stopReader() {
        readerSession?.abort();
        readerSession = undefined;
        clearTimeout(pollTimer);
        window.setLocalizedText(reader, 'Start RFID reader');
    }

    reader.addEventListener('click', () => {
        if (readerSession) {
            stopReader();
            window.setLocalizedText(readerStatus, 'Reader stopped.');
            message('Reader stopped.');
            return;
        }
        scanCursor = null;
        readerSession = new AbortController();
        window.setLocalizedText(reader, 'Stop RFID reader');
        window.setLocalizedText(readerStatus, 'Connecting to the RFID scan feed...');
        message('RFID reader started.');
        pollReader(readerSession);
    });

    window.addEventListener('pagehide', stopReader);
    function message(text, error = false) {
        window.showNotification(text, error);
    }
    function updateTotal() {
        saveBasket();
        let cents = 0;
        for (const product of cart.values()) {
            cents += Math.round(Number(product.price) * 100) * product.quantity;
        }
        const total = 'Total $' + (cents / 100).toFixed(2);
        window.setLocalizedText(document.getElementById('total'), total);

    }

    function render() {
        basket.replaceChildren();
        for (const product of cart.values()) {
            const row = document.createElement('tr');
            const name = document.createElement('td');
            name.textContent = product.name;

            const quantityCell = document.createElement('td');
            const quantity = document.createElement('input');
            quantity.className = 'basket-quantity';
            quantity.type = 'number';
            quantity.min = '1';
            quantity.max = String(Math.min(product.in_stock, 1000));
            quantity.step = '1';
            quantity.value = String(product.quantity);
            quantity.disabled = busy;
            quantity.dataset.i18nAriaLabel = 'Quantity';
            quantity.setAttribute('aria-label', window.translate('Quantity'));
            quantity.addEventListener('change', () => {
                const count = quantity.valueAsNumber;
                if (!Number.isInteger(count) || count < 1 || count > Number(quantity.max)) {
                    quantity.value = String(product.quantity);
                    message('Enter a quantity between 1 and ' + quantity.max + '.', true);
                    return;
                }
                product.quantity = count;
                updateTotal();
                message('Quantity updated.');
            });
            quantityCell.append(quantity);

            const price = document.createElement('td');
            price.textContent = '$' + Number(product.price).toFixed(2);
            row.append(name, quantityCell, price);
            const cell = document.createElement('td');
            const remove = document.createElement('button');
            remove.className = 'button';
            window.setLocalizedText(remove, "Remove");
            remove.disabled = busy;
            remove.onclick = () => { cart.delete(product.id); render(); message('Item removed.'); };
            cell.append(remove);
            row.append(cell);
            basket.append(row);
        }
        updateTotal();
        document.getElementById('empty').hidden = cart.size > 0;
        pay.disabled = busy || !cart.size;
        reader.disabled = busy;
    }
    const picker = document.getElementById('checkout-product');
    const scanInput = document.getElementById('code');
    picker?.addEventListener('change', () => {
        scanInput.value = picker.value;
        scanInput.focus();
        scanInput.select();
        if (picker.value) message('Product selected. Add it to your basket.');
    });

    document.getElementById('scan').onsubmit = async event => {
        event.preventDefault();
        if (busy) return;
        busy = true;
        render();
        const input = document.getElementById('code');
        try {
            const response = await fetch('/api/products/lookup?code=' + encodeURIComponent(input.value.trim()));
            const result = await response.json();
            if (!response.ok) throw new Error(result.message);
            const product = result.product;
            const quantity = (cart.get(product.id)?.quantity || 0) + 1;
            if (quantity > Math.min(product.in_stock, 1000)) throw new Error('No more stock available for this product.');
            cart.set(product.id, { ...product, quantity });
            input.value = '';
            if (picker) picker.value = '';
            message('Item added.');
        } catch (error) { message(error.message || 'Scanner lookup unavailable. Please retry.', true); }
        finally { busy = false; render(); input.focus(); }
    };
    document.getElementById('membership-code').addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            message('Membership code entered. It will be checked when you confirm payment.');
        }
    });
    const deliveryOption = payment.elements.namedItem('delivery');
    const address = payment.elements.namedItem('address');

    function estimateDelivery() {
        const date = new Date();
        const days = { standard: 3, express: 1, pickup: 0 };
        date.setDate(date.getDate() + days[deliveryOption.value]);
        return new Intl.DateTimeFormat('en-CA', {year: 'numeric', month: '2-digit', day: '2-digit'}).format(date);
    }

    function updateOrderForm() {
        const pickup = deliveryOption.value === 'pickup';
        document.getElementById('shipping-address').hidden = pickup;
        address.disabled = pickup;
        address.required = !pickup;
        window.setLocalizedText(document.getElementById('order-estimate'), 'Estimated fulfillment: ' + estimateDelivery());
    }

    deliveryOption.addEventListener('change', () => {
        updateOrderForm();
        message('Delivery option updated.');
    });

    function validateContactDetails() {
        const fields = payment.elements;
        const name = fields.namedItem('name');
        const phone = fields.namedItem('phone');
        const digits = phone.value.replace(/\D/g, '');
        name.value = name.value.trim();
        address.value = address.value.trim();
        phone.setCustomValidity(/^[0-9+() .-]+$/.test(phone.value) && digits.length >= 7 && digits.length <= 15
            ? '' : window.translate('Please enter a valid phone number.'));
        if (payment.checkValidity()) return true;
        message('Check your contact and delivery details.', true);
        payment.reportValidity();
        return false;
    }

    payment.onsubmit = async event => {
        event.preventDefault();
        if (busy || !cart.size || !validateContactDetails()) return;
        stopReader();
        window.setLocalizedText(readerStatus, "Reader stopped for payment.");
        busy = true;
        render();
        const body = new FormData();
        for (const field of ['csrf_token', 'key', 'membership_code']) {
            body.set(field, payment.elements.namedItem(field).value);
        }
        body.set('items', JSON.stringify([...cart.values()].map(p => ({id:p.id, quantity:p.quantity}))));
        try {
            const response = await fetch('/checkout', {method:'POST',body});
            const result = await response.json();
            if (!response.ok) throw new Error(result.message);
            try {
                const reference = 'SS-' + String(result.receipt_id).padStart(6, '0');
                const fulfillment = { delivery: deliveryOption.value, estimate: estimateDelivery() };
                sessionStorage.setItem('superstore.fulfillment.' + reference, JSON.stringify(fulfillment));
                sessionStorage.setItem('superstore.inventory.notice', 'Payment confirmed.');
            } catch {}
            location.assign(result.redirect);
        } catch (error) {
            message(error.message || 'Result uncertain. Retry this basket to check payment safely.', true);
            busy = false;
            render();
        }
    };
    updateOrderForm();
    render();
    document.getElementById('code').select();
})();
