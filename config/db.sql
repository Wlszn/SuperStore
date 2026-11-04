CREATE TABLE fridge (
  id        INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  name      VARCHAR(50) NOT NULL UNIQUE,
  max_temp  NUMERIC(4,1) NOT NULL DEFAULT 8.0
);

CREATE TABLE readings (
  id           INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  fridge_id    INT NOT NULL REFERENCES fridge(id),
  temperature  NUMERIC(4,1) NOT NULL,
  humidity     NUMERIC(4,1) NOT NULL,
  recorded_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_readings_fridge_time ON readings (fridge_id, recorded_at);

CREATE TABLE fan_state (
  id          INT PRIMARY KEY DEFAULT 1 CHECK (id = 1),
  is_on       BOOLEAN NOT NULL DEFAULT FALSE,
  updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE alerts (
  id           INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  fridge_id    INT NOT NULL REFERENCES fridge(id),
  temperature  NUMERIC(4,1) NOT NULL,
  sent_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
  response     VARCHAR(7) NOT NULL DEFAULT 'pending'
               CHECK (response IN ('pending','yes','no'))
);

INSERT INTO fridge (name, max_temp) VALUES ('Frig1', 8.0), ('Frig2', 8.0);
INSERT INTO fan_state (id, is_on) VALUES (1, FALSE);

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

-- Phase 3: product management and inventory

CREATE TABLE products (
  id          INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  category    VARCHAR(50)  NOT NULL,
  price       NUMERIC(10,2) NOT NULL CHECK (price >= 0),
  upc         VARCHAR(13) UNIQUE,
  epc         VARCHAR(24) UNIQUE,
  producer    VARCHAR(100) NOT NULL,
  created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
  CHECK (upc IS NOT NULL OR epc IS NOT NULL)
);

-- One product, many receptions (each delivery of stock).
CREATE TABLE receptions (
  id           INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  product_id   INT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  quantity     INT NOT NULL CHECK (quantity > 0),
  received_at  DATE NOT NULL DEFAULT CURRENT_DATE
);
CREATE INDEX idx_receptions_product ON receptions (product_id);

-- Stock is computed, never stored. Subtract sold quantities here once the checkout tables exist.
CREATE VIEW product_stock WITH (security_invoker = true) AS
SELECT p.*,
       COALESCE((SELECT SUM(r.quantity) FROM receptions r WHERE r.product_id = p.id), 0)::INT AS in_stock
FROM products p;

ALTER TABLE products   ENABLE ROW LEVEL SECURITY;
ALTER TABLE receptions ENABLE ROW LEVEL SECURITY;
