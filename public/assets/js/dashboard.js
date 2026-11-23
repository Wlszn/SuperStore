// Fridge dashboard: polls /api/readings/latest every 2 seconds and only updates
// the values that changed (no page reloads, no rebuilt HTML).
(() => {
    const POLL_MS = 2000;          // how often the page asks for new readings
    const OFFLINE_AFTER_S = 30;    // a fridge with no reading for this long is shown as offline
    const NEAR_LIMIT = 1.5;        // °C under the limit that counts as "close to limit"
    const TEMP_MIN = -10;          // temperature gauge scale (°C)
    const TEMP_MAX = 40;

    const fridges = [...document.querySelectorAll('[data-fridge]')].map(card => ({
        card,
        id: Number(card.dataset.fridge),
        name: card.querySelector('h2').textContent,
        limit: Number(card.dataset.limit),
        reading: null,
        state: 'waiting',
        role: name => card.querySelector('[data-role="' + name + '"]'),
    }));

    const live = document.getElementById('live');
    const liveText = document.getElementById('live-text');
    const overview = document.getElementById('overview');
    const overviewText = document.getElementById('overview-text');
    const fan = document.getElementById('fan');
    const fanState = document.getElementById('fan-state');
    const fanToggle = document.getElementById('fan-toggle');

    let fanOn = false;
    let lastSuccess = 0;
    let failed = false;
    let busy = false;
    let timer;

    const clamp = percent => Math.max(0, Math.min(100, percent));
    const secondsSince = time => Math.max(0, Math.round((Date.now() - Date.parse(time)) / 1000));
    const names = list => list.map(fridge => fridge.name).join(' and ');

    function setArc(path, percent) {
        path.style.strokeDashoffset = String(100 - clamp(percent));
    }

    // Puts the limit tick on the temperature arc (240° arc starting at 150°).
    function placeLimitMark(fridge) {
        const percent = clamp((fridge.limit - TEMP_MIN) / (TEMP_MAX - TEMP_MIN) * 100);
        const angle = (150 + 240 * percent / 100) * Math.PI / 180;
        const mark = fridge.role('limit-mark');
        mark.setAttribute('x1', 100 + 66 * Math.cos(angle));
        mark.setAttribute('y1', 100 + 66 * Math.sin(angle));
        mark.setAttribute('x2', 100 + 94 * Math.cos(angle));
        mark.setAttribute('y2', 100 + 94 * Math.sin(angle));
    }

    function stateOf(fridge) {
        const reading = fridge.reading;
        if (!reading) return 'waiting';
        if (secondsSince(reading.recorded_at) > OFFLINE_AFTER_S) return 'offline';
        if (reading.temperature > fridge.limit) return 'over';
        if (reading.temperature > fridge.limit - NEAR_LIMIT) return 'near';
        return 'ok';
    }

    const stateLabels = {
        waiting: 'Waiting',
        ok: 'Normal',
        near: 'Close to limit',
        over: 'Over limit',
        offline: 'Sensor offline',
    };

    function renderFridge(fridge) {
        const reading = fridge.reading;
        const state = stateOf(fridge);

        if (reading) {
            fridge.role('temp').textContent = reading.temperature.toFixed(1);
            fridge.role('hum').textContent = reading.humidity.toFixed(1);
            setArc(fridge.role('temp-arc'), (reading.temperature - TEMP_MIN) / (TEMP_MAX - TEMP_MIN) * 100);
            setArc(fridge.role('hum-arc'), reading.humidity);
            fridge.role('temp-gauge').setAttribute('aria-label', 'Temperature ' + reading.temperature.toFixed(1) + ' °C');
            fridge.role('hum-gauge').setAttribute('aria-label', 'Humidity ' + reading.humidity.toFixed(1) + ' %');

            const age = secondsSince(reading.recorded_at);
            window.setLocalizedText(fridge.role('updated'), age < 60
                ? 'Last reading {0} s ago'.replace('{0}', age)
                : 'Last reading at {0}'.replace('{0}', new Date(reading.recorded_at).toLocaleTimeString()));
        }

        if (state !== fridge.state) {
            // Warn once when a fridge goes over its limit (in the page and as a system notification).
            if (state === 'over') {
                const message = '{0} is above its limit: {1} °C.'
                    .replace('{0}', fridge.name).replace('{1}', reading.temperature.toFixed(1));
                window.showNotification(message, true);
                if ('Notification' in window && Notification.permission === 'granted') {
                    new Notification(window.translate('{0} temperature alert'.replace('{0}', fridge.name)), {
                        body: window.translate(message),
                    });
                }
            }
            fridge.state = state;
            fridge.card.dataset.state = state;
            window.setLocalizedText(fridge.role('state'), stateLabels[state]);
        }
    }

    // One sentence that sums up the whole store, most urgent first.
    function renderOverview() {
        const by = state => fridges.filter(fridge => fridge.state === state);
        const over = by('over');
        const offline = by('offline');
        const near = by('near');
        let state = 'ok';
        let message = 'All fridges are below their limit.';

        if (failed) {
            state = 'error';
            message = 'Cannot reach the server. The readings shown may be out of date.';
        } else if (over.length) {
            state = 'over';
            message = (over.length === 1
                ? '{0} is above its limit. Reply YES to the alert email or turn the fan on here.'
                : '{0} are above their limit. Reply YES to the alert email or turn the fan on here.')
                .replace('{0}', names(over));
        } else if (offline.length) {
            state = 'offline';
            message = (offline.length === 1
                ? '{0} has stopped sending readings. Check its sensor and monitor.py.'
                : '{0} have stopped sending readings. Check their sensors and monitor.py.')
                .replace('{0}', names(offline));
        } else if (near.length) {
            state = 'near';
            message = (near.length === 1 ? '{0} is close to its limit.' : '{0} are close to their limit.')
                .replace('{0}', names(near));
        } else if (fridges.every(fridge => fridge.state === 'waiting')) {
            state = 'waiting';
            message = 'Waiting for the first readings.';
        }

        overview.dataset.state = state;
        if (overviewText.dataset.i18n !== message) window.setLocalizedText(overviewText, message);
    }

    function renderFan(isOn) {
        fanOn = isOn;
        fan.classList.toggle('on', isOn);
        window.setLocalizedText(fanState, isOn ? 'On' : 'Off');
        window.setLocalizedText(fanToggle, isOn ? 'Turn off' : 'Turn on');
        fanToggle.disabled = false;
    }

    function renderLive() {
        live.classList.toggle('on', !failed && lastSuccess > 0);
        live.classList.toggle('off', failed);
        if (failed) {
            window.setLocalizedText(liveText, 'Reconnecting...');
        } else if (lastSuccess) {
            const age = Math.round((Date.now() - lastSuccess) / 1000);
            window.setLocalizedText(liveText, age < 3 ? 'Live' : 'Updated {0} s ago'.replace('{0}', age));
        }
    }

    function render() {
        fridges.forEach(renderFridge);
        renderOverview();
        renderLive();
    }

    async function refresh() {
        if (busy) return;   // never start a second request while one is still running
        busy = true;
        clearTimeout(timer);
        try {
            const response = await fetch('/api/readings/latest', { cache: 'no-store' });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const data = await response.json();

            for (const row of data.fridges || []) {
                const fridge = fridges.find(item => item.id === Number(row.id));
                if (!fridge) continue;
                fridge.reading = row.reading;
                // Keep the server's limit unless the manager is typing a new one.
                const input = fridge.card.querySelector('[name="max_temp"]');
                if (Number(row.max_temp) !== fridge.limit && document.activeElement !== input) {
                    fridge.limit = Number(row.max_temp);
                    input.value = fridge.limit.toFixed(1);
                    placeLimitMark(fridge);
                }
            }
            renderFan(Boolean(data.fan && data.fan.is_on));
            lastSuccess = Date.now();
            failed = false;
        } catch {
            failed = true;
        } finally {
            busy = false;
            render();
            // Pause while the tab is hidden; visibilitychange restarts it.
            if (!document.hidden) timer = setTimeout(refresh, POLL_MS);
        }
    }

    fanToggle.addEventListener('click', async () => {
        const wanted = !fanOn;
        fanToggle.disabled = true;
        try {
            const response = await fetch('/api/fan', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ on: wanted }),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message);
            renderFan(Boolean(data.fan.is_on));
            window.showNotification(wanted ? 'Fan turned on.' : 'Fan turned off.');
        } catch {
            fanToggle.disabled = false;
            window.showNotification('Could not change the fan. Try again.', true);
        }
    });

    for (const form of document.querySelectorAll('[data-limit-form]')) {
        const fridge = fridges.find(item => item.id === Number(form.elements.fridge_id.value));
        const input = form.elements.max_temp;
        const submit = form.querySelector('button[type="submit"]');

        input.addEventListener('input', () => input.removeAttribute('aria-invalid'));
        form.addEventListener('submit', async event => {
            event.preventDefault();
            submit.disabled = true;
            try {
                const response = await fetch('/thresholds', { method: 'POST', body: new FormData(form) });
                const data = await response.json();
                if (!response.ok) {
                    input.setAttribute('aria-invalid', 'true');
                    window.showNotification(Object.values(data.errors || {})[0] || data.message, true);
                    return;
                }
                fridge.limit = Number(data.fridge.max_temp);
                input.value = fridge.limit.toFixed(1);
                placeLimitMark(fridge);
                render();
                window.showNotification(data.message);
            } catch {
                window.showNotification('Could not reach the server.', true);
            } finally {
                submit.disabled = false;
            }
        });
    }

    // Keep the "s ago" texts and offline detection moving between two polls.
    setInterval(render, 1000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refresh();
    });
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }

    fridges.forEach(placeLimitMark);
    refresh();
})();
