# SuperStore — API Reference

Every form action and API answers in JSON with the same shape:

```json
{"status": "success", "message": "Product \"Coca-Cola 500ml\" added successfully.", "product": {...}}
{"status": "error", "message": "Please fix the highlighted fields and try again.", "errors": {"upc": "Another product already uses this UPC."}}
```

`errors` maps a form field name to its message (shown with `showFormErrors()` from `public/assets/js/form-utils.js`).

## Products and inventory (admin only)

### Pages

Each page receives the variables listed, plus `$csrfToken`. Until a view exists, the route returns its data as JSON (HTTP 501).

| Route | View | Variables |
|---|---|---|
| `GET /products` | `products/index.php` | `$products`, `$lowStock` |
| `GET /products/create` | `products/form.php` | `$product = null` |
| `GET /products/edit?id=5` | `products/form.php` | `$product` |
| `GET /products/show?id=5` | `products/show.php` | `$product`, `$receptions` |
| `GET /products/receive?id=5` | `products/receive.php` | `$products`, `$selectedId` (may be null), `$today` |

- Product: `id, name, category, price, upc, epc, producer, image_url, in_stock, low_stock`
- Reception: `id, product_id, quantity, received_at`

### Actions

Every form sends `csrf_token`.

| Route | Fields |
|---|---|
| `POST /products` | `name, category, price, upc, epc, producer, image_url` (UPC or EPC required) |
| `POST /products/update` | `id` + the same fields |
| `POST /products/delete` | `id` |
| `POST /receptions` | `product_id, quantity, received_at` (`YYYY-MM-DD`, empty = today) |

Barcodes: a 12-digit UPC-A is stored as 13 digits (leading `0`), and the check digit is verified.

## Scanners (no login)

| Route | Description |
|---|---|
| `GET /api/products/lookup?code=` | Product with this UPC or EPC, or `404`. |
| `POST /api/scans` | `{"epc": "E280..."}` or `{"epcs": ["E280...", "E280..."]}`, sent by the RFID reader script. |
| `GET /api/scans/latest` | The newest scan and its `last_id`. |
| `GET /api/scans/latest?after=12` | Every scan newer than `12`, oldest first, and the new `last_id`. |

Browser checkout accepts keyboard-mode barcode scans in the focused field. The standalone scanner script sends a code to `POST /api/barcode` as `{"barcode": "0123456789012"}` for its own session cart. Payment and points for browser checkout are handled by `POST /checkout`.

## Fridges and fan

| Route | Description |
|---|---|
| `POST /api/readings` | `{"fridge": "Frig1", "temp": 4.0, "hum": 55.0}`, sent by `monitor.py`; answers whether the threshold is exceeded. |
| `GET /api/readings/latest` | Latest reading per fridge and the fan state. |
| `GET /api/fan`, `POST /api/fan` | Read or set `{"on": true}`. |
| `GET /api/thresholds` | Every fridge with its maximum temperature. |
