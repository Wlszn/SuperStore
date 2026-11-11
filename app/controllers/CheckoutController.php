<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\CustomerModel;
use App\Models\PurchaseModel;
use App\Services\Lang;
use App\Services\ReceiptMailer;
use RuntimeException;

/**
 * Self-checkout kiosk. No login needed: customers identify themselves with
 * their membership card (scanned or typed), or check out as a guest.
 *
 *   GET  /checkout                     the kiosk page
 *   GET  /api/members/lookup?number=   the member behind a membership card (JSON)
 *   POST /checkout                     pay: JSON body {items, membership_number, payment_method, csrf_token}
 */
class CheckoutController
{
    private const GPIO_SCRIPT = __DIR__ . '/../../scripts/gpio_signal.py';
    private const MAX_LINES = 50;

    private PurchaseModel $purchases;

    public function __construct()
    {
        $this->purchases = new PurchaseModel();
    }

    /** GET /checkout */
    public function index(): void
    {
        $csrfToken = $this->csrfToken();
        $strings = Lang::forScript('js.');

        // A signed-in customer doesn't need to scan their card.
        $signedInMember = null;
        if (isset($_SESSION['customer_id']) && empty($_SESSION['is_admin'])) {
            $customer = CustomerModel::findById((int) $_SESSION['customer_id']);
            if ($customer !== null) {
                $signedInMember = $this->memberSummary($customer);
            }
        }

        require APP_ROOT . '/app/views/checkout.php';
    }

    /** GET /api/members/lookup?number=SS100003 */
    public function member(): void
    {
        $customer = CustomerModel::findByMembershipNumber((string) ($_GET['number'] ?? ''));

        if ($customer === null) {
            jsonResponse(404, ['status' => 'error', 'message' => Lang::t('checkout.member_not_found')]);
            return;
        }

        jsonResponse(200, [
            'status'  => 'success',
            'message' => Lang::t('checkout.member_found', ['name' => $this->firstName($customer->getName())]),
            'member'  => $this->memberSummary($customer),
        ]);
    }

    /** POST /checkout: save the purchase, add the points, email the receipt. */
    public function pay(): void
    {
        $data = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($data)) {
            jsonResponse(400, ['status' => 'error', 'message' => Lang::t('checkout.bad_request')]);
            return;
        }

        if (!hash_equals($this->csrfToken(), (string) ($data['csrf_token'] ?? ''))) {
            jsonResponse(403, ['status' => 'error', 'message' => Lang::t('checkout.session_expired')]);
            return;
        }

        // 1. Validate the shopping list.
        $items = $this->cleanItems($data['items'] ?? null);
        if ($items === null) {
            jsonResponse(422, ['status' => 'error', 'message' => Lang::t('checkout.cart_invalid')]);
            return;
        }

        $paymentMethod = (string) ($data['payment_method'] ?? '');
        if (!in_array($paymentMethod, ['card', 'cash'], true)) {
            jsonResponse(422, ['status' => 'error', 'message' => Lang::t('checkout.payment_invalid')]);
            return;
        }

        // 2. Membership card (optional).
        $customer = null;
        $number = trim((string) ($data['membership_number'] ?? ''));
        if ($number !== '') {
            $customer = CustomerModel::findByMembershipNumber($number);
            if ($customer === null) {
                jsonResponse(422, ['status' => 'error', 'message' => Lang::t('checkout.member_not_found')]);
                return;
            }
        }

        // 3. Pay: one database call saves everything or nothing.
        try {
            $result = $this->purchases->checkout($customer?->getId(), $items, $paymentMethod);

            if (empty($result['ok'])) {
                $this->triggerGPIO('fail');
                jsonResponse(409, ['status' => 'error', 'message' => $this->checkoutError($result)]);
                return;
            }

            $receipt = $this->purchases->find((int) $result['purchase_id']);
        } catch (RuntimeException $e) {
            $this->triggerGPIO('fail');
            jsonResponse(500, ['status' => 'error', 'message' => Lang::t('checkout.server_error')]);
            return;
        }

        $this->triggerGPIO('success');

        // The kiosk may open this receipt's page to print it.
        $_SESSION['last_receipt_id'] = $receipt['id'];

        // 4. Email the receipt to members.
        $emailedTo = null;
        if ($receipt['customer'] !== null && ReceiptMailer::send($receipt, Lang::current())) {
            $emailedTo = ReceiptMailer::maskEmail($receipt['customer']['email']);
        }

        if ($receipt['customer'] !== null) {
            unset($receipt['customer']['email']); // the kiosk screen never shows the full address
        }

        jsonResponse(201, [
            'status'     => 'success',
            'message'    => Lang::t('checkout.completed'),
            'receipt'    => $receipt,
            'emailed_to' => $emailedTo,
        ]);
    }

    /** [['product_id' => int, 'quantity' => int], ...] or null if anything is wrong. */
    private function cleanItems(mixed $items): ?array
    {
        if (!is_array($items) || $items === [] || count($items) > self::MAX_LINES) {
            return null;
        }

        $clean = [];
        foreach ($items as $item) {
            $productId = filter_var($item['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
            if ($productId === false || $quantity === false) {
                return null;
            }
            $clean[] = ['product_id' => $productId, 'quantity' => $quantity];
        }

        return $clean;
    }

    /** A clear message for each problem the checkout() function can report. */
    private function checkoutError(array $result): string
    {
        return match ($result['error'] ?? '') {
            'out_of_stock'     => Lang::t('checkout.out_of_stock', [
                'product' => $result['product'] ?? '',
                'count'   => $result['in_stock'] ?? 0,
            ]),
            'unknown_product'  => Lang::t('checkout.unknown_product'),
            'empty_cart'       => Lang::t('checkout.cart_invalid'),
            'unknown_customer' => Lang::t('checkout.member_not_found'),
            default            => Lang::t('checkout.server_error'),
        };
    }

    private function memberSummary(CustomerModel $customer): array
    {
        return [
            'first_name'        => $this->firstName($customer->getName()),
            'membership_number' => $customer->getMembershipNumber(),
            'points'            => $customer->getPoints(),
        ];
    }

    private function firstName(string $name): string
    {
        return explode(' ', trim($name))[0];
    }

    private function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /** Blue LED for a completed checkout, red for a refused one (same as Phase 1). */
    private function triggerGPIO(string $result): void
    {
        $script = escapeshellarg(self::GPIO_SCRIPT);
        $resultArg = escapeshellarg($result);
        shell_exec("python3 {$script} {$resultArg} > /dev/null 2>&1 &");
    }
}
