<?php
$title = 'Self-checkout';
$section = 'checkout';
require __DIR__ . '/products/_start.php';
?>

<header class="page-heading">
    <div>
        <h1><span data-i18n>Self-checkout</span></h1>
        <p><span data-i18n>Place all your items on the reader. They appear in your basket at once.</span></p>
    </div>
    <?php if (isset($_SESSION['customer_id'])): ?>
        <a class="button" href="/account"><span data-i18n>My account</span></a>
    <?php endif; ?>
</header>
<div class="panel editor-body reader-controls">
    <span class="reader-light" id="reader-light" aria-hidden="true"></span>
    <p id="reader-status" role="status" class="hint"><span data-i18n>Connecting to the RFID reader...</span></p>
    <button type="button" class="button" id="reader"><span data-i18n>Stop reader</span></button>
</div>
<?php if ($membershipCode === ''): ?>
    <p class="hint member-hint" id="card-hint" role="status"><span data-i18n>Have a membership card? Scan it at any time to earn points.</span></p>
<?php endif; ?>
<section class="panel section-gap">
    <div class="table-scroll compact" tabindex="0" role="region" aria-label="Shopping basket" data-i18n-aria-label="Shopping basket">
        <table>
            <thead>
                <tr>
                    <th><span data-i18n>Product</span></th>
                    <th class="numeric"><span data-i18n>Quantity</span></th>
                    <th class="numeric"><span data-i18n>Unit price</span></th>
                    <th class="numeric"><span data-i18n>Amount</span></th>
                    <th><span data-i18n>Action</span></th>
                </tr>
            </thead>
            <tbody id="basket">
            </tbody>
        </table>
    </div>
    <p class="empty" id="empty"><span data-i18n>Your basket is empty. Place your items on the reader.</span></p>
