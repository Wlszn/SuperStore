<?php
$title = 'Order confirmation';
$section = 'checkout';
require __DIR__ . '/products/_start.php';
?>
<header class="page-heading">
    <h1 data-i18n>Order confirmation</h1>
    <a class="button" href="/" data-i18n>Return to Home</a>
</header>
<section class="panel editor-body" id="order-confirmation" hidden>
    <h2 data-i18n>Order placed</h2>
    <p><span data-i18n>Order reference</span>: <strong class="code" id="order-reference"></strong></p>
    <p id="confirmation-delivery"></p>
    <h3 data-i18n>Order summary</h3>
    <ul id="confirmation-items"></ul>
    <p id="confirmation-total"></p>
</section>
<?php
$reference = '';
require __DIR__ . '/products/_tracking.php';
?>
<script src="/assets/js/order-confirmation.js" defer></script>
<script src="/assets/js/tracking.js" defer></script>
<?php require __DIR__ . '/products/_end.php'; ?>

