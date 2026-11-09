# SuperStore — Phase 3 ER Diagram

GitHub renders this diagram automatically. The full SQL is in `config/db.sql`.

```mermaid
erDiagram
    CUSTOMERS ||--o{ PURCHASES : "makes (optional: guests have none)"
    PURCHASES ||--|{ PURCHASE_ITEMS : "contains"
    PRODUCTS  ||--o{ PURCHASE_ITEMS : "sold as"
    PRODUCTS  ||--o{ RECEPTIONS : "received many times"

    CUSTOMERS {
        int id PK
        varchar name
        varchar email UK
        varchar phone
        varchar address
        varchar password_hash
        boolean is_admin
        varchar membership_number UK "auto: SS100001, SS100002..."
        int points
        timestamp created_at
    }
    PRODUCTS {
        int id PK
        varchar name
        varchar category
        numeric price
        varchar upc UK "barcode, max 13"
        varchar epc UK "RFID tag, max 24 hex"
        varchar producer
        text image_url "optional"
        timestamptz created_at
    }
    RECEPTIONS {
        int id PK
        int product_id FK
        int quantity "greater than 0"
        date received_at
    }
    PURCHASES {
        int id PK
        int customer_id FK "NULL for a guest"
        numeric total
        int points_earned
        timestamptz purchased_at
    }
    PURCHASE_ITEMS {
        int id PK
        int purchase_id FK
        int product_id FK "NULL if product deleted"
        varchar name "copied at checkout"
        numeric unit_price "copied at checkout"
        int quantity
    }
    RFID_SCANS {
        int id PK
        varchar epc
        timestamptz scanned_at
    }
```

## Design decisions

| Decision | Why |
|---|---|
| **Product 1 — N Reception** | The spec says a product is defined once but received many times. Each delivery is one row with its own quantity and date. |
| **Stock is computed, not stored** | The `product_stock` view = everything received − everything sold. A stored `stock` column could drift out of sync; a computed one can't. |
| **UPC and EPC are `UNIQUE`** | A scan must point to exactly one product. At least one of the two is required (`CHECK`). |
| **Purchase items copy name and price** | A receipt from last month must still show what the customer paid, even after the product is renamed, repriced or deleted. |
| **`ON DELETE CASCADE` on receptions, `SET NULL` on purchase items** | Deleting a product removes its delivery history, but never removes anyone's receipt. |
| **`customer_id` nullable on purchases** | A customer can check out without a membership card (no points, no email). |
| **`rfid_scans` table** | The RFID reader is plugged into the Pi, not the browser. `rfid.py` writes each tag here; the product form and checkout page poll for new ones. |
| **`is_admin` on customers** | Admins and customers share one login; only admins reach the management pages. Set it in Supabase, never from a form. |
