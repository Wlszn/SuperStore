<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\ProductModel;
use app\controllers\ProductController;
use RuntimeException;

class BarcodeController
{
    private ProductController $products;

    public function __construct()
    {
        $this->products = new ProductController();
    }

    /**
     * POST /api/barcode
     * Body (JSON or form): {"barcode": "0123456789012"}
     */
    public function processBarcode(): void
    {
        // 1. Read input from JSON first, fall back to form-encoded
        $raw    = file_get_contents('php://input');
        $input  = $raw !== '' ? json_decode($raw, true) : null;
        $barcode = trim($_POST['barcode'] ?? $input['barcode'] ?? '');

        // 2. Validate shape
        if ($barcode === '') {
            jsonResponse(400, [
                'status'  => 'error',
                'message' => 'Barcode is required.',
            ]);
            return;
        }

        if (!preg_match('/^\d{8,13}$/', $barcode)) {
            jsonResponse(422, [
                'status'  => 'error',
                'message' => 'Barcode must be 8 to 13 digits.',
            ]);
            return;
        }

        // 3. Look up the product
        try {
            $product = $this->products->getProductByBarcode($barcode);
        } catch (RuntimeException $e) {
            jsonResponse(500, [
                'status'  => 'error',
                'message' => 'Could not look up the product.',
            ]);
            return;
        }

        // 4. Respond
        if ($product === null) {
            jsonResponse(404, [
                'status'  => 'error',
                'message' => 'Product not found for the given barcode.',
                'barcode' => $barcode,
            ]);
            return;
        }

        jsonResponse(200, [
            'status'  => 'success',
            'product' => $product,
        ]);
    }
}