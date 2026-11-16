<?php
$e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$money = static fn ($value): string => '$' . number_format((float) $value, 2);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title <?= !isset($product) || $title !== $product['name'] ? 'data-i18n' : '' ?>><?= $e($title) ?> · SuperStore</title>
    <link rel="stylesheet" href="/assets/css/inventory.css">
    <script src="/assets/js/form-utils.js" defer></script>
    <script src="/assets/js/inventory.js" defer></script>
    <script src="/assets/js/language.js" defer></script>
</head>
<body>
<div class="workspace">
    <aside class="sidebar">
        <a class="brand" href="/products"><span class="brand-icon" aria-hidden="true">S</span>SuperStore</a>
        <nav aria-label="Store" data-i18n-aria-label="Store">
            <a class="nav-link" href="/checkout"><span data-i18n>Self-checkout</span>
            </a>
            <a class="nav-link" href="/account"><span data-i18n>My account</span>
            </a>
            <p class="nav-label"><span data-i18n>Workspace</span></p>
            <?php if (!empty($_SESSION['is_admin'])): ?>
                <a href="/products" class="nav-link <?= $section === 'products' ? 'current' : '' ?>" <?= $section === 'products' ? 'aria-current="page"' : '' ?>>
                    <span class="nav-icon" aria-hidden="true">▤</span> <span data-i18n>Products</span>
                </a>
                <a href="/products/receive" class="nav-link <?= $section === 'receive' ? 'current' : '' ?>" <?= $section === 'receive' ? 'aria-current="page"' : '' ?>>
                    <span class="nav-icon" aria-hidden="true">↓</span> <span data-i18n>Receive stock</span>
                </a>
            <?php endif; ?>
        </nav>
        <a class="nav-link signout" href="/logout"><span data-i18n>Sign out</span></a>
    </aside>
    <main id="main">
        <div class="topbar">
            <button type="button" class="button" id="language-switch" aria-label="Change language">FR</button>
            <a href="/products"><span data-i18n>Inventory</span></a><span aria-hidden="true">/</span><span <?= $section !== 'products' || !isset($product) || $title !== $product['name'] ? 'data-i18n' : '' ?>><?= $e($title) ?></span>
        </div>
        <div class="content">
            <div class="notice" id="notice" role="status" tabindex="-1" hidden></div>
