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
  ADD COLUMN points             INT NOT NULL DEFAULT 0 CHECK (points >= 0);

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

-- =====================================================================
-- Phase 3b: self-checkout
-- Safe to run after the Phase 3 section (it only adds things and can be
-- run twice).
-- =====================================================================

-- customers.created_at was TIMESTAMP (no timezone) in Phase 1. Its values
-- were written by Supabase's NOW(), which runs in UTC.
DO $$
BEGIN
  IF (SELECT data_type FROM information_schema.columns
      WHERE table_schema = 'public' AND table_name = 'customers' AND column_name = 'created_at')
     = 'timestamp without time zone' THEN
    ALTER TABLE customers ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC';
  END IF;
END $$;
ALTER TABLE customers ALTER COLUMN created_at SET DEFAULT now();

-- How the customer paid (simulated).
ALTER TABLE purchases
  ADD COLUMN IF NOT EXISTS payment_method VARCHAR(10) NOT NULL DEFAULT 'card'
                           CHECK (payment_method IN ('card', 'cash'));

-- Valid check digit for the demo Coca-Cola barcode.
UPDATE products SET upc = '0067000004117' WHERE upc = '0067000004114';

-- One checkout, all or nothing: checks stock, saves the purchase and its
-- items, and adds the points. Called by PurchaseModel through
-- POST /rest/v1/rpc/checkout. Business problems (empty cart, not enough
-- stock...) come back as {"ok": false, "error": ...} instead of an exception,
-- so the page can show a clear message.
--   p_items: [{"product_id": 1, "quantity": 2}, ...]
--   Points strategy: members earn 1 point per whole dollar spent.
CREATE OR REPLACE FUNCTION checkout(p_customer_id INT, p_items JSONB, p_payment_method TEXT)
RETURNS JSONB
LANGUAGE plpgsql
SET search_path = public
AS $$
DECLARE
  v_line        RECORD;
  v_total       NUMERIC(10,2) := 0;
  v_points      INT := 0;
  v_purchase_id INT;
BEGIN
  IF p_items IS NULL OR jsonb_typeof(p_items) <> 'array' OR jsonb_array_length(p_items) = 0 THEN
    RETURN jsonb_build_object('ok', false, 'error', 'empty_cart');
  END IF;

  IF p_payment_method IS NULL OR p_payment_method NOT IN ('card', 'cash') THEN
    RETURN jsonb_build_object('ok', false, 'error', 'invalid_payment');
  END IF;

  IF p_customer_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM customers WHERE id = p_customer_id) THEN
    RETURN jsonb_build_object('ok', false, 'error', 'unknown_customer');
  END IF;

  -- The same product sent twice counts as one line.
  DROP TABLE IF EXISTS checkout_lines;
  CREATE TEMP TABLE checkout_lines ON COMMIT DROP AS
    SELECT (e->>'product_id')::INT AS product_id, SUM((e->>'quantity')::INT)::INT AS quantity
    FROM jsonb_array_elements(p_items) AS e
    GROUP BY 1;

  -- Lock the products being sold, so two checkouts can't sell the last unit twice.
  PERFORM 1 FROM products
  WHERE id IN (SELECT product_id FROM checkout_lines)
  ORDER BY id
  FOR UPDATE;

  FOR v_line IN
    SELECT l.product_id, l.quantity, p.id AS found, p.name, p.price, s.in_stock
    FROM checkout_lines l
    LEFT JOIN products p      ON p.id = l.product_id
    LEFT JOIN product_stock s ON s.id = l.product_id
  LOOP
    IF v_line.found IS NULL THEN
      RETURN jsonb_build_object('ok', false, 'error', 'unknown_product', 'product_id', v_line.product_id);
    END IF;
    IF v_line.quantity < 1 OR v_line.quantity > 99 THEN
      RETURN jsonb_build_object('ok', false, 'error', 'invalid_quantity', 'product', v_line.name);
    END IF;
    IF v_line.quantity > v_line.in_stock THEN
      RETURN jsonb_build_object('ok', false, 'error', 'out_of_stock',
                                'product', v_line.name, 'in_stock', GREATEST(v_line.in_stock, 0));
    END IF;
    v_total := v_total + v_line.price * v_line.quantity;
  END LOOP;

  IF p_customer_id IS NOT NULL THEN
    v_points := FLOOR(v_total)::INT;
  END IF;

  INSERT INTO purchases (customer_id, total, points_earned, payment_method)
  VALUES (p_customer_id, v_total, v_points, p_payment_method)
  RETURNING id INTO v_purchase_id;

  -- Name and price are copied, so the receipt never changes afterwards.
  INSERT INTO purchase_items (purchase_id, product_id, name, unit_price, quantity)
  SELECT v_purchase_id, p.id, p.name, p.price, l.quantity
  FROM checkout_lines l
  JOIN products p ON p.id = l.product_id
  ORDER BY p.name;

  IF p_customer_id IS NOT NULL THEN
    UPDATE customers SET points = points + v_points WHERE id = p_customer_id;
  END IF;

  RETURN jsonb_build_object('ok', true, 'purchase_id', v_purchase_id,
                            'total', v_total, 'points_earned', v_points);
END;
$$;

-- Only the server (secret key) may run a checkout, never the public key.
REVOKE ALL ON FUNCTION checkout(INT, JSONB, TEXT) FROM PUBLIC;
DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'anon') THEN
    REVOKE ALL ON FUNCTION checkout(INT, JSONB, TEXT) FROM anon, authenticated;
    GRANT EXECUTE ON FUNCTION checkout(INT, JSONB, TEXT) TO service_role;
  END IF;
END $$;
