CREATE TABLE IF NOT EXISTS receipt_emails (
 purchase_id integer PRIMARY KEY REFERENCES purchases(id) ON DELETE CASCADE,
 recipient text NOT NULL,
 status text NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','sending','sent','failed')),
 attempts integer NOT NULL DEFAULT 0,
 updated_at timestamptz NOT NULL DEFAULT now()
);
ALTER TABLE receipt_emails ENABLE ROW LEVEL SECURITY;
CREATE OR REPLACE FUNCTION queue_receipt_email() RETURNS trigger LANGUAGE plpgsql SET search_path=public AS $$
BEGIN
 INSERT INTO receipt_emails(purchase_id,recipient)
 SELECT NEW.id,email FROM customers WHERE id=NEW.customer_id;
 RETURN NEW;
END $$;
DROP TRIGGER IF EXISTS queue_receipt_email ON purchases;
CREATE TRIGGER queue_receipt_email AFTER INSERT ON purchases FOR EACH ROW EXECUTE FUNCTION queue_receipt_email();
CREATE OR REPLACE FUNCTION claim_receipt_email() RETURNS SETOF receipt_emails LANGUAGE sql SET search_path=public AS $$
 UPDATE receipt_emails SET status='sending',attempts=attempts+1,updated_at=now()
 WHERE purchase_id=(SELECT purchase_id FROM receipt_emails
 WHERE (status='pending' OR (status IN ('failed','sending') AND updated_at < now()-interval '5 minutes')) AND attempts<5
 ORDER BY updated_at FOR UPDATE SKIP LOCKED LIMIT 1)
 RETURNING *;
$$;
REVOKE ALL ON FUNCTION claim_receipt_email() FROM PUBLIC,anon,authenticated;
GRANT EXECUTE ON FUNCTION claim_receipt_email() TO service_role;
