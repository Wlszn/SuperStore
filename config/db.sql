Create Database superstore;

Create Table Customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    address VARCHAR(255),
    phone VARCHAR(15),
    createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


Insert Into Customers (name, email, phone, address, createdAt) Values
('John Doe', 'john.doe@example.com', '123-456-7890', '123 Main St', NOW()),
('Jane Smith', 'jane.smith@example.com', '098-765-4321', '456 Oak Ave', NOW()),
('Alice Johnson', 'alice.johnson@example.com', '555-555-5555', '789 Pine Rd', NOW());

