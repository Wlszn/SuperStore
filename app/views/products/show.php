<?php
$title = $product['name'];
$section = 'products';
require __DIR__ . '/_start.php';
$id = $product['id'];
$upc = $product['upc'] ?? '';
$barcode = App\Services\Barcode::code128($upc);
?>
<header class="page-heading">
    <div><h1><?= $e($product['name']) ?></h1><p><?= $e($product['producer']) ?> · <?= $e($product['category']) ?></p></div>
    <div class="heading-actions">
        <a class="button" href="/products/edit?id=<?= $id ?>"><span data-i18n>Edit product</span></a>
        <a class="button primary" href="/products/receive?id=<?= $id ?>"><span data-i18n>Receive stock</span></a>
    </div>
</header>

<dl class="stats details">
    <div><dt><span data-i18n>In stock</span></dt><dd><?= number_format($product['in_stock']) ?></dd></div>
    <div><dt><span data-i18n>Last restocked</span></dt><dd><?= empty($product['last_restocked']) ? '—' : $e(date('M j, Y', strtotime($product['last_restocked']))) ?></dd></div>
    <div><dt><span data-i18n>Unit price</span></dt><dd><?= $money($product['price']) ?></dd></div>
    <div><dt><span data-i18n>Barcode (UPC)</span></dt><dd class="code"><?= $e($product['upc'] ?? '—') ?></dd></div>
    <div><dt><span data-i18n>Category</span></dt><dd class="code"><span data-i18n><?= $e($product['category']) ?></span></dd></div>
</dl>

<?php if ($upc !== ''): ?>
    <section class="panel editor-body barcode-panel" aria-labelledby="barcode-heading">
        <div class="page-heading">
            <div>
                <h2 id="barcode-heading" data-i18n>Product barcode</h2>
                <?php if ($barcode): ?>
                    <p data-i18n>Scan this barcode or print a label.</p>
                <?php endif; ?>
            </div>
            <?php if ($barcode): ?>
                <button class="button" type="button" onclick="window.print()" data-i18n>Print barcode</button>
            <?php endif; ?>
        </div>
        <?php if ($barcode): ?>
            <figure class="barcode-label">
                <strong><?= $e($product['name']) ?></strong>
                <svg viewBox="0 0 <?= $barcode['width'] ?> 52" width="<?= $barcode['width'] * 2 ?>" role="img"
                    aria-label="<?= $e('Barcode: ' . $upc) ?>" shape-rendering="crispEdges">
                    <rect width="100%" height="100%" fill="white"/>
                    <?php foreach ($barcode['bars'] as [$x, $width]): ?>
                        <rect x="<?= $x ?>" width="<?= $width ?>" height="52" fill="black"/>
                    <?php endforeach; ?>
                </svg>
                <figcaption class="code"><?= $e($upc) ?></figcaption>
            </figure>
        <?php else: ?>
            <p data-i18n>This code contains characters that cannot be printed as a Code 128 barcode.</p>
        <?php endif; ?>
    </section>
<?php endif; ?>

<section class="panel items-panel" aria-labelledby="items-heading">
    <div class="section-heading"><h2 id="items-heading"><span data-i18n>Items in stock</span></h2></div>
    <?php if ($items === []): ?>
        <p class="empty"><span data-i18n>No tagged items in stock. Receive stock to add some.</span></p>
    <?php else: ?>
        <div class="table-scroll compact" tabindex="0" role="region" aria-label="Items in stock" data-i18n-aria-label="Items in stock">
            <table>
                <thead><tr><th scope="col" data-i18n>RFID tag (EPC)</th><th scope="col" data-i18n>Delivery reference</th></tr></thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr><td class="code"><?= $e($item['epc']) ?></td><td class="code"><?= $item['reception_id'] === null ? '—' : 'DEL-' . (int) $item['reception_id'] ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel" aria-labelledby="history-heading">
    <div class="section-heading"><h2 id="history-heading"><span data-i18n>Reception history</span></h2></div>
    <?php if ($receptions === []): ?>
        <p class="empty"><span data-i18n>No deliveries recorded for this product.</span></p>
    <?php else: ?>
        <div class="table-scroll compact" tabindex="0" role="region" aria-label="Reception history" data-i18n-aria-label="Reception history">
            <table>
                <thead><tr><th scope="col" data-i18n>Delivery reference</th><th scope="col"><span data-i18n>Date received</span></th><th scope="col" class="numeric"><span data-i18n>Quantity received</span></th></tr></thead>
                <tbody>
                    <?php foreach ($receptions as $reception): ?>
                        <tr><td class="code">DEL-<?= (int) $reception['id'] ?></td><td><time datetime="<?= $e($reception['received_at']) ?>"><?= $e(date('M j, Y', strtotime($reception['received_at']))) ?></time></td><td class="numeric"><?= number_format($reception['quantity']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<div class="delete-area"><button class="button danger" type="button" data-open-delete><span data-i18n>Delete product</span></button></div>
<dialog id="delete-product" aria-labelledby="delete-title">
    <form method="post" action="/products/delete" data-action-form data-next="/products">
        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="editor-body">
            <h2 id="delete-title" data-i18n><span data-i18n>Delete</span> <?= $e($product['name']) ?>?</h2>
            <p class="hint"><span data-i18n>This permanently removes the product, its items in stock and its reception history.</span></p>
            <div class="notice" role="alert" tabindex="-1" hidden></div>
        </div>
        <div class="editor-footer">
            <button type="button" class="button" data-cancel-delete autofocus><span data-i18n>Cancel</span></button>
            <button type="submit" class="button danger solid"><span data-i18n>Delete product</span></button>
        </div>
    </form>
</dialog>
<?php require __DIR__ . '/_end.php'; ?>
