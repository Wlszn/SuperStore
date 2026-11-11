/* Shared helpers for the Phase 3 pages: notifications, scanners, money. */

/** Small notification in the corner ("Product added successfully"). type: success | error | info */
function notify(text, type) {
    let box = document.getElementById('toasts');
    if (!box) {
        box = document.createElement('div');
        box.id = 'toasts';
        box.setAttribute('role', 'status');
        box.setAttribute('aria-live', 'polite');
        document.body.appendChild(box);
    }
    const toast = document.createElement('div');
    toast.className = 'toast ' + (type || 'success');
    toast.textContent = text;
    box.appendChild(toast);
    // Many tags read at once: keep only the 4 newest on screen.
    while (box.children.length > 4) {
        box.firstChild.remove();
    }
    setTimeout(function () { toast.classList.add('leaving'); }, 3200);
    setTimeout(function () { toast.remove(); }, 3600);
}

function formatMoney(value) {
    return '$' + Number(value).toFixed(2);
}

/** Escapes text before putting it in innerHTML. */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text === null || text === undefined ? '' : String(text);
    return div.innerHTML;
}

/** The product behind a scanned UPC or EPC, or null. */
async function lookupProduct(code) {
    const response = await fetch('/api/products/lookup?code=' + encodeURIComponent(code));
    if (response.status === 404) {
        return null;
    }
    const data = await response.json();
    if (data.status !== 'success') {
        throw new Error(data.message || 'Lookup failed');
    }
    return data.product;
}

/**
 * Barcode scanners type the code and press Enter. Calls onCode(code) on Enter
 * instead of submitting the form, then empties the field.
 */
function wireScanInput(input, onCode) {
    input.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter') {
            return;
        }
        event.preventDefault();
        const code = input.value.trim();
        input.value = '';
        if (code !== '') {
            onCode(code);
        }
    });
}

/**
 * Follows the RFID reader: scripts/rfid.py posts tags to /api/scans and this
 * asks every `everyMs` for the ones newer than the last it saw.
 * Only tags read after start() are reported: onTags(['E280...', ...]).
 * Optional onStatus(true|false) says whether the server answered.
 */
function createRfidFeed(onTags, everyMs, onStatus) {
    let lastId = null;
    let timer = null;
    let busy = false;

    async function poll() {
        if (busy) {
            return;
        }
        busy = true;
        try {
            const url = '/api/scans/latest' + (lastId === null ? '' : '?after=' + lastId);
            const data = await (await fetch(url)).json();
            if (onStatus) onStatus(data.status === 'success');
            if (data.status === 'success') {
                const isFirstCall = lastId === null;
                lastId = data.last_id;
                // The first call only finds where "now" is; older scans are ignored.
                if (!isFirstCall && data.scans.length > 0) {
                    const epcs = [...new Set(data.scans.map(function (scan) { return scan.epc; }))];
                    await onTags(epcs);
                }
            }
        } catch (error) {
            // The server may restart; just try again next time.
            if (onStatus) onStatus(false);
        } finally {
            busy = false;
        }
    }

    return {
        start: function () {
            if (timer === null) {
                lastId = null;
                poll();
                timer = setInterval(poll, everyMs || 700);
            }
        },
        stop: function () {
            clearInterval(timer);
            timer = null;
        },
    };
}

/** A notification that survives a page change (e.g. "Product added" after going back to the list). */
function notifyAfterRedirect(text, type) {
    try {
        sessionStorage.setItem('flash', JSON.stringify({ text: text, type: type || 'success' }));
    } catch (error) {
        // Private browsing: the message is simply skipped.
    }
}

document.addEventListener('DOMContentLoaded', function () {
    try {
        const flash = JSON.parse(sessionStorage.getItem('flash') || 'null');
        if (flash) {
            sessionStorage.removeItem('flash');
            notify(flash.text, flash.type);
        }
    } catch (error) {
        // Nothing to show.
    }
});
