<?php
declare(strict_types=1);


namespace App\Controllers;
session_start();

use App\Models\ProductModel;
use App\Services\Cart;
use RuntimeException;

class BarcodeController
{
    private ProductModel $products;
    private Cart $cart;
   

    public function __construct()
    {
        // Uses ProductModel, not ProductController — see notes below.
        $this->products = new ProductModel();
        $this->cart     = new Cart();
    }

    /**
     * POST /api/barcode
     * Body (JSON or form): {"barcode": "0123456789012"}
     */
    public function processBarcode(): void
    {
        // 1. Read input
       
        $raw     = file_get_contents('php://input');
        $input   = $raw !== '' ? json_decode($raw, true) : null;
        $barcode = trim((string)($_POST['barcode'] ?? $input['barcode'] ?? ''));

        // 2. Validate shape — must be a valid UPC or EPC
        if ($barcode === '') {
            jsonResponse(400, ['status' => 'error', 'message' => 'Barcode is required.']);
            return;
        }

        $type = $this->detectCodeType($barcode);
        if ($type === null) {
            jsonResponse(422, [
                'status'  => 'error',
                'message' => 'Code must be a UPC (8–13 digits) or an EPC (8–24 hex chars).',
            ]);
            return;
        }

        // 3. Look up product
        try {
            $product = $this->products->findByCode($barcode);
        } catch (RuntimeException $e) {
            jsonResponse(500, ['status' => 'error', 'message' => 'Could not look up the product.']);
            return;
        }

        if ($product === null) {
            jsonResponse(404, [
                'status'  => 'error',
                'message' => $type === 'upc'
                    ? 'No product with this UPC.'
                    : 'No product with this EPC.',
                'code'    => $barcode,
                'type'    => $type,
            ]);
            return;
        }

        // 4. Put it in the session cart
        $this->cart->addProduct($product);

        // 5. Respond with the product + cart state so the UI updates in one call
        jsonResponse(200, [
            'status'  => 'success',
            'type'    => $type,
            'product' => $product,
            'cart'    => [
                'items' => array_values($this->cart->items()),
                'count' => $this->cart->count(),
                'total' => $this->cart->total(),
            ],
        ]);

        header("Refresh: 2");
    }

    private function detectCodeType(string $code): ?string
    {
        if (preg_match('/^\d{8,13}$/', $code)) {
            return 'upc';
        }
        if (preg_match('/^[0-9A-F]{8,24}$/', $code) && preg_match('/[A-F]/', $code)) {
            return 'epc';
        }
        return null;
    }
}