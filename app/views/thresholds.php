<?php
/**
 * Threshold settings page.
 * Variables from ThresholdController::index():
 *   array  $fridges    each: id, name, max_temp
 *   string $csrfToken
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fridge thresholds</title>
</head>
<body>
    <h1>Temperature thresholds</h1>
    <p>An alert email is sent when a fridge goes above its threshold.</p>

    <?php foreach ($fridges as $fridge): ?>
        <form class="threshold-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
            <input type="hidden" name="fridge_id" value="<?= (int) $fridge['id'] ?>">

            <label for="max-<?= (int) $fridge['id'] ?>">
                <?= htmlspecialchars($fridge['name'], ENT_QUOTES) ?> maximum temperature (°C)
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
            <button type="submit">Save</button>
            <span class="status" role="status"></span>
        </form>
    <?php endforeach; ?>

    <p><a href="/dashboard">Back to the dashboard</a></p>

    <script>
        document.querySelectorAll('.threshold-form').forEach(function (form) {
            form.addEventListener('submit', async function (event) {
                event.preventDefault();
                var status = form.querySelector('.status');
                status.textContent = 'Saving...';

                try {
                    var response = await fetch('/thresholds', { method: 'POST', body: new FormData(form) });
                    var data = await response.json();
                    var firstError = Object.values(data.errors || {})[0];
                    status.textContent = firstError || data.message;
                } catch (error) {
                    status.textContent = 'Could not reach the server.';
                }
            });
        });
    </script>
</body>
</html>