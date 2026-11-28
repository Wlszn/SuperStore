<?php
/**
 * Admin: edit one customer (CustomerController::edit).
 *
 * @var array $customer  id, name, email, phone, address, membership_number, points, is_admin, created_at
 * @var bool  $self      true when admins edit their own account
 * @var bool  $mailReady true when the Brevo settings are in .env
 */
$title = 'Edit customer';
$section = 'customers';
require __DIR__ . '/../products/_start.php';
?>
<header class="page-heading">
    <div><h1><span data-i18n>Edit customer</span></h1><p><?= $e($customer['name']) ?></p></div>
    <div class="row-actions"><a class="button" href="/customers" data-i18n>Back to customers</a></div>
</header>

<form class="panel editor" method="post" action="/customers/update" data-action-form data-next="/customers">
    <input type="hidden" name="csrf_token" value="<?= $e($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="id" value="<?= (int) $customer['id'] ?>">

    <div class="editor-body">
        <h2><span data-i18n>Personal information</span></h2>
        <div class="fields">
            <label><span data-i18n>Full name</span>
                <input name="name" required maxlength="50" value="<?= $e($customer['name']) ?>">
                <span data-error="name" class="field-error"></span>
            </label>
            <label><span data-i18n>Email address</span>
                <input name="email" type="email" required maxlength="100" value="<?= $e($customer['email']) ?>">
                <span data-error="email" class="field-error"></span>
            </label>
            <label><span data-i18n>Phone number</span>
                <input name="phone" type="tel" required maxlength="15" value="<?= $e($customer['phone'] ?? '') ?>">
                <span data-error="phone" class="field-error"></span>
            </label>
            <label class="wide"><span data-i18n>Address</span>
                <input name="address" required maxlength="255" value="<?= $e($customer['address'] ?? '') ?>">
                <span data-error="address" class="field-error"></span>
            </label>
        </div>
    </div>

    <div class="editor-body">
        <h2><span data-i18n>Membership</span></h2>
        <div class="fields">
            <label><span data-i18n>Membership number</span>
                <input name="membership_number" required maxlength="64" autocomplete="off" value="<?= $e($customer['membership_number'] ?? '') ?>">
                <span data-error="membership_number" class="field-error"></span>
            </label>
            <label><span data-i18n>Points</span>
                <input name="points" required inputmode="numeric" value="<?= (int) $customer['points'] ?>">
                <span data-error="points" class="field-error"></span>
            </label>
        </div>
    </div>

    <div class="editor-body">
        <h2><span data-i18n>Access</span></h2>
        <label class="checkbox-line">
            <input type="checkbox" name="is_admin" value="1" <?= !empty($customer['is_admin']) ? 'checked' : '' ?> <?= $self ? 'disabled' : '' ?>>
            <span data-i18n>Store administrator</span>
        </label>
        <p class="hint"><span data-i18n><?= $self ? 'This is your account: you cannot remove your own administrator access.' : 'Administrators can manage products, stock, reports and customers.' ?></span></p>
    </div>

    <div class="editor-footer">
        <a class="button" href="/customers" data-i18n>Cancel</a>
        <button type="submit" class="button primary"><span data-i18n>Save changes</span></button>
    </div>
</form>

<form class="panel editor editor-body section-gap" method="post" action="/customers/reset-password"
    data-action-form data-next="/customers/edit?id=<?= (int) $customer['id'] ?>">
    <input type="hidden" name="csrf_token" value="<?= $e($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="id" value="<?= (int) $customer['id'] ?>">
    <h2><span data-i18n>Password</span></h2>
    <p class="hint"><span data-i18n>Email this customer a link to choose a new password. The link works once and expires in 1 hour; their current password keeps working until they use it.</span></p>
    <?php if (!$mailReady): ?>
        <p class="notice error"><span data-i18n>Email is not set up yet: add BREVO_API_KEY and MAIL_FROM_EMAIL to .env.</span></p>
    <?php endif; ?>
    <button type="submit" class="button" <?= $mailReady ? '' : 'disabled' ?>><span data-i18n>Send password reset email</span></button>
</form>

<?php require __DIR__ . '/../products/_end.php'; ?>
