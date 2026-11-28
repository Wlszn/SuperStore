<?php
/**
 * Forgot / reset password (PasswordResetController). Same look as the sign-in page.
 *
 * @var string      $step    request | sent | choose | invalid | done
 * @var string      $email
 * @var string      $token
 * @var string|null $error   a message for the whole form
 * @var array       $errors  field => message
 */
$e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$subtitles = [
    'request' => 'Forgot your password?',
    'sent' => 'Check your email',
    'choose' => 'Choose a new password',
    'invalid' => 'This link no longer works',
    'done' => 'Your password was changed',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-i18n>SuperStore - Reset your password</title>
    <script src="/assets/js/theme.js"></script>
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="/assets/js/language.js" defer></script>
</head>
<body class="auth-page">

<div class="container">
    <div class="language-control"><button type="button" id="language-switch" aria-label="Passer au fran&#231;ais">FR</button><button type="button" class="theme-switch" aria-label="Switch to dark mode" data-i18n-aria-label="Switch to dark mode"><svg class="icon-moon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg><svg class="icon-sun" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg></button></div>

    <h1>SuperStore</h1>
    <p class="subtitle"><span data-i18n><?= $e($subtitles[$step]) ?></span></p>

    <?php if ($error !== null): ?>
        <div class="form-message error" role="alert"><span data-i18n><?= $e($error) ?></span></div>
    <?php endif; ?>

    <?php if ($step === 'request'): ?>
        <p class="field-hint" style="margin-bottom:18px"><span data-i18n>Enter the email address of your account. We will send you a link to choose a new password.</span></p>
        <form method="post" action="/forgot-password">
            <div class="field">
                <label for="email"><span data-i18n>Email address</span></label>
                <input type="email" id="email" name="email" maxlength="100" autocomplete="email" required value="<?= $e($email) ?>">
            </div>
            <button type="submit"><span data-i18n>Send the link</span></button>
        </form>

    <?php elseif ($step === 'sent'): ?>
        <div class="form-message success" role="status"><span data-i18n>If an account uses <?= $e($email) ?>, we sent it a link to reset the password.</span></div>
        <p class="field-hint"><span data-i18n>The link works once and expires in 1 hour. Not in your inbox? Check your spam folder.</span></p>

    <?php elseif ($step === 'choose'): ?>
        <form method="post" action="/reset-password">
            <input type="hidden" name="token" value="<?= $e($token) ?>">
            <div class="field">
                <label for="password"><span data-i18n>New password</span></label>
                <input type="password" id="password" name="password" autocomplete="new-password" required minlength="8" <?= isset($errors['password']) ? 'class="invalid" aria-invalid="true" aria-describedby="error-password"' : '' ?>>
                <p class="field-error" id="error-password"><?php if (isset($errors['password'])): ?><span data-i18n><?= $e($errors['password']) ?></span><?php endif; ?></p>
                <p class="field-hint"><span data-i18n>At least 8 characters, with at least one letter and one number.</span></p>
            </div>
            <div class="field">
                <label for="confirm_password"><span data-i18n>Confirm new password</span></label>
                <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required <?= isset($errors['confirm_password']) ? 'class="invalid" aria-invalid="true" aria-describedby="error-confirm_password"' : '' ?>>
                <p class="field-error" id="error-confirm_password"><?php if (isset($errors['confirm_password'])): ?><span data-i18n><?= $e($errors['confirm_password']) ?></span><?php endif; ?></p>
            </div>
            <label class="show-password" for="showPassword">
                <input type="checkbox" id="showPassword">
                <span data-i18n>Show passwords</span>
            </label>
            <button type="submit"><span data-i18n>Save my new password</span></button>
        </form>
        <script src="/assets/js/form-utils.js"></script>
        <script>wireShowPasswordToggle('showPassword', ['password', 'confirm_password']);</script>

    <?php elseif ($step === 'invalid'): ?>
        <div class="form-message error" role="alert"><span data-i18n>This link has expired or was already used. Ask for a new one.</span></div>
        <p class="switch-page"><a href="/forgot-password"><span data-i18n>Send me a new link</span></a></p>

    <?php else: ?>
        <div class="form-message success" role="status"><span data-i18n>You can now sign in with your new password.</span></div>
        <p class="switch-page"><a href="/login"><span data-i18n>Sign in</span></a></p>
    <?php endif; ?>

    <?php if ($step !== 'done'): ?>
        <p class="switch-page"><a href="/login"><span data-i18n>Back to sign in</span></a></p>
    <?php endif; ?>
</div>
</body>
</html>
