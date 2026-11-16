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
    const membership = document.getElementById('membership-code');
    const ownMembership = membership.value;
    const pointsPanel = document.getElementById('points-redemption');
    const pointsInput = document.getElementById('redeem-points');
    const pointsDiscount = document.getElementById('points-discount');
    let pointsLimit = 0;
    let dueCents = 0; // amount to pay after the points discount
    let step = null; // null while scanning, then 'details', 'confirm' or 'payment'
    let busy = false;
    let scanCursor = null;
    let readerSession;
    let pollTimer;
    const seenTags = new Set();
    const reader = document.getElementById('reader');
    const readerStatus = document.getElementById('reader-status');
    const scanInput = document.getElementById('code');
    let lastAutoAddedEpc;

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
                            if (!scanInput.value || scanInput.value === lastAutoAddedEpc) {
                                scanInput.value = scan.epc;
                                lastAutoAddedEpc = scan.epc;
                                if (document.activeElement === scanInput) scanInput.select();
                            }
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
        let discount = 0;
        if (pointsInput) {
            const eligible = membership.value.trim() === ownMembership;
            pointsPanel.hidden = !eligible;
            pointsInput.disabled = !eligible;
            if (!eligible) pointsInput.value = '0';
            pointsLimit = Math.min(cents, Number(pointsPanel.dataset.balance));
            if (eligible && !pointsError()) {
                discount = Number(pointsInput.value);
            }
        }
        pointsDiscount.hidden = discount === 0;
        if (discount > 0) {
            window.setLocalizedText(pointsDiscount, 'Points discount: $' + (discount / 100).toFixed(2));
        }
        dueCents = cents - discount;
        window.setLocalizedText(document.getElementById('total'), 'Total $' + (dueCents / 100).toFixed(2));
        showMember(dueCents);
    }

    // Membership card check: the result of the last lookup, or null when no valid card is entered.
    const memberStatus = document.getElementById('member-status');
    let member = null;

    function showMember(paidCents) {
        if (!member) return;
        // Same rule as complete_checkout(): 10 points per dollar paid, rounded down.
        window.setLocalizedText(memberStatus, 'Member: {0} \u00b7 {1} points. This purchase earns {2} points.'
            .replace('{0}', member.first_name).replace('{1}', member.points).replace('{2}', Math.floor(paidCents / 10)));
    }

    async function lookUpMember() {
        const code = membership.value.trim();
        member = null;
        memberStatus.hidden = code === '';
        memberStatus.classList.remove('error');
        if (code === '') return;
        try {
            const response = await fetch('/api/members/lookup?code=' + encodeURIComponent(code), {cache: 'no-store'});
            const result = await response.json();
            if (code !== membership.value.trim()) return; // the field changed while waiting
            if (!response.ok) throw new Error(result.message);
            member = result.member;
            updateTotal();
            message('Membership card accepted.');
        } catch (error) {
            const text = error.message || 'Membership lookup unavailable. Please retry.';
            memberStatus.classList.add('error');
            window.setLocalizedText(memberStatus, text);
            message(text, true);
        }
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
            quantity.disabled = busy || step !== null;
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
            remove.disabled = busy || step !== null;
            remove.onclick = () => {
                cart.delete(product.id);
                if (lastAutoAddedEpc && product.epc === lastAutoAddedEpc) {
                    if (scanInput.value === lastAutoAddedEpc) scanInput.value = '';
                    lastAutoAddedEpc = undefined;
                }
                render();
                message('Item removed.');
            };
            cell.append(remove);
            row.append(cell);
            basket.append(row);
        }
        updateTotal();
        document.getElementById('empty').hidden = cart.size > 0;
        // Once checkout starts, the basket is locked so the customer pays for the list they confirmed.
        pay.disabled = busy || !cart.size;
        pay.hidden = step !== null;
        reader.disabled = busy || step !== null;
        scanInput.disabled = step !== null;
        if (picker) picker.disabled = step !== null;
    }
    const picker = document.getElementById('checkout-product');
    scanInput.addEventListener('input', () => { lastAutoAddedEpc = undefined; });
    picker?.addEventListener('change', () => {
        lastAutoAddedEpc = undefined;
        scanInput.value = picker.value;
        scanInput.focus();
        scanInput.select();
        if (picker.value) message('Product selected. Add it to your basket.');
    });

    document.getElementById('scan').onsubmit = async event => {
        event.preventDefault();
        if (busy || step !== null) return;
        const code = scanInput.value.trim();
        if (code === lastAutoAddedEpc) {
            message('This RFID tag is already in your basket.');
            scanInput.select();
            return;
        }
        busy = true;
        render();
        try {
            const response = await fetch('/api/products/lookup?code=' + encodeURIComponent(code));
            const result = await response.json();
            if (!response.ok) throw new Error(result.message);
            const product = result.product;
            const quantity = (cart.get(product.id)?.quantity || 0) + 1;
            if (quantity > Math.min(product.in_stock, 1000)) throw new Error('No more stock available for this product.');
            cart.set(product.id, { ...product, quantity });
            scanInput.value = '';
            if (picker) picker.value = '';
            message('Item added.');
        } catch (error) { message(error.message || 'Scanner lookup unavailable. Please retry.', true); }
        finally { busy = false; render(); scanInput.focus(); }
    };
    let previousPointsError;
    function pointsError() {
        if (!pointsInput || pointsInput.disabled || pointsInput.value === '') return;
        if (!/^\d+$/.test(pointsInput.value)) return 'Enter a valid number of points.';

        const points = Number(pointsInput.value);
        if (points > Number(pointsPanel.dataset.balance)) return 'Not enough points for this discount.';
        if (points > pointsLimit) return 'The points discount cannot exceed the order total.';
    }

    function validatePoints() {
        const error = pointsError();
        if (error) {
            pointsInput.setAttribute('aria-invalid', 'true');
            message(error, true);
        } else {
            pointsInput?.removeAttribute('aria-invalid');
            if (previousPointsError) window.clearNotification(previousPointsError);
        }
        previousPointsError = error;
        return !error;
    }

    function onPointsInput() {
        updateTotal();
        validatePoints();
    }

    membership.addEventListener('input', onPointsInput);
    pointsInput?.addEventListener('input', onPointsInput);
    // The USB barcode scanner types the card number then presses Enter.
    membership.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            lookUpMember();
        }
    });
    membership.addEventListener('change', lookUpMember);
    membership.addEventListener('input', () => {
        member = null;
        memberStatus.hidden = true;
    });
    const deliveryOption = payment.elements.namedItem('delivery');
    const address = payment.elements.namedItem('address');
    // In-store purchase by default: contact and delivery details are only asked for a delivery.
    const deliver = document.getElementById('deliver-order');
    const deliveryDetails = document.getElementById('delivery-details');

    function estimateDelivery() {
        const date = new Date();
        const days = { standard: 3, express: 1, pickup: 0 };
        date.setDate(date.getDate() + days[deliveryOption.value]);
        return new Intl.DateTimeFormat('en-CA', {year: 'numeric', month: '2-digit', day: '2-digit'}).format(date);
    }

    function updateOrderForm() {
        deliveryDetails.hidden = !deliver.checked;
        document.getElementById('delivery-heading').hidden = !deliver.checked;
        // Disabled fields are skipped by the browser's form validation.
        for (const field of deliveryDetails.querySelectorAll('input, select')) field.disabled = !deliver.checked;
        const pickup = deliveryOption.value === 'pickup';
        document.getElementById('shipping-address').hidden = pickup;
        address.disabled = pickup || !deliver.checked;
        address.required = !pickup;
        window.setLocalizedText(document.getElementById('order-estimate'), 'Estimated fulfillment: ' + estimateDelivery());
    }

    deliver.addEventListener('change', () => {
        updateOrderForm();
        message(deliver.checked ? 'Enter your contact and delivery details.' : 'In-store purchase. No delivery details needed.');
    });
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
        if (!validatePoints()) {
            pointsInput.focus();
            return false;
        }
        if (payment.checkValidity()) return true;
        message('Check your contact and delivery details.', true);
        payment.reportValidity();
        return false;
    }

    // Checkout steps: 1. membership card and delivery, 2. confirm the list, 3. payment.
    const steps = {
        details: document.getElementById('step-details'),
        confirm: document.getElementById('step-confirm'),
        payment: document.getElementById('step-payment'),
    };
    const cashBox = document.getElementById('cash');
    const cashGiven = document.getElementById('cash-given');
    const change = document.getElementById('change');

    function showStep(name) {
        step = name;
        for (const [key, section] of Object.entries(steps)) section.hidden = key !== name;
        cashBox.hidden = true;
        render();
    }

    pay.addEventListener('click', () => {
        if (busy || !cart.size) return;
        stopReader();
        window.setLocalizedText(readerStatus, 'Reader stopped for payment.');
        showStep('details');
        membership.focus();
        membership.select();
    });

    for (const back of document.querySelectorAll('[data-back]')) {
        back.addEventListener('click', () => { if (!busy) showStep(back.dataset.back || null); });
    }

    async function detailsNext() {
        // A card number that was typed but not checked yet is checked now.
        if (membership.value.trim() !== '' && !member) await lookUpMember();
        if (membership.value.trim() !== '' && !member) {
            membership.focus();
            return;
        }
        if (!validateContactDetails()) return;

        const summary = document.getElementById('summary');
        summary.replaceChildren();
        for (const product of cart.values()) {
            const line = document.createElement('li');
            line.textContent = product.quantity + ' \u00d7 ' + product.name + ' \u00b7 $' + (Number(product.price) * product.quantity).toFixed(2);
            summary.append(line);
        }
        window.setLocalizedText(document.getElementById('summary-member'), member
            ? 'Member: {0} \u00b7 {1} points. This purchase earns {2} points.'
                .replace('{0}', member.first_name).replace('{1}', member.points).replace('{2}', Math.floor(dueCents / 10))
            : 'Guest checkout: no points earned.');
        const deliveryLabels = { standard: 'Delivery: Standard delivery', express: 'Delivery: Express delivery', pickup: 'Delivery: Store pickup' };
        window.setLocalizedText(document.getElementById('summary-delivery'),
            deliver.checked ? deliveryLabels[deliveryOption.value] : 'In-store purchase.');
        showStep('confirm');
    }

    document.getElementById('details-next').addEventListener('click', detailsNext);
    document.getElementById('confirm-list').addEventListener('click', () => showStep('payment'));

    document.getElementById('pay-card').addEventListener('click', () => {
        message('Card approved (simulated). Saving your purchase...');
        submitPayment('card');
    });
    document.getElementById('pay-cash').addEventListener('click', () => {
        cashBox.hidden = false;
        cashGiven.value = '';
        change.textContent = '';
        cashGiven.focus();
    });
    cashGiven.addEventListener('input', () => {
        const given = Math.round(cashGiven.valueAsNumber * 100);
        window.setLocalizedText(change, given >= dueCents ? 'Change: $' + ((given - dueCents) / 100).toFixed(2) : '');
    });

    // Enter in a field submits the form: continue in step 1, pay in the cash box.
    payment.onsubmit = event => {
        event.preventDefault();
        if (step === 'details') {
            detailsNext();
            return;
        }
        if (step !== 'payment' || cashBox.hidden) return;
        if (!(Math.round(cashGiven.valueAsNumber * 100) >= dueCents)) {
            message('The cash given must cover the total.', true);
            return;
        }
        submitPayment('cash');
    };

    async function submitPayment(method) {
        if (busy || !cart.size) return;
        busy = true;
        render();
        const body = new FormData();
        for (const field of ['csrf_token', 'key', 'membership_code']) {
            body.set(field, payment.elements.namedItem(field).value);
        }
        if (pointsInput && !pointsInput.disabled) body.set('redeem_points', String(Number(pointsInput.value)));
        body.set('payment_method', method);
        body.set('items', JSON.stringify([...cart.values()].map(p => ({id:p.id, quantity:p.quantity}))));
        try {
            const response = await fetch('/checkout', {method:'POST',body});
            const result = await response.json();
            if (!response.ok) throw new Error(result.message);
            try {
                if (deliver.checked) {
                    const reference = 'SS-' + String(result.receipt_id).padStart(6, '0');
                    const fulfillment = { delivery: deliveryOption.value, estimate: estimateDelivery() };
                    sessionStorage.setItem('superstore.fulfillment.' + reference, JSON.stringify(fulfillment));
                }
                sessionStorage.setItem('superstore.inventory.notice', 'Payment confirmed.');
            } catch {}
            location.assign(result.redirect);
        } catch (error) {
            // Back to step 1: from there the customer can fix the card or go back to the basket.
            busy = false;
            showStep('details');
            message(error.message || 'Result uncertain. Retry this basket to check payment safely.', true);
        }
    }
    updateOrderForm();
    render();
    if (membership.value.trim() !== '') lookUpMember(); // signed-in member: card filled in already
    document.getElementById('code').select();
})();
