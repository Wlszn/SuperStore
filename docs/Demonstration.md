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

The browser sends the RFID tags in the basket. PostgreSQL locks those items, checks that each one is in stock and reads the current prices, so two checkouts cannot sell the same item. The purchase, receipt lines and points update happen in one transaction. Failure rolls back the transaction.

Each checkout has a request key. Retrying that key returns its original purchase. Receipt lines store product names and prices as they were at purchase time.

Prices are final CAD amounts for this classroom simulation. Ten points are earned per dollar, rounded down to a whole point on a member purchase. Guest purchases earn no points.

## RFID demonstration

Every physical item has its own tag. The checkout page starts listening as soon as it opens: put all the items on the reader and they appear together, grouped by product (five KitKats = one line "× 5"). A tag is counted once per basket, including when the reader reports it repeatedly. Removing a line prevents repeated reads from adding those items again. An unknown or already-sold tag is reported and not added. The scan feed assumes one station at a time.

Without the reader, simulate one batch from PowerShell:

    Invoke-RestMethod -Method Post -Uri http://localhost:8000/api/scans -ContentType 'application/json' -Body '{"epcs":["E28011700000020A1B2C3D03","E28011700000020A1B2C3D04","E28011700000020A1B2C3D01"]}'

## Email setup (receipts and password resets)

Emails go through Brevo's API (free plan: 300 emails a day, no credit card).

1. Create a free account at brevo.com.
2. Under Senders, domains & IPs, add and verify the address the emails come from (Brevo emails it a confirmation link).
3. Under SMTP & API, create an API key.
4. In .env set BREVO_API_KEY, MAIL_FROM_EMAIL (the verified address), MAIL_FROM_NAME=SuperStore and APP_URL (the address customers open the site at, used in the password reset links).
5. Run config/receipt_emails.sql and config/password_resets.sql in the Supabase SQL Editor.

When a member pays, the receipt is emailed right away with a short thank-you message, and the receipt page shows where it was sent. Guests have no email address, so they get none. receipt_emails records each receipt email as sent or failed. If Brevo cannot be reached, the payment still completes; only the email is missing.

Password resets: "Forgot your password?" on the sign-in page, or "Send password reset email" on an admin's Edit customer page. The link works once and expires after one hour.

## Remaining verification

- Full browser customer journey, including translated screens and mobile layout.
- Concurrent checkout requests against the same last unit.
- Real receipt delivery after email configuration and queue migration.
- CF600 reader on the Raspberry Pi (`/dev/ttyACM0`). Reading on Windows and checkout with real tags are verified.
- Full French coverage. The language switch currently translates selected customer labels and messages.
Re-run config/checkout.sql for card codes up to 64 characters and the ten-points-per-dollar rule. Existing receipts keep their original points.


## Delivery-to-sale demo

1. Open Receive stock as an administrator and scan a product barcode or select the product.
2. Choose Fill sample delivery to enter 12 units and today's date. Review and record the delivery.
3. Show the delivery reference, quantity, and date in Reception history and point out the updated stock.
4. Open checkout from the product page, add the product, buy two units, and show the receipt and its barcode.
5. Reopen the product to show that the delivery remains in its history and the sale reduced available stock by two units.

Members can redeem 100 points for CAD 1 off. Points are earned at 10 per dollar actually paid after the discount. Re-run config/checkout.sql in Supabase before demonstrating redemption.
