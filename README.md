# SuperStore

A smart-store simulation built for the **Internet of Things** course (420-521-VA, Vanier College, taught by Samad Rostampour) final project.

## Overview

SuperStore simulates a physical store where sensors, actuators and single-board computers connect the customer experience to store management. It is built in phases, each adding to the last:

| Phase | Focus | Status |
|---|---|---|
| **Phase 1** | Customer registration with LED/buzzer feedback | Done |
| **Phase 2** | IoT dashboard: fridge sensors over MQTT, thresholds, email alerts, fan control | Done |
| **Phase 3** | Product management, inventory, barcode/RFID, self-checkout, loyalty points | In progress |

The store has two sides:

- **Management side**: a dashboard for fridges, products and inventory (admins only).
- **Customer side**: account creation and a self-checkout station where customers scan and pay on their own.

## Tech Stack

| Layer | Choice |
|---|---|
| Backend | PHP 8 (MVC, no framework) |
| Database | PostgreSQL hosted on Supabase, used through its REST API (PostgREST) |
| Front-end | HTML, CSS, vanilla JavaScript |
| Hardware control | Python 3 (`gpiozero`) on a Raspberry Pi 5 |
| Messaging | MQTT (Mosquitto broker on the Pi) |
| Microcontrollers | ESP32 + DHT11 (Arduino C++) |
| Scanners | USB barcode scanner, Chafon CF600 UHF RFID reader |

The web app and the Python scripts run on each Raspberry Pi. The database is shared on Supabase, so the whole team works against the same data.

## Architecture

```
 ESP32 + DHT11 ──MQTT──▶ Mosquitto (Pi) ──▶ monitor.py ──HTTP──▶ PHP app (Pi) ──REST──▶ Supabase
 (Frig1, Frig2)                              │  ▲                    ▲   │
                                   alert email│  │YES reply           │   │ GET /api/fan
                                              ▼  │                    │   ▼
                                             Gmail              browser   fan.py ──GPIO──▶ DC motor
 RFID reader (USB) ──▶ rfid.py ──POST /api/scans──▶ PHP app
 Barcode scanner (USB keyboard) ──▶ types into the browser form
```

The browser never talks to the hardware directly. Every device goes through the PHP API, and the PHP API is the only thing that talks to the database.

## Project Structure

```
SuperStore/
├── app/
│   ├── controllers/
│   │   ├── AuthController.php        # sign up, login, logout (Phase 1)
│   │   ├── ThresholdController.php   # per-fridge temperature limits (Phase 2)
│   │   ├── ReadingApiController.php  # sensor readings in, dashboard data out (Phase 2)
│   │   ├── FanApiController.php      # fan on/off state (Phase 2)
│   │   ├── ProductController.php     # products, inventory, receive stock (Phase 3)
│   │   └── ScanApiController.php     # RFID scans (Phase 3)
│   ├── models/
│   │   ├── CustomerModel.php
│   │   ├── Fridge.php  Reading.php  FanState.php  Alert.php
│   │   └── ProductModel.php  ReceptionModel.php  ScanModel.php
│   ├── services/
│   │   └── SupaClient.php            # throws on a failed Supabase request
│   └── views/                        # login, signup, dashboard, thresholds, products/*
├── config/
│   ├── db.php                        # reads .env, supa_request() REST helper
│   └── db.sql                        # full schema, one section per phase
├── docs/
│   ├── ER.md                         # database design: tables, relationships, examples
│   ├── SuperStore-ERD.drawio         # ER diagram (edit at app.diagrams.net)
│   └── SuperStore-ERD.png            # ER diagram image
├── public/
│   ├── index.php                     # front controller + every route
│   └── assets/                       # css, js (form-utils.js), images
├── scripts/
│   ├── gpio_signal.py                # blue/red LED (Phase 1)
│   ├── monitor.py                    # MQTT -> API, alert email, YES reply (Phase 2)
│   ├── fan.py                        # drives the DC motor from the fan state (Phase 2)
│   └── mqtt.py                       # ESP32 sketch (Arduino C++, flashed to the boards)
├── .env.example
└── README.md
```

**How a request flows (MVC):** `public/index.php` matches `"METHOD /path"` in its `$routes` array and calls the controller. The controller validates the input and calls a model. The model is the only place that builds Supabase requests (`SupaClient::rows()`). The controller then either renders a view or answers in JSON.

## Setup

### 1. Environment

Copy `.env.example` to `.env` and fill it in:

