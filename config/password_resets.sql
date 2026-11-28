-- Password reset links: "Forgot your password?" on the sign-in page, and
-- "Send password reset email" on the admin Customers page. Safe to run again.
-- Only a SHA-256 hash of each link's token is stored, so reading this table
-- does not give anyone a working link. A link works once and expires after 1 hour.
CREATE TABLE IF NOT EXISTS password_resets (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    customer_id INT NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    token_hash  CHAR(64) NOT NULL UNIQUE,
    expires_at  TIMESTAMPTZ NOT NULL,
    used_at     TIMESTAMPTZ,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS password_resets_customer ON password_resets (customer_id);
ALTER TABLE password_resets ENABLE ROW LEVEL SECURITY;
