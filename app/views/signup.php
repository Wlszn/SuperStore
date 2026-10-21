<?php
session_start();

if (isset($_SESSION['customer_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>SuperStore - Create account</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

<div class="container">

    <h1>Create your account</h1>
    <p class="subtitle">It only takes a minute</p>

    <div id="message" class="form-message" hidden></div>

    <form id="signupForm" novalidate>

        <div class="field">
            <label for="name">Full name</label>
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
            <label for="email">Email address</label>
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
            <label for="phone">Phone number</label>
            <input
                type="tel"
                id="phone"
                name="phone"
                maxlength="15"
                autocomplete="tel"
                required
            >
            <p class="field-error" id="error-phone"></p>
        </div>

        <div class="field">
            <label for="address">Address</label>
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
            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                autocomplete="new-password"
                required
            >
            <p class="field-hint">At least 8 characters, with at least one letter and one number.</p>
            <p class="field-error" id="error-password"></p>
        </div>

        <div class="field">
            <label for="confirm_password">Confirm password</label>
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
            Show passwords
        </label>

        <button type="submit" id="submitBtn">Create account</button>

    </form>

    <p class="switch-page">
        Already have an account?
        <a href="login.php">Sign in</a>
    </p>

</div>

<script src="assets/js/form-utils.js"></script>
<script>
    const form = document.getElementById('signupForm');
    const message = document.getElementById('message');
    const submitBtn = document.getElementById('submitBtn');

    wireShowPasswordToggle('showPassword', ['password', 'confirm_password']);

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
        submitBtn.textContent = 'Creating your account...';

        try {
            const data = await postForm('register', form);

            if (data.status === 'success') {
                showMessage(message, data.message, true);
                window.location.href = data.redirect || 'dashboard.php';
            } else {
                showFormErrors(form, data.errors || {});
                showMessage(message, data.message, false);
            }
        } catch (error) {
            showMessage(message, 'Could not reach the server. Please check your connection and try again.', false);
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Create account';
        }
    });
</script>
</body>
</html>
