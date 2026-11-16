<?php
// Threshold settings page.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title data-i18n>Fridge thresholds</title>
    <script src="/assets/js/language.js" defer></script>
</head>
<body>
    <button type="button" id="language-switch" aria-label="Passer au fran&#231;ais">FR</button>
    <h1><span data-i18n>Temperature thresholds</span></h1>
    <p><span data-i18n>An alert email is sent when a fridge goes above its threshold.</span></p>

    <?php foreach ($fridges as $fridge): ?>
        <form class="threshold-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
            <input type="hidden" name="fridge_id" value="<?= (int) $fridge['id'] ?>">

            <label for="max-<?= (int) $fridge['id'] ?>">
                <?= htmlspecialchars($fridge['name'], ENT_QUOTES) ?> <span data-i18n>maximum temperature (°C)</span>
            </label>
            <input
                id="max-<?= (int) $fridge['id'] ?>"
                name="max_temp"
                type="number"
                step="0.5"
                min="-30"
                max="50"
                value="<?= htmlspecialchars((string) $fridge['max_temp'], ENT_QUOTES) ?>"
                required
            >
            <button type="submit"><span data-i18n>Save</span></button>
            <span class="status" role="status"></span>
        </form>
    <?php endforeach; ?>

    <p><a href="/dashboard"><span data-i18n>Back to the dashboard</span></a></p>

    <script>
        document.querySelectorAll('.threshold-form').forEach(function (form) {
            form.addEventListener('submit', async function (event) {
                event.preventDefault();
                var status = form.querySelector('.status');
                window.setLocalizedText(status, 'Saving...');

                try {
                    var response = await fetch('/thresholds', { method: 'POST', body: new FormData(form) });
                    var data = await response.json();
                    var firstError = Object.values(data.errors || {})[0];
                    window.setLocalizedText(status, firstError || data.message);
                } catch (error) {
                    window.setLocalizedText(status, 'Could not reach the server.');
                }
            });
        });
    </script>
</body>
</html>