-- Sales report (spec 2.2): sold quantity and revenue per product for a date range.
-- Dates are store days in Montreal time; p_to is included.
-- Run after config/checkout.sql. Safe to run again.
CREATE OR REPLACE FUNCTION sales_report(p_from date, p_to date, p_category text DEFAULT NULL)
RETURNS jsonb
LANGUAGE sql STABLE
SET search_path = public
AS $$
  WITH lines AS (
    -- Every receipt line sold in the range. The name and price were copied at
    -- checkout, so a deleted product still shows under its old name.
    SELECT pi.purchase_id, pi.product_id, pi.name, pi.unit_price, pi.quantity,
           pr.category                                   -- NULL once the product is deleted
    FROM purchase_items pi
    JOIN purchases pu      ON pu.id = pi.purchase_id
    LEFT JOIN products pr  ON pr.id = pi.product_id
    WHERE pu.purchased_at >= (p_from::timestamp       AT TIME ZONE 'America/Toronto')
      AND pu.purchased_at <  ((p_to + 1)::timestamp   AT TIME ZONE 'America/Toronto')
      AND (p_category IS NULL OR pr.category = p_category)
  ),
  per_product AS (
    SELECT product_id,
           name,
           COALESCE(category, 'Uncategorized')        AS category,
           product_id IS NULL                         AS deleted,
           SUM(quantity)::int                         AS sold_qty,
           SUM(unit_price * quantity)::numeric(12,2)  AS revenue
    FROM lines
    GROUP BY product_id, name, category
  )
  SELECT jsonb_build_object(
    'from',          p_from,
    'to',            p_to,
    'category',      p_category,
    'products',      COALESCE((SELECT jsonb_agg(to_jsonb(pp) ORDER BY pp.sold_qty DESC, pp.name)
                               FROM per_product pp), '[]'::jsonb),
    'items_sold',    COALESCE((SELECT SUM(quantity) FROM lines), 0),
    'total_sales',   COALESCE((SELECT SUM(unit_price * quantity) FROM lines), 0)::numeric(12,2),
    'receipt_count', (SELECT COUNT(DISTINCT purchase_id) FROM lines),
    -- What customers actually paid (after the points discount). Only meaningful for
    -- whole receipts, so it is NULL when a category filter is applied.
    'amount_paid',   CASE WHEN p_category IS NULL THEN
                       (SELECT COALESCE(SUM(total), 0) FROM purchases
                        WHERE id IN (SELECT purchase_id FROM lines))
                     END
  );
$$;

-- Only the server (service key) may call it, like the other functions.
REVOKE ALL ON FUNCTION sales_report(date, date, text) FROM PUBLIC, anon, authenticated;
GRANT EXECUTE ON FUNCTION sales_report(date, date, text) TO service_role;

-- Sales trend (spec 2): revenue and items sold per day or per week, for the line chart.
-- Every day/week in the range is returned, with 0 when nothing sold (no gaps).
-- Weeks start on Monday. Days are Montreal time, p_to is included.
CREATE OR REPLACE FUNCTION sales_trend(p_from date, p_to date,
                                       p_category text DEFAULT NULL,
                                       p_group text DEFAULT 'day')
RETURNS jsonb
LANGUAGE sql STABLE
SET search_path = public
AS $$
  WITH buckets AS (
    -- One row per day (or per Monday) from the start to the end of the range.
    SELECT gs::date AS bucket
    FROM generate_series(
      CASE WHEN p_group = 'week' THEN date_trunc('week', p_from::timestamp) ELSE p_from::timestamp END,
      p_to::timestamp,
      CASE WHEN p_group = 'week' THEN interval '1 week' ELSE interval '1 day' END
    ) AS gs
  ),
  lines AS (
    -- Same receipt lines and filters as sales_report().
    SELECT (pu.purchased_at AT TIME ZONE 'America/Toronto')::date AS day,
           pi.quantity, pi.unit_price
    FROM purchase_items pi
    JOIN purchases pu     ON pu.id = pi.purchase_id
    LEFT JOIN products pr ON pr.id = pi.product_id
    WHERE pu.purchased_at >= (p_from::timestamp     AT TIME ZONE 'America/Toronto')
      AND pu.purchased_at <  ((p_to + 1)::timestamp AT TIME ZONE 'America/Toronto')
      AND (p_category IS NULL OR pr.category = p_category)
  ),
  per_bucket AS (
    SELECT CASE WHEN p_group = 'week' THEN date_trunc('week', day::timestamp)::date ELSE day END AS bucket,
           SUM(quantity)::int                        AS items_sold,
           SUM(unit_price * quantity)::numeric(12,2) AS revenue
    FROM lines
    GROUP BY 1
  )
  SELECT COALESCE(jsonb_agg(jsonb_build_object(
           'period',     b.bucket,
           'items_sold', COALESCE(p.items_sold, 0),
           'revenue',    COALESCE(p.revenue, 0)
         ) ORDER BY b.bucket), '[]'::jsonb)
  FROM buckets b
  LEFT JOIN per_bucket p USING (bucket);
$$;

REVOKE ALL ON FUNCTION sales_trend(date, date, text, text) FROM PUBLIC, anon, authenticated;
GRANT EXECUTE ON FUNCTION sales_trend(date, date, text, text) TO service_role;