| Key | Where to find it |
|---|---|
| `SUPABASE_URL` | Supabase → Project Settings → API |
| `SUPABASE_SECRET_KEY` | Supabase → Project Settings → API keys (secret key, server only) |
| `GMAIL_USER` | The Gmail account that sends the alerts |
| `GMAIL_APP_PASSWORD` | Google Account → Security → App passwords (16 characters) |
| `ALERT_TO` | The address that receives fridge alerts (defaults to `GMAIL_USER`) |

`.env` is ignored by git. Never commit it.

### 2. Database

In the Supabase SQL Editor, run the sections of `config/db.sql` that the database doesn't have yet (each phase has its own section). Then make your account an admin:

```sql
UPDATE customers SET is_admin = TRUE WHERE email = 'you@example.com';
```

### 3. Raspberry Pi

```bash
sudo apt install php-cli php-curl mosquitto python3-gpiozero python3-paho-mqtt python3-dotenv
php -S 0.0.0.0:8000 -t public        # the scripts expect the app on port 8000
```

Let the ESP32s reach the broker. Add this to `/etc/mosquitto/conf.d/superstore.conf`, then run `sudo systemctl restart mosquitto`:

```
listener 1883
allow_anonymous true
```

Each script runs in its own terminal:

```bash
python3 scripts/monitor.py   # fridge readings + email alerts
python3 scripts/fan.py       # fan motor
```

### 4. ESP32 boards

