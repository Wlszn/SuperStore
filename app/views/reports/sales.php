<?php
/** @var array $filters  @var string[] $categories  @var ?array $report  @var ?string $error */
/** @var ?DateTimeImmutable $from  @var ?DateTimeImmutable $to  @var callable $e  @var callable $money */
/**@var ?array $ranking*/
$title = 'Sales report';
$section = 'sales';
require __DIR__ . '/../products/_start.php';

// "Nov 20, 2026" for one day, "Nov 1 – Nov 15, 2026" for a range.
$period = '';
if ($report !== null) {
    $period = $from == $to
        ? $from->format('M j, Y')
        : $from->format($from->format('Y') === $to->format('Y') ? 'M j' : 'M j, Y') . ' – ' . $to->format('M j, Y');
}
?>
<header class="page-heading">
    <div><h1><span data-i18n>Sales report</span></h1><p><span data-i18n>Sold items and revenue for a day or a date range.</span></p></div>
</header>

<form class="panel toolbar report-filters" method="get" action="/reports/sales">
    <label class="filter"><input type="radio" name="mode" value="day" <?= $filters['mode'] === 'day' ? 'checked' : '' ?>> <span data-i18n>Single day</span></label>
    <label class="filter"><input type="radio" name="mode" value="range" <?= $filters['mode'] === 'range' ? 'checked' : '' ?>> <span data-i18n>Date range</span></label>

    <label data-mode="day"><span data-i18n>Day</span> <input type="date" name="day" value="<?= $e($filters['day']) ?>"></label>
    <label data-mode="range"><span data-i18n>From</span> <input type="date" name="from" value="<?= $e($filters['from']) ?>"></label>
    <label data-mode="range"><span data-i18n>To</span> <input type="date" name="to" value="<?= $e($filters['to']) ?>"></label>

    <label><span data-i18n>Category</span>
        <select name="category">
            <option value="" data-i18n>All categories</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= $e($category) ?>" <?= $filters['category'] === $category ? 'selected' : '' ?> data-i18n><?= $e($category) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
        <label><span data-i18n>Show top</span>
        <input type="number" name="top" min="1" max="20" value="<?= $e($filters['top']) ?>">
    </label>
    <button type="submit" class="button primary"><span data-i18n>Show report</span></button>
</form>

<?php if ($error !== null): ?>
    <p class="empty field-error" role="alert"><span data-i18n><?= $e($error) ?></span></p>
<?php else: ?>
    <dl class="stats four">
        <div><dt><span data-i18n>Total sales</span></dt><dd><?= $money($report['total_sales']) ?></dd>
            <?php if ($report['amount_paid'] !== null && $report['amount_paid'] != $report['total_sales']): ?>
                <span class="subtext"><span data-i18n>Paid after points</span>: <?= $money($report['amount_paid']) ?></span>
            <?php endif; ?></div>
        <div><dt><span data-i18n>Receipts</span></dt><dd><?= number_format($report['receipt_count']) ?></dd></div>
        <div><dt><span data-i18n>Items sold</span></dt><dd><?= number_format($report['items_sold']) ?></dd></div>
        <div><dt><span data-i18n>Period</span></dt><dd class="period"><?= $e($period) ?></dd></div>
    </dl>
    <div class="ranking">
        <?php foreach (['most' => 'Most sold', 'least' => 'Least sold'] as $key => $label): ?>
            <?php $list = $ranking[$key]; ?>
            <section class="panel ranking-list" aria-label="<?= $e($label) ?>" data-i18n-aria-label="<?= $e($label) ?>">
                <h2><span data-i18n><?= $e($label) ?></span></h2>
                <p class="subtext">
                    <?= $e($period) ?> · <?= count($list) ?> <span data-i18n><?= count($list) === 1 ? 'item' : 'items' ?></span>
                    <?php if (count($list) > $ranking['limit']): ?>
                        <span data-i18n>(includes ties)</span>
                    <?php endif; ?>
                </p>
                <?php if ($list === []): ?>
                    <p class="empty"><span data-i18n><?= $key === 'most' ? 'No sales in this period.' : 'No products to rank.' ?></span></p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($list as $item): ?>
                            <li>
                                <span>
                                    <span class="rank">#<?= $item['rank'] ?></span>
                                    <?= $e($item['name']) ?>
                                    <?php if ($item['deleted']): ?><span class="subtext" data-i18n>Deleted product</span><?php endif; ?>
                                </span>
                                <strong><?= number_format($item['sold_qty']) ?> <span data-i18n>sold</span></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>

    <section class="panel" aria-label="Sold items by product" data-i18n-aria-label="Sold items by product">
        <?php if ($report['products'] === []): ?>
            <p class="empty"><span data-i18n>No sales in this period.</span> <span data-i18n>Try a wider date range or another category.</span></p>
        <?php else: ?>
            <div class="table-scroll" tabindex="0" role="region" aria-label="Sold items" data-i18n-aria-label="Sold items">
                <table>
                    <thead><tr>
                        <th scope="col"><span data-i18n>Product</span></th>
                        <th scope="col"><span data-i18n>Category</span></th>
                        <th scope="col" class="numeric"><span data-i18n>Sold quantity</span></th>
                        <th scope="col" class="numeric"><span data-i18n>Revenue</span></th>
                        <th scope="col"><span data-i18n>Period</span></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($report['products'] as $line): ?>
                        <tr>
                            <th scope="row">
                                <?php if ($line['deleted']): ?>
                                    <?= $e($line['name']) ?> <span class="subtext" data-i18n>Deleted product</span>
                                <?php else: ?>
                                    <a class="product-name" href="/products/show?id=<?= $line['product_id'] ?>"><?= $e($line['name']) ?></a>
                                <?php endif; ?>
                            </th>
                            <td data-i18n><?= $e($line['category']) ?></td>
                            <td class="numeric"><?= number_format($line['sold_qty']) ?></td>
                            <td class="numeric"><?= $money($line['revenue']) ?></td>
                            <td><?= $e($period) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="table-footer"><?= count($report['products']) ?> <span data-i18n><?= count($report['products']) === 1 ? 'product' : 'products' ?></span></div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<script src="/assets/js/sales-report.js" defer></script>
<?php require __DIR__ . '/../products/_end.php'; ?>