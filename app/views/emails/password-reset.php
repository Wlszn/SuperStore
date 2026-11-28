<?php
/**
 * Password reset email: a single-use link that expires after one hour.
 * The message is written in English, then repeated in French.
 * @var array  $customer  name
 * @var string $link      the reset page with the token
 * @var bool   $byAdmin   true when an administrator sent it from the Customers page
 */
$subject = 'Reset your SuperStore password / Réinitialisez votre mot de passe SuperStore';
$footer = 'You get this email because a password reset was requested for your SuperStore account. / Vous recevez ce courriel parce qu\'une réinitialisation du mot de passe a été demandée pour votre compte SuperStore.';
$firstName = explode(' ', trim((string) $customer['name']))[0];
$muted = 'color:#596371;';
$divider = 'margin:24px 0;border:0;border-top:1px solid #e2e6ec;';
require __DIR__ . '/_top.php';
?>
<p style="margin:0 0 6px;font-size:20px;font-weight:700;">Hi <?= $e($firstName) ?>,</p>
<p style="margin:0 0 14px;<?= $muted ?>">
    <?= $byAdmin ? 'A SuperStore administrator sent you this link so you can choose a new password.' : 'We received a request to reset the password of your SuperStore account.' ?>
    Click the button below to choose a new one. The link works once and expires in 1 hour.
</p>
<p style="margin:0;font-size:13px;<?= $muted ?>">Did not ask for this? You can ignore this email: your password stays the same.</p>

<hr style="<?= $divider ?>">

<div lang="fr">
<p style="margin:0 0 6px;font-size:20px;font-weight:700;">Bonjour <?= $e($firstName) ?>,</p>
<p style="margin:0 0 14px;<?= $muted ?>">
    <?= $byAdmin ? 'Un administrateur SuperStore vous a envoyé ce lien pour que vous puissiez choisir un nouveau mot de passe.' : 'Nous avons reçu une demande de réinitialisation du mot de passe de votre compte SuperStore.' ?>
    Cliquez sur le bouton ci-dessous pour en choisir un nouveau. Le lien ne fonctionne qu'une fois et expire dans 1 heure.
</p>
<p style="margin:0;font-size:13px;<?= $muted ?>">Vous n'avez rien demandé? Ignorez ce courriel : votre mot de passe reste le même.</p>
</div>

<p style="margin:24px 0 20px;"><a href="<?= $e($link) ?>" style="display:inline-block;padding:11px 18px;background:#234b78;color:#ffffff;border-radius:6px;text-decoration:none;font-weight:600;">Choose a new password / Choisir un nouveau mot de passe</a></p>
<p style="margin:0 0 6px;font-size:13px;<?= $muted ?>">If the button does not work, copy this address into your browser. / Si le bouton ne fonctionne pas, copiez cette adresse dans votre navigateur.</p>
<p style="margin:0;font-size:12px;word-break:break-all;"><a href="<?= $e($link) ?>" style="color:#234b78;"><?= $e($link) ?></a></p>
<?php require __DIR__ . '/_bottom.php'; ?>
