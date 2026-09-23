<?php

$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'superstore';

// Connect (no database yet, since we're creating it)
$conn = new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 1. Create database
$conn->query("CREATE DATABASE IF NOT EXISTS superstore");
$conn->select_db('superstore');

// 2. Create table
$db = new PDO("mysql:host=$host;dbname=superstore", $user, $pass);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = file_get_contents(__DIR__ . '/db.sql');

try {
    $db->exec($sql);
    echo "Database and table created successfully.";
} catch (PDOException $e) {
    echo "Error creating database or table: " . $e->getMessage();
}
