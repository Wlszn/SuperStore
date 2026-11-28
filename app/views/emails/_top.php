<?php
/**
 * Top of every email: a centred 560px card with the SuperStore band.
 * Email apps ignore most CSS, so the layout is tables with inline styles.
 * @var string $subject
 */
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $e($subject) ?></title></head>
<body style="margin:0;padding:0;background:#f3f5f8;font-family:Segoe UI,Arial,sans-serif;color:#1d242d;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f5f8;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #e2e6ec;border-radius:10px;overflow:hidden;">
<tr><td style="background:#234b78;padding:18px 28px;color:#ffffff;font-size:18px;font-weight:700;">SuperStore</td></tr>
<tr><td style="padding:28px;font-size:15px;line-height:1.55;">
