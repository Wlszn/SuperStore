<?php
$title = 'Receipt';
$section = 'checkout';
require __DIR__ . '/products/_start.php';

$receiptId = (int) $receipt['id'];
$reference = 'SS-' . str_pad((string) $receiptId, 6, '0', STR_PAD_LEFT);
$barcode = App\Services\Barcode::code128($reference);
$purchasedAt = (new DateTimeImmutable($receipt['purchased_at']))->setTimezone(new DateTimeZone(date_default_timezone_get()));
?>

<header class="page-heading receipt-actions">
    <h1 data-i18n>Checkout completed</h1>
    <button class="button" type="button" onclick="window.print()" data-i18n>Print receipt</button>
</header>

<article class="panel sales-receipt" aria-labelledby="receipt-heading">
    <header class="receipt-header">
        <div>
            <strong class="receipt-brand">SuperStore</strong>
            <h2 id="receipt-heading" data-i18n>Purchase receipt</h2>
        </div>
        <dl class="receipt-meta">
            <div><dt data-i18n>Order reference</dt><dd class="code"><?= $e($reference) ?></dd></div>
            <div><dt data-i18n>Date</dt><dd><time datetime="<?= $e($purchasedAt->format(DATE_ATOM)) ?>"><?= $e($purchasedAt->format('Y-m-d H:i T')) ?></time></dd></div>
        </dl>
    </header>

    <div class="table-scroll compact" tabindex="0" role="region" aria-label="Receipt items" data-i18n-aria-label="Receipt items">
        <table>
            <thead>
                <tr>
                    <th scope="col" data-i18n>Product</th>
                    <th scope="col" class="numeric" data-i18n>Quantity</th>
                    <th scope="col" class="numeric" data-i18n>Unit price</th>
                    <th scope="col" class="numeric" data-i18n>Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($receipt['purchase_items'] as $item): ?>
                    <tr>
                        <th scope="row"><?= $e($item['name']) ?></th>
                        <td class="numeric"><?= (int) $item['quantity'] ?></td>
                        <td class="numeric"><?= $money($item['unit_price']) ?></td>
                        <td class="numeric"><?= $money($item['unit_price'] * $item['quantity']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <dl class="receipt-totals">
        <div><dt data-i18n>Total paid</dt><dd><?= $money($receipt['total']) ?> CAD</dd></div>
        <div><dt data-i18n>Points earned</dt><dd><?= (int) $receipt['points_earned'] ?></dd></div>
    </dl>

    <footer class="receipt-footer">
        <p data-i18n>Simulated payment approved.</p>
        <figure class="barcode-label">
            <svg viewBox="0 0 <?= $barcode['width'] ?> 42" width="<?= $barcode['width'] * 2 ?>" role="img"
                aria-label="<?= $e('Barcode: ' . $reference) ?>" shape-rendering="crispEdges">
                <rect width="100%" height="100%" fill="white"/>
                <?php foreach ($barcode['bars'] as [$x, $width]): ?>
                    <rect x="<?= $x ?>" width="<?= $width ?>" height="42" fill="black"/>
                <?php endforeach; ?>
            </svg>
            <figcaption class="code"><?= $e($reference) ?></figcaption>
        </figure>
    </footer>
</article>

<?php
$estimate = $purchasedAt->modify('+3 days')->format('Y-m-d');
require __DIR__ . '/products/_tracking.php';
?>
<script src="/assets/js/tracking.js" defer></script>


<div class="heading-actions section-gap receipt-actions">
    <a class="button primary" href="/checkout" data-i18n>New checkout</a>
    <a class="button" href="/account" data-i18n>My account</a>
</div>
<?php require __DIR__ . '/products/_end.php'; ?>

