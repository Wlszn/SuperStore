<?php
/** Customer account. From AccountController::index: $customer (CustomerModel), $receipts (array|null). */
use App\Services\Barcode;

$pageTitle = t('account.title');
$active = 'account';
$firstName = explode(' ', trim($customer->getName()))[0];
$memberNumber = (string) $customer->getMembershipNumber();

require APP_ROOT . '/app/views/partials/header.php';
?>
<main class="page">
    <div class="page-title">
        <div>
            <p class="eyebrow"><?= t('account.eyebrow') ?></p>
            <h1><?= t('account.hello', ['name' => htmlspecialchars($firstName)]) ?></h1>
            <p><?= htmlspecialchars($customer->getEmail()) ?></p>
        </div>
        <div class="title-actions">
            <a class="button" href="/checkout"><?= t('account.go_checkout') ?></a>
        </div>
    </div>

    <div class="account-grid">
        <section class="member-card" aria-label="<?= htmlspecialchars(t('account.card'), ENT_QUOTES) ?>">
            <div class="member-card-top">
                <span class="logo">Super<span>Store</span></span>
                <span><?= t('account.card') ?></span>
            </div>
            <strong class="member-name"><?= htmlspecialchars($customer->getName()) ?></strong>
            <?php if ($memberNumber !== ''): ?>
                <div class="barcode-box"><?= Barcode::code128Svg($memberNumber) ?></div>
                <span class="member-number"><?= htmlspecialchars($memberNumber) ?></span>
            <?php endif; ?>
            <p class="member-hint"><?= t('account.card_hint') ?></p>
        </section>

        <section class="card points-card">
            <span class="muted"><?= t('account.points') ?></span>
            <strong class="points-value"><?= number_format($customer->getPoints()) ?></strong>
            <p class="muted"><?= t('account.points_rule') ?></p>
        </section>
    </div>

    <section class="card">
        <h2><?= t('account.history') ?></h2>
        <?php if ($receipts === null): ?>
            <p class="form-message error"><?= t('account.history_error') ?></p>
        <?php elseif ($receipts === []): ?>
            <p class="muted"><?= t('account.history_empty') ?></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th><?= t('account.date') ?></th><th><?= t('account.receipt') ?></th>
                        <th class="num"><?= t('account.items') ?></th><th class="num"><?= t('account.total') ?></th>
                        <th class="num"><?= t('account.points_earned') ?></th><th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($receipts as $receipt): ?>
                        <tr>
                            <td><?= htmlspecialchars($receipt['date']) ?></td>
                            <td>#<?= $receipt['id'] ?></td>
                            <td class="num"><?= $receipt['item_count'] ?></td>
                            <td class="num">$<?= number_format($receipt['total'], 2) ?></td>
                            <td class="num">+<?= $receipt['points_earned'] ?></td>
                            <td class="actions-col"><a class="link-button" href="/receipts/show?id=<?= $receipt['id'] ?>"><?= t('account.view') ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php require APP_ROOT . '/app/views/partials/footer.php'; ?>
</body>
</html>
