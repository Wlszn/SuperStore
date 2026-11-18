-- Run after config/db.sql. Safe to run again.
BEGIN;
-- Membership card codes up to 64 characters (a scanned card can be longer than SS100001).
ALTER TABLE customers ALTER COLUMN membership_number TYPE varchar(64);
ALTER TABLE purchases ADD COLUMN IF NOT EXISTS request_key text UNIQUE;
ALTER TABLE purchases ADD COLUMN IF NOT EXISTS points_redeemed integer NOT NULL DEFAULT 0 CHECK (points_redeemed >= 0);
ALTER TABLE purchases ADD COLUMN IF NOT EXISTS payment_method text NOT NULL DEFAULT 'card' CHECK (payment_method IN ('card', 'cash'));

CREATE OR REPLACE FUNCTION complete_checkout(p_key text, p_customer integer, p_items jsonb, p_redeem_points integer)
RETURNS jsonb LANGUAGE plpgsql SET search_path = public AS $$
DECLARE
    receipt purchases;
    item record;
    product products;
    stock bigint;
    amount numeric := 0;
    amount_due numeric;
    balance integer;
    earned integer;
BEGIN
    IF length(p_key) <> 64 OR jsonb_typeof(p_items) <> 'array' OR jsonb_array_length(p_items) NOT BETWEEN 1 AND 100 THEN
        RAISE EXCEPTION 'Invalid checkout';
    END IF;
    IF p_redeem_points IS NULL OR p_redeem_points < 0 OR (p_redeem_points > 0 AND p_customer IS NULL) THEN
        RAISE EXCEPTION 'Invalid points redemption';
    END IF;

    PERFORM pg_advisory_xact_lock(hashtextextended(p_key, 0));
    SELECT * INTO receipt FROM purchases WHERE request_key = p_key;
    IF FOUND THEN RETURN to_jsonb(receipt); END IF;

    FOR item IN
        SELECT (x->>'id')::integer id, sum((x->>'quantity')::integer)::integer quantity
        FROM jsonb_array_elements(p_items) x GROUP BY 1 ORDER BY 1
    LOOP
        IF item.quantity NOT BETWEEN 1 AND 1000 THEN RAISE EXCEPTION 'Invalid quantity'; END IF;
        SELECT * INTO STRICT product FROM products WHERE id = item.id FOR UPDATE;
        SELECT in_stock INTO stock FROM product_stock WHERE id = item.id;
        IF stock < item.quantity THEN RAISE EXCEPTION 'Insufficient stock'; END IF;
        amount := amount + product.price * item.quantity;
    END LOOP;

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

    INSERT INTO purchases(customer_id,total,points_earned,points_redeemed,request_key)
    VALUES(p_customer,amount_due,earned,p_redeem_points,p_key) RETURNING * INTO receipt;

    INSERT INTO purchase_items(purchase_id,product_id,name,unit_price,quantity)
    SELECT receipt.id,p.id,p.name,p.price,sum((x->>'quantity')::integer)
    FROM jsonb_array_elements(p_items) x JOIN products p ON p.id=(x->>'id')::integer GROUP BY p.id;

    UPDATE customers SET points = points - p_redeem_points + earned WHERE id = p_customer;
    RETURN to_jsonb(receipt);
END $$;

CREATE OR REPLACE FUNCTION complete_checkout(p_key text, p_customer integer, p_items jsonb)
RETURNS jsonb LANGUAGE sql SET search_path = public AS $$
    SELECT complete_checkout(p_key, p_customer, p_items, 0);
$$;
REVOKE ALL ON FUNCTION complete_checkout(text,integer,jsonb,integer) FROM PUBLIC, anon, authenticated;
REVOKE ALL ON FUNCTION complete_checkout(text,integer,jsonb) FROM PUBLIC, anon, authenticated;
GRANT EXECUTE ON FUNCTION complete_checkout(text,integer,jsonb,integer) TO service_role;
GRANT EXECUTE ON FUNCTION complete_checkout(text,integer,jsonb) TO service_role;
COMMIT;
