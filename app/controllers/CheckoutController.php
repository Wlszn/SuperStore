<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ProductModel;
use App\Services\SupaClient;
use RuntimeException;

class CheckoutController
{
    public function index(): void
    {
        $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
        $_SESSION['checkout_key'] ??= bin2hex(random_bytes(32));
        $scanCode = $_GET['code'] ?? '';
        $scanCode = is_string($scanCode) && mb_strlen($scanCode) <= 24 ? $scanCode : '';
        $membershipCode = '';
        $membershipUnavailable = false;

        if (isset($_SESSION['customer_id'])) {
            header('Cache-Control: no-store');
            try {
                $members = SupaClient::rows('GET', 'customers', [
                    'id' => 'eq.' . $_SESSION['customer_id'],
                    'select' => 'membership_number',
                    'limit' => 1,
                ]);
                $membershipCode = $members[0]['membership_number'] ?? '';
            } catch (RuntimeException $e) {
                $membershipUnavailable = true;
            }
        }

        try {
            $products = (new ProductModel())->all();
            $catalogUnavailable = false;
        } catch (RuntimeException $e) {
            $products = [];
            $catalogUnavailable = true;
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
        $items = json_decode($_POST['items'] ?? '', true);
        if (!is_array($items) || !array_is_list($items) || count($items) < 1 || count($items) > 100) {
            jsonResponse(422, [
                'status' => 'error',
                'message' => 'Add between 1 and 100 products.',
            ]);
            return;
        }
        foreach ($items as $item) {
            if (!is_array($item) || !is_int($item['id'] ?? null) || $item['id'] < 1 ||
                !is_int($item['quantity'] ?? null) || $item['quantity'] < 1 || $item['quantity'] > 1000) {
                jsonResponse(422, [
                    'status' => 'error',
                    'message' => 'Invalid product or quantity.',
                ]);
                return;
            }
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
            $receipt = SupaClient::rows('POST', 'rpc/complete_checkout',null, [
                'p_key' => $key,
                'p_customer' => $customerId,
                'p_items' => $items,
            ]);
            $_SESSION['receipts'][(int) $receipt['id']] = true;
            jsonResponse(200, [
                'status' => 'success',
                'redirect' => '/receipt?id=' . $receipt['id'],
            ]);
        } catch (RuntimeException $error) {
            jsonResponse(409, [
                'status' => 'error',
                'message' => 'Payment could not be confirmed. Stock may have changed or the database may be unavailable. Retry with the same basket to safely check the result.',
            ]);
        }
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
                'select' => 'name,email,membership_number,points',
            ])[0] ?? null;
            $purchases = SupaClient::rows('GET', 'purchases', [
                'customer_id' => 'eq.' . $_SESSION['customer_id'],
                'order' => 'purchased_at.desc',
                'select' => 'id,total,points_earned,purchased_at',
            ]);
            require APP_ROOT . '/app/views/account.php';
        } catch (RuntimeException $error) {
            http_response_code(503);
            echo 'Your account is temporarily unavailable. Please reload the page.';
        }
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
                'select' => 'id,customer_id,total,points_earned,purchased_at,purchase_items(name,unit_price,quantity)',
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
