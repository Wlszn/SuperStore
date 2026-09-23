<?php

$host = 'localhost';
$user = 'root';
$pass = '';

// Connect (no database yet, since we're creating it)
$conn = new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 1. Create database
$conn->query("CREATE DATABASE IF NOT EXISTS superstore");
$conn->select_db('superstore');

// 2. Create table
$createTable = "
CREATE TABLE IF NOT EXISTS Customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    address VARCHAR(255),
    phone VARCHAR(15),
    createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($createTable);

// 3. Insert data
$insert = "
INSERT INTO Customers (name, email, phone, address, createdAt) VALUES
('John Doe', 'john.doe@example.com', '123-456-7890', '123 Main St', NOW()),
('Jane Smith', 'jane.smith@example.com', '098-765-4321', '456 Oak Ave', NOW()),
('Alice Johnson', 'alice.johnson@example.com', '555-555-5555', '789 Pine Rd', NOW())
";
$conn->query($insert);

echo "Database, table, and records created successfully!";
$conn->close();