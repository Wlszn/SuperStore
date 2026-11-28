// Light/dark mode. Loaded in <head> without defer: it sets data-theme on <html> before the page
// is drawn, so a dark page never flashes white. The colours themselves are CSS variables
// (inventory.css and style.css). Until the user clicks the switch, the page follows the
// computer's own light/dark setting; their choice is then remembered in this browser.
(() => {
    const storageKey = 'superstore.theme';
    const root = document.documentElement;
    const system = window.matchMedia('(prefers-color-scheme: dark)');
    let chosen = null;
    try {
        chosen = localStorage.getItem(storageKey);
    } catch {}

    function apply(theme) {
        root.dataset.theme = theme;
        const label = theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode';
        for (const button of document.querySelectorAll('.theme-switch')) {
            button.setAttribute('data-i18n-aria-label', label);
            button.setAttribute('aria-label', window.translate ? window.translate(label) : label);
        }
        // Chart.js draws on a canvas, so its text and grid colours are set here, not by CSS.
        if (window.Chart) {
            const style = getComputedStyle(root);
            const ink = style.getPropertyValue('--muted').trim();
            const grid = style.getPropertyValue('--line').trim();
            const canvas = style.getPropertyValue('--canvas').trim();
            for (const chart of Object.values(window.Chart.instances)) {
                for (const scale of Object.values(chart.options.scales ?? {})) {
                    if (scale.ticks) scale.ticks.color = ink;
                    if (scale.grid && scale.grid.display !== false) scale.grid.color = grid;
                }
                for (const dataset of chart.data.datasets) {
                    if (dataset.pointBorderColor) dataset.pointBorderColor = canvas;
                }
                chart.update('none');
            }
        }
    }

    apply(chosen === 'dark' || chosen === 'light' ? chosen : (system.matches ? 'dark' : 'light'));
    system.addEventListener('change', (event) => {
        if (!chosen) apply(event.matches ? 'dark' : 'light');
    });
    // The buttons are further down the page: one listener on the document covers them.
    document.addEventListener('click', (event) => {
        if (!event.target.closest('.theme-switch')) return;
        chosen = root.dataset.theme === 'dark' ? 'light' : 'dark';
        try {
            localStorage.setItem(storageKey, chosen);
        } catch {}
        apply(chosen);
    });
    document.addEventListener('DOMContentLoaded', () => apply(root.dataset.theme));
    // Paper is always light: print the charts with the light colours, then switch back.
    let screenTheme = null;
    window.addEventListener('beforeprint', () => {
        screenTheme ??= root.dataset.theme;   // a second beforeprint must not overwrite it
        apply('light');
    });
    window.addEventListener('afterprint', () => {
        if (screenTheme) apply(screenTheme);
        screenTheme = null;
    });
})();
