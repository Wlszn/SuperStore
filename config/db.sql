Create Table customers (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    address VARCHAR(255),
    phone VARCHAR(15),
    password_hash VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

Insert Into customers (name, email, phone, address, created_at) Values
('Iulia Api', 'iuliaapi@gmail.com', '123-456-7890', '246 Boul Sam', NOW()),
('Sam Wil', 'samWil@gmail.com', '098-765-4321', '964 Sherb Ave', NOW()),
('Will', 'will102@yahoo.com', '555-555-5555', '876 St-Jean Rd', NOW());