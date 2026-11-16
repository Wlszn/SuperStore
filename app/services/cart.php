<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Session-backed shopping cart.
 *
 * Structure of $_SESSION['cart']:
 *   [
 *     'items' => [
 *        <product_id> => [
 *           'product_id' => 12,
 *           'name'       => 'Coca-Cola 500ml',
 *           'category'   => 'Beverage',
 *           'price'      => 2.50,        // float
 *           'quantity'   => 2,
 *           'image_url'  => '...',
 *        ],
 *     ],
 *   ]
 */
class Cart
{
    private const KEY = 'cart';

    public function __construct()
    {
        if (!isset($_SESSION[self::KEY]['items']) || !is_array($_SESSION[self::KEY]['items'])) {
            $_SESSION[self::KEY] = ['items' => []];
        }
    }

    /**
     * Add one unit of a product (bumps quantity if already present).
     * $product is the normalized array returned by ProductModel::findByEpc().
     */
    public function addProduct(array $product): void
    {
        $id = (int)($product['id'] ?? 0);
        if ($id <= 0) {
            return;
        }

        if (isset($_SESSION[self::KEY]['items'][$id])) {
            $_SESSION[self::KEY]['items'][$id]['quantity']++;
            return;
        }

        $_SESSION[self::KEY]['items'][$id] = [
            'product_id' => $id,
            'name'       => (string)($product['name'] ?? ''),
            'category'   => (string)($product['category'] ?? ''),
            'price'      => (float)($product['price'] ?? 0),
            'quantity'   => 1,
            'image_url'  => $product['image_url'] ?? null,
        ];
    }

    public function removeProduct(int $productId): void
    {
        unset($_SESSION[self::KEY]['items'][$productId]);
    }

    public function setQuantity(int $productId, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->removeProduct($productId);
            return;
        }
        if (isset($_SESSION[self::KEY]['items'][$productId])) {
            $_SESSION[self::KEY]['items'][$productId]['quantity'] = $quantity;
        }
    }

    public function clear(): void
    {
        $_SESSION[self::KEY] = ['items' => []];
    }

    /** @return array<int, array> items indexed by product_id */
    public function items(): array
    {
        return $_SESSION[self::KEY]['items'];
    }

    public function total(): float
    {
        $sum = 0.0;
        foreach ($_SESSION[self::KEY]['items'] as $item) {
            $sum += (float)$item['price'] * (int)$item['quantity'];
        }
        return round($sum, 2);
    }

    public function count(): int
    {
        $n = 0;
        foreach ($_SESSION[self::KEY]['items'] as $item) {
            $n += (int)$item['quantity'];
        }
        return $n;
    }

    public function isEmpty(): bool
    {
        return $_SESSION[self::KEY]['items'] === [];
    }
}