<?php
$title = 'My account';
$section = 'account';
$tabs = ['profile' => 'Profile', 'membership' => 'Membership', 'orders' => 'Orders'];
$tab = $_GET['tab'] ?? 'profile';
if (!is_string($tab) || !isset($tabs[$tab])) {
    $tab = 'profile';
}
require __DIR__ . '/products/_start.php';
?>

<header class="page-heading">
    <div>
        <h1 data-i18n>My account</h1>
        <p><?= $e($customer['name'] ?? '') ?></p>
    </div>
    <div class="heading-actions">
        <?php if (!empty($_SESSION['is_admin'])): ?>
            <a class="button" href="/products" data-i18n>Products</a>
        <?php endif; ?>
        <a class="button primary" href="/checkout" data-i18n>Start shopping</a>
    </div>
</header>

<nav class="account-tabs" aria-label="Account sections" data-i18n-aria-label="Account sections">
    <?php foreach ($tabs as $key => $label): ?>
        <a href="/account?tab=<?= $e($key) ?>" <?= $tab === $key ? 'aria-current="page"' : '' ?> data-i18n><?= $e($label) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($tab === 'profile'): ?>
    <section class="panel editor editor-body" aria-labelledby="profile-heading">
        <h2 id="profile-heading" data-i18n>Profile</h2>
        <dl class="profile-fields">
            <div><dt data-i18n>Full name</dt><dd><?= $e($customer['name'] ?? '') ?></dd></div>
            <div><dt data-i18n>Email address</dt><dd><?= $e($customer['email'] ?? '') ?></dd></div>
            <div><dt data-i18n>Phone number</dt><dd><?= $e($customer['phone'] ?? '') ?></dd></div>
            <div><dt data-i18n>Address</dt><dd><?= $e($customer['address'] ?? '') ?></dd></div>
        </dl>
    </section>
<?php elseif ($tab === 'membership'): ?>
    <form class="panel editor editor-body" action="/account/membership" method="post" data-action-form data-next="/account?tab=membership">
        <input type="hidden" name="csrf_token" value="<?= $e($_SESSION['csrf_token']) ?>">
        <h2 data-i18n>Membership</h2>
        <p class="membership-balance"><strong><?= number_format((int) ($customer['points'] ?? 0)) ?></strong> <span data-i18n>Points</span></p>
        <?php $memberCode = (string) ($customer['membership_number'] ?? ''); ?>
        <?php $memberBarcode = $memberCode !== '' ? App\Services\Barcode::code128($memberCode) : null; ?>
        <?php if ($memberBarcode): ?>
            <p class="hint"><span data-i18n>Show this card at the self-checkout and scan it with the barcode scanner.</span></p>
            <figure class="barcode-label member-card">
                <svg viewBox="0 0 <?= $memberBarcode['width'] ?> 42" width="<?= $memberBarcode['width'] * 2 ?>" role="img"
                    aria-label="<?= $e('Barcode: ' . $memberCode) ?>" shape-rendering="crispEdges">
                    <rect width="100%" height="100%" fill="white"/>
                    <?php foreach ($memberBarcode['bars'] as [$x, $width]): ?>
                        <rect x="<?= $x ?>" width="<?= $width ?>" height="42" fill="black"/>
                    <?php endforeach; ?>
                </svg>
                <figcaption class="code"><?= $e($memberCode) ?></figcaption>
            </figure>
        <?php endif; ?>
        <label for="member-barcode"><span data-i18n>Membership barcode</span></label>
        <p class="hint"><span data-i18n>Scan a card into this field to link it to your account. Your previous membership code will be replaced.</span></p>
        <input id="member-barcode" name="membership_number" value="<?= $e($customer['membership_number'] ?? '') ?>" maxlength="64" required autocomplete="off">
        <button type="submit" class="button primary" style="margin-top:12px"><span data-i18n>Save membership barcode</span></button>
    </form>
<?php else: ?>
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
                        <th data-i18n>Status</th>
                        <th data-i18n>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($purchases as $purchase): ?>
                        <tr>
                            <td><?= $e((new DateTimeImmutable($purchase['purchased_at']))->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('M j, Y H:i')) ?></td>
                            <td><?= $money($purchase['total']) ?></td>
                            <td><?= $e($purchase['points_earned']) ?></td>
                            <td><span class="order-status" data-order-status="SS-<?= $e(str_pad((string) $purchase['id'], 6, '0', STR_PAD_LEFT)) ?>" data-i18n>In progress</span></td>
                            <td>
                                <div class="heading-actions">
                                    <a class="button order-link receipt-link" href="/receipt?id=<?= (int) $purchase['id'] ?>" data-i18n>View receipt</a>
                                    <a class="button order-link tracking-link" href="/receipt?id=<?= (int) $purchase['id'] ?>#tracking" data-i18n>Track order</a>
                                </div>
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
    <?php if (ORDER_TRACKING): ?>
        <section id="sample-orders" class="panel editor-body section-gap" hidden>
            <h2 data-i18n>Online orders</h2>
            <ul id="sample-order-list" class="order-list"></ul>
        </section>
        <script src="/assets/js/tracking.js" defer></script>
    <?php endif; ?>

<?php endif; ?>
<?php require __DIR__ . '/products/_end.php'; ?>
