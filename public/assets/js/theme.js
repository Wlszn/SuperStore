(() => {
    const storageKey = 'superstore.theme';
    const root = document.documentElement;
    const system = window.matchMedia('(prefers-color-scheme: dark)');
    let chosen = null;
    try {
        const savedTheme = localStorage.getItem(storageKey);
        if (savedTheme === 'dark' || savedTheme === 'light') chosen = savedTheme;
    } catch {}

    function apply(theme) {
        root.dataset.theme = theme;
        const label = theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode';
        for (const button of document.querySelectorAll('.theme-switch')) {
            button.setAttribute('data-i18n-aria-label', label);
            button.setAttribute('aria-label', window.translate ? window.translate(label) : label);
            button.setAttribute('aria-pressed', String(theme === 'dark'));
        }

        const charts = window.Chart?.instances;
        if (!charts) return;
        const style = getComputedStyle(root);
        const ink = style.getPropertyValue('--muted').trim();
        const grid = style.getPropertyValue('--line').trim();
        const canvas = style.getPropertyValue('--canvas').trim();
        for (const chart of Object.values(charts)) {
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

    apply(chosen ?? (system.matches ? 'dark' : 'light'));
    system.addEventListener('change', (event) => {
        if (chosen === null) apply(event.matches ? 'dark' : 'light');
    });

    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element) || !event.target.closest('.theme-switch')) return;
        chosen = root.dataset.theme === 'dark' ? 'light' : 'dark';
        try {
            localStorage.setItem(storageKey, chosen);
        } catch {}
        apply(chosen);
    });
    document.addEventListener('DOMContentLoaded', () => apply(root.dataset.theme));

    let screenTheme = null;
    window.addEventListener('beforeprint', () => {
        screenTheme ??= root.dataset.theme;
        apply('light');
    });
    window.addEventListener('afterprint', () => {
        if (screenTheme !== null) apply(screenTheme);
        screenTheme = null;
    });
})();
