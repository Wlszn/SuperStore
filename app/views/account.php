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
    <?php
    // ?edit=details or ?edit=password opens that form in place of the summary (works without JavaScript).
    $edit = $_GET['edit'] ?? '';
    $words = array_values(array_filter(preg_split('/\s+/', trim((string) ($customer['name'] ?? ''))) ?: []));
    $initials = mb_strtoupper(implode('', array_map(static fn (string $word): string => mb_substr($word, 0, 1), array_slice($words, 0, 2))));
    $since = !empty($customer['created_at']) ? (new DateTimeImmutable((string) $customer['created_at']))->format('M Y') : null;
    $fields = [
        'name' => 'Full name',
        'email' => 'Email address',
        'phone' => 'Phone number',
        'address' => 'Address',
    ];
    ?>
    <section class="panel profile-summary" aria-labelledby="profile-name">
        <span class="avatar" aria-hidden="true"><?= $e($initials !== '' ? $initials : '?') ?></span>
        <div class="profile-identity">
            <h2 id="profile-name"><?= $e($customer['name'] ?? '') ?></h2>
            <p><?= $e($customer['email'] ?? '') ?></p>
            <?php if (!empty($_SESSION['is_admin'])): ?>
                <span class="role-badge" data-i18n>Store administrator</span>
            <?php endif; ?>
        </div>
        <dl class="profile-facts">
            <?php if ($since !== null): ?>
                <div><dt data-i18n>Member since</dt><dd><?= $e($since) ?></dd></div>
            <?php endif; ?>
            <div><dt data-i18n>Membership number</dt><dd class="code"><a href="/account?tab=membership"><?= $e($customer['membership_number'] ?? '') ?></a></dd></div>
            <div><dt data-i18n>Points</dt><dd><?= number_format((int) ($customer['points'] ?? 0)) ?></dd></div>
        </dl>
    </section>

    <?php if ($glance !== null && $glance['orders'] > 0): ?>
        <dl class="stats four profile-glance" aria-label="At a glance" data-i18n-aria-label="At a glance">
            <div><dt data-i18n>Total spent</dt><dd><?= $e($money($glance['spent'])) ?></dd></div>
            <div><dt data-i18n>Orders</dt><dd><a href="/account?tab=orders&amp;mode=range&amp;from=2000-01-01&amp;to=<?= date('Y-m-d') ?>"><?= number_format($glance['orders']) ?></a></dd></div>
            <div><dt data-i18n>Last purchase</dt><dd class="period"><?= $e($glance['last']->format('M j, Y')) ?></dd></div>
            <div><dt data-i18n>Favourite item</dt>
                <?php if ($glance['favourite'] !== null): ?>
                    <dd class="period"><a href="/account?<?= $e(http_build_query(['tab' => 'prices', 'item' => $glance['favourite']['name']])) ?>"><?= $e($glance['favourite']['name']) ?></a></dd>
                    <span class="subtext" data-i18n><?= $glance['favourite']['units'] ?> bought</span>
                <?php else: ?><dd>—</dd><?php endif; ?></div>
        </dl>
    <?php endif; ?>

    <?php if ($edit === 'details'): ?>
        <form class="panel profile-section" action="/account/profile" method="post" data-action-form data-next="/account?tab=profile">
            <input type="hidden" name="csrf_token" value="<?= $e($_SESSION['csrf_token']) ?>">
            <div class="editor-body">
                <h2 data-i18n>Edit personal information</h2>
                <p class="hint" data-i18n>Receipts are emailed to your email address.</p>
                <div class="fields">
                    <label><span data-i18n>Full name</span>
                        <input name="name" required maxlength="50" autocomplete="name" value="<?= $e($customer['name'] ?? '') ?>">
                        <span data-error="name" class="field-error"></span>
                    </label>
                    <label><span data-i18n>Email address</span>
                        <input name="email" type="email" required maxlength="100" autocomplete="email" value="<?= $e($customer['email'] ?? '') ?>">
                        <span data-error="email" class="field-error"></span>
                    </label>
                    <label><span data-i18n>Phone number</span>
                        <input name="phone" type="tel" required maxlength="15" autocomplete="tel" value="<?= $e($customer['phone'] ?? '') ?>">
                        <span data-error="phone" class="field-error"></span>
                    </label>
                    <label class="wide"><span data-i18n>Address</span>
                        <input name="address" required maxlength="255" autocomplete="street-address" value="<?= $e($customer['address'] ?? '') ?>">
                        <span data-error="address" class="field-error"></span>
                    </label>
                </div>
            </div>
            <div class="editor-footer">
                <a class="button" href="/account?tab=profile" data-i18n>Cancel</a>
                <button type="submit" class="button primary"><span data-i18n>Save changes</span></button>
            </div>
        </form>
    <?php else: ?>
        <section class="panel profile-section" aria-labelledby="details-heading">
            <div class="profile-section-head">
                <div>
                    <h2 id="details-heading" data-i18n>Personal information</h2>
                    <p class="hint" data-i18n>Receipts are emailed to your email address.</p>
                </div>
                <a class="button" href="/account?tab=profile&amp;edit=details" data-i18n>Edit</a>
            </div>
            <dl class="profile-fields">
                <?php foreach ($fields as $key => $label): ?>
                    <?php $value = trim((string) ($customer[$key] ?? '')); ?>
                    <div>
                        <dt data-i18n><?= $e($label) ?></dt>
                        <?php if ($value === ''): ?>
                            <dd class="missing" data-i18n>Not provided</dd>
                        <?php else: ?>
                            <dd><?= $e($value) ?></dd>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </dl>
        </section>
    <?php endif; ?>

    <?php if ($edit === 'password'): ?>
        <form class="panel profile-section" action="/account/password" method="post" data-action-form data-next="/account?tab=profile">
            <input type="hidden" name="csrf_token" value="<?= $e($_SESSION['csrf_token']) ?>">
            <div class="editor-body">
                <h2 data-i18n>Change password</h2>
                <p class="hint" data-i18n>Use at least 8 characters, with at least one letter and one number.</p>
                <div class="fields">
                    <label class="wide"><span data-i18n>Current password</span>
                        <input name="current_password" type="password" required autocomplete="current-password">
                        <span data-error="current_password" class="field-error"></span>
                    </label>
                    <label><span data-i18n>New password</span>
                        <input name="password" type="password" required minlength="8" autocomplete="new-password">
                        <span data-error="password" class="field-error"></span>
                    </label>
                    <label><span data-i18n>Confirm new password</span>
                        <input name="confirm_password" type="password" required autocomplete="new-password">
                        <span data-error="confirm_password" class="field-error"></span>
                    </label>
                </div>
            </div>
            <div class="editor-footer">
                <a class="button" href="/account?tab=profile" data-i18n>Cancel</a>
                <button type="submit" class="button primary"><span data-i18n>Change password</span></button>
            </div>
        </form>
    <?php else: ?>
        <section class="panel profile-section" aria-labelledby="password-heading">
            <div class="profile-section-head">
                <div>
                    <h2 id="password-heading" data-i18n>Password</h2>
                    <p class="hint" data-i18n>Change the password you use to sign in.</p>
                </div>
                <a class="button" href="/account?tab=profile&amp;edit=password" data-i18n>Change password</a>
            </div>
        </section>
    <?php endif; ?>
