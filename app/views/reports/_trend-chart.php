<?php
/**
 * Line chart for the sales report. Loaded by reports/sales.php, which provides these variables.
 *
 * @var array    $trend  rows of ['period' => 'Y-m-d', 'items_sold' => int, 'revenue' => float]
 * @var string   $group  'day' or 'week'
 * @var callable $money  from products/_start.php: 12.5 -> "$12.50"
 */
$width = 720; $height = 240;
$left = 64; $right = 16; $top = 16; $bottom = 36;          // room for the axis labels
$plotW = $width - $left - $right;
$plotH = $height - $top - $bottom;

$count = count($trend);
$max = max([0.0, ...array_column($trend, 'revenue')]);
$scaleMax = $max > 0 ? $max : 1.0;                        // all zero: flat line, no division by zero

$x = fn (int $i): float => $left + ($count > 1 ? $i * $plotW / ($count - 1) : $plotW / 2);
$y = fn (float $v): float => $top + $plotH - $v / $scaleMax * $plotH;
$label = fn (string $d): string => (new DateTimeImmutable($d))->format('M j');

$points = [];
foreach ($trend as $i => $row) {
    $points[] = round($x($i), 1) . ',' . round($y($row['revenue']), 1);
}
$labelEvery = max(1, (int) ceil($count / 8));             // at most ~8 date labels
?>
<svg class="trend-chart" viewBox="0 0 <?= $width ?> <?= $height ?>" role="img"
     aria-label="Revenue per <?= $group ?>, <?= $count ?> points">
    <?php for ($k = 0; $k <= 4; $k++):
        if ($max == 0 && $k > 0) break;                   // only the $0 line when nothing sold
        $value = $scaleMax * $k / 4;
        $gy = round($y($value), 1); ?>
        <line class="grid" x1="<?= $left ?>" x2="<?= $width - $right ?>" y1="<?= $gy ?>" y2="<?= $gy ?>"/>
        <text class="axis" x="<?= $left - 8 ?>" y="<?= $gy + 4 ?>" text-anchor="end"><?= $money($value) ?></text>
    <?php endfor; ?>

    <?php foreach ($trend as $i => $row): if ($i % $labelEvery !== 0) continue; ?>
        <text class="axis" x="<?= round($x($i), 1) ?>" y="<?= $height - 12 ?>" text-anchor="middle"><?= $label($row['period']) ?></text>
    <?php endforeach; ?>

    <polyline class="line" points="<?= implode(' ', $points) ?>"/>

    <?php foreach ($trend as $i => $row): ?>
        <circle class="dot" cx="<?= round($x($i), 1) ?>" cy="<?= round($y($row['revenue']), 1) ?>" r="3.5">
            <title><?= ($group === 'week' ? 'Week of ' : '') . $label($row['period']) ?>: <?= $money($row['revenue']) ?> · <?= $row['items_sold'] ?> items</title>
        </circle>
    <?php endforeach; ?>
</svg>