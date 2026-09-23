Create Database superstore;

Create Table Customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    address VARCHAR(255),
    phone_number VARCHAR(15),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

Create Table Products (
    product_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    stock_quantity INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

Insert Into Customers (name, email, phone_number, address) Values
('John Doe', 'john.doe@example.com', '123-456-7890', '123 Main St'),
('Jane Smith', 'jane.smith@example.com', '098-765-4321', '456 Oak Ave'),
('Alice Johnson', 'alice.johnson@example.com', '555-555-5555', '789 Pine Rd');



Insert Into Products (name, description, price, stock_quantity) Values
('Laptop', 'High-performance laptop', 999.99, 50),
('Smartphone', 'Latest model smartphone', 699.99, 100),
('Headphones', 'Noise-cancelling headphones', 199.99, 200);