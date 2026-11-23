-- Run after config/db.sql. Safe to run again.
-- Also upgrades a database created before product types and tagged items existed.
BEGIN;
-- Membership card codes up to 64 characters (a scanned card can be longer than SS100001).
ALTER TABLE customers ALTER COLUMN membership_number TYPE varchar(64);
ALTER TABLE purchases ADD COLUMN IF NOT EXISTS request_key text UNIQUE;
ALTER TABLE purchases ADD COLUMN IF NOT EXISTS points_redeemed integer NOT NULL DEFAULT 0 CHECK (points_redeemed >= 0);
ALTER TABLE purchases ADD COLUMN IF NOT EXISTS payment_method text NOT NULL DEFAULT 'card' CHECK (payment_method IN ('card', 'cash'));

-- Categories: a fixed list the product form offers.
CREATE TABLE IF NOT EXISTS categories (name varchar(50) PRIMARY KEY);
ALTER TABLE categories ENABLE ROW LEVEL SECURITY;
INSERT INTO categories (name) VALUES
  ('Beverages'), ('Snacks'), ('Chocolate & candy'), ('Dairy'), ('Bakery'),
  ('Frozen'), ('Produce'), ('Household'), ('Personal care')
ON CONFLICT DO NOTHING;
UPDATE products SET category = 'Beverages' WHERE category = 'Beverage';
INSERT INTO categories (name) SELECT DISTINCT category FROM products ON CONFLICT DO NOTHING; -- keep older ones valid
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'products_category_fkey') THEN
    ALTER TABLE products ADD CONSTRAINT products_category_fkey
      FOREIGN KEY (category) REFERENCES categories(name) ON UPDATE CASCADE;
  END IF;
END $$;

