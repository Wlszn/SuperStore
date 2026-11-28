<?php
/**
 * Top of every admin page. The page that requires this file sets these first.
 *
 * @var string     $title    page title, e.g. 'Sales report'
 * @var string     $section  menu item to highlight, e.g. 'sales'
 * @var array|null $product  only on product pages
 */
$e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$money = static fn ($value): string => '$' . number_format((float) $value, 2);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title <?= !isset($product) || $title !== $product['name'] ? 'data-i18n' : '' ?>><?= $e($title) ?> · SuperStore</title>
    <script src="/assets/js/theme.js"></script>
    <link rel="stylesheet" href="/assets/css/inventory.css">
    <script src="/assets/js/form-utils.js" defer></script>
    <script src="/assets/js/language.js" defer></script>
    <script src="/assets/js/notifications.js" defer></script>
    <script src="/assets/js/rfid-feed.js" defer></script>
    <script src="/assets/js/inventory.js" defer></script>
</head>
<body data-order-owner="<?= $e($_SESSION['customer_id'] ?? 'guest') ?>">
<div class="workspace">
    <aside class="sidebar">
        <a class="brand" href="/"><span class="brand-icon" aria-hidden="true">S</span>SuperStore</a>
        <nav aria-label="Store" data-i18n-aria-label="Store">
            <a class="nav-link <?= $section === 'checkout' ? 'current' : '' ?>" href="/checkout" <?= $section === 'checkout' ? 'aria-current="page"' : '' ?>><span data-i18n>Self-checkout</span></a>
            <?php if (isset($_SESSION['customer_id'])): ?>
                <a class="nav-link <?= $section === 'account' ? 'current' : '' ?>" href="/account" <?= $section === 'account' ? 'aria-current="page"' : '' ?>><span data-i18n>My account</span></a>
            <?php endif; ?>
            <?php if (!empty($_SESSION['is_admin'])): ?>
                <p class="nav-label"><span data-i18n>Workspace</span></p>
                <a href="/products" class="nav-link <?= $section === 'products' ? 'current' : '' ?>" <?= $section === 'products' ? 'aria-current="page"' : '' ?>>
                    <span class="nav-icon" aria-hidden="true">▤</span> <span data-i18n>Products</span>
                </a>
                <a href="/products/receive" class="nav-link <?= $section === 'receive' ? 'current' : '' ?>" <?= $section === 'receive' ? 'aria-current="page"' : '' ?>>
                    <span class="nav-icon" aria-hidden="true">↓</span> <span data-i18n>Receive stock</span>
                </a>
                <a href="/dashboard" class="nav-link <?= $section === 'fridges' ? 'current' : '' ?>" <?= $section === 'fridges' ? 'aria-current="page"' : '' ?>>
                    <span class="nav-icon" aria-hidden="true">❄</span> <span data-i18n>Fridge dashboard</span>
                </a>
                <a href="/customers" class="nav-link <?= $section === 'customers' ? 'current' : '' ?>" <?= $section === 'customers' ? 'aria-current="page"' : '' ?>>
                    <span class="nav-icon" aria-hidden="true">◉</span> <span data-i18n>Customers</span>
                </a>
                <a href="/reports/sales" class="nav-link <?= $section === 'sales' ? 'current' : '' ?>" <?= $section === 'sales' ? 'aria-current="page"' : '' ?>>
                    <span class="nav-icon" aria-hidden="true">$</span> <span data-i18n>Sales report</span>
                </a>
            <?php endif; ?>
        </nav>
        <?php if (isset($_SESSION['customer_id'])): ?>
            <a class="nav-link signout" href="/logout"><span data-i18n>Sign out</span></a>
        <?php else: ?>
            <a class="nav-link signout" href="/login"><span data-i18n>Sign in</span></a>
        <?php endif; ?>
    </aside>
    <main id="main">
        <div class="topbar">
            <button type="button" class="button" id="language-switch" aria-label="Change language">FR</button>
            <button type="button" class="button theme-switch" aria-label="Switch to dark mode" data-i18n-aria-label="Switch to dark mode"><svg class="icon-moon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg><svg class="icon-sun" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg></button>
            <?php if ($section === 'fridges'): ?>
                <a href="/dashboard"><span data-i18n>Monitoring</span></a>
            <?php elseif ($section === 'products' || $section === 'receive'): ?>
                <a href="/products"><span data-i18n>Inventory</span></a>
            <?php elseif ($section === 'sales'): ?>
                <a href="/reports/sales"><span data-i18n>Reports</span></a>
            <?php else: ?>
                <a href="/"><span data-i18n>Store</span></a>
            <?php endif; ?><span aria-hidden="true">/</span><span <?= $section !== 'products' || !isset($product) || $title !== $product['name'] ? 'data-i18n' : '' ?>><?= $e($title) ?></span>
        </div>
        <div class="content">
            <div class="notice toast" id="notice" hidden>
                <span class="toast-icon" aria-hidden="true"></span>
                <span id="notice-message" role="status"></span>
                <button class="toast-close" id="notice-dismiss" type="button" aria-label="Dismiss notification"
                    data-i18n-aria-label="Dismiss notification">&times;</button>
            </div>
