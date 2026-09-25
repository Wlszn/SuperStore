<?php

declare(strict_types=1);

$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

$host = $_ENV['DB_HOST'] ?? 'localhost';
$port = $_ENV['DB_PORT'] ?? '3306';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';
$dbname = $_ENV['DB_NAME'] ?? 'superstore';


try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
        $user,
        $pass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}


// Connect (no database yet, since we're creating it)
// $conn = new mysqli($host, $user, $pass);

// if ($conn->connect_error) {
//     die("Connection failed: " . $conn->connect_error);
// }

// // 1. Create database
// $conn->query("CREATE DATABASE IF NOT EXISTS superstore");
// $conn->select_db('superstore');

// // 2. Create table
// $db = new PDO("mysql:host=$host;dbname=superstore", $user, $pass);
// $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// $sql = file_get_contents(__DIR__ . '/db.sql');

// try {
//     $db->exec($sql);
//     echo "Database and table created successfully.";
// } catch (PDOException $e) {
//     echo "Error creating database or table: " . $e->getMessage();
// }
