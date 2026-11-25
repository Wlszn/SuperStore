(() => {
    const notice = document.getElementById('notice');
    const noticeKey = 'superstore.inventory.notice';

    function displayNotice(element, message, isError) {
        if (element === notice) {
            window.showNotification(message, isError);
            return;
        }
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

    const inventory = document.querySelector('[data-inventory-url]');
    if (inventory) {
        const lowStock = Number(inventory.dataset.lowStock);
        const liveStatus = document.getElementById('inventory-live-status');
        const numberFormat = new Intl.NumberFormat(document.documentElement.lang || 'en');
        let inventoryRequest;
        let refreshFailed = false;

        async function refreshInventory() {
            if (document.hidden || inventoryRequest) return;

            inventoryRequest = new AbortController();
            try {
                const response = await fetch(inventory.dataset.inventoryUrl, {
                    cache: 'no-store',
                    signal: inventoryRequest.signal,
                });
                if (!response.ok) throw new Error('Inventory request failed.');
                const result = await response.json();
                if (result.status !== 'success' || !Array.isArray(result.products)) {
                    throw new Error('Invalid inventory response.');
                }

                const currentRows = new Map(rows.map(row => [Number(row.dataset.productId), row]));
                let units = 0;
                let restock = 0;
                for (const product of result.products) {
                    const stock = Number(product.in_stock);
                    const row = currentRows.get(Number(product.id));
                    if (!Number.isInteger(stock) || stock < 0 || !row) continue;

                    units += stock;
                    const isLow = stock <= lowStock;
                    if (isLow) restock++;
                    row.dataset.low = String(isLow);
                    row.querySelector('[data-stock-count]').textContent = numberFormat.format(stock);

                    const status = row.querySelector('[data-stock-status]');
                    const label = stock <= 0 ? 'Out of stock' : (isLow ? 'Low stock' : 'In stock');
                    status.classList.remove('out', 'low', 'available');
                    status.classList.add(stock <= 0 ? 'out' : (isLow ? 'low' : 'available'));
                    window.setLocalizedText(status, label);
                }

                document.getElementById('units-in-stock').textContent = numberFormat.format(units);
                document.getElementById('products-needing-restock').textContent = numberFormat.format(restock);
                filterProducts();
                if (refreshFailed) {
                    window.setLocalizedText(liveStatus, 'Live inventory updates every 5 seconds.');
                    refreshFailed = false;
                }
            } catch (error) {
                if (!inventoryRequest.signal.aborted) {
                    window.setLocalizedText(liveStatus, 'Inventory updates unavailable. Retrying...');
                    refreshFailed = true;
                }
            } finally {
                inventoryRequest = undefined;
            }
        }

        refreshInventory();
        window.setInterval(refreshInventory, 5000);
        document.addEventListener('visibilitychange', refreshInventory);
    }

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
                    showScanStatus('Product selected. Put the new items on the reader.');
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

    // Receive stock: every NEW tag the reader sees becomes one item of the chosen product.
    // Tags that already belong to an item are listed but not sent.
    const tagList = document.getElementById('received-tags');
    if (tagList) {
        const newTags = [];
        const listed = new Set();
        const hiddenEpcs = document.getElementById('reception-epcs');
        const receiveButton = document.getElementById('receive-submit');
        const reader = document.getElementById('reader');
        const readerStatus = document.getElementById('reader-status');
        const readerLight = document.getElementById('reader-light');
        let stopFeed;

        function renderTags() {
            hiddenEpcs.replaceChildren(...newTags.map(epc => Object.assign(document.createElement('input'),
                { type: 'hidden', name: 'epcs[]', value: epc })));
            document.getElementById('no-tags').hidden = listed.size > 0;
            window.setLocalizedText(receiveButton, newTags.length === 1
                ? 'Receive 1 item' : 'Receive {0} items'.replace('{0}', newTags.length));
        }

        // The reader repeats every tag about twice a second while it lies on the pad. A tag not seen
        // for RESCAN_MS was lifted off; if it comes back, it was scanned again.
        const RESCAN_MS = 3000;
        const lastSeen = new Map();     // epc -> when the reader last reported it
        const registered = new Map();   // epc -> product name, for tags that already belong to an item
        const removedTags = new Set();  // taken off the list: added again only if put back on the reader

        function addTags(items) {
            const now = Date.now();
            let added = 0;
            const alreadyRegistered = [];
            let scannedAgain = 0;
            for (const item of items) {
                const putBack = now - (lastSeen.get(item.epc) ?? now) > RESCAN_MS;
                lastSeen.set(item.epc, now);
                if (removedTags.has(item.epc)) {
                    if (!putBack) continue;
                    removedTags.delete(item.epc);
                }
                if (listed.has(item.epc)) {
                    if (putBack && registered.has(item.epc)) alreadyRegistered.push(registered.get(item.epc));
                    else if (putBack) scannedAgain++;
                    continue;
                }
                listed.add(item.epc);
                const row = document.createElement('li');
                const code = Object.assign(document.createElement('span'), { className: 'code', textContent: item.epc });
                row.append(code);
                if (item.status === 'unknown') {
                    newTags.push(item.epc);
                    added++;
                    const remove = Object.assign(document.createElement('button'), { type: 'button', className: 'button' });
                    window.setLocalizedText(remove, 'Remove');
                    remove.onclick = () => {
                        newTags.splice(newTags.indexOf(item.epc), 1);
                        row.remove();
                        listed.delete(item.epc);
                        removedTags.add(item.epc);
                        renderTags();
                    };
                    row.append(remove);
                } else {
                    const taken = Object.assign(document.createElement('span'), { className: 'tag-taken' });
                    window.setLocalizedText(taken, 'Already registered: {0}'.replace('{0}', item.product.name));
                    row.append(taken);
                    registered.set(item.epc, item.product.name);
                    alreadyRegistered.push(item.product.name);
                }
                tagList.append(row);
            }
            renderTags();
            // One message per batch, the most important first.
            if (alreadyRegistered.length) {
                window.showNotification(alreadyRegistered.length === 1
                    ? 'This tag is already registered ({0}). It will not be added again.'.replace('{0}', alreadyRegistered[0])
                    : '{0} tags are already registered. They will not be added again.'.replace('{0}', alreadyRegistered.length), true);
            } else if (scannedAgain) {
                window.showNotification(scannedAgain === 1
                    ? 'This tag was already scanned. It is counted once.'
                    : '{0} tags were already scanned. Each one is counted once.'.replace('{0}', scannedAgain), true);
            } else if (added) {
                window.showNotification((added === 1 ? '{0} new tag read.' : '{0} new tags read.').replace('{0}', added));
            }
        }

        function setReaderStatus(text, isError) {
            window.setLocalizedText(readerStatus, text);
            readerStatus.classList.toggle('error', isError);
            readerLight.classList.toggle('on', Boolean(stopFeed) && !isError);
        }

        function startReader() {
            lastSeen.clear(); // the first read after (re)starting is not a re-scan
            stopFeed = window.startRfidFeed(addTags, setReaderStatus);
            window.setLocalizedText(reader, 'Stop reader');
        }

        reader.addEventListener('click', () => {
            if (!stopFeed) {
                startReader();
                return;
            }
            stopFeed();
            stopFeed = undefined;
            window.setLocalizedText(reader, 'Start reader');
            setReaderStatus('Reader stopped.', false);
        });
        window.addEventListener('pagehide', () => stopFeed?.());
        renderTags();
        startReader();
    }
})();
