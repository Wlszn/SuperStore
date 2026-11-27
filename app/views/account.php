<?php
$title = 'My account';
$section = 'account';
$tabs = ['profile' => 'Profile', 'membership' => 'Membership', 'orders' => 'Orders', 'prices' => 'Price history'];
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
<?php elseif ($tab === 'prices'): ?>
    <?php
    // P4-19: how many times an item was bought, at what price, and how the price changed.
    $local = new DateTimeZone(date_default_timezone_get());
    $signed = static fn (float $value): string => ($value > 0 ? '+' : ($value < 0 ? '−' : '')) . '$' . number_format(abs($value), 2);
    $percent = static fn (?float $value): string => $value === null ? '' : ($value > 0 ? '+' : ($value < 0 ? '−' : '')) . number_format(abs($value), 1) . '%';
    ?>
    <?php if ($itemNames === []): ?>
        <p class="panel empty"><span data-i18n>Your items will appear here after your first purchase.</span> <a href="/checkout" data-i18n>Start shopping</a></p>
    <?php else: ?>
        <!-- Two ways to find an item: pick it from the list, or type part of its name. -->
        <div class="panel toolbar report-filters price-finder">
            <form method="get" action="/account">
                <input type="hidden" name="tab" value="prices">
                <label><span data-i18n>Item</span>
                    <!-- Picking an item shows its history right away; the button is for keyboards without JavaScript. -->
                    <select id="item-choice" name="item" onchange="this.form.submit()">
                        <option value="" data-i18n>Choose an item you bought</option>
                        <?php foreach ($itemNames as $name): ?>
                            <option value="<?= $e($name) ?>" <?= $itemChoice === $name ? 'selected' : '' ?>><?= $e($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="button" type="submit"><span data-i18n>Show</span></button>
            </form>
            <span class="price-finder-or" data-i18n>or</span>
            <form method="get" action="/account" role="search">
                <input type="hidden" name="tab" value="prices">
                <label for="item-search"><span data-i18n>Search</span></label>
                <!-- As the customer types, item-search.js shows every item whose name contains the text; clicking one opens its history. -->
                <div class="item-search-box">
                    <input id="item-search" name="q" type="search" value="<?= $e($itemSearch) ?>" maxlength="50" autocomplete="off"
                        role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="item-suggestions"
                        placeholder="For example: milk" data-i18n-placeholder="For example: milk">
                    <ul id="item-suggestions" class="item-suggestions" role="listbox" hidden>
                        <?php foreach ($itemNames as $i => $name): ?>
                            <li id="item-suggestion-<?= $i ?>" role="option" aria-selected="false">
                                <a href="/account?<?= $e(http_build_query(['tab' => 'prices', 'item' => $name])) ?>" tabindex="-1"><?= $e($name) ?></a>
                            </li>
                        <?php endforeach; ?>
                        <li class="item-suggestions-none" hidden><span data-i18n>No item you bought matches this.</span></li>
                    </ul>
                </div>
                <button class="button primary" type="submit"><span data-i18n>Search</span></button>
            </form>
        </div>
        <script src="/assets/js/item-search.js" defer></script>
    <?php endif; ?>

    <?php if ($itemError !== null): ?>
        <p class="empty field-error" role="alert"><span data-i18n><?= $e($itemError) ?></span></p>
    <?php elseif ($itemNames !== [] && $itemSearch === '' && $itemChoice === ''): ?>
        <p class="panel empty"><span data-i18n>Choose an item or search part of its name to see how many times you bought it and how its price changed.</span></p>
    <?php elseif ($itemNames !== [] && $itemResults === []): ?>
        <p class="panel empty"><span data-i18n>You have not bought any item matching this search.</span> <span data-i18n>Try part of the name, for example "kit" for KitKat.</span></p>
    <?php endif; ?>

    <?php foreach ($itemResults as $index => $item): ?>
        <?php
        $labels = [];
        $titles = [];
        foreach ($item['purchases'] as $purchase) {
            $when = (new DateTimeImmutable($purchase['purchased_at']))->setTimezone($local);
            $labels[] = $when->format('M j');
            $titles[] = $when->format('M j, Y · H:i');
        }
        ?>
        <section class="section-gap price-item" aria-labelledby="item-<?= $index ?>">
            <h2 id="item-<?= $index ?>" class="price-item-name"><?= $e($item['name']) ?></h2>
            <dl class="stats four">
                <div><dt><span data-i18n>Times purchased</span></dt><dd><?= number_format($item['times']) ?></dd></div>
                <div><dt><span data-i18n>Units bought</span></dt><dd><?= number_format($item['units']) ?></dd></div>
                <div><dt><span data-i18n>Price now</span></dt><dd><?= $money($item['last_price']) ?></dd></div>
                <div><dt><span data-i18n>Change since first purchase</span></dt>
                    <dd><?= $item['times'] > 1 ? $e($percent($item['change_percent']) ?: '0.0%') : '—' ?></dd>
                    <?php if ($item['times'] > 1): ?><span class="subtext"><?= $money($item['first_price']) ?> → <?= $money($item['last_price']) ?></span><?php endif; ?></div>
            </dl>
            <div class="panel section-gap">
                <div class="section-heading">
                    <h3><span data-i18n>Price at each purchase</span></h3>
                </div>
                <div class="chart-box">
                    <canvas class="price-chart" role="img"
                        aria-label="<?= $e('Price of ' . $item['name'] . ' at each purchase, from ' . $money($item['first_price']) . ' to ' . $money($item['last_price'])) ?>"
                        data-labels="<?= $e(json_encode($labels)) ?>"
                        data-titles="<?= $e(json_encode($titles)) ?>"
                        data-prices="<?= $e(json_encode(array_column($item['purchases'], 'unit_price'))) ?>"></canvas>
                </div>
                <div class="table-scroll compact">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col" data-i18n>Date and time</th>
                                <th scope="col" class="numeric" data-i18n>Unit price</th>
                                <th scope="col" class="numeric" data-i18n>Quantity</th>
                                <th scope="col" class="numeric" data-i18n>Change since previous purchase</th>
                                <th scope="col" data-i18n>Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($item['purchases'] as $i => $purchase): ?>
                                <tr>
                                    <td><?= $e($titles[$i]) ?></td>
                                    <td class="numeric"><?= $money($purchase['unit_price']) ?></td>
                                    <td class="numeric"><?= $purchase['quantity'] ?></td>
                                    <td class="numeric">
                                        <?php if ($purchase['change'] === null): ?>
                                            <span class="hint" data-i18n>First purchase</span>
                                        <?php elseif ($purchase['change'] == 0): ?>
                                            <span class="hint" data-i18n>No change</span>
                                        <?php else: ?>
                                            <?= $e($signed($purchase['change']) . ' (' . $percent($purchase['change_percent']) . ')') ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><a href="/receipt?id=<?= $purchase['purchase_id'] ?>" data-i18n>View receipt</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    <?php endforeach; ?>
    <?php if ($itemResults !== []): ?>
        <script src="/assets/js/vendor/chart.umd.min.js" defer></script>
        <script src="/assets/js/price-chart.js" defer></script>
    <?php endif; ?>

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

    <?php if ($spending !== null): ?>
        <!-- P4-18: total spent in the period, from the same receipts as the list below -->
        <dl class="stats four section-gap" aria-label="Spending summary" data-i18n-aria-label="Spending summary">
            <div><dt><span data-i18n>Total spent</span></dt><dd><?= $money($spending['total']) ?></dd></div>
            <div><dt><span data-i18n>Receipts</span></dt><dd><?= number_format($spending['count']) ?></dd></div>
            <div><dt><span data-i18n>Average per receipt</span></dt><dd><?= $money($spending['average']) ?></dd></div>
            <div><dt><span data-i18n>Points earned</span></dt><dd><?= number_format($spending['points_earned']) ?></dd></div>
        </dl>
        <?php if ($spending['count'] > 0): ?>
            <?php
            // Labels are made here, in Montreal time, so the browser never shifts a day.
            $labels = [];
            foreach (array_keys($spending['series']) as $day) {
                $labels[] = $spending['by'] === 'month'
                    ? DateTimeImmutable::createFromFormat('!Y-m', $day)->format('M Y')
                    : DateTimeImmutable::createFromFormat('!Y-m-d', $day)->format('M j');
            }
            ?>
            <section class="panel section-gap" aria-labelledby="spending-heading">
                <div class="section-heading">
                    <h2 id="spending-heading"><span data-i18n>Spending over time</span></h2>
                    <p class="hint"><span data-i18n><?= $spending['by'] === 'month' ? 'Amount paid per month' : 'Amount paid per day' ?></span></p>
                </div>
                <div class="chart-box">
                    <canvas id="spending-chart" role="img"
                        aria-label="<?= $e('Spending over time: ' . $money($spending['total']) . ' in ' . $spending['count'] . ' receipts, ' . $period) ?>"
                        data-labels="<?= $e(json_encode($labels)) ?>"
                        data-amounts="<?= $e(json_encode(array_values($spending['series']))) ?>"></canvas>
                </div>
            </section>
            <script src="/assets/js/vendor/chart.umd.min.js" defer></script>
            <script src="/assets/js/spending-chart.js" defer></script>
        <?php endif; ?>
    <?php endif; ?>

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
