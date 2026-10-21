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

    <title>SuperStore - Sign in</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

<div class="container">

    <h1>SuperStore</h1>
    <p class="subtitle">Sign in to your account</p>

    <div id="message" class="form-message" hidden></div>

    <form id="loginForm" novalidate>

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
            <label for="password">Password</label>
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
            Show password
        </label>

        <button type="submit" id="submitBtn">Sign in</button>

    </form>

    <p class="switch-page">
        New to SuperStore?
        <a href="signup.php">Create an account</a>
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
        submitBtn.textContent = 'Signing in...';

        try {
            const data = await postForm('login', form);

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
            submitBtn.textContent = 'Sign in';
        }
    });
</script>
</body>
</html>
