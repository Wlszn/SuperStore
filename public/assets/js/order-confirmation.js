(() => {
    const reference = new URLSearchParams(location.search).get('order');
    let order;
    try {
        const orders = JSON.parse(sessionStorage.getItem('superstore.orders.' + document.body.dataset.orderOwner)) || [];
        order = orders.find(entry => entry.reference === reference);
    } catch {}
    if (!order) {
        window.showNotification('Order details are unavailable in this tab.', true);
        return;
    }

    document.getElementById('order-reference').textContent = order.reference;
    const method = { standard: 'Standard delivery', express: 'Express delivery', pickup: 'Store pickup' };
    window.setLocalizedText(document.getElementById('confirmation-delivery'), method[order.delivery]);
    const items = document.getElementById('confirmation-items');
    for (const item of order.items) {
        const row = document.createElement('li');
        row.textContent = item.quantity + ' × ' + item.name + ' · $' + (item.cents * item.quantity / 100).toFixed(2);
        items.append(row);
    }
    window.setLocalizedText(document.getElementById('confirmation-total'), 'Total $' + (order.cents / 100).toFixed(2));
    document.getElementById('order-confirmation').hidden = false;
    const tracking = document.getElementById('tracking');
    tracking.dataset.orderReference = order.reference;
    document.getElementById('tracking-estimate').textContent = order.estimate;
    if (order.delivery === 'pickup') {
        window.setLocalizedText(document.getElementById('tracking-dispatch'), 'Ready for pickup');
        window.setLocalizedText(document.getElementById('tracking-complete'), 'Collected');
    }
    tracking.hidden = false;
    if (location.hash === '#tracking') tracking.scrollIntoView();
    else {
        window.showNotification('Order placed successfully.');
    }
})();

