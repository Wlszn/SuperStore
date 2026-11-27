// Live item search on the account page (P4-19).
// PHP already put every item the customer bought in #item-suggestions, each linking to its history.
// As the customer types, this shows only the items whose name contains the text (any case),
// with the matching part in bold. Click an item (or pick it with the arrow keys and Enter) to open it;
// pressing Enter without picking one still searches and shows every match.
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('item-search');
    const list = document.getElementById('item-suggestions');
    if (!input || !list) return;

    const options = [...list.querySelectorAll('[role=option]')];
    const none = list.querySelector('.item-suggestions-none');
    const names = options.map(option => option.textContent.trim());
    let shown = [];
    let active = -1;

    const highlight = index => {
        options.forEach(option => option.setAttribute('aria-selected', 'false'));
        active = index;
        if (index < 0) {
            input.removeAttribute('aria-activedescendant');
            return;
        }
        shown[index].setAttribute('aria-selected', 'true');
        input.setAttribute('aria-activedescendant', shown[index].id);
        shown[index].scrollIntoView({ block: 'nearest' });
    };

    const close = () => {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        highlight(-1);
    };

    const update = () => {
        const text = input.value.trim().toLowerCase();
        if (text === '') {
            close();
            return;
        }
        shown = [];
        options.forEach((option, i) => {
            const at = names[i].toLowerCase().indexOf(text);
            option.hidden = at === -1;
            if (at === -1) return;
            // Rebuild the name with the typed part in <mark> (text nodes, so names are never read as HTML).
            const link = option.querySelector('a');
            const mark = document.createElement('mark');
            mark.textContent = names[i].slice(at, at + text.length);
            link.replaceChildren(names[i].slice(0, at), mark, names[i].slice(at + text.length));
            shown.push(option);
        });
        none.hidden = shown.length > 0;
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        highlight(-1);
    };

    input.addEventListener('input', update);
    input.addEventListener('focus', update);

    input.addEventListener('keydown', event => {
        if (list.hidden || shown.length === 0) return;
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            highlight((active + 1) % shown.length);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            highlight(active <= 0 ? shown.length - 1 : active - 1);
        } else if (event.key === 'Enter' && active >= 0) {
            event.preventDefault();
            window.location.href = shown[active].querySelector('a').href;
        } else if (event.key === 'Escape') {
            close();
        }
    });

    // Clicking anywhere else closes the list.
    document.addEventListener('click', event => {
        if (!event.target.closest('.item-search-box')) close();
    });
});
