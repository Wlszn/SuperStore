<?php
$title = 'Receive stock';
$section = 'receive';
require __DIR__ . '/_start.php';
?>
<header class="page-heading">
    <div><h1><span data-i18n>Receive stock</span></h1><p><span data-i18n>Record a delivery and update inventory.</span></p></div>
    <a class="button" id="receiving-checkout" href="/checkout"><span data-i18n>Open checkout</span></a>
</header>

<?php if ($products === []): ?>
    <div class="panel empty"><span data-i18n>Add a product before receiving stock.</span> <a href="/products/create"><span data-i18n>Add product</span></a></div>
<?php else: ?>
    <div class="panel editor">
        <form class="editor-body scan-line" data-barcode-form id="receive-scan">
            <label for="receive-upc"><span data-i18n>Find by barcode</span></label>
            <input id="receive-upc" type="text" inputmode="text" autocomplete="off" placeholder="Scan or enter a UPC" data-i18n-placeholder="Scan or enter a UPC" required>
            <button type="submit" class="button"><span data-i18n>Find product</span></button>
            <span class="scan-status" role="status"></span>
            <a class="scan-add" hidden><span data-i18n>Add this product</span></a>
        </form>
        <form method="post" action="/receptions" data-action-form data-next="/products" data-reception>
            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
            <div class="editor-body fields">
                <label class="wide"><span data-i18n>Product</span>
                    <select id="received-product" name="product_id" required>
                        <option value="" data-i18n>Choose a product</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?= $product['id'] ?>" data-upc="<?= $e($product['upc'] ?? '') ?>" data-epc="<?= $e($product['epc'] ?? '') ?>" <?= $product['id'] === $selectedId ? 'selected' : '' ?>><?= $e($product['name'] . (($product['upc'] ?? '') !== '' ? ' · ' . $product['upc'] : '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span data-error="product_id" class="field-error"></span>
                </label>
                <p id="latest-reception" class="hint wide" role="status" hidden></p>
                <label><span data-i18n>Quantity received</span>
                    <input name="quantity" type="number" required min="1" max="100000" step="1">
                    <span data-error="quantity" class="field-error"></span>
                </label>
                <label><span data-i18n>Date received</span>
                    <input name="received_at" type="date" required max="<?= $e($today) ?>" value="<?= $e($today) ?>">
                    <span data-error="received_at" class="field-error"></span>
                </label>
            </div>
            <div class="editor-footer">
                <button type="button" class="button" id="demo-delivery" data-i18n>Fill sample delivery</button>
                <a class="button" href="/products"><span data-i18n>Cancel</span></a>
                <button type="submit" class="button primary"><span data-i18n>Record delivery</span></button>
            </div>
        </form>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/_end.php'; ?>
