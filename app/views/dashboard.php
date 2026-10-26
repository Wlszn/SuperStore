<?php

if (!isset($_SESSION['customer_id'])) {
    header('Location: /login');
    exit;
}

$customerName = $_SESSION['customer_name'] ?? 'there';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SuperStore - Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .dashboard-shell { max-width: 1100px; margin: 0 auto; }
        .dashboard-header { display: flex; justify-content: space-between; gap: 16px; align-items: center; margin-bottom: 24px; }
        .dashboard-header h1 { text-align: left; margin: 0; }
        .dashboard-header p { color: #606770; margin: 4px 0 0; }
        .dashboard-nav { display: flex; gap: 14px; align-items: center; }
        .dashboard-nav a { color: #1877f2; text-decoration: none; }
        .dashboard-nav a:hover { text-decoration: underline; }
        .dashboard-status { background: #fff; border: 1px solid #dddfe2; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; color: #606770; }
        .dashboard-status.error { color: #c5221f; background: #fce8e6; border-color: #f5c6c2; }
        .alert-banner { display: none; background: #fff4e5; border: 1px solid #f0b429; border-radius: 8px; color: #7a4b00; padding: 14px 16px; margin-bottom: 16px; }
        .alert-banner.visible { display: block; }
        .fridge-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .fridge-card { background: #fff; border: 1px solid #dddfe2; border-radius: 8px; overflow: hidden; }
        .fridge-card.alerting { border-color: #f0b429; }
        .fridge-photo { width: 100%; height: 150px; object-fit: cover; display: block; background: #dce8f2; }
        .fridge-content { padding: 20px; }
        .fridge-heading { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; margin-bottom: 16px; }
        .fridge-card h2 { font-size: 1.15rem; margin: 0; }
        .threshold { color: #606770; font-size: .85rem; white-space: nowrap; }
        .gauge-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .gauge { border: 1px solid #e4e6eb; border-radius: 6px; padding: 14px; text-align: center; }
        .gauge-dial { --gauge-fill: #1877f2; --gauge-value: 0%; width: 112px; height: 112px; margin: 0 auto 10px; border-radius: 50%; display: grid; place-items: center; background: conic-gradient(var(--gauge-fill) var(--gauge-value), #e4e6eb 0); position: relative; }
        .gauge-dial::after { content: ''; width: 82px; height: 82px; background: #fff; border-radius: 50%; position: absolute; }
        .gauge-reading { position: relative; z-index: 1; font-size: 1.15rem; font-weight: 700; }
        .gauge.temperature .gauge-dial { --gauge-fill: #e05a47; }
        .gauge.humidity .gauge-dial { --gauge-fill: #1877f2; }
        .gauge-label { color: #606770; font-size: .9rem; }
        .temperature-state { display: flex; align-items: center; gap: 8px; margin-top: 14px; padding: 8px 10px; border-radius: 6px; background: #f0f2f5; font-size: .9rem; }
        .temperature-state img, .fan-icon { width: 34px; height: 34px; object-fit: cover; border-radius: 4px; }
        .temperature-state.high { background: #fce8e6; color: #c5221f; }
        .temperature-state.low { background: #e8f0fe; color: #174ea6; }
        .temperature-state.normal { background: #e6f4ea; color: #137333; }
        .fan-row { display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #e4e6eb; margin-top: 16px; padding-top: 14px; }
        .fan-label { display: flex; align-items: center; gap: 8px; }
        .fan-on { color: #137333; font-weight: 700; }
        .fan-off { color: #606770; font-weight: 700; }
        @media (max-width: 640px) {
            body { padding: 24px 12px; }
            .dashboard-header { align-items: flex-start; flex-direction: column; }
            .fridge-grid { grid-template-columns: 1fr; }
            .fridge-photo { height: 125px; }
        }
    </style>
</head>
<body>
<div class="dashboard-shell">
    <header class="dashboard-header">
        <div>
            <h1>Store dashboard</h1>
            <p>Welcome, <?= htmlspecialchars($customerName, ENT_QUOTES) ?>. Live refrigerator status.</p>
        </div>
        <nav class="dashboard-nav" aria-label="Dashboard navigation">
            <a href="/thresholds">Thresholds</a>
            <a href="/logout">Sign out</a>
        </nav>
    </header>

    <div id="dashboardStatus" class="dashboard-status" role="status">Connecting to sensors...</div>
    <div id="alertBanner" class="alert-banner" role="alert"></div>

    <main class="fridge-grid">
        <?php
        $fridgePhotos = [
            1 => 'https://www.nuvending.co.uk/wp-content/uploads/2024/08/Cantaloupe-Kiln-Farm-shoot-11-1.jpg',
            2 => 'https://images.samsung.com/is/image/samsung/assets/us/explore/family-hub-refrigerator/overview/10232024/DA-FHMLP-FT02-ControlYourHome-MO.png?$720_N_PNG$',
        ];
        foreach ([1, 2] as $fridge): ?>
            <section class="fridge-card" id="fridge-card-<?= $fridge ?>" aria-labelledby="fridge-<?= $fridge ?>-title">
                <img class="fridge-photo" src="<?= htmlspecialchars($fridgePhotos[$fridge], ENT_QUOTES) ?>" alt="Refrigerator <?= $fridge ?> storage area" onerror="this.onerror=null; this.src='https://commons.wikimedia.org/wiki/Special:FilePath/Refrigerator.jpg';">
                <div class="fridge-content">
                    <div class="fridge-heading">
                        <h2 id="fridge-<?= $fridge ?>-title">Refrigerator <?= $fridge ?></h2>
                        <span class="threshold" id="threshold-<?= $fridge ?>">Limit: -- °C</span>
                    </div>
                    <div class="gauge-grid">
                        <div class="gauge temperature">
                            <div class="gauge-dial" id="temp-dial-<?= $fridge ?>"><strong class="gauge-reading" id="temp-<?= $fridge ?>">-- °C</strong></div>
                            <span class="gauge-label">Temperature</span>
                        </div>
                        <div class="gauge humidity">
                            <div class="gauge-dial" id="humidity-dial-<?= $fridge ?>"><strong class="gauge-reading" id="humidity-<?= $fridge ?>">-- %</strong></div>
                            <span class="gauge-label">Humidity</span>
                        </div>
                    </div>
                    <div class="temperature-state" id="temperature-state-<?= $fridge ?>">
                        <img id="temperature-state-image-<?= $fridge ?>" src="https://m.media-amazon.com/images/I/71kNnoNhufL.jpg" alt="Temperature status">
                        <span id="temperature-state-label-<?= $fridge ?>">Waiting for temperature...</span>
                    </div>
                <div class="fan-row">
                    <span class="fan-label"><img class="fan-icon" src="https://guide-images.cdn.ifixit.com/igi/JoJrZQCOY5QF6Vkr.full" alt="Fan"> Fan status</span>
                    <strong id="fan-<?= $fridge ?>" class="fan-off">OFF</strong>
                </div>
                </div>
            </section>
        <?php endforeach; ?>
    </main>
</div>

<script>
    var alertingFridges = new Set();

    function setGauge(valueId, dialId, value, suffix, percent) {
        var valueElement = document.getElementById(valueId);
        var dialElement = document.getElementById(dialId);
        if (value === null || value === undefined || Number.isNaN(Number(value))) return;
        valueElement.textContent = Number(value).toFixed(1) + suffix;
        dialElement.style.setProperty('--gauge-value', Math.max(0, Math.min(100, percent)) + '%');
    }

    function setThreshold(id, threshold) {
        var element = document.getElementById(id);
        if (element && threshold !== null && threshold !== undefined) {
            element.textContent = 'Limit: ' + Number(threshold).toFixed(1) + ' °C';
        }
    }

    function setTemperatureState(fridge, temperature, threshold) {
        var state = document.getElementById('temperature-state-' + fridge);
        var image = document.getElementById('temperature-state-image-' + fridge);
        var label = document.getElementById('temperature-state-label-' + fridge);
        if (!state || temperature === undefined || threshold === undefined) return;

        var stateName = Number(temperature) > Number(threshold) ? 'high' : Number(temperature) < 0 ? 'low' : 'normal';
        var stateText = stateName === 'high'
            ? 'Temperature is too high'
            : stateName === 'low'
                ? 'Temperature is too low'
                : 'Temperature is in the safe range';
        var stateImage = stateName === 'high'
            ? 'https://tillmansequipment.com/wp-content/uploads/2019/07/3507FS-a.jpg'
            : stateName === 'low'
                ? 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQemApkwxKFCfMJiJbxXsQFS2QL_oXs4bl4zcToZ0E-aRILTewcji3DKFVO&s=10'
                : 'https://commons.wikimedia.org/wiki/Special:FilePath/Thermometer.jpg';

        state.className = 'temperature-state ' + stateName;
        image.src = stateImage;
        image.alt = stateText;
        label.textContent = stateText;
    }

    function setValue(id, value, suffix) {
        var element = document.getElementById(id);
        if (element && value !== null && value !== undefined) {
            element.textContent = Number(value).toFixed(1) + suffix;
        }
    }

    function setFan(id, isOn) {
        var element = document.getElementById(id);
        if (!element) return;
        element.textContent = isOn ? 'ON' : 'OFF';
        element.className = isOn ? 'fan-on' : 'fan-off';
    }

    async function loadDashboard() {
        var status = document.getElementById('dashboardStatus');
        var alertBanner = document.getElementById('alertBanner');
        try {
            var readingsResponse = await fetch('/api/readings/latest');
            var readings = await readingsResponse.json();
            var fridgeRows = readings.fridges || [];
            var alerts = [];

            fridgeRows.forEach(function (fridge) {
                var number = Number(fridge.id);
                var reading = fridge.reading || {};
                var temperature = reading.temperature;
                var humidity = reading.humidity;
                var threshold = fridge.max_temp;

                setThreshold('threshold-' + number, threshold);
                if (temperature !== undefined) {
                    setGauge('temp-' + number, 'temp-dial-' + number, temperature, ' °C', (Number(temperature) + 10) / 60 * 100);
                    setTemperatureState(number, temperature, threshold);
                }
                if (humidity !== undefined) {
                    setGauge('humidity-' + number, 'humidity-dial-' + number, humidity, ' %', humidity);
                }

                var card = document.getElementById('fridge-card-' + number);
                if (fridge.over_threshold) {
                    card.classList.add('alerting');
                    alerts.push(fridge.name + ' is at ' + Number(temperature).toFixed(1) + ' °C, above its ' + Number(threshold).toFixed(1) + ' °C limit. The Raspberry Pi alert service will email the user: “The current temperature is ' + Number(temperature).toFixed(1) + ' °C. Would you like to turn on the fan?”');
                    if (!alertingFridges.has(number) && 'Notification' in window && Notification.permission === 'granted') {
                        new Notification(fridge.name + ' temperature alert', { body: 'Current temperature: ' + Number(temperature).toFixed(1) + ' °C. Above the ' + Number(threshold).toFixed(1) + ' °C limit.' });
                    }
                    alertingFridges.add(number);
                } else {
                    card.classList.remove('alerting');
                    alertingFridges.delete(number);
                }
            });

            var fanResponse = await fetch('/api/fan');
            var fanData = await fanResponse.json();
            var isFanOn = Boolean(fanData.fan && fanData.fan.is_on);
            [1, 2].forEach(function (fridge) {
                setFan('fan-' + fridge, isFanOn);
            });

            status.textContent = 'Last updated: ' + new Date().toLocaleTimeString();
            status.className = 'dashboard-status';
            alertBanner.textContent = alerts.length ? alerts.join(' ') : 'Temperature status is normal. No active alerts.';
            alertBanner.className = alerts.length ? 'alert-banner visible' : 'alert-banner';
        } catch (error) {
            status.textContent = 'Sensor data is temporarily unavailable. Retrying...';
            status.className = 'dashboard-status error';
        }
    }

    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
    loadDashboard();
    setInterval(loadDashboard, 10000);
</script>
</body>
</html>
