(() => {
    const toast = document.getElementById('notice');
    const text = document.getElementById('notice-message');
    const dismiss = document.getElementById('notice-dismiss');
    let timeout;

    function hide() {
        clearTimeout(timeout);
        toast.hidden = true;
    }

    dismiss.addEventListener('click', hide);
    window.showNotification = (message, isError = false) => {
        clearTimeout(timeout);
        const changed = toast.hidden || text.dataset.i18n !== message || toast.classList.contains('error') !== isError;
        toast.classList.toggle('error', isError);
        toast.hidden = false;
        if (changed) window.setLocalizedText(text, message);
        if (!isError) timeout = setTimeout(hide, 4500);
    };
})();

