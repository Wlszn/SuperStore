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

    <title data-i18n>SuperStore - Create account</title>

    <link rel="stylesheet" href="assets/css/style.css">
<script src="/assets/js/language.js" defer></script>
</head>

<body class="auth-page">

<div class="container">
    <div class="language-control"><button type="button" id="language-switch" aria-label="Passer au fran&#231;ais">FR</button></div>

    <h1><span data-i18n>Create your account</span></h1>
    <p class="subtitle"><span data-i18n>It only takes a minute</span></p>

    <div id="message" class="form-message" hidden></div>

    <form id="signupForm" novalidate>

        <div class="field">
            <label for="name"><span data-i18n>Full name</span></label>
            <input
                type="text"
                id="name"
                name="name"
                maxlength="50"
                autocomplete="name"
                required
            >
            <p class="field-error" id="error-name"></p>
        </div>

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
            <label for="phone"><span data-i18n>Phone number</span></label>
            <input
                type="tel"
                id="phone"
                name="phone"
                inputmode="numeric"
                autocomplete="tel"
                required
            >
            <p class="field-error" id="error-phone"></p>
        </div>

        <div class="field">
            <label for="address"><span data-i18n>Address</span></label>
            <input
                type="text"
                id="address"
                name="address"
                maxlength="255"
                autocomplete="street-address"
                required
            >
            <p class="field-error" id="error-address"></p>
        </div>

        <div class="field">
            <label for="password"><span data-i18n>Password</span></label>
            <input
                type="password"
                id="password"
                name="password"
                autocomplete="new-password"
                required
            >
            <p class="field-hint"><span data-i18n>At least 8 characters, with at least one letter and one number.</span></p>
            <p class="field-error" id="error-password"></p>
        </div>

        <div class="field">
            <label for="confirm_password"><span data-i18n>Confirm password</span></label>
            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                autocomplete="new-password"
                required
            >
            <p class="field-error" id="error-confirm_password"></p>
        </div>

        <label class="show-password" for="showPassword">
            <input type="checkbox" id="showPassword">
            <span data-i18n>Show passwords</span>
        </label>

        <button type="submit" id="submitBtn"><span data-i18n>Create account</span></button>

    </form>

    <p class="switch-page">
        <span data-i18n>Already have an account?</span>
        <a href="/login"><span data-i18n>Sign in</span></a>
    </p>

</div>

<script src="assets/js/form-utils.js"></script>
<script>
    const form = document.getElementById('signupForm');
    const message = document.getElementById('message');
    const submitBtn = document.getElementById('submitBtn');

    wireShowPasswordToggle('showPassword', ['password', 'confirm_password']);
    wirePhoneNumberInput(form.elements.phone);

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        hideMessage(message);

        // Quick check in the browser so the user gets instant feedback.
        const errors = Object.assign(
            {},
            validateCustomerFields(form),
            validatePasswordFields(form)
        );

        if (Object.keys(errors).length > 0) {
            showFormErrors(form, errors);
            showMessage(message, 'Please fix the highlighted fields and try again.', false);
            return;
        }
        clearFormErrors(form);

        submitBtn.disabled = true;
        window.setLocalizedText(submitBtn, 'Creating your account...');

        try {
            const data = await postForm('register', form);

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
            window.setLocalizedText(submitBtn, 'Create account');
        }
    });
</script>
</body>
</html>