-- Items: one row per RFID tag, so a type can have many tagged items in stock.
CREATE TABLE IF NOT EXISTS items (
  id            integer GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  epc           varchar(24) NOT NULL UNIQUE,
  product_id    integer NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  reception_id  integer REFERENCES receptions(id) ON DELETE CASCADE,
  purchase_id   integer REFERENCES purchases(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_items_product ON items (product_id) WHERE purchase_id IS NULL;
ALTER TABLE items ENABLE ROW LEVEL SECURITY;

-- Older databases stored one EPC on the product: move it to an item, then drop the column.
DROP VIEW IF EXISTS product_stock;
DO $$ BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'products' AND column_name = 'epc') THEN
    INSERT INTO items (epc, product_id) SELECT epc, id FROM products WHERE epc IS NOT NULL ON CONFLICT DO NOTHING;
    ALTER TABLE products DROP COLUMN epc;
  END IF;
END $$;

-- Stock = tagged items of this type that are not sold yet.
CREATE VIEW product_stock WITH (security_invoker = true) AS
SELECT p.*,
       (SELECT COUNT(*) FROM items i WHERE i.product_id = p.id AND i.purchase_id IS NULL)::int AS in_stock
FROM products p;

-- Receive stock: every tag on the reader becomes one item of the chosen type, in one transaction.
CREATE OR REPLACE FUNCTION receive_items(p_product integer, p_received date, p_epcs jsonb)
RETURNS jsonb LANGUAGE plpgsql SET search_path = public AS $$
DECLARE
    reception receptions;
    taken text;
BEGIN
    IF jsonb_typeof(p_epcs) <> 'array' OR jsonb_array_length(p_epcs) NOT BETWEEN 1 AND 100 THEN
        RAISE EXCEPTION 'Invalid delivery';
    END IF;
    SELECT i.epc INTO taken FROM items i
    WHERE i.epc IN (SELECT upper(x) FROM jsonb_array_elements_text(p_epcs) x) LIMIT 1;
    IF FOUND THEN
        RAISE EXCEPTION USING ERRCODE = 'PT422', MESSAGE = 'Tag ' || taken || ' is already registered.';
    END IF;

    INSERT INTO receptions (product_id, quantity, received_at)
    VALUES (p_product, (SELECT count(DISTINCT upper(x)) FROM jsonb_array_elements_text(p_epcs) x), p_received)
    RETURNING * INTO reception;
    INSERT INTO items (epc, product_id, reception_id)
    SELECT DISTINCT upper(x), p_product, reception.id FROM jsonb_array_elements_text(p_epcs) x;
    RETURN to_jsonb(reception);
END $$;

-- Checkout by tags: the basket is the list of EPCs the reader saw.
-- Older versions took (product id, quantity) lines; drop them first.
DROP FUNCTION IF EXISTS complete_checkout(text, integer, jsonb);
DROP FUNCTION IF EXISTS complete_checkout(text, integer, jsonb, integer);
CREATE FUNCTION complete_checkout(p_key text, p_customer integer, p_epcs jsonb, p_redeem_points integer DEFAULT 0)
RETURNS jsonb LANGUAGE plpgsql SET search_path = public AS $$
DECLARE
    receipt purchases;
    tags text[];
    available integer;
    amount numeric;
    amount_due numeric;
    balance integer;
    earned integer;
BEGIN
    IF length(p_key) <> 64 OR jsonb_typeof(p_epcs) <> 'array' OR jsonb_array_length(p_epcs) NOT BETWEEN 1 AND 100 THEN
        RAISE EXCEPTION 'Invalid checkout';
    END IF;
    IF p_redeem_points IS NULL OR p_redeem_points < 0 OR (p_redeem_points > 0 AND p_customer IS NULL) THEN
        RAISE EXCEPTION 'Invalid points redemption';
    END IF;

    -- The same checkout sent twice (double click, retry) returns the first receipt.
    PERFORM pg_advisory_xact_lock(hashtextextended(p_key, 0));
    SELECT * INTO receipt FROM purchases WHERE request_key = p_key;
    IF FOUND THEN RETURN to_jsonb(receipt); END IF;

    tags := ARRAY(SELECT DISTINCT upper(x) FROM jsonb_array_elements_text(p_epcs) x);
    -- Lock the items so two checkouts cannot sell the same item.
    PERFORM 1 FROM items WHERE epc = ANY(tags) ORDER BY id FOR UPDATE;
    SELECT count(*), sum(p.price) INTO available, amount
    FROM items i JOIN products p ON p.id = i.product_id
    WHERE i.epc = ANY(tags) AND i.purchase_id IS NULL;
    IF available <> cardinality(tags) THEN
        RAISE EXCEPTION USING ERRCODE = 'PT422',
            MESSAGE = 'An item in your basket is unknown or already sold. Remove it and try again.';
    END IF;

    -- Check the balance under lock so parallel orders cannot spend the same points.
    IF p_customer IS NOT NULL THEN
        SELECT points INTO STRICT balance FROM customers WHERE id = p_customer FOR UPDATE;
        IF p_redeem_points > balance THEN
            RAISE EXCEPTION USING ERRCODE = 'PT422', MESSAGE = 'Not enough points for this discount.';
        END IF;
    END IF;

    amount_due := amount - p_redeem_points / 100.0;
    IF amount_due < 0 THEN
        RAISE EXCEPTION USING ERRCODE = 'PT422', MESSAGE = 'The points discount cannot exceed the order total.';
    END IF;
    earned := CASE WHEN p_customer IS NULL THEN 0 ELSE floor(amount_due * 10)::integer END;

    INSERT INTO purchases (customer_id, total, points_earned, points_redeemed, request_key)
    VALUES (p_customer, amount_due, earned, p_redeem_points, p_key) RETURNING * INTO receipt;

    -- One receipt line per type: "KitKat 4 Finger 45g x 5".
    INSERT INTO purchase_items (purchase_id, product_id, name, unit_price, quantity)
    SELECT receipt.id, p.id, p.name, p.price, count(*)
    FROM items i JOIN products p ON p.id = i.product_id
    WHERE i.epc = ANY(tags) GROUP BY p.id;
    UPDATE items SET purchase_id = receipt.id WHERE epc = ANY(tags);

    UPDATE customers SET points = points - p_redeem_points + earned WHERE id = p_customer;
    RETURN to_jsonb(receipt);
END $$;

REVOKE ALL ON FUNCTION complete_checkout(text, integer, jsonb, integer) FROM PUBLIC, anon, authenticated;
GRANT EXECUTE ON FUNCTION complete_checkout(text, integer, jsonb, integer) TO service_role;
REVOKE ALL ON FUNCTION receive_items(integer, date, jsonb) FROM PUBLIC, anon, authenticated;
GRANT EXECUTE ON FUNCTION receive_items(integer, date, jsonb) TO service_role;
COMMIT;
