// Shared by the self-checkout and the receive-stock page.
// scripts/RFID_Scanner.py posts every tag the reader sees to /api/scans; this polls that feed and
// hands the page each new batch at once, already looked up (/api/items/lookup).
(() => {
    const POLL_MS = 500;

    /**
     * Start listening. onTags(items) gets every new batch: [{epc, status, product}], where status is
     * 'in_stock', 'sold' or 'unknown'. onStatus(text, isError) reports the feed state.
     * Returns a function that stops listening.
     */
    window.startRfidFeed = (onTags, onStatus) => {
        const session = new AbortController();
        const options = { signal: session.signal, cache: 'no-store' };
        let cursor = null; // id of the last scan seen; null until listening starts
        let timer;

        async function poll() {
            try {
                // ?arm=1 only returns where the feed is now: tags read before the page opened are skipped.
                const response = await fetch('/api/scans/latest' + (cursor === null ? '?arm=1' : '?after=' + cursor), options);
                if (!response.ok) throw new Error('feed');
                const result = await response.json();

                if (cursor !== null && result.scans.length) {
                    const epcs = [...new Set(result.scans.map(scan => scan.epc.trim().toUpperCase()))];
                    const lookup = await fetch('/api/items/lookup?epcs=' + epcs.join(','), options);
                    if (!lookup.ok) throw new Error('lookup');
                    const { items } = await lookup.json();
                    if (session.signal.aborted) return;
                    await onTags(items);
                }
                cursor = result.last_id; // only after the page has the batch, so nothing is lost on an error
                onStatus('Waiting for RFID tags. Place the items on the reader.', false);
            } catch {
                if (!session.signal.aborted) onStatus('Reader feed unavailable. Retrying.', true);
            } finally {
                if (!session.signal.aborted) timer = setTimeout(poll, POLL_MS);
            }
        }

        onStatus('Connecting to the RFID reader...', false);
        poll();
        return () => {
            session.abort();
            clearTimeout(timer);
        };
    };
})();
