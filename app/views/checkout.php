<?php
$title = 'Self-checkout';
$section = 'checkout';
require __DIR__ . '/products/_start.php';
?>

<header class="page-heading">
    <div>
        <h1><span data-i18n>Self-checkout</span></h1>
        <p><span data-i18n>Scan your products, review your basket, then simulate payment.</span></p>
    </div>
    <a class="button" href="/account"><span data-i18n>My account</span></a>
</header>
<form id="scan" class="panel editor-body scan-line">
    <?php if ($products): ?>
        <label class="checkout-picker" for="checkout-product">
            <span data-i18n>Choose a product</span>
            <select id="checkout-product">
                <option value="" data-i18n>Choose a product</option>
                <?php foreach ($products as $product): ?>
                    <?php $productCode = ($product['upc'] ?? '') !== '' ? $product['upc'] : ($product['epc'] ?? ''); ?>
                    <?php if ($productCode === '') continue; ?>
                    <option value="<?= $e($productCode) ?>"><?= $e($product['name'] . ' · ' . $productCode) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    <?php elseif ($catalogUnavailable): ?>
        <p class="hint checkout-picker" data-i18n>The product list is unavailable. Try scanning or entering a code.</p>
    <?php endif; ?>
    <label for="code"><span data-i18n>UPC or EPC</span></label>
    <input id="code" value="<?= $e($scanCode) ?>" autocomplete="off" required autofocus maxlength="24">
    <button class="button"><span data-i18n>Add item</span></button>
</form>
<div class="reader-controls">
    <button type="button" class="button" id="reader"><span data-i18n>Start RFID reader</span></button>
    <p id="reader-status" role="status" class="hint"><span data-i18n>Run the RFID bridge, then start listening before placing products on the reader.</span></p>
</div>
<section class="panel section-gap">
    <div class="table-scroll compact" tabindex="0" role="region" aria-label="Shopping basket" data-i18n-aria-label="Shopping basket">
        <table>
            <thead>
                <tr>
                    <th><span data-i18n>Product</span></th>
                    <th><span data-i18n>Quantity</span></th>
                    <th><span data-i18n>Unit price</span></th>
                    <th><span data-i18n>Action</span></th>
                </tr>
            </thead>
            <tbody id="basket">
            </tbody>
        </table>
    </div>
    <p class="empty" id="empty"><span data-i18n>Your basket is empty.</span></p>
</section>
<form id="payment" class="panel editor-body section-gap payment-panel" novalidate>
    <input type="hidden" name="csrf_token" value="<?= $e($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="key" value="<?= $e($_SESSION['checkout_key']) ?>">
    <?php if (!isset($_SESSION['customer_id'])): ?>
        <p><a href="/login"><span data-i18n>Sign in</span></a> <span data-i18n>to earn points, or continue as a guest.</span></p>
    <?php elseif ($membershipUnavailable): ?>
        <p class="hint" data-i18n>Your membership code could not be loaded. You can still scan or enter it.</p>
    <?php endif; ?>
    <h2 data-i18n>Contact and delivery</h2>
    <div class="fields checkout-details">
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
    <label for="membership-code"><span data-i18n>Membership card barcode (optional)</span></label>
    <input id="membership-code" name="membership_code" value="<?= $e($membershipCode) ?>" maxlength="64" autocomplete="off">
    <p class="hint"><span data-i18n>Earn 10 points per dollar and receive an emailed receipt with a membership code. Clear the field to continue as a guest.</span></p>
    <p class="hint"><span data-i18n>Classroom payment simulation. Prices are final in CAD. No card details required.</span></p>
    <?php if ($membershipCode !== ''): ?>
        <div class="checkout-details" id="points-redemption" data-balance="<?= (int) ($profile['points'] ?? 0) ?>">
            <label for="redeem-points" data-i18n>Use points</label>
            <input type="text" inputmode="numeric" id="redeem-points" name="redeem_points" value="0"
                aria-describedby="points-help points-available">
            <p id="points-help" class="hint" data-i18n>100 points = $1 off. Earn points on the amount you pay.</p>
            <p id="points-available" class="hint" data-i18n><?= (int) ($profile['points'] ?? 0) ?> points available</p>
        </div>
    <?php endif; ?>
    <p id="points-discount" class="hint" hidden></p>
    <h2 id="total" data-i18n>Total $0.00</h2>
    <button class="button primary" id="pay" disabled><span data-i18n>Confirm payment</span></button>
</form>
<script src="/assets/js/checkout.js" defer></script>
<?php require __DIR__ . '/products/_end.php'; ?>
