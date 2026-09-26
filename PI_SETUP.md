# Raspberry Pi setup — SuperStore Phase 1

Follow these steps in order. Two steps here (marked ⚠️) fix issues that aren't obvious from the error messages you'll get — do them upfront and you'll save yourself the debugging time it took to find them the first time.

## 1. Install the web server stack


```bash
sudo apt update
sudo apt install apache2 php libapache2-mod-php php-mysql php-pdo mariadb-server
sudo systemctl start mariadb
sudo systemctl enable mariadb
```

## 2. Clone the repo into the web root

```bash
cd /var/www/html
git clone https://github.com/Wlszn/SuperStore.git
```


If you cloned it with `sudo` and are hitting permission errors, fix ownership after the fact:
```bash
sudo chown -R $USER:www-data /var/www/html/SuperStore
sudo find /var/www/html/SuperStore -type d -exec chmod 2775 {} \;
sudo find /var/www/html/SuperStore -type f -exec chmod 664 {} \;
```

## 3. ⚠️ Create a dedicated MySQL user (don't use root)

Raspberry Pi OS locks the `root` MySQL user to `auth_socket` authentication by default — it will refuse normal password logins even with the right password, and PHP's PDO needs password auth. Skip past this entirely by creating a separate app user:

```bash
sudo mysql
```

At the MySQL prompt:
```sql
CREATE DATABASE IF NOT EXISTS superstore;
CREATE USER 'superstore_app'@'localhost' IDENTIFIED BY 'yourpassword';
GRANT ALL PRIVILEGES ON superstore.* TO 'superstore_app'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Pick any password

## 4. Create your `.env`

In the project root (`/var/www/html/SuperStore/.env` — this file is gitignored, make it fresh, don't copy anyone else's):

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=superstore
DB_USER=superstore_app
DB_PASS=yourpassword
```

Use the same password you set in step 3.

## 5. Install gpiozero (usually already there, confirm anyway)

```bash
sudo apt install python3-gpiozero
```

## 6. ⚠️ Give Apache permission to access GPIO

Apache runs as the `www-data` user, not as you — by default it can't touch the GPIO pins, so the DB insert will work but the LED/buzzer won't fire, with no error shown anywhere:

```bash
sudo usermod -a -G gpio www-data
sudo systemctl restart apache2
```

## 7. Wire up the breadboard

- Blue LED (+ 330Ω resistor) → **GPIO17**
- Red LED (+ 330Ω resistor) → **GPIO18**
- Buzzer (no resistor needed) → **GPIO22**
- All three negative legs → a shared ground rail → any GND pin on the Pi

## 8. Test it

First, test the GPIO script directly (isolates wiring/script issues from web server issues):
```bash
cd /var/www/html/SuperStore
python3 scripts/gpio_signal.py success   # blue LED should light for 2 sec
python3 scripts/gpio_signal.py fail      # red LED + buzzer should fire for 2 sec
```

If that works, test through the actual site:
- Go to `localhost/SuperStore/views/add_customer.php`
- Submit a valid customer → blue LED should light, and the row should appear in the database
- Submit an invalid email (e.g. no `@`) → red LED + buzzer should fire, no DB row added

If the terminal test works but the website doesn't trigger anything, you skipped step 6 — go back and do it.
