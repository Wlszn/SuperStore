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
    <?php
    // "Nov 20, 2026" for one day, "Oct 27 – Nov 25, 2026" for a range.
    $period = '';
    if ($historyError === null) {
        $period = $from == $to
            ? $from->format('M j, Y')
            : $from->format($from->format('Y') === $to->format('Y') ? 'M j' : 'M j, Y') . ' – ' . $to->format('M j, Y');
    }
    $local = new DateTimeZone(date_default_timezone_get());
    ?>
    <form class="panel toolbar report-filters" method="get" action="/account">
        <input type="hidden" name="tab" value="orders">
        <label class="filter"><input type="radio" name="mode" value="day" <?= $filters['mode'] === 'day' ? 'checked' : '' ?>> <span data-i18n>Single day</span></label>
        <label class="filter"><input type="radio" name="mode" value="range" <?= $filters['mode'] === 'range' ? 'checked' : '' ?>> <span data-i18n>Date range</span></label>
        <label data-mode="day"><span data-i18n>Day</span> <input type="date" name="day" value="<?= $e($filters['day']) ?>"></label>
        <label data-mode="range"><span data-i18n>From</span> <input type="date" name="from" value="<?= $e($filters['from']) ?>"></label>
        <label data-mode="range"><span data-i18n>To</span> <input type="date" name="to" value="<?= $e($filters['to']) ?>"></label>
        <button type="submit" class="button primary"><span data-i18n>Show receipts</span></button>
    </form>

    <section class="section-gap" aria-labelledby="history-heading">
        <div class="section-heading">
            <h2 id="history-heading"><span data-i18n>Receipt history</span></h2>
            <?php if ($historyError === null): ?>
                <p class="hint"><span data-i18n><?= $e(count($receipts) === 1 ? '1 receipt' : count($receipts) . ' receipts') ?></span> · <?= $e($period) ?></p>
            <?php endif; ?>
        </div>

        <?php if ($historyError !== null): ?>
            <p class="empty field-error" role="alert"><span data-i18n><?= $e($historyError) ?></span></p>
        <?php elseif ($receipts === []): ?>
            <p class="panel empty"><span data-i18n>No receipts in this period.</span> <span data-i18n>Try a wider date range.</span></p>
        <?php endif; ?>

        <?php foreach ($receipts as $receipt): ?>
            <?php $reference = 'SS-' . str_pad((string) $receipt['id'], 6, '0', STR_PAD_LEFT); ?>
            <article class="panel receipt-card" aria-labelledby="receipt-<?= $receipt['id'] ?>">
                <header class="receipt-card-head">
                    <div>
                        <h3 id="receipt-<?= $receipt['id'] ?>"><?= $e((new DateTimeImmutable($receipt['purchased_at']))->setTimezone($local)->format('M j, Y · H:i')) ?></h3>
                        <p class="hint"><span data-i18n>Order reference</span> <span class="code"><?= $e($reference) ?></span>
                            <?php if (ORDER_TRACKING): ?> · <span class="order-status" data-order-status="<?= $e($reference) ?>" data-i18n>In progress</span><?php endif; ?></p>
                    </div>
                    <div class="heading-actions">
                        <a class="button order-link receipt-link" href="/receipt?id=<?= $receipt['id'] ?>" data-i18n>View receipt</a>
                        <?php if (ORDER_TRACKING): ?>
                            <a class="button order-link tracking-link" href="/receipt?id=<?= $receipt['id'] ?>#tracking" data-i18n>Track order</a>
                        <?php endif; ?>
                    </div>
                </header>
                <div class="table-scroll compact">
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
                            <?php foreach ($receipt['items'] as $line): ?>
                                <tr>
                                    <td><?= $e($line['name']) ?></td>
                                    <td class="numeric"><?= $line['quantity'] ?></td>
                                    <td class="numeric"><?= $money($line['unit_price']) ?></td>
                                    <td class="numeric"><?= $money($line['amount']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <dl class="receipt-card-totals">
                    <?php if ($receipt['points_redeemed'] > 0): ?>
                        <div><dt data-i18n>Subtotal</dt><dd><?= $money($receipt['subtotal']) ?></dd></div>
                        <div><dt data-i18n>Points discount</dt><dd>−<?= $money($receipt['subtotal'] - $receipt['total']) ?></dd></div>
                    <?php endif; ?>
                    <div class="total"><dt data-i18n>Total</dt><dd><?= $money($receipt['total']) ?></dd></div>
                    <?php if ($receipt['points_earned'] > 0): ?>
                        <div><dt data-i18n>Points earned</dt><dd><?= $receipt['points_earned'] ?></dd></div>
                    <?php endif; ?>
                </dl>
            </article>
        <?php endforeach; ?>
    </section>
    <script src="/assets/js/sales-report.js" defer></script>
    <?php if (ORDER_TRACKING): ?>
        <section id="sample-orders" class="panel editor-body section-gap" hidden>
            <h2 data-i18n>Online orders</h2>
            <ul id="sample-order-list" class="order-list"></ul>
        </section>
        <script src="/assets/js/tracking.js" defer></script>
    <?php endif; ?>

<?php endif; ?>
<?php require __DIR__ . '/products/_end.php'; ?>
