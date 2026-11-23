document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.report-filters');
    if (!form) return;
    const update = () => {
        const mode = form.querySelector('input[name="mode"]:checked')?.value ?? 'range';
        form.querySelectorAll('[data-mode]').forEach((label) =>{
            const on = label.dataset.mode === mode;
            label.hidden = !on;
            label.querySelector('input').disabled = !on;
        });
    };
    form.addEventListener('change', update);
    update();
});