<?php
/**
 * Receipt email, sent right after a member's checkout.
 * The store doesn't know which language each customer prefers, so every
 * line is written in English and then in French.
 * @var array  $purchase  purchases row with purchase_items
 * @var array  $customer  name, points
 * @var string $base      APP_URL
 */
$subject = 'Your SuperStore receipt / Votre reçu SuperStore';
$footer = 'You get this email because you used your SuperStore membership at checkout. / Vous recevez ce courriel parce que vous avez utilisé votre carte de membre SuperStore à la caisse.';
$reference = 'SS-' . str_pad((string) $purchase['id'], 6, '0', STR_PAD_LEFT);
$when = (new DateTimeImmutable($purchase['purchased_at']))->setTimezone(new DateTimeZone(date_default_timezone_get()));
$items = $purchase['purchase_items'] ?? [];
$subtotal = array_sum(array_map(static fn (array $line): float => (float) $line['unit_price'] * (int) $line['quantity'], $items));
$firstName = explode(' ', trim((string) $customer['name']))[0];
$cash = ($purchase['payment_method'] ?? 'card') === 'cash';
$earned = (int) $purchase['points_earned'];
$points = number_format((int) $customer['points']);
$cell = 'padding:8px 0;border-bottom:1px solid #e2e6ec;font-size:14px;';
$french = 'color:#596371;font-style:italic;';
require __DIR__ . '/_top.php';
?>
<p style="margin:0 0 6px;font-size:20px;font-weight:700;">Thanks for shopping with us, <?= $e($firstName) ?>!</p>
<p style="margin:0 0 12px;color:#596371;">Here is your receipt. We hope you enjoy your purchase, and we look forward to seeing you again soon.</p>
<p lang="fr" style="margin:0 0 6px;font-size:17px;font-weight:700;">Merci d'avoir magasiné chez nous, <?= $e($firstName) ?>!</p>
<p lang="fr" style="margin:0 0 20px;<?= $french ?>">Voici votre reçu. Nous espérons que vous apprécierez vos achats et nous avons hâte de vous revoir.</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:18px;font-size:13px;color:#596371;">
    <tr><td>Order reference / Numéro de commande</td><td align="right" style="font-family:Consolas,monospace;color:#1d242d;"><?= $e($reference) ?></td></tr>
    <tr><td>Date</td><td align="right" style="color:#1d242d;"><?= $e($when->format('Y-m-d H:i')) ?></td></tr>
    <tr><td>Payment / Paiement</td><td align="right" style="color:#1d242d;"><?= $cash ? 'Cash / Comptant' : 'Card / Carte' ?></td></tr>
</table>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <th align="left" style="<?= $cell ?>color:#596371;font-size:12px;font-weight:600;">Item / Article</th>
        <th align="right" style="<?= $cell ?>color:#596371;font-size:12px;font-weight:600;">Qty / Qté</th>
        <th align="right" style="<?= $cell ?>color:#596371;font-size:12px;font-weight:600;">Amount / Montant</th>
    </tr>
    <?php foreach ($items as $line): ?>
        <tr>
            <td style="<?= $cell ?>"><?= $e($line['name']) ?><br><span style="color:#596371;font-size:12px;"><?= $money($line['unit_price']) ?> each / chacun</span></td>
            <td align="right" style="<?= $cell ?>"><?= (int) $line['quantity'] ?></td>
            <td align="right" style="<?= $cell ?>"><?= $money((float) $line['unit_price'] * (int) $line['quantity']) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if ((int) ($purchase['points_redeemed'] ?? 0) > 0): ?>
        <tr><td colspan="2" align="right" style="padding:10px 0 0;color:#596371;">Subtotal / Sous-total</td><td align="right" style="padding:10px 0 0;"><?= $money($subtotal) ?></td></tr>
        <tr><td colspan="2" align="right" style="padding:4px 0 0;color:#596371;">Points discount / Rabais points</td><td align="right" style="padding:4px 0 0;">−<?= $money($subtotal - (float) $purchase['total']) ?></td></tr>
    <?php endif; ?>
    <tr><td colspan="2" align="right" style="padding:10px 0 0;font-weight:700;">Total paid / Total payé</td><td align="right" style="padding:10px 0 0;font-weight:700;font-size:17px;"><?= $money($purchase['total']) ?></td></tr>
</table>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:22px 0;background:#eaf0f7;border-radius:8px;">
    <tr><td style="padding:14px 16px;font-size:14px;color:#234b78;">
        <?php if ($earned > 0): ?><strong>+<?= number_format($earned) ?> points</strong> earned on this visit.<?php endif; ?>
        Your balance is now <strong><?= $points ?> points</strong>, worth <?= $money((int) $customer['points'] / 100) ?> off your next order.
        <br><span lang="fr" style="display:inline-block;margin-top:8px;font-style:italic;">
        <?php if ($earned > 0): ?><strong>+<?= number_format($earned) ?> points</strong> accumulés lors de cette visite.<?php endif; ?>
        Votre solde est maintenant de <strong><?= $points ?> points</strong>, soit <?= $money((int) $customer['points'] / 100) ?> de rabais sur votre prochaine commande.</span>
    </td></tr>
</table>

<p style="margin:0;"><a href="<?= $e($base) ?>/account?tab=orders" style="display:inline-block;padding:11px 18px;background:#234b78;color:#ffffff;border-radius:6px;text-decoration:none;font-weight:600;">See all my orders / Voir toutes mes commandes</a></p>
<?php require __DIR__ . '/_bottom.php'; ?>
