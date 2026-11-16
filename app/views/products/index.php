<?php
$title = 'Products';
$section = 'products';
require __DIR__ . '/_start.php';

$units = array_sum(array_column($products, 'in_stock'));
$restock = count(array_filter($products, static fn ($product) => $product['in_stock'] <= $lowStock));
?>
<header class="page-heading">
    <div><h1><span data-i18n>Products</span></h1><p><span data-i18n>Manage your catalog and track what’s in stock.</span></p></div>
    <a class="button primary" href="/products/create"><span data-i18n>＋ Add product</span></a>
</header>

<dl class="stats">
    <div><dt><span data-i18n>Products</span></dt><dd><?= number_format(count($products)) ?></dd></div>
    <div><dt><span data-i18n>Units in stock</span></dt><dd><?= number_format($units) ?></dd></div>
    <div><dt><span data-i18n>Need restocking</span></dt><dd><?= number_format($restock) ?></dd></div>
</dl>

<section class="panel" aria-label="Product catalog" data-i18n-aria-label="Product catalog">
    <div class="toolbar">
        <input id="search" class="search" type="search" placeholder="Search name, category or barcode" data-i18n-placeholder="Search name, category or barcode" aria-label="Search name, category or barcode" data-i18n-aria-label="Search name, category or barcode">
        <label class="filter"><input type="checkbox" id="stock-filter"> <span data-i18n>Needs restocking</span></label>
        <button class="button scan-toggle" type="button" aria-expanded="false" aria-controls="scan-product"><span data-i18n>Scan barcode</span></button>
    </div>
    <form id="scan-product" class="scan-line" data-barcode-form hidden>
        <label for="catalog-upc"><span data-i18n>Barcode</span></label>
        <input id="catalog-upc" type="text" inputmode="text" autocomplete="off" placeholder="Scan or enter a UPC" data-i18n-placeholder="Scan or enter a UPC" required>
        <button type="submit" class="button"><span data-i18n>Find product</span></button>
        <span class="scan-status" role="status"></span>
        <a class="scan-add" hidden><span data-i18n>Add this product</span></a>
    </form>
    <div class="table-scroll" tabindex="0" role="region" aria-label="Product inventory" data-i18n-aria-label="Product inventory">
        <table>
            <thead><tr><th scope="col"><span data-i18n>Product</span></th><th scope="col"><span data-i18n>Category</span></th><th scope="col"><span data-i18n>Barcode</span></th><th scope="col" class="numeric"><span data-i18n>Price</span></th><th scope="col" class="numeric"><span data-i18n>In stock</span></th><th scope="col"><span data-i18n>Status</span></th><th scope="col"><span data-i18n>Actions</span></th></tr></thead>
            <tbody>
            <?php foreach ($products as $product): ?>
                <?php $stock = $product['in_stock']; $id = $product['id']; ?>
                <tr data-product data-upc="<?= $e($product['upc'] ?? '') ?>" data-search="<?= $e(implode(' ', [$product['name'], $product['category'], $product['upc'], $product['producer']])) ?>" data-low="<?= $stock <= $lowStock ? 'true' : 'false' ?>">
                    <th scope="row">
                        <a class="product-name" href="/products/show?id=<?= $id ?>"><?= $e($product['name']) ?></a>
                        <span class="subtext"><?= $e($product['producer']) ?></span>
                    </th>
                    <td><?= $e($product['category']) ?></td>
                    <td class="code"><?= $e($product['upc'] ?? '—') ?></td>
                    <td class="numeric"><?= $money($product['price']) ?></td>
                    <td class="numeric"><?= number_format($stock) ?></td>
                    <td><span data-i18n class="stock <?= $stock <= 0 ? 'out' : ($stock <= $lowStock ? 'low' : 'available') ?>"><?= $stock <= 0 ? 'Out of stock' : ($stock <= $lowStock ? 'Low stock' : 'In stock') ?></span></td>
                    <td><div class="row-actions"><a href="/products/edit?id=<?= $id ?>"><span data-i18n>Edit</span></a><a href="/products/receive?id=<?= $id ?>"><span data-i18n>Receive</span></a><a href="/checkout?code=<?= $e(rawurlencode(($product['upc'] ?? '') !== '' ? $product['upc'] : ($product['epc'] ?? ''))) ?>"><span data-i18n>Open checkout</span></a></div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($products === []): ?>
        <p class="empty"><span data-i18n>No products yet.</span> <a href="/products/create"><span data-i18n>Add your first product</span></a>.</p>
    <?php else: ?>
        <p id="no-results" class="empty" hidden><span data-i18n>No products match your filters.</span></p>
    <?php endif; ?>
    <div class="table-footer" id="product-count" aria-live="polite" data-i18n><?= count($products) ?> <?= count($products) === 1 ? 'product' : 'products' ?></div>
</section>
<?php require __DIR__ . '/_end.php'; ?>
