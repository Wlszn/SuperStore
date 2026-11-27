// Spending graph on the account page (P4-18). The data comes from the page (data-labels and
// data-amounts on the canvas, made by PHP); Chart.js is the copy in assets/js/vendor/.
document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById('spending-chart');
    if (!canvas || !window.Chart) return;

    const money = value => '$' + Number(value).toFixed(2);
    const ink = getComputedStyle(document.documentElement).getPropertyValue('--muted').trim() || '#5d6875';

    new window.Chart(canvas, {
        type: 'bar',
        data: {
            labels: JSON.parse(canvas.dataset.labels),
            datasets: [{
                label: window.translate ? window.translate('Amount paid') : 'Amount paid',
                data: JSON.parse(canvas.dataset.amounts),
                backgroundColor: '#2f6db3',
                hoverBackgroundColor: '#24578f',
                borderRadius: { topLeft: 4, topRight: 4 },  // rounded data end, flat on the baseline
                borderSkipped: 'bottom',
                maxBarThickness: 32,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 400 },
            interaction: { mode: 'index', intersect: false },   // hovering anywhere in a column shows its value
            plugins: {
                legend: { display: false },   // one series: the heading names it
                tooltip: { displayColors: false, callbacks: { label: context => money(context.parsed.y) } },
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: ink, maxRotation: 0, autoSkipPadding: 12 } },
                y: {
                    beginAtZero: true,
                    border: { display: false },
                    grid: { color: 'rgba(0, 0, 0, 0.06)' },
                    ticks: { color: ink, callback: money, maxTicksLimit: 5 },
                },
            },
        },
    });
});
