<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ScanModel;
use App\Models\PurchaseHistoryModel;
use App\Services\SupaClient;
use DateTimeImmutable;
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
        if (!isset($_SESSION['customer_id'])) {
            jsonResponse(401, [
                'status' => 'error',
                'message' => 'Please sign in first.',
            ]);
            return;
        }
        if (empty($_SESSION['csrf_token']) || !is_string($_POST['csrf_token'] ?? null) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            jsonResponse(403, [
                'status' => 'error',
                'message' => 'Reload the account page and try again.',
            ]);
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
                'select' => 'name,email,phone,address,membership_number,points',
            ])[0] ?? null;
            // Orders tab (P4-17): receipts for one day or a date range, the last 30 days by default.
            $today = (new DateTimeImmutable('today'))->format('Y-m-d');
            $filters = [
                'mode' => ($_GET['mode'] ?? 'range') === 'day' ? 'day' : 'range',
                'day' => (string) ($_GET['day'] ?? $today),
                'from' => (string) ($_GET['from'] ?? (new DateTimeImmutable('-29 days'))->format('Y-m-d')),
                'to' => (string) ($_GET['to'] ?? $today),
            ];
            // A single day is a range where from = to.
            $from = $this->date($filters['mode'] === 'day' ? $filters['day'] : $filters['from']);
            $to = $this->date($filters['mode'] === 'day' ? $filters['day'] : $filters['to']);
            $receipts = [];
            $historyError = null;
            if ($from === null || $to === null || $from > $to) {
                $historyError = 'Choose a valid date range (\'from\' must be before \'to\').';
            } else {
                // The session's customer id: customers only ever see their own receipts.
                $receipts = (new PurchaseHistoryModel())->receipts((int) $_SESSION['customer_id'], $from, $to);
            }
            require APP_ROOT . '/app/views/account.php';
        } catch (RuntimeException $error) {
            http_response_code(503);
            echo 'Your account is temporarily unavailable. Please reload the page.';
        }
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
