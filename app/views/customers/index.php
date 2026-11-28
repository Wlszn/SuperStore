<?php
/**
 * Admin Customers page: widgets on top, then every registered account.
 *
 * @var array      $customers from CustomerDirectoryModel::all()
 * @var array|null $summary   from CustomerDirectoryModel::summary()
 * @var string|null $error
 */
$title = 'Customers';
$section = 'customers';
require __DIR__ . '/../products/_start.php';
$day = static fn (?DateTimeImmutable $date): string => $date === null ? '—' : $date->format('M j, Y');
?>
<header class="page-heading">
    <div><h1><span data-i18n>Customers</span></h1><p><span data-i18n>Everyone registered in SuperStore, with their membership and shopping activity.</span></p></div>
    <?php if ($error === null && $customers !== []): ?>
        <div class="row-actions">
            <a class="button" href="/customers/export" download><span data-i18n>Export CSV</span></a>
        </div>
    <?php endif; ?>
</header>

<?php if ($error !== null): ?>
    <p class="notice error" role="alert"><span data-i18n><?= $e($error) ?></span></p>
<?php else: ?>
    <dl class="stats four section-gap" aria-label="Customer summary" data-i18n-aria-label="Customer summary">
        <div><dt><span data-i18n>Registered customers</span></dt><dd><?= number_format($summary['total']) ?></dd></div>
        <div><dt><span data-i18n>New in the last 30 days</span></dt><dd><?= number_format($summary['new']) ?></dd></div>
        <div><dt><span data-i18n>Shopped in the last 30 days</span></dt><dd><?= number_format($summary['active']) ?></dd></div>
        <div><dt><span data-i18n>Points not yet used</span></dt><dd><?= number_format($summary['points']) ?></dd>
            <span class="subtext" data-i18n>Worth <?= $e($money($summary['points'] / 100)) ?> in discounts</span></div>
    </dl>

    <div class="ranking">
        <section class="panel ranking-list" aria-labelledby="top-customers">
            <h2 id="top-customers"><span data-i18n>Top customers</span></h2>
            <p class="subtext" data-i18n>By total spent, all time</p>
            <?php if ($summary['top'] === []): ?>
                <p class="empty"><span data-i18n>No member purchases yet.</span></p>
            <?php else: ?>
                <ul>
                    <?php foreach ($summary['top'] as $rank => $customer): ?>
                        <li><span><span class="rank">#<?= $rank + 1 ?></span><?= $e($customer['name']) ?></span><strong><?= $money($customer['spent']) ?></strong></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
        <section class="panel ranking-list" aria-labelledby="newest-customers">
            <h2 id="newest-customers"><span data-i18n>Newest members</span></h2>
            <p class="subtext" data-i18n>Most recent sign-ups</p>
            <?php if ($summary['newest'] === []): ?>
                <p class="empty"><span data-i18n>No customers yet.</span></p>
            <?php else: ?>
                <ul>
                    <?php foreach ($summary['newest'] as $customer): ?>
                        <li><span><?= $e($customer['name']) ?></span><span class="hint"><?= $e($day($customer['joined'])) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <section class="panel customer-directory" aria-label="All customers" data-i18n-aria-label="All customers">
        <div class="toolbar">
            <input id="customer-search" class="search" type="search" data-customer-search
                placeholder="Search name, email, phone or card" data-i18n-placeholder="Search name, email, phone or card"
                aria-label="Search name, email, phone or card" data-i18n-aria-label="Search name, email, phone or card">
        </div>
        <div class="table-scroll" tabindex="0" role="region" aria-label="Customer list" data-i18n-aria-label="Customer list">
            <table>
                <thead><tr>
                    <th scope="col"><span data-i18n>Customer</span></th>
                    <th scope="col"><span data-i18n>Contact</span></th>
                    <th scope="col"><span data-i18n>Membership number</span></th>
                    <th scope="col" class="numeric"><span data-i18n>Points</span></th>
                    <th scope="col"><span data-i18n>Joined</span></th>
                    <th scope="col" class="numeric"><span data-i18n>Purchases</span></th>
                    <th scope="col" class="numeric"><span data-i18n>Total spent</span></th>
                    <th scope="col"><span data-i18n>Last purchase</span></th>
                    <th scope="col"><span data-i18n>Actions</span></th>
                </tr></thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                        <tr data-customer data-search="<?= $e(implode(' ', [$customer['name'], $customer['email'], $customer['phone'], $customer['membership_number']])) ?>">
                            <th scope="row">
                                <a class="product-name" href="/customers/edit?id=<?= $customer['id'] ?>"><?= $e($customer['name']) ?></a>
                                <?php if ($customer['is_admin']): ?><span class="role-badge" data-i18n>Administrator</span><?php endif; ?>
                                <span class="subtext"><?= $e($customer['email']) ?></span>
                            </th>
                            <td class="contact">
                                <span class="nowrap"><?= $customer['phone'] !== '' ? $e($customer['phone']) : '—' ?></span>
                                <?php if ($customer['address'] !== ''): ?><span class="subtext"><?= $e($customer['address']) ?></span><?php endif; ?>
                            </td>
                            <td class="code"><?= $e($customer['membership_number']) ?></td>
                            <td class="numeric"><?= number_format($customer['points']) ?></td>
                            <td><?= $e($day($customer['joined'])) ?></td>
                            <td class="numeric"><?= number_format($customer['purchases']) ?></td>
                            <td class="numeric"><?= $money($customer['spent']) ?></td>
                            <td><?= $e($day($customer['last_purchase'])) ?></td>
                            <td><a href="/customers/edit?id=<?= $customer['id'] ?>" data-i18n>Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p id="no-customers" class="empty" hidden><span data-i18n>No customers match your search.</span></p>
        </div>
        <div class="table-footer" id="customer-count" aria-live="polite" data-i18n><?= count($customers) ?> <?= count($customers) === 1 ? 'customer' : 'customers' ?></div>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/../products/_end.php'; ?>
