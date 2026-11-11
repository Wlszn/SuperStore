<?php
/** Self-checkout kiosk. From CheckoutController::index: $csrfToken, $strings, $signedInMember. */
$pageTitle = t('checkout.title');
$active = 'checkout';
$bodyClass = 'kiosk';

require APP_ROOT . '/app/views/partials/header.php';
?>
<main class="kiosk-main" id="scanScreen">
    <section class="kiosk-cart">
        <div class="scan-bar">
            <div class="scan-input">
                <label for="scanBox"><?= t('checkout.scan_label') ?></label>
                <input type="text" id="scanBox" autocomplete="off" autofocus placeholder="<?= htmlspecialchars(t('checkout.scan_placeholder'), ENT_QUOTES) ?>">
            </div>
            <div class="reader-status" id="readerStatus" title="<?= htmlspecialchars(t('checkout.reader_title'), ENT_QUOTES) ?>">
                <span class="dot"></span><span id="readerText"><?= t('checkout.reader_connecting') ?></span>
            </div>
        </div>

        <div class="cart-head">
            <h1><?= t('checkout.your_items') ?></h1>
            <span id="itemCount" class="muted"></span>
        </div>

        <div id="emptyCart" class="empty-cart">
            <div class="empty-icon" aria-hidden="true">⌁</div>
            <h2><?= t('checkout.empty_title') ?></h2>
            <p><?= t('checkout.empty_text') ?></p>
        </div>

        <ul class="cart-list" id="cartList" aria-live="polite"></ul>
    </section>

    <aside class="kiosk-summary">
        <div class="member-box" id="memberBox"></div>

        <dl class="totals">
            <dt><?= t('checkout.items') ?></dt><dd id="sumItems">0</dd>
            <dt><?= t('checkout.points_to_earn') ?></dt><dd id="sumPoints">0</dd>
            <dt class="grand"><?= t('checkout.total') ?></dt><dd class="grand" id="sumTotal">$0.00</dd>
        </dl>

        <button type="button" class="pay-button" id="payButton" disabled><?= t('checkout.checkout_button') ?></button>
        <button type="button" class="secondary full" id="clearButton" disabled><?= t('checkout.cancel_order') ?></button>
        <p class="points-note"><?= t('checkout.points_rule') ?></p>
    </aside>
</main>

<main class="receipt-screen" id="receiptScreen" hidden>
    <div class="receipt-card" id="receiptCard"></div>
    <div class="receipt-actions">
        <a class="button secondary" id="printReceipt" target="_blank" href="#"><?= t('checkout.print') ?></a>
        <button type="button" id="nextCustomer"><?= t('checkout.next_customer') ?></button>
    </div>
    <p class="muted center" id="resetCountdown"></p>
</main>

<div class="modal-backdrop" id="flowModal" hidden>
    <div class="modal wide" role="dialog" aria-modal="true" aria-labelledby="flowTitle">
        <ol class="steps" aria-hidden="true">
            <li data-step="member"><?= t('checkout.step_member') ?></li>
            <li data-step="confirm"><?= t('checkout.step_confirm') ?></li>
            <li data-step="payment"><?= t('checkout.step_payment') ?></li>
        </ol>

        <section data-panel="member">
            <h2 id="flowTitle"><?= t('checkout.member_question') ?></h2>
            <p><?= t('checkout.member_help') ?></p>
            <div class="input-with-button">
                <input type="text" id="memberInput" placeholder="SS100001" autocomplete="off">
                <button type="button" id="memberApply"><?= t('checkout.apply') ?></button>
            </div>
            <p class="field-error" id="memberError"></p>
            <div class="modal-actions">
                <button type="button" class="secondary" data-close><?= t('checkout.back_to_items') ?></button>
                <button type="button" class="secondary" id="noCard"><?= t('checkout.no_card') ?></button>
            </div>
        </section>

        <section data-panel="confirm">
            <h2><?= t('checkout.confirm_title') ?></h2>
            <table class="data-table compact" id="confirmTable"></table>
            <p id="confirmMember" class="muted"></p>
            <div class="modal-actions">
                <button type="button" class="secondary" data-close><?= t('checkout.edit_list') ?></button>
                <button type="button" id="confirmList"><?= t('checkout.confirm_button') ?></button>
            </div>
        </section>

        <section data-panel="payment">
            <h2><?= t('checkout.payment_title') ?></h2>
            <p class="pay-amount" id="payAmount"></p>
            <div class="pay-methods" id="payMethods">
                <button type="button" class="pay-method" data-method="card"><span aria-hidden="true">▭</span><?= t('checkout.card') ?></button>
                <button type="button" class="pay-method" data-method="cash"><span aria-hidden="true">$</span><?= t('checkout.cash') ?></button>
            </div>
            <div id="cashPanel" hidden>
                <label for="cashGiven"><?= t('checkout.cash_given') ?></label>
                <div class="input-with-button">
                    <input type="text" id="cashGiven" inputmode="decimal" placeholder="20.00">
                    <button type="button" id="cashPay"><?= t('checkout.pay') ?></button>
                </div>
                <p class="field-error" id="cashError"></p>
            </div>
            <div class="processing" id="processing" hidden>
                <div class="spinner" aria-hidden="true"></div>
                <p id="processingText"></p>
            </div>
            <div class="modal-actions" id="paymentBack">
                <button type="button" class="secondary" data-back="confirm"><?= t('checkout.back') ?></button>
            </div>
        </section>
    </div>
</div>

<?php require APP_ROOT . '/app/views/partials/footer.php'; ?>
<script>
    window.CHECKOUT = {
        text: <?= json_encode($strings, JSON_UNESCAPED_UNICODE) ?>,
        csrfToken: <?= json_encode($csrfToken) ?>,
        signedInMember: <?= json_encode($signedInMember) ?>,
    };
</script>
<script src="/assets/js/checkout.js"></script>
</body>
</html>
