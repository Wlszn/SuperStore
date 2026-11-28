<?php

if (isset($_SESSION['customer_id'])) {
    header('Location: ' . (empty($_SESSION['is_admin']) ? '/account' : '/products'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title data-i18n>SuperStore - Sign in</title>

    <script src="/assets/js/theme.js"></script>
    <link rel="stylesheet" href="assets/css/style.css">
<script src="/assets/js/language.js" defer></script>
</head>

<body class="auth-page">

<div class="container">
<<<<<<< Updated upstream
    <div class="language-control"><button type="button" id="language-switch" aria-label="Passer au fran&#231;ais">FR</button><button type="button" class="theme-switch" aria-label="Switch to dark mode" data-i18n-aria-label="Switch to dark mode"><svg class="icon-moon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg><svg class="icon-sun" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg></button></div>
=======
    <div class="language-control">
        <button type="button" id="language-switch" aria-label="Passer au fran&#231;ais">FR</button>
        <button type="button" class="theme-switch" id="theme-switch" aria-label="Switch to dark mode" data-i18n-aria-label="Switch to dark mode" aria-pressed="false"><span aria-hidden="true">🌙</span></button>
    </div>
>>>>>>> Stashed changes

    <h1>SuperStore</h1>
    <p class="subtitle"><span data-i18n>Sign in to your account</span></p>

    <div id="message" class="form-message" hidden></div>

    <form id="loginForm" novalidate>

        <div class="field">
            <label for="email"><span data-i18n>Email address</span></label>
            <input
                type="email"
                id="email"
                name="email"
                maxlength="100"
                autocomplete="email"
                required
            >
            <p class="field-error" id="error-email"></p>
        </div>

        <div class="field">
            <label for="password"><span data-i18n>Password</span></label>
            <input
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                required
            >
            <p class="field-error" id="error-password"></p>
            <p class="field-hint"><a href="/forgot-password"><span data-i18n>Forgot your password?</span></a></p>
        </div>

        <label class="show-password" for="showPassword">
            <input type="checkbox" id="showPassword">
            <span data-i18n>Show password</span>
        </label>

        <button type="submit" id="submitBtn"><span data-i18n>Sign in</span></button>

    </form>

    <p class="switch-page">
        <span data-i18n>New to SuperStore?</span>
        <a href="/signup"><span data-i18n>Create an account</span></a>
    </p>
    <p class="switch-page guest-link">
        <span data-i18n>Shopping without an account?</span>
        <a href="/checkout"><span data-i18n>Go to self-checkout</span></a>
    </p>

</div>

<script src="assets/js/form-utils.js"></script>
<script>
    const form = document.getElementById('loginForm');
    const message = document.getElementById('message');
    const submitBtn = document.getElementById('submitBtn');

    wireShowPasswordToggle('showPassword', ['password']);

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        hideMessage(message);

        // Quick check in the browser so the user gets instant feedback.
        const fields = form.elements;
        const email = fields['email'].value.trim();
        const password = fields['password'].value;

        const errors = {};
        if (email === '') {
            errors.email = 'Please enter your email address.';
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            errors.email = 'This email address does not look valid. Example: name@example.com';
        }
        if (password === '') {
            errors.password = 'Please enter your password.';
        }

        if (Object.keys(errors).length > 0) {
            showFormErrors(form, errors);
            showMessage(message, 'Please fix the highlighted fields and try again.', false);
            return;
        }
        clearFormErrors(form);

        submitBtn.disabled = true;
        window.setLocalizedText(submitBtn, 'Signing in...');

        try {
            const data = await postForm('login', form);

            if (data.status === 'success') {
                showMessage(message, data.message, true);
                window.location.href = data.redirect || '/';
            } else {
                showFormErrors(form, data.errors || {});
                showMessage(message, data.message, false);
            }
        } catch (error) {
            showMessage(message, 'Could not reach the server. Please check your connection and try again.', false);
        } finally {
            submitBtn.disabled = false;
            window.setLocalizedText(submitBtn, 'Sign in');
        }
    });
</script>
</body>
</html>
