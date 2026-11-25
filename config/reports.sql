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

-- Most and least sold products for a date range (spec 2.2, P4-11).
-- Ties at the cut-off are all kept (RANK). Products with no sales count as 0,
-- so they can be least sold. Deleted products that sold keep their old name.
CREATE OR REPLACE FUNCTION sales_ranking(p_from date, p_to date, p_limit integer, p_category text DEFAULT NULL)
RETURNS jsonb
LANGUAGE sql STABLE
SET search_path = public
AS $$
  WITH sold AS (
    -- Quantity sold per product in the period (Montreal days, p_to included).
    SELECT pi.product_id, pi.name, SUM(pi.quantity)::int AS qty
    FROM purchase_items pi
    JOIN purchases pu ON pu.id = pi.purchase_id
    WHERE pu.purchased_at >= (p_from::timestamp     AT TIME ZONE 'America/Toronto')
      AND pu.purchased_at <  ((p_to + 1)::timestamp AT TIME ZONE 'America/Toronto')
    GROUP BY pi.product_id, pi.name
  ),
  candidates AS (
    -- Every current product, with 0 when it did not sell.
    SELECT p.id AS product_id, p.name, p.category, false AS deleted,
           COALESCE((SELECT SUM(s.qty) FROM sold s WHERE s.product_id = p.id), 0)::int AS sold_qty
    FROM products p
    WHERE p_category IS NULL OR p.category = p_category
    UNION ALL
    -- Deleted products that sold in the period (no category any more).
    SELECT NULL, s.name, 'Uncategorized', true, s.qty
    FROM sold s
    WHERE s.product_id IS NULL AND p_category IS NULL
  ),
  ranked AS (
    SELECT c.*,
           RANK() OVER (ORDER BY sold_qty DESC) AS top_rank,
           RANK() OVER (ORDER BY sold_qty ASC)  AS bottom_rank
    FROM candidates c
  )
  SELECT jsonb_build_object(
    'from',     p_from,
    'to',       p_to,
    'limit',    p_limit,
    'category', p_category,
    'most',  COALESCE((SELECT jsonb_agg(jsonb_build_object(
                         'rank', top_rank, 'product_id', product_id, 'name', name,
                         'category', category, 'deleted', deleted, 'sold_qty', sold_qty)
                       ORDER BY sold_qty DESC, name)
                       FROM ranked WHERE top_rank <= p_limit AND sold_qty > 0), '[]'::jsonb),
    'least', COALESCE((SELECT jsonb_agg(jsonb_build_object(
                         'rank', bottom_rank, 'product_id', product_id, 'name', name,
                         'category', category, 'deleted', deleted, 'sold_qty', sold_qty)
                       ORDER BY sold_qty ASC, name)
                       FROM ranked WHERE bottom_rank <= p_limit), '[]'::jsonb)
  );
$$;

REVOKE ALL ON FUNCTION sales_ranking(date, date, integer, text) FROM PUBLIC, anon, authenticated;
GRANT EXECUTE ON FUNCTION sales_ranking(date, date, integer, text) TO service_role;


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