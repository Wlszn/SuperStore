<?php

if (isset($_SESSION['customer_id'])) {
    header('Location: /dashboard');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title data-i18n>SuperStore - Sign in</title>

    <link rel="stylesheet" href="assets/css/style.css">
<script src="/assets/js/language.js" defer></script>
</head>

<body class="auth-page">

<div class="container">
    <div class="language-control"><button type="button" id="language-switch" aria-label="Passer au fran&#231;ais">FR</button></div>

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
        </div>

        <label class="show-password" for="showPassword">
            <input type="checkbox" id="showPassword">
            <span data-i18n>Show password</span>
        </label>

        <button type="submit" id="submitBtn"><span data-i18n>Sign in</span></button>

    </form>

    <p class="switch-page">
        New to SuperStore?
        <a href="/signup"><span data-i18n>Create an account</span></a>
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
                window.location.href = data.redirect || '/dashboard';
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
