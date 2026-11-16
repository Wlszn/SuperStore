-- Run after config/db.sql. Safe to run again.
ALTER TABLE purchases ADD COLUMN IF NOT EXISTS request_key text UNIQUE;
CREATE OR REPLACE FUNCTION complete_checkout(p_key text, p_customer integer, p_items jsonb)
RETURNS jsonb LANGUAGE plpgsql SET search_path = public AS $$
DECLARE receipt purchases; item record; product products; stock bigint; amount numeric := 0; earned integer;
BEGIN
 IF length(p_key) <> 64 OR jsonb_typeof(p_items) <> 'array' OR jsonb_array_length(p_items) NOT BETWEEN 1 AND 100 THEN
   RAISE EXCEPTION 'Invalid checkout';
 END IF;
 PERFORM pg_advisory_xact_lock(hashtextextended(p_key, 0));
 SELECT * INTO receipt FROM purchases WHERE request_key = p_key;
 IF FOUND THEN RETURN to_jsonb(receipt); END IF;
 FOR item IN SELECT (x->>'id')::integer id, sum((x->>'quantity')::integer)::integer quantity
 FROM jsonb_array_elements(p_items) x GROUP BY 1 ORDER BY 1 LOOP
   IF item.quantity NOT BETWEEN 1 AND 1000 THEN RAISE EXCEPTION 'Invalid quantity'; END IF;
   SELECT * INTO STRICT product FROM products WHERE id = item.id FOR UPDATE;
   SELECT in_stock INTO stock FROM product_stock WHERE id = item.id;
   IF stock < item.quantity THEN RAISE EXCEPTION 'Insufficient stock'; END IF;
   amount := amount + product.price * item.quantity;
 END LOOP;
 earned := CASE WHEN p_customer IS NULL THEN 0 ELSE floor(amount * 10)::integer END;
 INSERT INTO purchases(customer_id,total,points_earned,request_key)
 VALUES(p_customer,amount,earned,p_key) RETURNING * INTO receipt;
 INSERT INTO purchase_items(purchase_id,product_id,name,unit_price,quantity)
 SELECT receipt.id,p.id,p.name,p.price,sum((x->>'quantity')::integer)
 FROM jsonb_array_elements(p_items) x JOIN products p ON p.id=(x->>'id')::integer GROUP BY p.id;
 UPDATE customers SET points=points+earned WHERE id=p_customer;
 RETURN to_jsonb(receipt);
END $$;
REVOKE ALL ON FUNCTION complete_checkout(text,integer,jsonb) FROM PUBLIC, anon, authenticated;
GRANT EXECUTE ON FUNCTION complete_checkout(text,integer,jsonb) TO service_role;
