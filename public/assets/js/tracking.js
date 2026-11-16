(() => {
    const tracking = document.getElementById('tracking');

    function readStage(reference) {
        try {
            const stage = Number(sessionStorage.getItem('superstore.tracking.' + reference));
            return Number.isInteger(stage) && stage >= 0 && stage <= 3 ? stage : 0;
        } catch { return 0; }
    }

    function readFulfillment(reference) {
        try {
            return JSON.parse(sessionStorage.getItem('superstore.fulfillment.' + reference));
        } catch { return null; }
    }

    function updateHistory() {
        for (const badge of document.querySelectorAll('[data-order-status]')) {
            // In-store purchases have no delivery: show them as completed, without tracking.
            const trackingLink = badge.closest('tr')?.querySelector('.tracking-link');
            const fulfillment = readFulfillment(badge.dataset.orderStatus);
            if (!fulfillment) {
                if (trackingLink) trackingLink.hidden = true;
                badge.classList.add('is-delivered');
                window.setLocalizedText(badge, 'Completed');
                continue;
            }
            if (trackingLink) trackingLink.hidden = false;
            const delivered = readStage(badge.dataset.orderStatus) === 3;
            const pickup = fulfillment.delivery === 'pickup';
            badge.classList.toggle('is-delivered', delivered);
            window.setLocalizedText(badge, delivered
                ? (pickup ? 'Collected' : 'Delivered')
                : 'In progress');
        }
    }

    window.addEventListener('pageshow', updateHistory);
    updateHistory();

    if (!tracking?.dataset.orderReference) return;

    const steps = [...tracking.querySelectorAll('.tracking-steps li')];
    const advance = document.getElementById('advance-tracking');
    const reset = document.getElementById('reset-tracking');
    const status = document.getElementById('tracking-status');
    const reference = tracking.dataset.orderReference;
    let currentStep = readStage(reference);
    const fulfillment = readFulfillment(reference);
    if (!fulfillment) {
        tracking.hidden = true;
        return;
    }
    if (fulfillment) {
        document.getElementById('tracking-estimate').textContent = fulfillment.estimate;
        if (fulfillment.delivery === 'pickup') {
            window.setLocalizedText(document.getElementById('tracking-dispatch'), 'Ready for pickup');
            window.setLocalizedText(document.getElementById('tracking-complete'), 'Collected');
        }
    }

    function render() {
        steps.forEach((step, index) => {
            step.classList.toggle('is-complete', index < currentStep);
            if (index === currentStep) step.setAttribute('aria-current', 'step');
            else step.removeAttribute('aria-current');
        });
        advance.disabled = currentStep === steps.length - 1;
        reset.disabled = currentStep === 0;
        const label = steps[currentStep].querySelector('[data-i18n]');
        const stage = label.dataset.i18n || label.textContent;
        window.setLocalizedText(status, stage);
        return stage;
    }

    function setStep(step) {
        currentStep = step;
        try {
            sessionStorage.setItem('superstore.tracking.' + reference, String(step));
        } catch {}
        window.showNotification('Order status: ' + render());
    }

    advance.addEventListener('click', () => setStep(Math.min(currentStep + 1, steps.length - 1)));
    reset.addEventListener('click', () => setStep(0));
    render();
})();

