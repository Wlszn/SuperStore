<?php
/**
 * Fridge dashboard (managers only, rendered by ThresholdController::index).
 *
 * @var array  $fridges    every fridge: id, name, max_temp
 * @var string $csrfToken  sent back with every threshold save
 *
 * The page is drawn once; public/assets/js/dashboard.js then asks
 * /api/readings/latest every 2 seconds and only updates the numbers.
 */
require __DIR__ . '/products/_start.php';

// Gauge arc: 240° of a circle (radius 80) opening at the bottom.
// pathLength="100" lets the script fill it with a simple percentage.
$arc = 'M 30.72 140 A 80 80 0 1 1 169.28 140';
?>
<header class="page-heading">
    <div>
        <h1 data-i18n>Fridges</h1>
        <p data-i18n>Live temperature and humidity from the fridge sensors.</p>
    </div>
    <p class="live" id="live" role="status">
        <span class="live-dot" aria-hidden="true"></span>
        <span id="live-text" data-i18n>Connecting...</span>
    </p>
</header>

<section class="fridge-overview" id="overview" data-state="waiting" aria-label="Store status" data-i18n-aria-label="Store status">
    <p id="overview-text" data-i18n>Waiting for the first readings.</p>
    <div class="fan-control" id="fan">
        <svg class="fan-icon" viewBox="0 0 24 24" aria-hidden="true">
            <g fill="currentColor">
                <circle cx="12" cy="12" r="2.1"/>
                <path id="fan-blade" d="M12.6 9.9c.2-2.9 1.3-6.4 4.1-6.1 2.1.2 2.5 2.6 1 4.1-1.2 1.2-3 1.8-5.1 2z"/>
                <use href="#fan-blade" transform="rotate(120 12 12)"/>
                <use href="#fan-blade" transform="rotate(240 12 12)"/>
            </g>
        </svg>
        <div>
            <span class="fan-label" data-i18n>Fan</span>
            <strong id="fan-state" data-i18n>Off</strong>
        </div>
        <button class="button" id="fan-toggle" type="button" disabled data-i18n>Turn on</button>
    </div>
</section>

<div class="fridge-grid">
    <?php foreach ($fridges as $fridge): ?>
        <?php $id = (int) $fridge['id']; ?>
        <article class="fridge" data-fridge="<?= $id ?>" data-limit="<?= $e($fridge['max_temp']) ?>" data-state="waiting" aria-labelledby="fridge-<?= $id ?>-name">
            <header class="fridge-head">
                <h2 id="fridge-<?= $id ?>-name"><?= $e($fridge['name']) ?></h2>
                <span class="fridge-state" data-role="state" data-i18n>Waiting</span>
            </header>

            <div class="gauges">
                <figure class="gauge">
                    <svg viewBox="0 6 200 150" role="img" data-role="temp-gauge" aria-label="Temperature">
                        <path class="gauge-track" d="<?= $arc ?>" pathLength="100"/>
                        <path class="gauge-fill" data-role="temp-arc" d="<?= $arc ?>" pathLength="100"/>
                        <line class="gauge-limit" data-role="limit-mark" x1="0" y1="0" x2="0" y2="0"/>
                        <text class="gauge-number" x="100" y="104" text-anchor="middle" data-role="temp">--</text>
                        <text class="gauge-unit" x="100" y="126" text-anchor="middle">°C</text>
                        <text class="gauge-end" x="31" y="156" text-anchor="middle">-10</text>
                        <text class="gauge-end" x="169" y="156" text-anchor="middle">40</text>
                    </svg>
                    <figcaption data-i18n>Temperature</figcaption>
                </figure>
                <figure class="gauge humidity">
                    <svg viewBox="0 6 200 150" role="img" data-role="hum-gauge" aria-label="Humidity">
                        <path class="gauge-track" d="<?= $arc ?>" pathLength="100"/>
                        <path class="gauge-fill" data-role="hum-arc" d="<?= $arc ?>" pathLength="100"/>
                        <text class="gauge-number" x="100" y="104" text-anchor="middle" data-role="hum">--</text>
                        <text class="gauge-unit" x="100" y="126" text-anchor="middle">%</text>
                        <text class="gauge-end" x="31" y="156" text-anchor="middle">0</text>
                        <text class="gauge-end" x="169" y="156" text-anchor="middle">100</text>
                    </svg>
                    <figcaption data-i18n>Humidity</figcaption>
                </figure>
            </div>

            <p class="fridge-meta" data-role="updated" data-i18n>No reading yet</p>

            <form class="limit-form" data-limit-form novalidate>
                <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                <input type="hidden" name="fridge_id" value="<?= $id ?>">
                <label for="limit-<?= $id ?>" data-i18n>Alert above</label>
                <span class="limit-input">
                    <input id="limit-<?= $id ?>" name="max_temp" type="number" step="0.5" min="-30" max="50"
                        inputmode="decimal" value="<?= number_format($fridge['max_temp'], 1, '.', '') ?>" required>
                    <span aria-hidden="true">°C</span>
                </span>
                <button class="button" type="submit" data-i18n>Save limit</button>
            </form>
        </article>
    <?php endforeach; ?>
</div>

<?php if ($fridges === []): ?>
    <p class="empty" data-i18n>No fridges yet. Add them in the fridge table (see config/db.sql).</p>
<?php endif; ?>

<script src="/assets/js/dashboard.js" defer></script>
<?php require __DIR__ . '/products/_end.php'; ?>