<?php elseif ($tab === 'membership'): ?>
    <?php
    $points = (int) ($customer['points'] ?? 0);
    $memberCode = (string) ($customer['membership_number'] ?? '');
    $memberBarcode = $memberCode !== '' ? App\Services\Barcode::code128($memberCode) : null;
    $toNextDollar = 100 - $points % 100;   // 100 points = $1 off at the self-checkout
    ?>
    <div class="membership-layout">
        <figure class="member-card-visual" aria-labelledby="member-card-caption">
            <div class="member-card-face">
                <div class="member-card-top">
                    <span class="brand-icon" aria-hidden="true">S</span>
                    <span class="member-card-store">SuperStore</span>
                    <span class="member-card-kind" data-i18n>Membership card</span>
                </div>
                <p class="member-card-name"><?= $e($customer['name'] ?? '') ?></p>
                <?php if ($memberBarcode): ?>
                    <div class="member-card-barcode">
                        <svg viewBox="0 0 <?= $memberBarcode['width'] ?> 42" role="img"
                            aria-label="<?= $e('Barcode: ' . $memberCode) ?>" shape-rendering="crispEdges" preserveAspectRatio="none">
                            <rect width="100%" height="100%" fill="white"/>
                            <?php foreach ($memberBarcode['bars'] as [$x, $width]): ?>
                                <rect x="<?= $x ?>" width="<?= $width ?>" height="42" fill="black"/>
                            <?php endforeach; ?>
                        </svg>
                        <span class="code"><?= $e($memberCode) ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <figcaption id="member-card-caption" class="hint" data-i18n>Scan this barcode at the self-checkout to earn and use your points.</figcaption>
        </figure>

        <section class="panel points-panel" aria-labelledby="points-heading">
            <h2 id="points-heading" data-i18n>Points balance</h2>
            <p class="points-balance"><strong><?= number_format($points) ?></strong> <span data-i18n>points</span></p>
            <p class="points-value" data-i18n>Worth <?= $e($money($points / 100)) ?> off at the self-checkout.</p>
            <div class="points-progress" role="img" aria-label="<?= $e($toNextDollar . ' more points until your next $1 off.') ?>">
                <span style="width: <?= (100 - $toNextDollar) ?>%"></span>
            </div>
            <p class="hint" data-i18n><?= $toNextDollar ?> more points until your next $1 off.</p>
            <ul class="points-rules">
                <li data-i18n>Earn 10 points for every dollar you pay.</li>
                <li data-i18n>Every 100 points take $1 off an order.</li>
            </ul>
            <button type="button" class="button" data-print-card><span data-i18n>Print my card</span></button>
        </section>
    </div>

    <?php if ($pointsActivity !== null): ?>
        <dl class="stats four">
            <div><dt data-i18n>Points earned</dt><dd><?= number_format($pointsActivity['earned']) ?></dd></div>
            <div><dt data-i18n>Points used</dt><dd><?= number_format($pointsActivity['redeemed']) ?></dd></div>
            <div><dt data-i18n>Saved with points</dt><dd><?= $e($money($pointsActivity['redeemed'] / 100)) ?></dd></div>
            <div><dt data-i18n>Purchases with your card</dt><dd><?= number_format($pointsActivity['purchases']) ?></dd></div>
        </dl>

        <section class="panel points-activity" aria-labelledby="activity-heading">
            <h2 id="activity-heading" data-i18n>Recent points activity</h2>
            <?php if ($pointsActivity['recent'] === []): ?>
                <p class="empty" data-i18n>No points yet. Scan your card at the self-checkout to start earning.</p>
            <?php else: ?>
                <table>
                    <thead><tr>
                        <th data-i18n>Date</th>
                        <th data-i18n>Receipt</th>
                        <th class="numeric" data-i18n>Paid</th>
                        <th class="numeric" data-i18n>Points</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($pointsActivity['recent'] as $row): ?>
                            <tr>
                                <td><?= $e($row['purchased_at']->format('M j, Y')) ?></td>
                                <td><a href="/receipt?id=<?= $row['id'] ?>">#<?= $row['id'] ?></a></td>
                                <td class="numeric"><?= $e($money($row['total'])) ?></td>
                                <td class="numeric points-change">
                                    <?php if ($row['earned'] > 0): ?><span class="gain">+<?= number_format($row['earned']) ?></span><?php endif; ?>
                                    <?php if ($row['redeemed'] > 0): ?><span class="spend">−<?= number_format($row['redeemed']) ?></span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <details class="panel link-card">
        <summary data-i18n>Use a different membership card</summary>
        <form action="/account/membership" method="post" data-action-form data-next="/account?tab=membership">
            <input type="hidden" name="csrf_token" value="<?= $e($_SESSION['csrf_token']) ?>">
            <p class="hint" data-i18n>Scan the new card into this field to link it to your account. Your current card will stop working.</p>
            <label for="member-barcode"><span data-i18n>Membership barcode</span></label>
            <div class="link-card-row">
                <input id="member-barcode" name="membership_number" value="<?= $e($memberCode) ?>" maxlength="64" required autocomplete="off">
                <button type="submit" class="button primary"><span data-i18n>Save membership barcode</span></button>
            </div>
        </form>
    </details>
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
        <?php if ($topItems !== []): ?>
            <nav class="quick-ranges most-bought" aria-label="Most bought" data-i18n-aria-label="Most bought">
                <span class="hint" data-i18n>Most bought:</span>
                <?php foreach ($topItems as $name): ?>
                    <a class="chip" href="/account?<?= $e(http_build_query(['tab' => 'prices', 'item' => $name])) ?>"
                        <?= $itemChoice === $name ? 'aria-current="true"' : '' ?>><?= $e($name) ?></a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
        <script src="/assets/js/item-search.js" defer></script>
    <?php endif; ?>

    <?php if ($itemError !== null): ?>
        <p class="empty field-error" role="alert"><span data-i18n><?= $e($itemError) ?></span></p>
    <?php elseif ($itemNames !== [] && $itemSearch === '' && $itemChoice === ''): ?>
        <p class="panel empty"><span data-i18n>Choose an item or search part of its name to see how many times you bought it and how its price changed.</span></p>
    <?php elseif ($itemNames !== [] && $itemResults === []): ?>
        <p class="panel empty"><span data-i18n>You have not bought any item matching this search.</span> <span data-i18n>Try part of the name, for example "kit" for KitKat.</span></p>
    <?php endif; ?>

    <?php if ($itemResults !== []): ?>
        <?php $exportQuery = $itemSearch !== '' ? ['q' => $itemSearch] : ['item' => $itemChoice]; ?>
        <div class="print-report-meta">
            <strong>SuperStore</strong>
            <span><span data-i18n>Price history</span> · <?= $e($customer['name'] ?? '') ?> · <span data-i18n>Generated on</span> <?= $e(date('M j, Y')) ?></span>
        </div>
        <div class="export-actions section-gap">
            <a class="button" href="/account/prices/export?<?= $e(http_build_query($exportQuery)) ?>" download><span data-i18n>Export CSV</span></a>
            <button type="button" class="button" data-download-pdf><span data-i18n>Download PDF</span></button>
        </div>
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
                <div><dt><span data-i18n>Price now</span></dt><dd><?= $money($item['last_price']) ?></dd>
                    <?php if ($item['times'] > 1): ?>
                        <span class="subtext" data-i18n>Lowest paid: <?= $money($item['lowest_price']) ?></span>
                        <span class="subtext" data-i18n>Highest paid: <?= $money($item['highest_price']) ?></span>
                    <?php endif; ?></div>
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
    // Quick ranges: one click instead of typing two dates.
    $today = new DateTimeImmutable('today');
    $presets = [
        'Last 7 days' => [$today->modify('-6 days'), $today],
        'Last 30 days' => [$today->modify('-29 days'), $today],
        'This month' => [$today->modify('first day of this month'), $today],
        'This year' => [$today->setDate((int) $today->format('Y'), 1, 1), $today],
    ];
    $visibleItems = 3;   // longer receipts show the first 3 lines and a "Show all" button
    ?>
    <!-- Printed by "Download PDF" (the browser's Save as PDF); hidden on screen. -->
    <div class="print-report-meta">
        <strong>SuperStore</strong>
        <span><span data-i18n>Order history</span> · <?= $e($customer['name'] ?? '') ?><?= $period !== '' ? ' · ' . $e($period) : '' ?></span>
    </div>
    <div class="account-toolbar">
        <nav class="quick-ranges" aria-label="Quick date ranges" data-i18n-aria-label="Quick date ranges">
            <?php foreach ($presets as $label => [$start, $end]): ?>
                <?php $active = $filters['mode'] === 'range' && $filters['from'] === $start->format('Y-m-d') && $filters['to'] === $end->format('Y-m-d'); ?>
                <a class="chip" href="/account?<?= $e(http_build_query(['tab' => 'orders', 'mode' => 'range', 'from' => $start->format('Y-m-d'), 'to' => $end->format('Y-m-d')])) ?>"
                    <?= $active ? 'aria-current="true"' : '' ?> data-i18n><?= $e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <?php if ($historyError === null && $receipts !== []): ?>
            <div class="export-actions">
                <a class="button" href="/account/orders/export?<?= $e(http_build_query(array_intersect_key($filters, array_flip(['mode', 'day', 'from', 'to'])))) ?>" download><span data-i18n>Export CSV</span></a>
                <button type="button" class="button" data-download-pdf><span data-i18n>Download PDF</span></button>
            </div>
        <?php endif; ?>
    </div>
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

    <?php if ($receipts !== []): ?>
        <?php
        // "Most bought in this period": units per item, from the receipts already on the page.
        $units = [];
        foreach ($receipts as $receipt) {
            foreach ($receipt['items'] as $line) {
                $units[$line['name']] = ($units[$line['name']] ?? 0) + $line['quantity'];
            }
        }
        arsort($units);
        ?>
        <section class="panel ranking-list section-gap period-top" aria-labelledby="period-top-heading">
            <h2 id="period-top-heading" data-i18n>Most bought in this period</h2>
            <ul>
                <?php foreach (array_slice($units, 0, 3, true) as $name => $count): ?>
                    <li><a href="/account?<?= $e(http_build_query(['tab' => 'prices', 'item' => $name])) ?>"><?= $e($name) ?></a><strong data-i18n><?= $count ?> bought</strong></li>
                <?php endforeach; ?>
            </ul>
        </section>
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
                        <?php if ($receipt['points_earned'] > 0 || $receipt['points_redeemed'] > 0): ?>
                            <p class="points-change">
                                <?php if ($receipt['points_earned'] > 0): ?><span class="gain" data-i18n>+<?= number_format($receipt['points_earned']) ?> points</span><?php endif; ?>
                                <?php if ($receipt['points_redeemed'] > 0): ?><span class="spend" data-i18n>−<?= number_format($receipt['points_redeemed']) ?> points</span><?php endif; ?>
                            </p>
                        <?php endif; ?>
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
                            <?php foreach ($receipt['items'] as $n => $line): ?>
                                <tr <?= $n >= $visibleItems ? 'class="receipt-extra"' : '' ?>>
                                    <td><?= $e($line['name']) ?></td>
                                    <td class="numeric"><?= $line['quantity'] ?></td>
                                    <td class="numeric"><?= $money($line['unit_price']) ?></td>
                                    <td class="numeric"><?= $money($line['amount']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (count($receipt['items']) > $visibleItems): ?>
                        <!-- inventory.js hides the extra lines and shows this button; without JavaScript every line stays visible. -->
                        <button type="button" class="link-button" data-show-all hidden
                            data-more="<?= $e('Show all ' . count($receipt['items']) . ' items') ?>" data-less="Show fewer items"
                            data-i18n><?= $e('Show all ' . count($receipt['items']) . ' items') ?></button>
                    <?php endif; ?>
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