</section>
<form id="payment" class="panel editor-body section-gap payment-panel" novalidate>
    <input type="hidden" name="csrf_token" value="<?= $e($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="key" value="<?= $e($_SESSION['checkout_key']) ?>">

    <!-- Step 1: membership card, points and delivery -->
    <div id="step-details" class="payment-panel" hidden>
        <?php if ($membershipCode !== ''): ?>
            <!-- Signed-in member: their own card is applied, nothing to scan. -->
            <h2 data-i18n>Step 1 of 3: Your membership</h2>
            <p><span data-i18n>Your membership card is applied:</span> <span class="code"><?= $e($membershipCode) ?></span></p>
            <input type="hidden" id="membership-code" name="membership_code" value="<?= $e($membershipCode) ?>">
            <p id="member-status" class="hint" role="status" hidden></p>
        <?php else: ?>
            <h2 data-i18n>Step 1 of 3: Scan your membership card</h2>
            <?php if ($membershipUnavailable): ?>
                <p class="hint" data-i18n>Your membership code could not be loaded. You can still scan or enter it.</p>
            <?php endif; ?>
            <p data-i18n>Hold your card's barcode under the scanner, or type its number. No card? Continue as a guest.</p>
            <label for="membership-code"><span data-i18n>Membership card number</span></label>
            <input id="membership-code" name="membership_code" value="" maxlength="64" autocomplete="off">
            <p id="member-status" class="hint" role="status" hidden></p>
            <p class="hint"><span data-i18n>Members earn 10 points per dollar and receive the receipt by email.</span>
                <?php if (!isset($_SESSION['customer_id'])): ?><a href="/login"><span data-i18n>Sign in</span></a> <span data-i18n>to use your points.</span><?php endif; ?></p>
        <?php endif; ?>
        <?php if ($membershipCode !== ''): ?>
            <div class="checkout-details" id="points-redemption" data-balance="<?= (int) ($profile['points'] ?? 0) ?>">
                <label for="redeem-points" data-i18n>Use points</label>
                <input type="text" inputmode="numeric" id="redeem-points" name="redeem_points" value="0"
                    aria-describedby="points-help points-available">
                <p id="points-help" class="hint" data-i18n>100 points = $1 off. Earn points on the amount you pay.</p>
                <p id="points-available" class="hint" data-i18n><?= (int) ($profile['points'] ?? 0) ?> points available</p>
            </div>
        <?php endif; ?>
        <label class="checkbox-line" <?= ORDER_TRACKING ? '' : 'hidden' ?>><input type="checkbox" id="deliver-order"> <span data-i18n>Deliver this order (optional)</span></label>
        <h2 data-i18n id="delivery-heading" hidden>Contact and delivery</h2>
        <div class="fields checkout-details" id="delivery-details" hidden>
            <label><span data-i18n>Full name</span>
                <input name="name" value="<?= $e($profile['name'] ?? '') ?>" autocomplete="name" maxlength="100" required>
            </label>
            <label><span data-i18n>Email address</span>
                <input name="email" value="<?= $e($profile['email'] ?? '') ?>" type="email" autocomplete="email" maxlength="100" required>
            </label>
            <label><span data-i18n>Phone number</span>
                <input name="phone" value="<?= $e($profile['phone'] ?? '') ?>" type="tel" inputmode="tel" autocomplete="tel" maxlength="24" required>
            </label>
            <label><span data-i18n>Delivery option</span>
                <select name="delivery">
                    <option value="standard" data-i18n>Standard delivery</option>
                    <option value="express" data-i18n>Express delivery</option>
                    <option value="pickup" data-i18n>Store pickup</option>
                </select>
            </label>
            <label class="wide" id="shipping-address"><span data-i18n>Shipping address</span>
                <input name="address" value="<?= $e($profile['address'] ?? '') ?>" autocomplete="street-address" maxlength="255" required>
            </label>
            <p id="order-estimate" class="hint wide"></p>
        </div>
        <div class="heading-actions">
            <button type="button" class="button primary" id="details-next"><span data-i18n>Continue</span></button>
            <?php if ($membershipCode === ''): ?>
                <button type="button" class="button" id="skip-card"><span data-i18n>Continue without a card</span></button>
            <?php endif; ?>
            <button type="button" class="button" data-back=""><span data-i18n>Back to basket</span></button>
        </div>
    </div>

    <!-- Step 2: the customer confirms the list -->
    <div id="step-confirm" class="payment-panel" hidden>
        <h2 data-i18n>Step 2 of 3: Confirm your items</h2>
        <ul id="summary" class="checkout-summary"></ul>
        <p id="summary-member" class="hint"></p>
        <p id="summary-delivery" class="hint"></p>
        <div class="heading-actions">
            <button type="button" class="button primary" id="confirm-list"><span data-i18n>Confirm list</span></button>
            <button type="button" class="button" data-back="details"><span data-i18n>Back</span></button>
        </div>
    </div>

    <!-- Step 3: simulated payment -->
    <div id="step-payment" class="payment-panel" hidden>
        <h2 data-i18n>Step 3 of 3: Payment</h2>
        <p class="hint"><span data-i18n>Classroom payment simulation. Prices are final in CAD. No card details required.</span></p>
        <div class="heading-actions">
            <button type="button" class="button primary" id="pay-card"><span data-i18n>Pay by card</span></button>
            <button type="button" class="button" id="pay-cash"><span data-i18n>Pay cash</span></button>
            <button type="button" class="button" data-back="confirm"><span data-i18n>Back</span></button>
        </div>
        <div id="cash" class="payment-panel" hidden>
            <label for="cash-given"><span data-i18n>Cash given</span></label>
            <input id="cash-given" type="number" min="0" step="0.01" inputmode="decimal">
            <p id="change" class="hint"></p>
            <button class="button primary" id="cash-confirm"><span data-i18n>Pay</span></button>
        </div>
    </div>

    <p id="points-discount" class="hint" hidden></p>
    <h2 id="total" data-i18n>Total $0.00</h2>
    <button type="button" class="button primary" id="pay" disabled><span data-i18n>Checkout</span></button>
</form>
<script src="/assets/js/checkout.js" defer></script>
<?php require __DIR__ . '/products/_end.php'; ?>
