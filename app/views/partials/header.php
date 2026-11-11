<?php
/**
 * Top of every Phase 3 page.
 * Set before including: $pageTitle (string), $active (nav key, e.g. 'products').
 */
use App\Services\Lang;

$isAdmin = !empty($_SESSION['is_admin']);
$signedIn = isset($_SESSION['customer_id']);
$here = $_SERVER['REQUEST_URI'] ?? '/';
$otherLang = Lang::current() === 'en' ? 'fr' : 'en';

$links = $isAdmin
    ? ['dashboard' => ['/dashboard', t('nav.dashboard')], 'products' => ['/products', t('nav.products')],
       'receive' => ['/products/receive', t('nav.receive')], 'checkout' => ['/checkout', t('nav.checkout')],
       'account' => ['/account', t('nav.account')]]
    : ['checkout' => ['/checkout', t('nav.checkout')]]
      + ($signedIn ? ['account' => ['/account', t('nav.account')]] : []);
?>
<!DOCTYPE html>
<html lang="<?= Lang::current() ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SuperStore - <?= htmlspecialchars($pageTitle, ENT_QUOTES) ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="<?= htmlspecialchars($bodyClass ?? '', ENT_QUOTES) ?>">
<header class="site-header">
    <a class="logo" href="<?= $signedIn ? homePath() : '/checkout' ?>">Super<span>Store</span></a>
    <nav aria-label="Main">
        <?php foreach ($links as $key => [$href, $label]): ?>
            <a href="<?= $href ?>" class="<?= ($active ?? '') === $key ? 'active' : '' ?>"><?= htmlspecialchars($label) ?></a>
        <?php endforeach; ?>
        <a href="/lang?to=<?= $otherLang ?>&amp;back=<?= rawurlencode($here) ?>" class="lang-switch" title="<?= t('nav.language_title') ?>"><?= strtoupper($otherLang) ?></a>
        <?php if ($signedIn): ?>
            <a href="/logout"><?= t('nav.sign_out') ?></a>
        <?php else: ?>
            <a href="/login"><?= t('nav.sign_in') ?></a>
        <?php endif; ?>
    </nav>
</header>