Open `scripts/mqtt.py` in the Arduino IDE (it is an Arduino sketch). Install the **PubSubClient** and **DHT sensor library** libraries, then set `WIFI_SSID`, `WIFI_PASS` and `MQTT_HOST` (the Pi's IP address). Flash one board with `TOPIC = "Frig1"` and the other with `"Frig2"`.

---

## Phase 1 — Customer Registration

- Sign-up page (name, email, phone, address, password) that stores the customer in the database.
- Validation in the browser and again on the server, with one clear message per field.
- Passwords stored as `password_hash()` hashes; sign-in starts a fresh session.
- **On success:** confirmation message + **blue LED** for 2 seconds.
- **On failure:** error message + **red LED** for 2 seconds.

`AuthController` runs `scripts/gpio_signal.py success|fail` in the background, so the page doesn't wait for the LED.

| Route | What |
|---|---|
| `GET /signup`, `GET /login` | Pages |
| `POST /register` | Create an account (JSON) |
| `POST /login` | Sign in (JSON) |
| `GET /logout` | Sign out |

**Hardware:** blue + red LEDs, resistors, buzzer, breadboard, wires, Raspberry Pi 5.

## Phase 2 — IoT Dashboard & Fridge Monitoring

Two ESP32 boards with DHT11 sensors each watch one fridge and publish `{"temp": 4.0, "hum": 55.0}` every 5 seconds to the MQTT topics **`Frig1`** and **`Frig2`**.

**How it works:**

1. `monitor.py` subscribes to both topics and forwards each reading to `POST /api/readings`.
2. The API stores the reading and answers whether it is above that fridge's threshold.
3. When it is above, `monitor.py` emails *"The current temperature is X °C. Would you like to turn on the fan?"* (at most one email per fridge every 2 minutes).
4. Every 15 seconds `monitor.py` checks the inbox. A reply that starts with **YES** calls `POST /api/fan {"on": true}`.
5. `fan.py` asks `GET /api/fan` once a second and switches the DC motor (the "fan") through the motor driver.
6. The dashboard polls `GET /api/readings/latest` and shows **4 gauges** (temperature + humidity per fridge), the **fan ON/OFF icon** and a notification when a fridge is too warm.

Thresholds are stored per fridge in the database and edited on the **Thresholds** page.

| Route | What |
|---|---|
| `GET /dashboard` | Gauges, fan icon, alerts |
| `GET /thresholds`, `POST /thresholds` | View / change a fridge's limit (login + CSRF) |
| `GET /api/thresholds` | Every fridge with its limit (JSON) |
| `POST /api/readings` | `{"fridge":"Frig1","temp":4.0,"hum":55.0}`, sent by `monitor.py` |
| `GET /api/readings/latest` | Latest reading per fridge + fan state (dashboard) |
| `GET /api/fan`, `POST /api/fan` | Read / set `{"on": true}` |

**Hardware:** 2 × ESP32, 2 × DHT11, DC motor + motor driver, breadboard, Raspberry Pi 5.

## Phase 3 — Product Management, Inventory & Self-Checkout

**Management (admins only):**

- Register, edit and delete products: name, category, price, UPC (max 13), EPC (max 24), producer, optional image.
- Each product is defined once but **received many times**. Every delivery records its quantity and date.
- Inventory dashboard with the stock of every product. Low stock (5 or fewer) is flagged.
- UPC read by the barcode scanner, EPC read by the RFID reader.
- A notification after every action (add, edit, delete, receive stock).

**Self-checkout (customers):**

- Scan every product at once with the RFID reader; the list and total update live.
- Remove items, enter a membership card, simulate payment.
- Earn loyalty points; a receipt is emailed to the customer's registered address.
- Customers see their points and purchase history (past receipts).

**Database:** see [`docs/ER.md`](docs/ER.md) for the ER diagram and the relations between products, customers and purchases. Stock is not stored. It is computed by the `product_stock` view as *everything received − everything sold*.

### Pages (admin only)

Each page receives the variables listed, plus `$csrfToken`. Until a view file exists, the route answers with that data as JSON (HTTP 501), so the backend can be tested on its own.

| Route | View | Variables |
|---|---|---|
| `GET /products` | `products/index.php` | `$products`, `$lowStock` |
| `GET /products/create` | `products/form.php` | `$product = null` |
| `GET /products/edit?id=5` | `products/form.php` | `$product` |
| `GET /products/show?id=5` | `products/show.php` | `$product`, `$receptions` |
| `GET /products/receive?id=5` | `products/receive.php` | `$products`, `$selectedId` (may be null), `$today` |

A product row: `id, name, category, price, upc, epc, producer, image_url, in_stock, low_stock`.
A reception row: `id, product_id, quantity, received_at`.

### Form actions (admin only, JSON)

Send with `postForm()` from `form-utils.js`. Every form includes `csrf_token`.

| Route | Fields |
|---|---|
| `POST /products` | `name, category, price, upc, epc, producer, image_url` (UPC **or** EPC required) |
| `POST /products/update` | `id` + the same fields |
| `POST /products/delete` | `id` |
| `POST /receptions` | `product_id, quantity, received_at` (`YYYY-MM-DD`, empty = today) |

Every answer has the same shape, ready for `showMessage()` and `showFormErrors()`:

```json
{"status": "success", "message": "Product \"Coca-Cola 500ml\" added successfully.", "product": {...}}
{"status": "error", "message": "Please fix the highlighted fields and try again.", "errors": {"upc": "Another product already uses this UPC."}}
```

Error slots in the form: `<span class="field-error" id="error-upc"></span>` (one per field name).

### Scanner APIs (no login, shared with self-checkout)

| Route | Use |
|---|---|
| `GET /api/products/lookup?code=` | The product behind a UPC or EPC (`404` if none). Spaces and lowercase are fine. |
| `POST /api/scans` | Body `{"epc":"E280..."}` or `{"epcs":["E280...","E280..."]}`. Sent by `scripts/rfid.py`. |
| `GET /api/scans/latest` | The newest scan. Remember its `last_id`. |
| `GET /api/scans/latest?after=12` | Every scan newer than 12, oldest first, plus the new `last_id`. Poll every second. |

- **Barcode scanner:** it types like a keyboard, so it fills the focused UPC field with no code needed.
- **RFID on the product form:** poll `/api/scans/latest?after=<last_id>` and put the newest `epc` into the EPC field.

### RFID reader setup (Raspberry Pi)

1. Switch the CF600 to **CDC-COM** mode with Chafon's configuration tool (Windows, from the SDK).
2. `sudo modprobe usbserial vendor=0x0483 product=0x5750`
3. `ls /dev/ttyUSB*` should list the reader.
4. Add yourself to the serial group once: `sudo usermod -aG dialout $USER`, then log out and back in.

**Hardware:** Chafon CF600 UHF RFID reader + tags, USB barcode scanner, Raspberry Pi 5.

---

## Raspberry Pi Pinout (BCM numbers)

| Pin | Used by | Part |
|---|---|---|
| GPIO 17 | `gpio_signal.py` | Blue LED |
| GPIO 18 | `gpio_signal.py` | Red LED |
| GPIO 22 | `fan.py` | Motor driver enable |
| GPIO 27 | `fan.py` | Motor driver input 1 |
| GPIO 17 | `fan.py` | Motor driver input 2 |

GPIO 17 is listed twice: the Phase 1 blue LED and the Phase 2 motor driver share it. Move one of them before running both on the same Pi.

## Team

Group of 4, each with a Raspberry Pi 5. Phase 3 is split into management (inventory backend + database, inventory frontend) and self-checkout.
