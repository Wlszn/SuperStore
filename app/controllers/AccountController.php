<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\CustomerModel;
use App\Models\PurchaseModel;
use RuntimeException;

/**
 * The customer's own account.
 *
 *   GET /account               membership card, points, purchase history
 *   GET /receipts/show?id=12   one receipt (its owner, an admin, or the kiosk that just printed it)
 */
class AccountController
{
    private PurchaseModel $purchases;

    public function __construct()
    {
        $this->purchases = new PurchaseModel();
    }

    /** GET /account */
    public function index(): void
    {
        if (!isset($_SESSION['customer_id'])) {
            header('Location: /login');
            return;
        }

        $customer = CustomerModel::findById((int) $_SESSION['customer_id']);
        if ($customer === null) {
            // The account was deleted while signed in.
            header('Location: /logout');
            return;
        }

        try {
            $receipts = $this->purchases->forCustomer((int) $customer->getId());
        } catch (RuntimeException $e) {
            $receipts = null; // the page says the history could not be loaded
        }

        require APP_ROOT . '/app/views/account.php';
    }

    /** GET /receipts/show?id=12 */
    public function receipt(): void
    {
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        try {
            $receipt = $id === false ? null : $this->purchases->find($id);
        } catch (RuntimeException $e) {
            http_response_code(500);
            echo 'Could not load the receipt. Please try again.';
            return;
        }

        $allowed = $receipt !== null && (
            !empty($_SESSION['is_admin'])
            || ($receipt['customer_id'] !== null && $receipt['customer_id'] === ($_SESSION['customer_id'] ?? null))
            || $receipt['id'] === ($_SESSION['last_receipt_id'] ?? null)
        );

        if (!$allowed) {
            http_response_code(404);
            echo 'Receipt not found. <a href="/account">Back to my account</a>';
            return;
        }

        require APP_ROOT . '/app/views/receipt.php';
    }
}
