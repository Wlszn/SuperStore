# Demonstration checks

## Verified with Supabase

- Submit an empty registration. Field validation rejects it.
- Register a customer. An account and membership number are created.
- Register the same email again. The server rejects the duplicate.
- Log out and open the account page. The app redirects to login.
- Enter a wrong password. Login is rejected.
- Sign in correctly. The account page opens.
- Open product management as a customer. Access is denied.
- Buy two units priced at $2.50 from stock of three. The total is $5.00, stock becomes one, and the member earns fifty points.
- Retry the same checkout request. The original purchase is returned. Stock and points change once.
- Attempt to buy two more units. Insufficient stock rejects the purchase and leaves stock intact.

These tests used temporary records which were deleted and checked afterward.

## How checkout works

The browser sends product IDs and quantities. PostgreSQL reads the current prices and checks stock. Product locks are acquired in ID order so simultaneous checkout requests cannot both spend the same stock. The purchase, receipt lines and points update happen in one transaction. Failure rolls back the transaction.

Each checkout has a request key. Retrying that key returns its original purchase. Receipt lines store product names and prices as they were at purchase time.

Prices are final CAD amounts for this classroom simulation. Ten points are earned per dollar, rounded down to a whole point on a member purchase. Guest purchases earn no points.

## RFID demonstration

Start the reader feed before placing tagged products on the reader. The checkout reads new scan records in batches. A tag is counted once per basket, including when the reader reports it repeatedly. Removing an item prevents repeated reads from adding it again in that basket.

The current schema supports one EPC per product. Multiple individually tagged units of the same product require a separate tag-to-product table. Physical CF600 integration is pending the teacher-provided SDK. The scan feed currently assumes one checkout station.

## Receipt email setup

Run config/receipt_emails.sql in Supabase SQL Editor after config/checkout.sql.

Configure GMAIL_USER and GMAIL_APP_PASSWORD in .env. The sender uses a Gmail app password. Start the worker with:

    python scripts/receipt_worker.py

New member purchases queue email to the email address stored on the customer account. Guest purchases have no email recipient. The worker sends the receipt and retries failed deliveries up to five attempts with at least five minutes between attempts. Payment remains completed when email fails.

SMTP delivery cannot guarantee exactly-once delivery. If a worker stops after SMTP accepts a message and before the database is updated, its job remains in sending status. Inspect that job before resetting it to pending to avoid an unintended duplicate.

## Remaining verification

- Full browser customer journey, including translated screens and mobile layout.
- Concurrent checkout requests against the same last unit.
- Real receipt delivery after email configuration and queue migration.
- CF600 hardware and SDK integration.
- Full French coverage. The language switch currently translates selected customer labels and messages.
Apply config/membership.sql for card codes up to 64 characters. Re-run config/checkout.sql for the ten-points-per-dollar rule. Existing receipts keep their original points.


## Delivery-to-sale demo

1. Open Receive stock as an administrator and scan a product barcode or select the product.
2. Choose Fill sample delivery to enter 12 units and today's date. Review and record the delivery.
3. Show the delivery reference, quantity, and date in Reception history and point out the updated stock.
4. Open checkout from the product page, add the product, buy two units, and show the receipt and its barcode.
5. Reopen the product to show that the delivery remains in its history and the sale reduced available stock by two units.
