(() => {
    // Self-checkout, RFID only: every item has its own tag. The customer puts everything on the
    // reader, rfid-feed.js hands over all the tags at once, and the basket groups them by product.
    const payment = document.getElementById('payment');
    const checkoutKey = payment.elements.namedItem('key').value;
    const storageKey = 'superstore.basket';
    const tags = new Map();      // epc -> product, for every item in the basket
    const ignoredTags = new Set(); // removed by the customer, unknown or already sold: not added again
    let cart = new Map();        // product id -> {...product, quantity, epcs}, built from tags

    try {
        const saved = JSON.parse(sessionStorage.getItem(storageKey));
        if (saved?.key === checkoutKey) {
            for (const [epc, product] of saved.tags) tags.set(epc, product);
            for (const epc of saved.ignored) ignoredTags.add(epc);
        }
    } catch {}

    function saveBasket() {
        try {
            sessionStorage.setItem(storageKey, JSON.stringify({key: checkoutKey, tags: [...tags], ignored: [...ignoredTags]}));
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
    let stopFeed;
    const reader = document.getElementById('reader');
    const readerStatus = document.getElementById('reader-status');
    const readerLight = document.getElementById('reader-light');

    // One batch = everything the reader saw together: add it all, then one message.
    // The reader repeats every tag about twice a second while it lies on the pad. A tag not seen
    // for RESCAN_MS was lifted off; if it comes back, the customer scanned it again.
    const RESCAN_MS = 3000;
    const lastSeen = new Map(); // epc -> when the reader last reported it

    function addTags(items) {
        if (busy || step !== null) return;
        const now = Date.now();
        let added = 0;
        const problems = [];
        const inBasket = new Set();
        for (const item of items) {
            const putBack = now - (lastSeen.get(item.epc) ?? now) > RESCAN_MS;
            lastSeen.set(item.epc, now);
            if (tags.has(item.epc)) {
                if (putBack) inBasket.add(item.product.name); // the same item scanned again
                continue;
            }
            if (ignoredTags.has(item.epc)) {
                if (!putBack) continue; // still lying on the reader: stay quiet
                ignoredTags.delete(item.epc); // removed earlier, put back now: check it again below
            }
            if (item.status === 'in_stock') {
                tags.set(item.epc, item.product);
                added++;
            } else {
                ignoredTags.add(item.epc);
                problems.push(item.status === 'sold'
                    ? 'This item was already sold: {0}'.replace('{0}', item.product.name)
                    : 'Unknown RFID tag: {0}'.replace('{0}', item.epc));
            }
        }
        if (added) render();
        // One message per batch, the most important first.
        if (problems.length) {
            message(problems.join(' '), true);
        } else if (inBasket.size) {
            message('Already in your basket: {0}. Each item is counted once.'.replace('{0}', [...inBasket].join(', ')), true);
        } else if (added) {
            message((added === 1 ? '{0} item added to your basket.' : '{0} items added to your basket.').replace('{0}', added));
        }
    }

    function setReaderStatus(text, isError) {
        window.setLocalizedText(readerStatus, text);
        readerStatus.classList.toggle('error', isError);
        readerLight.classList.toggle('on', Boolean(stopFeed) && !isError);
    }

    function startReader() {
        if (stopFeed) return;
        lastSeen.clear(); // the first read after (re)starting is not a re-scan
        stopFeed = window.startRfidFeed(addTags, setReaderStatus);
        window.setLocalizedText(reader, 'Stop reader');
    }

    function stopReader(text = 'Reader stopped.') {
        stopFeed?.();
        stopFeed = undefined;
        window.setLocalizedText(reader, 'Start reader');
        setReaderStatus(text, false);
    }

    reader.addEventListener('click', () => (stopFeed ? stopReader() : startReader()));
    window.addEventListener('pagehide', () => stopFeed?.());

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
    const cardHint = document.getElementById('card-hint'); // basket screen, guests only
    let member = null;

    function showMember(paidCents) {
        if (!member) return;
        // Same rule as complete_checkout(): 10 points per dollar paid, rounded down.
        window.setLocalizedText(memberStatus, 'Member: {0} · {1} points. This purchase earns {2} points.'
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
            if (cardHint) {
                cardHint.classList.remove('error');
                window.setLocalizedText(cardHint, 'Membership card accepted: {0}, {1} points.'
                    .replace('{0}', member.first_name).replace('{1}', member.points));
            }
        } catch (error) {
            const text = error.message || 'Membership lookup unavailable. Please retry.';
            memberStatus.classList.add('error');
            window.setLocalizedText(memberStatus, text);
            if (cardHint) {
                cardHint.classList.add('error');
                window.setLocalizedText(cardHint, text);
            }
            message(text, true);
        }
    }

    // Guests can scan their membership card at any time, even on the basket screen. The USB
    // scanner types the number very fast and ends with Enter; a person typing is much slower.
    if (cardHint) {
        let scanned = '';
        let lastKey = 0;
        document.addEventListener('keydown', event => {
            if (busy || (step !== null && step !== 'details') || event.target.closest('input, select, textarea')) return;
            const now = Date.now();
            if (now - lastKey > 60) scanned = '';
            lastKey = now;
            if (event.key === 'Enter') {
                if (scanned.length >= 4) {
                    event.preventDefault(); // don't press the focused button
                    membership.value = scanned;
                    lookUpMember();
                }
                scanned = '';
            } else if (event.key.length === 1) {
                scanned += event.key;
            }
        });
    }

    function render() {
        // Group the tags by product: 5 KitKat tags = one line "KitKat x 5".
        cart = new Map();
        for (const [epc, product] of tags) {
            const line = cart.get(product.id) || {...product, quantity: 0, epcs: []};
            line.quantity++;
            line.epcs.push(epc);
            cart.set(product.id, line);
        }

        basket.replaceChildren();
        for (const product of cart.values()) {
            const row = document.createElement('tr');
            const cells = [product.name, String(product.quantity), '$' + Number(product.price).toFixed(2),
                '$' + (Number(product.price) * product.quantity).toFixed(2)].map((text, index) => {
                const cell = document.createElement('td');
                cell.textContent = text;
                if (index > 0) cell.className = 'numeric';
                return cell;
            });
            row.append(...cells);

            const cell = document.createElement('td');
            const remove = document.createElement('button');
            remove.className = 'button';
            remove.type = 'button';
            window.setLocalizedText(remove, 'Remove');
            remove.disabled = busy || step !== null;
            remove.onclick = () => {
                // Taken out of the basket: the reader must not add these tags back.
                for (const epc of product.epcs) {
                    tags.delete(epc);
                    ignoredTags.add(epc);
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
    }

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
        if (name === null) startReader(); // back to the basket: listen for items again
    }

    pay.addEventListener('click', () => {
        if (busy || !cart.size) return;
        stopReader('Reader stopped for payment.');
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
    document.getElementById('skip-card')?.addEventListener('click', () => {
        membership.value = '';
        member = null;
        memberStatus.hidden = true;
        updateTotal();
        detailsNext();
    });
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
        body.set('epcs', JSON.stringify([...tags.keys()]));
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
    startReader(); // like a store kiosk: listening as soon as the page opens
})();
