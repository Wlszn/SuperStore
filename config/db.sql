Create Table customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    address VARCHAR(255),
    phone VARCHAR(15),
    createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

Create Table fridge {
    id int PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    max_temp decimal(4,1) NOT NULL DEFAULT 8.0
}

Create Table readings {
    id int PRIMARY KEY AUTO_INCREMENT,
    fridge_id int NOT NULL,
    temperature decimal(4,1) NOT NULL DEFAULT 8.0,
    humidity decimal(4,1) NOT NULL DEFAULT 8.0
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fridge_id) REFERENCES fridge(id),
    INDEX (fridge_id, recorded_at)
}

CREATE TABLE fan_state (
  id          INT PRIMARY KEY DEFAULT 1,            
  is_on       BOOLEAN NOT NULL DEFAULT FALSE,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE alerts (
  id           INT PRIMARY KEY AUTO_INCREMENT,
  fridge_id    INT NOT NULL,
  temperature  DECIMAL(4,1) NOT NULL,
  sent_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  response     ENUM('pending','yes','no') NOT NULL DEFAULT 'pending',
  FOREIGN KEY (fridge_id) REFERENCES fridge(id)
);


Insert Into customers (name, email, phone, address, createdAt) Values
('John Doe', 'john.doe@example.com', '123-456-7890', '123 Main St', NOW()),
('Jane Smith', 'jane.smith@example.com', '098-765-4321', '456 Oak Ave', NOW()),
('Alice Johnson', 'alice.johnson@example.com', '555-555-5555', '789 Pine Rd', NOW());

INSERT INTO fridge (name, max_temp) VALUES ('Frig1', 8.0), ('Frig2', 8.0);
INSERT INTO fan_state (id, is_on) VALUES (1, FALSE);