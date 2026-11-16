<?php
$editing = $product !== null;
$title = $editing ? 'Edit product' : 'Add product';
$section = 'products';
$detail = $editing ? '/products/show?id=' . $product['id'] : '/products';
$prefill = $_GET['upc'] ?? '';
if (!is_string($prefill) || mb_strlen($prefill) > 13 || preg_match('/[\x00-\x1F\x7F]/', $prefill)) {
    $prefill = '';
}
require __DIR__ . '/_start.php';
?>
<header class="page-heading">
    <div><h1 data-i18n><?= $e($title) ?></h1><p><?php if ($editing): ?><?= $e($product['name']) ?><?php else: ?><span data-i18n>Register a product in the catalog.</span><?php endif; ?></p></div>
</header>

<form class="panel editor" method="post" action="<?= $editing ? '/products/update' : '/products' ?>" data-action-form data-next="<?= $e($detail) ?>" <?= $editing ? '' : 'data-new-product' ?>>
    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
    <?php if ($editing): ?><input type="hidden" name="id" value="<?= $product['id'] ?>"><?php endif; ?>

    <div class="editor-body">
        <h2><span data-i18n>Product details</span></h2>
        <div class="fields">
            <label><span data-i18n>Product name</span>
                <input name="name" required maxlength="100" value="<?= $e($product['name'] ?? '') ?>">
                <span data-error="name" class="field-error"></span>
            </label>
            <label><span data-i18n>Category</span>
                <input name="category" required maxlength="50" value="<?= $e($product['category'] ?? '') ?>">
                <span data-error="category" class="field-error"></span>
            </label>
            <label><span data-i18n>Producer</span>
                <input name="producer" required maxlength="100" value="<?= $e($product['producer'] ?? '') ?>">
                <span data-error="producer" class="field-error"></span>
            </label>
            <label><span data-i18n>Unit price (CAD)</span>
                <input name="price" required type="text" inputmode="decimal" placeholder="0.00" value="<?= $editing ? number_format($product['price'], 2, '.', '') : '' ?>">
                <span data-error="price" class="field-error"></span>
            </label>
            <label class="wide"><span data-i18n>Image URL</span> <span class="optional"><span data-i18n>Optional</span></span>
                <input name="image_url" type="url" maxlength="500" value="<?= $e($product['image_url'] ?? '') ?>">
                <span data-error="image_url" class="field-error"></span>
            </label>
        </div>
    </div>
    <div class="editor-body">
        <h2><span data-i18n>Scanning codes</span></h2>
        <p class="hint">Enter a UPC (8–13 digits) or an EPC. Focus the UPC field before scanning.</p>
        <div class="fields">
            <label><span data-i18n>Barcode (UPC)</span>
                <input id="product-upc" name="upc" class="code" inputmode="text" autocomplete="off" value="<?= $e($editing ? ($product['upc'] ?? '') : $prefill) ?>" data-upc-entry>
                <span data-error="upc" class="field-error"></span>
            </label>
            <label><span data-i18n>RFID tag (EPC)</span>
                <input name="epc" class="code" autocomplete="off" maxlength="24" value="<?= $e($product['epc'] ?? '') ?>">
                <span data-error="epc" class="field-error"></span>
                <button class="button" type="button" id="capture-epc"><span data-i18n>Read EPC from reader</span></button><span id="epc-status" role="status" class="hint"></span>
            </label>
        </div>
    </div>
    <div class="editor-footer">
        <a class="button" href="<?= $e($detail) ?>"><span data-i18n>Cancel</span></a>
        <button type="submit" class="button primary" data-i18n><?= $editing ? 'Save changes' : 'Add product' ?></button>
    </div>
</form>
<?php require __DIR__ . '/_end.php'; ?>
