<?php
/** One product. From ProductController::show: $product, $receptions, $csrfToken. */
$pageTitle = $product['name'];
$active = 'products';
$received = array_sum(array_map(fn($r) => $r['quantity'], $receptions));

require APP_ROOT . '/app/views/partials/header.php';
?>
<main class="page">
    <a class="back-link" href="/products">← Inventory</a>
    <div class="page-title">
        <div class="product-heading">
            <?php if ($product['image_url']): ?>
                <img src="<?= htmlspecialchars($product['image_url'], ENT_QUOTES) ?>" alt="" class="product-photo">
            <?php endif; ?>
            <div>
                <p class="eyebrow"><?= htmlspecialchars(strtoupper($product['category'])) ?></p>
                <h1><?= htmlspecialchars($product['name']) ?></h1>
                <p><?= htmlspecialchars($product['producer']) ?> · $<?= number_format($product['price'], 2) ?></p>
            </div>
        </div>
        <div class="title-actions">
            <a class="button secondary" href="/products/edit?id=<?= $product['id'] ?>">Edit</a>
            <a class="button" href="/products/receive?id=<?= $product['id'] ?>">Receive stock</a>
        </div>
    </div>

    <section class="stats">
        <div class="stat <?= $product['in_stock'] <= 0 ? 'danger' : ($product['low_stock'] ? 'warning' : '') ?>">
            <span>In stock</span><strong><?= $product['in_stock'] ?></strong>
        </div>
        <div class="stat"><span>Received in total</span><strong><?= $received ?></strong></div>
        <div class="stat"><span>Sold</span><strong><?= $received - $product['in_stock'] ?></strong></div>
    </section>

    <div class="two-columns">
        <section class="card">
            <h2>Codes</h2>
            <dl class="details">
                <dt>UPC (barcode)</dt><dd class="code"><?= htmlspecialchars($product['upc'] ?? '—') ?></dd>
                <dt>EPC (RFID tag)</dt><dd class="code"><?= htmlspecialchars($product['epc'] ?? '—') ?></dd>
                <dt>Product ID</dt><dd><?= $product['id'] ?></dd>
            </dl>
        </section>

        <section class="card">
            <h2>Reception history</h2>
            <?php if ($receptions === []): ?>
                <p class="muted">Never received yet. <a href="/products/receive?id=<?= $product['id'] ?>">Receive the first delivery</a>.</p>
            <?php else: ?>
                <table class="data-table compact">
                    <thead><tr><th>Date received</th><th class="num">Quantity</th></tr></thead>
                    <tbody>
                    <?php foreach ($receptions as $reception): ?>
                        <tr><td><?= htmlspecialchars($reception['received_at']) ?></td><td class="num">+<?= $reception['quantity'] ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </div>
</main>
<?php require APP_ROOT . '/app/views/partials/footer.php'; ?>
</body>
</html>
