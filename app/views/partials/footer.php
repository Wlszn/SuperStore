<?php
/** Bottom of every Phase 3 page. Optional: $flash = ['text' => ..., 'type' => 'success'] shown on load. */
?>
<script src="/assets/js/form-utils.js"></script>
<script src="/assets/js/app.js"></script>
<?php if (!empty($flash)): ?>
<script>notify(<?= json_encode($flash['text']) ?>, <?= json_encode($flash['type'] ?? 'success') ?>);</script>
<?php endif; ?>
