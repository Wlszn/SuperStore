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


-- Phase 3: product management, inventory and self-checkout
CREATE SEQUENCE membership_number_seq START 100001;

ALTER TABLE customers
  ADD COLUMN is_admin           BOOLEAN NOT NULL DEFAULT FALSE,
  ADD COLUMN membership_number  VARCHAR(10) NOT NULL UNIQUE
                                DEFAULT ('SS' || nextval('membership_number_seq')),
  ADD COLUMN points           INT NOT NULL DEFAULT 0 CHECK (points >= 0);

-- Each product is defined once...
CREATE TABLE products (
  id          INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  name        VARCHAR(100)  NOT NULL,
  category    VARCHAR(50)   NOT NULL,
  price       NUMERIC(10,2) NOT NULL CHECK (price >= 0),
  upc         VARCHAR(13)   UNIQUE,          -- barcode
  epc         VARCHAR(24)   UNIQUE,          -- RFID tag, uppercase hex
  producer    VARCHAR(100)  NOT NULL,
  image_url   TEXT,
  created_at  TIMESTAMPTZ   NOT NULL DEFAULT now(),
  CHECK (upc IS NOT NULL OR epc IS NOT NULL)
);

-- ...but received many times (one product -> many receptions).
CREATE TABLE receptions (
  id           INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  product_id   INT  NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  quantity     INT  NOT NULL CHECK (quantity > 0),
  received_at  DATE NOT NULL DEFAULT CURRENT_DATE
);
CREATE INDEX idx_receptions_product ON receptions (product_id);

-- One checkout = one purchase (the receipt). customer_id is NULL for a
-- guest who skipped the membership card.
CREATE TABLE purchases (
  id             INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  customer_id    INT REFERENCES customers(id) ON DELETE SET NULL,
  total          NUMERIC(10,2) NOT NULL CHECK (total >= 0),
  points_earned  INT NOT NULL DEFAULT 0 CHECK (points_earned >= 0),
  purchased_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_purchases_customer ON purchases (customer_id, purchased_at);


  -- The lines of a receipt. Name and price are copied at checkout time so
-- old receipts stay correct after a product is edited or deleted.
CREATE TABLE purchase_items (
  id           INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  purchase_id  INT NOT NULL REFERENCES purchases(id) ON DELETE CASCADE,
  product_id   INT REFERENCES products(id) ON DELETE SET NULL,
  name         VARCHAR(100)  NOT NULL,
  unit_price   NUMERIC(10,2) NOT NULL CHECK (unit_price >= 0),
  quantity     INT NOT NULL CHECK (quantity > 0)
);
CREATE INDEX idx_purchase_items_purchase ON purchase_items (purchase_id);
CREATE INDEX idx_purchase_items_product  ON purchase_items (product_id);

-- Tags read by the RFID reader (scripts/rfid.py posts them to /api/scans).
-- The product form and the checkout page pick them up from here.
CREATE TABLE rfid_scans (
  id          INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  epc         VARCHAR(24) NOT NULL,
  scanned_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Stock is computed, never stored: everything received minus everything sold.
CREATE VIEW product_stock WITH (security_invoker = true) AS
SELECT p.*,
       ( COALESCE((SELECT SUM(r.quantity) FROM receptions r     WHERE r.product_id = p.id), 0)
       - COALESCE((SELECT SUM(i.quantity) FROM purchase_items i WHERE i.product_id = p.id), 0)
       )::INT AS in_stock
FROM products p;

ALTER TABLE products       ENABLE ROW LEVEL SECURITY;
ALTER TABLE receptions     ENABLE ROW LEVEL SECURITY;
ALTER TABLE purchases      ENABLE ROW LEVEL SECURITY;
ALTER TABLE purchase_items ENABLE ROW LEVEL SECURITY;
ALTER TABLE rfid_scans     ENABLE ROW LEVEL SECURITY;

-- Demo data. Replace the EPCs with the ones printed on your own tags.
INSERT INTO products (name, category, price, upc, epc, producer) VALUES
  ('Coca-Cola 500ml',      'Beverage', 2.49, '0067000004114', 'E28011700000020A1B2C3D01', 'The Coca-Cola Company'),
  ('Lay''s Classic 235g',  'Snacks',   4.99, '0060410013304', 'E28011700000020A1B2C3D02', 'Frito-Lay'),
  ('Natrel 2% Milk 2L',    'Dairy',    5.29, '0068700011054', 'E28011700000020A1B2C3D03', 'Natrel');

INSERT INTO receptions (product_id, quantity, received_at) VALUES
  (1, 24, CURRENT_DATE - 7), (1, 12, CURRENT_DATE),
  (2, 10, CURRENT_DATE - 3),
  (3,  4, CURRENT_DATE);

-- Make your own account an admin (change the email):
-- UPDATE customers SET is_admin = TRUE WHERE email = 'you@example.com';
