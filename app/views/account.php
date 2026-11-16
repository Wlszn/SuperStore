<?php
$title = 'My account';
$section = 'account';
require __DIR__ . '/products/_start.php';
?>

<header class="page-heading">
    <div>
        <h1><?= $e($customer['name'] ?? 'My account') ?></h1>
        <p><?= $e($customer['email'] ?? '') ?></p>
    </div>
    <?php if (!empty($_SESSION['is_admin'])): ?>
        <a class="button" href="/products"><span data-i18n>Products</span></a>
    <?php endif; ?>
    <a class="button primary" href="/checkout"><span data-i18n>Start shopping</span></a>
</header>
<dl class="stats">
    <div>
        <dt><span data-i18n>Membership</span></dt>
        <dd class="code"><?= $e($customer['membership_number'] ?? '') ?></dd>
    </div>
    <div>
        <dt><span data-i18n>Points</span></dt>
        <dd><?= $e($customer['points'] ?? 0) ?></dd>
    </div>
    <div>
        <dt><span data-i18n>Purchases</span></dt>
        <dd><?= count($purchases) ?></dd>
    </div>
</dl>
<section class="panel">
    <div class="section-heading">
        <h2><span data-i18n>Purchase history</span></h2>
    </div>
    <div class="table-scroll compact" tabindex="0" role="region" aria-label="Purchase history" data-i18n-aria-label="Purchase history">
        <table>
            <thead>
                <tr>
                    <th><span data-i18n>Date</span></th>
                    <th><span data-i18n>Total</span></th>
                    <th><span data-i18n>Points earned</span></th>
                    <th><span data-i18n>Receipt</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($purchases as $purchase): ?>
                    <tr>
                        <td><?= $e((new DateTimeImmutable($purchase['purchased_at']))->format('M j, Y H:i T')) ?></td>
                        <td><?= $money($purchase['total']) ?></td>
                        <td><?= $e($purchase['points_earned']) ?></td>
                        <td>
                        <a href="/receipt?id=<?= (int) $purchase['id'] ?>"><span data-i18n>View receipt</span></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (!$purchases): ?>
        <p class="empty"><span data-i18n>Your purchases will appear here.</span></p>
    <?php endif; ?>
</section>
<form class="panel editor editor-body" style="margin-top:20px" action="/account/membership" method="post" data-action-form data-next="/account">
    <input type="hidden" name="csrf_token" value="<?= $e($_SESSION['csrf_token']) ?>">
    <label for="member-barcode"><span data-i18n>Membership barcode</span></label>
    <p class="hint"><span data-i18n>Scan a card into this field to link it to your account. Your previous membership code will be replaced.</span></p>
    <input id="member-barcode" name="membership_number" value="<?= $e($customer['membership_number'] ?? '') ?>" maxlength="64" required autocomplete="off">
    <button type="submit" class="button primary" style="margin-top:12px"><span data-i18n>Save membership barcode</span></button>
</form>
<?php require __DIR__ . '/products/_end.php'; ?>
