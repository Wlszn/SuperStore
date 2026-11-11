<?php
/** One receipt, printable. From AccountController::receipt: $receipt. */
$pageTitle = t('receipt.title', ['id' => $receipt['id']]);
$active = 'account';
$customer = $receipt['customer'];

require APP_ROOT . '/app/views/partials/header.php';
?>
<main class="page narrow">
    <a class="back-link no-print" href="<?= isset($_SESSION['customer_id']) ? '/account' : '/checkout' ?>">← <?= t('receipt.back') ?></a>

    <article class="card receipt-paper">
        <header class="receipt-header">
            <span class="logo">Super<span>Store</span></span>
            <div>
                <strong><?= t('receipt.title', ['id' => $receipt['id']]) ?></strong>
                <span class="muted"><?= htmlspecialchars($receipt['date']) ?></span>
            </div>
        </header>

        <table class="data-table compact receipt-table">
            <thead>
            <tr><th><?= t('receipt.item') ?></th><th class="num"><?= t('receipt.qty') ?></th><th class="num"><?= t('receipt.price') ?></th><th class="num"><?= t('receipt.amount') ?></th></tr>
            </thead>
            <tbody>
            <?php foreach ($receipt['items'] as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['name']) ?></td>
                    <td class="num"><?= $item['quantity'] ?></td>
                    <td class="num">$<?= number_format($item['unit_price'], 2) ?></td>
                    <td class="num">$<?= number_format($item['line_total'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
            <tr><th colspan="3"><?= t('receipt.total') ?></th><th class="num">$<?= number_format($receipt['total'], 2) ?></th></tr>
            </tfoot>
        </table>

        <dl class="details">
            <dt><?= t('receipt.payment') ?></dt>
            <dd><?= $receipt['payment_method'] === 'card' ? t('receipt.card') : t('receipt.cash') ?></dd>
            <dt><?= t('receipt.points_earned') ?></dt>
            <dd><?= $receipt['points_earned'] ?></dd>
            <?php if ($customer !== null): ?>
                <dt><?= t('receipt.member') ?></dt>
                <dd><?= htmlspecialchars($customer['name']) ?> · <?= htmlspecialchars($customer['membership_number']) ?></dd>
            <?php else: ?>
                <dt><?= t('receipt.member') ?></dt>
                <dd><?= t('receipt.guest') ?></dd>
            <?php endif; ?>
        </dl>
        <p class="center muted"><?= t('receipt.thanks') ?></p>
    </article>

    <div class="receipt-actions no-print">
        <button type="button" onclick="window.print()"><?= t('receipt.print') ?></button>
    </div>
</main>
<?php require APP_ROOT . '/app/views/partials/footer.php'; ?>
</body>
</html>
