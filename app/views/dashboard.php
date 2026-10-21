<?php

// This page is only for signed-in users.
if (!isset($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit;
}

$customerName = $_SESSION['customer_name'] ?? 'there';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>SuperStore - Dashboard</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

<div class="topbar">
    <span class="brand">SuperStore</span>
    <span>
        <a href="../index.php?action=logout">Sign out</a>
        <a href="/thresholds">Fridge thresholds</a>
    </span>
</div>

<div class="container">

    <h1>Welcome, <?= htmlspecialchars($customerName) ?>!</h1>
    <p class="subtitle">You are signed in to SuperStore.</p>

</div>

</body>
</html>
