// Price history charts on the account page (P4-19): the unit price of one item at each purchase.
// The data comes from the page (data-labels, data-titles, data-prices, made by PHP);
// Chart.js is the copy in assets/js/vendor/.
document.addEventListener('DOMContentLoaded', () => {
    if (!window.Chart) return;
    const money = value => '$' + Number(value).toFixed(2);
    const ink = getComputedStyle(document.documentElement).getPropertyValue('--muted').trim() || '#5d6875';
    const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    for (const canvas of document.querySelectorAll('.price-chart')) {
        const titles = JSON.parse(canvas.dataset.titles);
        const prices = JSON.parse(canvas.dataset.prices);

        new window.Chart(canvas, {
            type: 'line',
            data: {
                labels: JSON.parse(canvas.dataset.labels),
                datasets: [{
                    label: window.translate ? window.translate('Price') : 'Price',
                    data: prices,
                    borderColor: '#2f6db3',
                    backgroundColor: '#2f6db3',
                    borderWidth: 2,
                    pointRadius: 5,          // every purchase is a visible point
                    pointHoverRadius: 7,
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    tension: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: still ? false : { duration: 400 },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },   // one series: the heading names it
                    tooltip: {
                        displayColors: false,
                        callbacks: {
                            title: items => titles[items[0].dataIndex],
                            label: item => money(item.parsed.y),
                            // The change since the previous purchase, like the table.
                            afterLabel: item => {
                                if (item.dataIndex === 0) return '';
                                const before = prices[item.dataIndex - 1];
                                const change = item.parsed.y - before;
                                if (Math.abs(change) < 0.005) return '';
                                const sign = change > 0 ? '+' : '−';
                                return sign + money(Math.abs(change)) + ' (' + sign + Math.abs(change / before * 100).toFixed(1) + '%)';
                            },
                        },
                    },
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: ink, maxRotation: 0, autoSkipPadding: 12 } },
                    y: {
                        grace: '15%',   // room above and below so small price changes are visible
                        border: { display: false },
                        grid: { color: 'rgba(0, 0, 0, 0.06)' },
                        ticks: { color: ink, callback: money, maxTicksLimit: 5 },
                    },
                },
            },
        });
    }
});
