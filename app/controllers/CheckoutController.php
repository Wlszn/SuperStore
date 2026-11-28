<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ScanModel;
use App\Models\CustomerModel;
use App\Models\PurchaseHistoryModel;
use App\Services\Mailer;
use App\Services\SupaClient;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use function config\supa_request;

class CheckoutController
{
    public function index(): void
    {
        $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
        $_SESSION['checkout_key'] ??= bin2hex(random_bytes(32));
        $profile = [];
        $membershipCode = '';
        $membershipUnavailable = false;

        if (isset($_SESSION['customer_id'])) {
            header('Cache-Control: no-store');
            try {
                $members = SupaClient::rows('GET', 'customers', [
                    'id' => 'eq.' . $_SESSION['customer_id'],
                    'select' => 'name,email,phone,address,membership_number,points',
                    'limit' => 1,
                ]);
                $profile = $members[0] ?? [];
                $membershipCode = $profile['membership_number'] ?? '';
            } catch (RuntimeException $e) {
                $membershipUnavailable = true;
            }
        }

        require APP_ROOT . '/app/views/checkout.php';
    }

    public function pay(): void
    {
        if (!is_string($_POST['csrf_token'] ?? null) ||
            !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']) ||
            empty($_SESSION['csrf_token'])) {
            jsonResponse(403, [
                'status' => 'error',
                'message' => 'Reload the page before paying.',
            ]);
            return;
        }
        $key = $_POST['key'] ?? '';
        if (!is_string($key) || !hash_equals($_SESSION['checkout_key'] ?? '', $key)) {
            jsonResponse(409, [
                'status' => 'error',
                'message' => 'This checkout session expired. Reload the page.',
            ]);
            return;
        }
        // The basket is the list of RFID tags the reader saw: one tag = one item.
        $epcs = json_decode($_POST['epcs'] ?? '', true);
        [$epcs, $errors] = (new ScanModel())->validate(is_array($epcs) ? array_values($epcs) : []);
        if ($errors !== []) {
            jsonResponse(422, [
                'status' => 'error',
                'message' => 'Place between 1 and 100 items on the reader.',
            ]);
            return;
        }
        $points = filter_var($_POST['redeem_points'] ?? 0, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => 2147483647],
        ]);
        if ($points === false) {
            jsonResponse(422, ['status' => 'error', 'message' => 'Enter a valid number of points.']);
            return;
        }
        $method = $_POST['payment_method'] ?? 'card';
        if (!in_array($method, ['card', 'cash'], true)) {
            jsonResponse(422, ['status' => 'error', 'message' => 'Choose card or cash.']);
            return;
        }

        try {
            $customerId = isset($_POST['membership']) ? ($_SESSION['customer_id'] ?? null) : null;
            $membership = trim(is_string($_POST['membership_code'] ?? null) ? $_POST['membership_code'] : '');
            if ($membership !== '') {
                $members = SupaClient::rows('GET', 'customers', [
                    'membership_number' => 'eq.' . $membership, 'select' => 'id', 'limit' => 1,
                ]);
                if (!$members) {
                    jsonResponse(422, [
                        'status' => 'error',
                        'message' => 'Membership barcode not found. Check the code or clear it to continue.',
                    ]);
                    return;
                }
                $customerId = $members[0]['id'];
            }
            if ($points > 0 && (!isset($_SESSION['customer_id']) || (int) $customerId !== (int) $_SESSION['customer_id'])) {
                jsonResponse(403, ['status' => 'error', 'message' => 'Use your own membership to spend points.']);
                return;
            }
            // complete_checkout() in config/checkout.sql: checks every tag, saves the receipt,
            // marks the items sold and updates the points, all in one transaction.
            [$status, $receipt] = supa_request('POST', 'rpc/complete_checkout', null, [
                'p_key' => $key,
                'p_customer' => $customerId,
                'p_epcs' => $epcs,
                'p_redeem_points' => $points,
            ]);
            if (($receipt['code'] ?? '') === 'PT422') {
                // A clear reason: a tag already sold, not enough points...
                jsonResponse(422, ['status' => 'error', 'message' => $receipt['message']]);
                return;
            }
            if ($status < 200 || $status >= 300 || !isset($receipt['id'])) {
                throw new RuntimeException('Checkout failed.');
            }
            if ($method === 'cash') {
                // 'card' is the column default. The purchase is already saved, so a failure here
                // (for example config/checkout.sql not re-run yet) must not report the payment as failed.
                try {
                    SupaClient::rows('PATCH', 'purchases', ['id' => 'eq.' . $receipt['id']], ['payment_method' => 'cash']);
                } catch (RuntimeException $e) {
                }
            }
            $_SESSION['receipts'][(int) $receipt['id']] = true;
            if ($customerId !== null) {
                // Members get their receipt by email right away (no worker script needed).
                $this->emailReceipt((int) $receipt['id']);
            }
            jsonResponse(200, [
                'status' => 'success',
                'receipt_id' => (int) $receipt['id'],
                'redirect' => '/receipt?id=' . $receipt['id'],
            ]);
        } catch (RuntimeException $error) {
            jsonResponse(409, [
                'status' => 'error',
                'message' => 'Payment could not be confirmed. The database may be unavailable. Retry with the same basket to safely check the result.',
            ]);
        }
    }

    /**
     * GET /api/members/lookup?code=SS100004
     * Called by the kiosk when a membership card is scanned, so the customer sees right away
     * whether the card is valid. Only the first name and points are returned: the kiosk is public.
     */
    public function memberLookup(): void
    {
        $code = trim(is_string($_GET['code'] ?? null) ? $_GET['code'] : '');
        if ($code === '' || mb_strlen($code) > 64) {
            jsonResponse(422, ['status' => 'error', 'message' => 'Scan or enter a membership card.']);
            return;
        }
        try {
            $members = SupaClient::rows('GET', 'customers', [
                'membership_number' => 'eq.' . $code,
                'select' => 'name,points',
                'limit' => 1,
            ]);
        } catch (RuntimeException $e) {
            jsonResponse(503, ['status' => 'error', 'message' => 'Membership lookup unavailable. Please retry.']);
            return;
        }
        if (!$members) {
            jsonResponse(404, ['status' => 'error', 'message' => 'Membership card not found.']);
            return;
        }
        jsonResponse(200, [
            'status' => 'success',
            'member' => [
                'first_name' => explode(' ', trim((string) $members[0]['name']))[0],
                'points' => (int) $members[0]['points'],
            ],
        ]);
    }

    public function membership(): void
    {
        if (!$this->accountActionAllowed()) {
            return;
        }
        $code = trim(is_string($_POST['membership_number'] ?? null) ? $_POST['membership_number'] : '');
        if ($code === '' || mb_strlen($code) > 64 || preg_match('/[\x00-\x1F\x7F]/', $code)) {
            jsonResponse(422, [
                'status' => 'error',
                'message' => 'Use a barcode with 1 to 64 printable characters.',
            ]);
            return;
        }
        try {
            SupaClient::rows('PATCH', 'customers', [
                'id' => 'eq.' . $_SESSION['customer_id'],
            ], [
                'membership_number' => $code,
            ]);
            jsonResponse(200, [
                'status' => 'success',
                'message' => 'Membership barcode saved.',
            ]);
        } catch (RuntimeException $error) {
            jsonResponse(409, [
                'status' => 'error',
                'message' => 'Could not save this barcode. It may already belong to another account, or the database may be unavailable.',
            ]);
        }
    }

    /**
     * Emails a member their receipt through Brevo (App\Services\Mailer). The payment is already
     * saved, so a failure here is only logged: it never turns a paid checkout into an error.
     * The receipt page then shows where the copy went.
     */
    private function emailReceipt(int $purchaseId): void
    {
        try {
            $purchase = SupaClient::rows('GET', 'purchases', [
                'id' => 'eq.' . $purchaseId,
                'select' => 'id,customer_id,total,points_earned,points_redeemed,payment_method,purchased_at,purchase_items(name,unit_price,quantity)',
            ])[0] ?? null;
            $customer = $purchase === null ? null : (SupaClient::rows('GET', 'customers', [
                'id' => 'eq.' . $purchase['customer_id'],
                'select' => 'name,email,points',
            ])[0] ?? null);
        } catch (RuntimeException $error) {
            return;
        }
        if ($purchase === null || $customer === null || ($customer['email'] ?? '') === '') {
            return;
        }
        $reference = 'SS-' . str_pad((string) $purchaseId, 6, '0', STR_PAD_LEFT);
        $sent = Mailer::send(
            $customer['email'],
            $customer['name'],
            'Your SuperStore receipt / Votre reçu SuperStore ' . $reference,
            Mailer::render('receipt', ['purchase' => $purchase, 'customer' => $customer])
        );
        if ($sent) {
            $_SESSION['receipt_emailed'][$purchaseId] = $customer['email'];
        }
        // receipt_emails (config/receipt_emails.sql) keeps a record of each receipt email.
        try {
            SupaClient::rows('PATCH', 'receipt_emails', ['purchase_id' => 'eq.' . $purchaseId], [
                'status' => $sent ? 'sent' : 'failed',
                'attempts' => 1,
                'updated_at' => gmdate('Y-m-d\TH:i:s\Z'),
            ]);
        } catch (RuntimeException $error) {
            // config/receipt_emails.sql not run yet: the email itself is what matters.
        }
    }

    /** POST /account/profile: the signed-in customer changes their name, email, phone or address. */
    public function profile(): void
    {
        if (!$this->accountActionAllowed()) {
            return;
        }
        $fields = [];
        foreach (['name', 'email', 'phone', 'address'] as $field) {
            $fields[$field] = trim(is_string($_POST[$field] ?? null) ? $_POST[$field] : '');
        }
        $errors = CustomerModel::validateData($fields['name'], $fields['email'], $fields['phone'], $fields['address']);
        if ($errors !== []) {
            jsonResponse(422, ['status' => 'error', 'message' => 'Please fix the highlighted fields and try again.', 'errors' => $errors]);
            return;
        }
        try {
            $owner = CustomerModel::findByEmail($fields['email']);
            if ($owner !== null && $owner->getId() !== (int) $_SESSION['customer_id']) {
                jsonResponse(409, [
                    'status' => 'error',
                    'message' => 'Please fix the highlighted fields and try again.',
                    'errors' => ['email' => 'Another account already uses this email address.'],
                ]);
                return;
            }
            // Only these four columns: points and the membership card are never touched here.
            SupaClient::rows('PATCH', 'customers', ['id' => 'eq.' . $_SESSION['customer_id']], $fields);
        } catch (RuntimeException $error) {
            jsonResponse(503, ['status' => 'error', 'message' => 'Could not save your profile. Please try again.']);
            return;
        }
        $_SESSION['customer_name'] = $fields['name'];
        jsonResponse(200, ['status' => 'success', 'message' => 'Your profile was updated.']);
    }

    /** POST /account/password: the current password, then the new one twice. */
    public function password(): void
    {
        if (!$this->accountActionAllowed()) {
            return;
        }
        $current = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $errors = CustomerModel::validatePassword($password, is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '');
        if ($current === '') {
            $errors['current_password'] = 'Please enter your current password.';
        } elseif ($password !== '' && $password === $current && !isset($errors['password'])) {
            $errors['password'] = 'Choose a password different from your current one.';
        }
        if ($errors !== []) {
            jsonResponse(422, ['status' => 'error', 'message' => 'Please fix the highlighted fields and try again.', 'errors' => $errors]);
            return;
        }
        try {
            $customer = CustomerModel::findById((int) $_SESSION['customer_id']);
            if ($customer === null || !$customer->verifyPassword($current)) {
                jsonResponse(422, [
                    'status' => 'error',
                    'message' => 'Please fix the highlighted fields and try again.',
                    'errors' => ['current_password' => 'This is not your current password.'],
                ]);
                return;
            }
            $customer->setPassword($password);
            SupaClient::rows('PATCH', 'customers', ['id' => 'eq.' . $_SESSION['customer_id']], ['password_hash' => $customer->getPasswordHash()]);
        } catch (RuntimeException $error) {
            jsonResponse(503, ['status' => 'error', 'message' => 'Could not change your password. Please try again.']);
            return;
        }
        session_regenerate_id(true); // a new session id after a credential change
        jsonResponse(200, ['status' => 'success', 'message' => 'Your password was changed.']);
    }

    /** Account forms: signed in, and the form's CSRF token matches. Sends the error itself. */
    private function accountActionAllowed(): bool
    {
        if (!isset($_SESSION['customer_id'])) {
            jsonResponse(401, ['status' => 'error', 'message' => 'Please sign in first.']);
            return false;
        }
        if (empty($_SESSION['csrf_token']) || !is_string($_POST['csrf_token'] ?? null) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            jsonResponse(403, ['status' => 'error', 'message' => 'Reload the account page and try again.']);
            return false;
        }
        return true;
    }

    public function account(): void
    {
        if (!isset($_SESSION['customer_id'])) {
            header('Location: /login');
            return;
        }
        $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
        try {
            $customer = SupaClient::rows('GET', 'customers', [
                'id' => 'eq.' . $_SESSION['customer_id'],
                'select' => 'name,email,phone,address,membership_number,points,created_at',
            ])[0] ?? null;
            // Profile tab "At a glance": shopping totals and the item bought most.
            $glance = null;
            if (($_GET['tab'] ?? 'profile') === 'profile') {
                $history = new PurchaseHistoryModel();
                $glance = $history->overview((int) $_SESSION['customer_id']);
                $counts = $history->itemCounts((int) $_SESSION['customer_id']);
                arsort($counts);
                $glance['favourite'] = $counts === [] ? null : ['name' => array_key_first($counts), 'units' => reset($counts)];
            }
            // Membership tab: points earned and spent with the card.
            $pointsActivity = ($_GET['tab'] ?? '') === 'membership'
                ? (new PurchaseHistoryModel())->pointsActivity((int) $_SESSION['customer_id'])
                : null;
            // Orders tab (P4-17): receipts for one day or a date range, the last 30 days by default.
            // Only loaded on that tab, so the other tabs do not wait for the receipt history.
            [$filters, $from, $to] = $this->orderPeriod();
            $receipts = [];
            $spending = null;
            $historyError = null;
            if (($_GET['tab'] ?? '') === 'orders' && ($from === null || $to === null || $from > $to)) {
                $historyError = 'Choose a valid date range (\'from\' must be before \'to\').';
            } elseif (($_GET['tab'] ?? '') === 'orders') {
                // The session's customer id: customers only ever see their own receipts.
                $history = new PurchaseHistoryModel();
                $receipts = $history->receipts((int) $_SESSION['customer_id'], $from, $to);
                $spending = $history->spending($receipts, $from, $to); // P4-18: total spent + graph
            }

            // Price history tab (P4-19): pick an item they bought, or search part of a name.
            // Only ever the session customer's own purchases.
            $itemSearch = trim((string) ($_GET['q'] ?? ''));
            $itemChoice = trim((string) ($_GET['item'] ?? ''));
            $itemNames = [];
            $topItems = [];
            $itemResults = [];
            $itemError = null;
            if (($_GET['tab'] ?? '') === 'prices') {
                $history = new PurchaseHistoryModel();
                $customerId = (int) $_SESSION['customer_id'];
                $counts = $history->itemCounts($customerId);
                $itemNames = array_keys($counts);
                // "Most bought" shortcuts: the 5 items with the most units, once there is a choice to make.
                if (count($counts) > 1) {
                    arsort($counts);
                    $topItems = array_slice(array_keys($counts), 0, 5);
                }
                if (mb_strlen($itemSearch) > 50) {
                    $itemError = 'Search for at most 50 characters.';
                } elseif ($itemSearch !== '') {
                    $itemChoice = '';   // a typed search wins over the dropdown
                    $itemResults = $history->itemHistory($customerId, $itemSearch);
                } elseif ($itemChoice !== '' && !in_array($itemChoice, $itemNames, true)) {
                    $itemError = 'Choose an item from the list.';
                } elseif ($itemChoice !== '') {
                    $itemResults = $history->itemHistory($customerId, $itemChoice, true);
                }
            }
            require APP_ROOT . '/app/views/account.php';
        } catch (RuntimeException $error) {
            http_response_code(503);
            echo 'Your account is temporarily unavailable. Please reload the page.';
        }
    }

    /**
     * GET /account/orders/export: the receipts of the Orders tab's period as a CSV file,
     * one row per item line. Same filters as the page, so the file matches the screen.
     */
    public function ordersExport(): void
    {
        if (!isset($_SESSION['customer_id'])) {
            header('Location: /login');
            return;
        }
        [$filters, $from, $to] = $this->orderPeriod();
        if ($from === null || $to === null || $from > $to) {
            http_response_code(422);
            echo 'Choose a valid date range (\'from\' must be before \'to\').';
            return;
        }
        try {
            $receipts = (new PurchaseHistoryModel())->receipts((int) $_SESSION['customer_id'], $from, $to);
        } catch (RuntimeException $error) {
            http_response_code(503);
            echo 'Your orders are temporarily unavailable. Please try again.';
            return;
        }
        $local = new DateTimeZone(date_default_timezone_get());
        $rows = [];
        foreach ($receipts as $receipt) {
            $when = (new DateTimeImmutable($receipt['purchased_at']))->setTimezone($local);
            foreach ($receipt['items'] as $line) {
                $rows[] = [
                    $when->format('Y-m-d'),
                    $when->format('H:i'),
                    'SS-' . str_pad((string) $receipt['id'], 6, '0', STR_PAD_LEFT),
                    $line['name'],
                    $line['quantity'],
                    number_format($line['unit_price'], 2, '.', ''),
                    number_format($line['amount'], 2, '.', ''),
                    number_format($receipt['total'], 2, '.', ''),
                    $receipt['points_earned'],
                    $receipt['points_redeemed'],
                ];
            }
        }
        $this->sendCsv(
            'orders-' . $from->format('Y-m-d') . '-to-' . $to->format('Y-m-d') . '.csv',
            ['Date', 'Time', 'Order reference', 'Product', 'Quantity', 'Unit price', 'Amount', 'Receipt total paid', 'Points earned', 'Points used'],
            $rows
        );
    }

    /**
     * GET /account/prices/export?item=... or ?q=...: the Price history tab's result as a CSV file,
     * one row per purchase of each item shown.
     */
    public function pricesExport(): void
    {
        if (!isset($_SESSION['customer_id'])) {
            header('Location: /login');
            return;
        }
        $customerId = (int) $_SESSION['customer_id'];
        $search = trim((string) ($_GET['q'] ?? ''));
        $choice = trim((string) ($_GET['item'] ?? ''));
        try {
            $history = new PurchaseHistoryModel();
            if ($search !== '' && mb_strlen($search) <= 50) {
                $items = $history->itemHistory($customerId, $search);
            } elseif ($choice !== '' && array_key_exists($choice, $history->itemCounts($customerId))) {
                $items = $history->itemHistory($customerId, $choice, true);
            } else {
                http_response_code(422);
                echo 'Choose an item from the list or search part of its name.';
                return;
            }
        } catch (RuntimeException $error) {
            http_response_code(503);
            echo 'Your price history is temporarily unavailable. Please try again.';
            return;
        }
        $local = new DateTimeZone(date_default_timezone_get());
        $rows = [];
        foreach ($items as $item) {
            foreach ($item['purchases'] as $purchase) {
                $when = (new DateTimeImmutable($purchase['purchased_at']))->setTimezone($local);
                $rows[] = [
                    $item['name'],
                    $when->format('Y-m-d'),
                    $when->format('H:i'),
                    number_format($purchase['unit_price'], 2, '.', ''),
                    $purchase['quantity'],
                    $purchase['change'] === null ? '' : number_format($purchase['change'], 2, '.', ''),
                    $purchase['change_percent'] === null ? '' : number_format($purchase['change_percent'], 1, '.', ''),
                    'SS-' . str_pad((string) $purchase['purchase_id'], 6, '0', STR_PAD_LEFT),
                ];
            }
        }
        $name = $search !== '' ? $search : $choice;
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
        $this->sendCsv(
            'price-history-' . ($slug !== '' ? $slug : 'items') . '.csv',
            ['Item', 'Date', 'Time', 'Unit price', 'Quantity', 'Change since previous purchase', 'Change (%)', 'Order reference'],
            $rows
        );
    }

    /** The Orders tab's period from the URL: [filters, from, to]. The last 30 days by default; a single day is from = to. */
    private function orderPeriod(): array
    {
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        $filters = [
            'mode' => ($_GET['mode'] ?? 'range') === 'day' ? 'day' : 'range',
            'day' => (string) ($_GET['day'] ?? $today),
            'from' => (string) ($_GET['from'] ?? (new DateTimeImmutable('-29 days'))->format('Y-m-d')),
            'to' => (string) ($_GET['to'] ?? $today),
        ];
        $from = $this->date($filters['mode'] === 'day' ? $filters['day'] : $filters['from']);
        $to = $this->date($filters['mode'] === 'day' ? $filters['day'] : $filters['to']);

        return [$filters, $from, $to];
    }

    /**
     * Sends a CSV download (UTF-8 with BOM so Excel shows accents). Text (not numbers like -0.20)
     * that starts with = + - @ gets a leading ' so a spreadsheet never runs it as a formula.
     */
    private function sendCsv(string $filename, array $header, array $rows): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $header, ',', '"', '');
        foreach ($rows as $row) {
            $row = array_map(static fn ($cell) => is_string($cell) && !is_numeric($cell) && preg_match('/^[=+\-@\t\r]/', $cell) ? "'" . $cell : $cell, $row);
            fputcsv($out, $row, ',', '"', '');
        }
        fclose($out);
    }

    /** "2026-11-20" -> date, anything else -> null. */
    private function date(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }

    public function receipt(): void
    {
        $id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
        if (!$id || $id < 1) {
            http_response_code(404);
            echo 'Receipt not found.';
            return;
        }
        try {
            $query = [
                'id' => 'eq.' . $id,
                'select' => '*,purchase_items(name,unit_price,quantity)',
            ];
            if (empty($_SESSION['receipts'][$id])) {
                if (!isset($_SESSION['customer_id'])) {
                    http_response_code(404);
                    echo 'Receipt not found.';
                    return;
                }
                $query['customer_id'] = 'eq.' . $_SESSION['customer_id'];
            }
            $receipt = SupaClient::rows('GET', 'purchases', $query)[0] ?? null;
            if (!$receipt) {
                http_response_code(404);
                echo 'Receipt not found.';
                return;
            }
            unset($_SESSION['checkout_key']);
            require APP_ROOT . '/app/views/receipt.php';
        } catch (RuntimeException $error) {
            http_response_code(503);
            echo 'Receipt temporarily unavailable. Reload to try again.';
        }
    }
}
