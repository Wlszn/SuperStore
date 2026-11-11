/*
 * Self-checkout kiosk.
 *
 * Workflow (Phase 3 spec):
 *   1. The customer scans all products: RFID reader (all at once, through
 *      scripts/rfid.py) or barcode scanner (one at a time, into the scan box).
 *   2. The shopping list updates live; items can be removed or changed.
 *   3. The system asks for a membership card (optional).
 *   4. The customer confirms the list, then pays (simulated card or cash).
 *   5. The server saves the purchase, adds the points and emails the receipt.
 *   6. The receipt is shown; the kiosk resets for the next customer.
 */
(function () {
    const T = window.CHECKOUT.text;
    const csrfToken = window.CHECKOUT.csrfToken;
    const signedInMember = window.CHECKOUT.signedInMember;
    const RESET_AFTER_SECONDS = 60;

    const cart = new Map();          // product id -> { product, quantity }
    let member = signedInMember;     // { first_name, membership_number, points } or null
    let paying = false;
    let resetTimer = null;

    const el = function (id) { return document.getElementById(id); };
    const scanBox = el('scanBox');
    const modal = el('flowModal');

    function fmt(text, values) {
        return String(text).replace(/\{(\w+)\}/g, function (match, key) {
            return values && values[key] !== undefined ? values[key] : match;
        });
    }

    function cartTotal() {
        let total = 0;
        cart.forEach(function (line) { total += line.product.price * line.quantity; });
        return Math.round(total * 100) / 100;
    }

    function cartCount() {
        let count = 0;
        cart.forEach(function (line) { count += line.quantity; });
        return count;
    }

    function pointsToEarn() {
        return member ? Math.floor(cartTotal()) : 0;
    }

    /* ---------- 1. Scanning ---------- */

    async function handleCode(code, source) {
        // A membership card scanned (or typed) into the main box.
        if (/^SS\d{6,8}$/i.test(code)) {
            await applyMember(code);
            return;
        }
        try {
            const product = await lookupProduct(code);
            if (!product) {
                notify(fmt(source === 'rfid' ? T.unknown_tag : T.unknown_code, { code: code }), 'error');
                return;
            }
            addProduct(product, source);
        } catch (error) {
            notify(T.lookup_failed, 'error');
        }
    }

    function addProduct(product, source) {
        const line = cart.get(product.id);
        if (line) {
            // The reader keeps seeing the same tag: that's the same item, not a second one.
            if (source === 'rfid') {
                return;
            }
            if (line.quantity >= Math.min(99, product.in_stock)) {
                notify(fmt(T.no_more_stock, { name: product.name, count: product.in_stock }), 'error');
                return;
            }
            line.quantity++;
            line.product = product;
        } else {
            if (product.in_stock <= 0) {
                notify(fmt(T.out_of_stock, { name: product.name }), 'error');
                return;
            }
            cart.set(product.id, { product: product, quantity: 1 });
        }
        notify(fmt(T.added, { name: product.name }), 'success');
        render(product.id);
    }

    function changeQuantity(id, delta) {
        const line = cart.get(id);
        if (!line) return;
        const next = line.quantity + delta;
        if (next <= 0) {
            removeLine(id);
            return;
        }
        if (next > Math.min(99, line.product.in_stock)) {
            notify(fmt(T.no_more_stock, { name: line.product.name, count: line.product.in_stock }), 'error');
            return;
        }
        line.quantity = next;
        render(id);
    }

    function removeLine(id) {
        const line = cart.get(id);
        if (!line) return;
        cart.delete(id);
        notify(fmt(T.removed, { name: line.product.name }), 'info');
        render();
    }

    /* ---------- 2. Live shopping list ---------- */

    function render(highlightId) {
        const list = el('cartList');
        list.innerHTML = '';
        cart.forEach(function (line, id) {
            const item = document.createElement('li');
            item.className = 'cart-line' + (id === highlightId ? ' flash' : '');
            const picture = line.product.image_url
                ? '<img src="' + escapeHtml(line.product.image_url) + '" alt="" class="thumb">'
                : '<span class="thumb placeholder">' + escapeHtml(line.product.name.charAt(0)) + '</span>';
            item.innerHTML =
                picture +
                '<div class="line-info"><strong>' + escapeHtml(line.product.name) + '</strong>' +
                '<small>' + formatMoney(line.product.price) + ' ' + escapeHtml(T.each) + '</small></div>' +
                '<div class="qty" role="group" aria-label="' + escapeHtml(T.quantity) + '">' +
                '<button type="button" class="qty-btn" data-minus="' + id + '" aria-label="' + escapeHtml(T.less) + '">−</button>' +
                '<span>' + line.quantity + '</span>' +
                '<button type="button" class="qty-btn" data-plus="' + id + '" aria-label="' + escapeHtml(T.more) + '">+</button></div>' +
                '<strong class="line-total">' + formatMoney(line.product.price * line.quantity) + '</strong>' +
                '<button type="button" class="remove-btn" data-remove="' + id + '" aria-label="' + escapeHtml(fmt(T.remove_item, { name: line.product.name })) + '">✕</button>';
            list.appendChild(item);
        });

        const empty = cart.size === 0;
        el('emptyCart').hidden = !empty;
        el('itemCount').textContent = empty ? '' : fmt(cartCount() === 1 ? T.one_item : T.n_items, { count: cartCount() });
        el('sumItems').textContent = cartCount();
        el('sumPoints').textContent = pointsToEarn();
        el('sumTotal').textContent = formatMoney(cartTotal());
        el('payButton').disabled = empty;
        el('clearButton').disabled = empty;
        el('payButton').textContent = empty ? T.checkout_button : fmt(T.pay_total, { total: formatMoney(cartTotal()) });
        renderMember();
    }

    el('cartList').addEventListener('click', function (event) {
        const button = event.target.closest('button');
        if (!button) return;
        if (button.dataset.plus) changeQuantity(Number(button.dataset.plus), 1);
        if (button.dataset.minus) changeQuantity(Number(button.dataset.minus), -1);
        if (button.dataset.remove) removeLine(Number(button.dataset.remove));
        scanBox.focus();
    });

    el('clearButton').addEventListener('click', function () {
        cart.clear();
        render();
        notify(T.order_cancelled, 'info');
        scanBox.focus();
    });

    /* ---------- 3. Membership card ---------- */

    function renderMember() {
        const box = el('memberBox');
        if (member) {
            box.className = 'member-box active';
            box.innerHTML =
                '<span class="member-label">' + escapeHtml(T.member) + '</span>' +
                '<strong>' + escapeHtml(fmt(T.welcome, { name: member.first_name })) + '</strong>' +
                '<span>' + escapeHtml(member.membership_number) + ' · ' + escapeHtml(fmt(T.points_balance, { points: member.points })) + '</span>' +
                (signedInMember ? '' : '<button type="button" class="link-button" id="removeMember">' + escapeHtml(T.not_you) + '</button>');
            const remove = el('removeMember');
            if (remove) {
                remove.addEventListener('click', function () {
                    member = null;
                    render();
                    notify(T.member_removed, 'info');
                });
            }
        } else {
            box.className = 'member-box';
            box.innerHTML =
                '<span class="member-label">' + escapeHtml(T.member) + '</span>' +
                '<span>' + escapeHtml(T.member_hint) + '</span>';
        }
    }

    async function applyMember(number) {
        el('memberError').textContent = '';
        try {
            const response = await fetch('/api/members/lookup?number=' + encodeURIComponent(number.trim()));
            const data = await response.json();
            if (data.status !== 'success') {
                el('memberError').textContent = data.message;
                notify(data.message, 'error');
                return false;
            }
            member = data.member;
            notify(data.message, 'success');
            render();
            return true;
        } catch (error) {
            notify(T.lookup_failed, 'error');
            return false;
        }
    }

    /* ---------- 4. Confirm, then pay ---------- */

    function showStep(step) {
        modal.hidden = false;
        modal.querySelectorAll('[data-panel]').forEach(function (panel) {
            panel.hidden = panel.dataset.panel !== step;
        });
        let reached = true;
        modal.querySelectorAll('.steps li').forEach(function (li) {
            li.classList.toggle('current', li.dataset.step === step);
            li.classList.toggle('done', reached && li.dataset.step !== step);
            if (li.dataset.step === step) reached = false;
        });
        if (step === 'member') {
            el('memberInput').value = '';
            el('memberInput').focus();
        }
        if (step === 'confirm') {
            fillConfirm();
            el('confirmList').focus();
        }
        if (step === 'payment') {
            el('payAmount').textContent = formatMoney(cartTotal());
            el('payMethods').hidden = false;
            el('cashPanel').hidden = true;
            el('processing').hidden = true;
            el('paymentBack').hidden = false;
        }
    }

    function closeFlow() {
        if (paying) return;
        modal.hidden = true;
        scanBox.focus();
    }

    function fillConfirm() {
        let rows = '<thead><tr><th>' + escapeHtml(T.item) + '</th><th class="num">' + escapeHtml(T.qty) +
            '</th><th class="num">' + escapeHtml(T.price) + '</th><th class="num">' + escapeHtml(T.amount) + '</th></tr></thead><tbody>';
        cart.forEach(function (line) {
            rows += '<tr><td>' + escapeHtml(line.product.name) + '</td><td class="num">' + line.quantity +
                '</td><td class="num">' + formatMoney(line.product.price) + '</td><td class="num">' +
                formatMoney(line.product.price * line.quantity) + '</td></tr>';
        });
        rows += '</tbody><tfoot><tr><th colspan="3">' + escapeHtml(T.total) + '</th><th class="num">' +
            formatMoney(cartTotal()) + '</th></tr></tfoot>';
        el('confirmTable').innerHTML = rows;
        el('confirmMember').textContent = member
            ? fmt(T.confirm_member, { number: member.membership_number, points: pointsToEarn() })
            : T.confirm_guest;
    }

    el('payButton').addEventListener('click', function () {
        if (cart.size === 0) return;
        showStep(member ? 'confirm' : 'member');
    });
    modal.querySelectorAll('[data-close]').forEach(function (button) {
        button.addEventListener('click', closeFlow);
    });
    modal.querySelectorAll('[data-back]').forEach(function (button) {
        button.addEventListener('click', function () { showStep(button.dataset.back); });
    });
    modal.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeFlow();
    });

    el('noCard').addEventListener('click', function () { showStep('confirm'); });
    async function memberFromModal() {
        const value = el('memberInput').value.trim();
        if (value === '') {
            el('memberError').textContent = T.member_empty;
            return;
        }
        if (await applyMember(value)) showStep('confirm');
    }
    el('memberApply').addEventListener('click', memberFromModal);
    wireScanInput(el('memberInput'), function (code) {
        el('memberInput').value = code;
        memberFromModal();
    });

    el('confirmList').addEventListener('click', function () { showStep('payment'); });

    el('payMethods').addEventListener('click', function (event) {
        const button = event.target.closest('[data-method]');
        if (!button) return;
        if (button.dataset.method === 'card') {
            simulateCard();
        } else {
            el('payMethods').hidden = true;
            el('cashPanel').hidden = false;
            el('cashGiven').value = '';
            el('cashError').textContent = '';
            el('cashGiven').focus();
        }
    });

    function processing(text) {
        el('payMethods').hidden = true;
        el('cashPanel').hidden = true;
        el('paymentBack').hidden = true;
        el('processing').hidden = false;
        el('processingText').textContent = text;
    }

    function simulateCard() {
        processing(T.card_tap);
        setTimeout(function () { el('processingText').textContent = T.card_processing; }, 1200);
        setTimeout(function () { submitCheckout('card', null); }, 2200);
    }

    function payCash() {
        const given = Number(String(el('cashGiven').value).replace(',', '.'));
        if (!Number.isFinite(given) || given < cartTotal()) {
            el('cashError').textContent = fmt(T.cash_too_low, { total: formatMoney(cartTotal()) });
            return;
        }
        const change = Math.round((given - cartTotal()) * 100) / 100;
        processing(fmt(T.cash_change, { change: formatMoney(change) }));
        setTimeout(function () { submitCheckout('cash', change); }, 1200);
    }
    el('cashPay').addEventListener('click', payCash);
    el('cashGiven').addEventListener('keydown', function (event) {
        if (event.key === 'Enter') { event.preventDefault(); payCash(); }
    });

    /* ---------- 5. Save the purchase ---------- */

    async function submitCheckout(method, change) {
        paying = true;
        el('processingText').textContent = T.saving;
        const items = [];
        cart.forEach(function (line, id) { items.push({ product_id: id, quantity: line.quantity }); });

        try {
            const response = await fetch('/checkout', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    csrf_token: csrfToken,
                    items: items,
                    membership_number: member ? member.membership_number : '',
                    payment_method: method,
                }),
            });
            const data = await response.json();
            paying = false;

            if (data.status !== 'success') {
                notify(data.message, 'error');
                modal.hidden = true;
                refreshCartProducts();
                scanBox.focus();
                return;
            }

            modal.hidden = true;
            notify(data.message, 'success');
            showReceipt(data.receipt, data.emailed_to, change);
        } catch (error) {
            paying = false;
            notify(T.server_unreachable, 'error');
            showStep('payment');
        }
    }

    /** After a refused checkout (e.g. stock changed), reload prices and stock. */
    async function refreshCartProducts() {
        for (const [id, line] of cart) {
            const code = line.product.upc || line.product.epc;
            try {
                const fresh = code ? await lookupProduct(code) : null;
                if (fresh) {
                    line.product = fresh;
                    if (line.quantity > fresh.in_stock) line.quantity = Math.max(fresh.in_stock, 0);
                }
                if (!fresh || line.quantity === 0) cart.delete(id);
            } catch (error) {
                // Keep the line as it is.
            }
        }
        render();
    }

    /* ---------- 6. Receipt ---------- */

    function showReceipt(receipt, emailedTo, change) {
        feed.stop();
        el('scanScreen').hidden = true;
        el('receiptScreen').hidden = false;

        let lines = '';
        receipt.items.forEach(function (item) {
            lines += '<tr><td>' + escapeHtml(item.name) + '</td><td class="num">' + item.quantity + '</td><td class="num">' +
                formatMoney(item.unit_price) + '</td><td class="num">' + formatMoney(item.line_total) + '</td></tr>';
        });

        const customer = receipt.customer;
        el('receiptCard').innerHTML =
            '<div class="receipt-ok" aria-hidden="true">✓</div>' +
            '<h1>' + escapeHtml(T.thank_you) + '</h1>' +
            '<p class="muted">' + escapeHtml(fmt(T.receipt_number, { id: receipt.id })) + ' · ' + escapeHtml(receipt.date) + '</p>' +
            '<table class="data-table compact receipt-table"><thead><tr><th>' + escapeHtml(T.item) + '</th><th class="num">' +
            escapeHtml(T.qty) + '</th><th class="num">' + escapeHtml(T.price) + '</th><th class="num">' + escapeHtml(T.amount) +
            '</th></tr></thead><tbody>' + lines + '</tbody><tfoot><tr><th colspan="3">' + escapeHtml(T.total) +
            '</th><th class="num">' + formatMoney(receipt.total) + '</th></tr></tfoot></table>' +
            '<dl class="details">' +
            '<dt>' + escapeHtml(T.payment) + '</dt><dd>' + escapeHtml(receipt.payment_method === 'card' ? T.card : T.cash) +
            (change !== null && change !== undefined ? ' · ' + escapeHtml(fmt(T.change_given, { change: formatMoney(change) })) : '') + '</dd>' +
            '<dt>' + escapeHtml(T.points_earned) + '</dt><dd>' + receipt.points_earned + '</dd>' +
            (customer ? '<dt>' + escapeHtml(T.new_balance) + '</dt><dd>' + customer.points + '</dd>' : '') +
            '</dl>' +
            '<p class="email-status ' + (emailedTo ? 'sent' : '') + '">' + escapeHtml(
                customer
                    ? (emailedTo ? fmt(T.emailed, { email: emailedTo }) : T.email_failed)
                    : T.guest_no_email
            ) + '</p>';

        el('printReceipt').href = '/receipts/show?id=' + receipt.id;
        if (customer) {
            member = Object.assign({}, member, { points: customer.points });
        }

        let seconds = RESET_AFTER_SECONDS;
        el('resetCountdown').textContent = fmt(T.reset_in, { seconds: seconds });
        resetTimer = setInterval(function () {
            seconds--;
            el('resetCountdown').textContent = fmt(T.reset_in, { seconds: seconds });
            if (seconds <= 0) nextCustomer();
        }, 1000);
        el('nextCustomer').focus();
    }

    function nextCustomer() {
        clearInterval(resetTimer);
        cart.clear();
        member = signedInMember ? member : null;
        el('receiptScreen').hidden = true;
        el('scanScreen').hidden = false;
        render();
        feed.start();
        scanBox.focus();
    }
    el('nextCustomer').addEventListener('click', nextCustomer);

    /* ---------- Scanners ---------- */

    // Barcode scanner (and the RFID reader in keyboard mode) types into the scan box.
    wireScanInput(scanBox, function (code) { handleCode(code, 'barcode'); });

    // RFID reader through scripts/rfid.py: every tag read at once.
    const feed = createRfidFeed(async function (epcs) {
        if (paying || !modal.hidden) return;
        for (const epc of epcs) {
            await handleCode(epc, 'rfid');
        }
    }, 600, function (ok) {
        el('readerStatus').classList.toggle('ok', ok);
        el('readerStatus').classList.toggle('down', !ok);
        el('readerText').textContent = ok ? T.reader_ready : T.reader_down;
    });

    // Keep the scan box ready for the barcode scanner.
    document.addEventListener('click', function (event) {
        if (!modal.hidden || !el('receiptScreen').hidden) return;
        if (event.target.closest('input, button, a, select, textarea')) return;
        scanBox.focus();
    });

    render();
    feed.start();
})();
