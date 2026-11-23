<section id="tracking" class="panel editor-body receipt-tracking section-gap" data-order-reference="<?= $e($reference) ?>" <?= $reference === '' ? 'hidden' : '' ?> aria-labelledby="tracking-heading">
    <h2 id="tracking-heading" data-i18n>Order tracking</h2>
    <p class="hint"><span data-i18n>Estimated fulfillment</span>: <time id="tracking-estimate"><?= $e($estimate ?? '') ?></time></p>
    <ol class="tracking-steps">
        <li aria-current="step"><span data-i18n>Order placed</span></li>
        <li><span data-i18n>Preparing order</span></li>
        <li><span id="tracking-dispatch" data-i18n>Out for delivery</span></li>
        <li><span id="tracking-complete" data-i18n>Delivered</span></li>
    </ol>
    <p class="hint" id="tracking-status" role="status"></p>
    <div class="heading-actions">
        <button type="button" class="button primary" id="advance-tracking" data-i18n>Update status</button>
        <button type="button" class="button" id="reset-tracking" data-i18n>Reset status</button>
    </div>
</section>
