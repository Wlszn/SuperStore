<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\SupaClient;

/**
 * A product TYPE, identified by its barcode (e.g. "KitKat 4 Finger 45g", producer Nestle).
 * The physical items of a type, one per RFID tag, are in ItemModel; stock = items not sold yet.
 */
class ProductModel
{
    private const TABLE = 'products';
    private const STOCK_VIEW = 'product_stock';
    private const COLUMNS = 'id,name,category,price,upc,producer,image_url,in_stock';

    private const MAX_PRICE = 99999999.99; // NUMERIC(10,2)

    /** At or below this quantity the dashboard flags the product as low stock. */
    public const LOW_STOCK = 5;

    /** All products with their current stock, for the inventory dashboard. */
    public function all(): array
    {
        $rows = SupaClient::rows('GET', self::STOCK_VIEW, [
            'select' => self::COLUMNS,
            'order' => 'name.asc',
        ]);

        return array_map([$this, 'normalize'], $rows);
    }

    /** One product by id, or null if it doesn't exist. */
    public function find(int $id): ?array
    {
        $rows = SupaClient::rows('GET', self::STOCK_VIEW, [
            'select' => self::COLUMNS,
            'id' => 'eq.' . $id,
            'limit' => 1,
        ]);

        return $rows ? $this->normalize($rows[0]) : null;
    }

    /** The categories the product form offers, in alphabetical order. */
    public function categories(): array
    {
        $rows = SupaClient::rows('GET', 'categories', ['select' => 'name', 'order' => 'name.asc']);

        return array_column($rows, 'name');
    }

    /** The product type whose barcode (UPC) matches a scan, or null. */
    public function findByCode(string $code): ?array
    {
        $code = trim($code);
        if ($code === '' || strlen($code) > 13 || preg_match('/[\x00-\x1F\x7F]/', $code)) {
            return null;
        }
        $rows = SupaClient::rows('GET', self::STOCK_VIEW, [
            'select' => self::COLUMNS, 'upc' => 'eq.' . $code, 'limit' => 1,
        ]);
        if (!$rows && ctype_digit($code)) {
            $alternateUpc = strlen($code) === 12 ? self::normalizeUpc($code) : '';
            if (strlen($code) === 13 && str_starts_with($code, '0')) {
                $alternateUpc = substr($code, 1);
            }
            if ($alternateUpc !== '') {
                $rows = SupaClient::rows('GET', self::STOCK_VIEW, [
                    'select' => self::COLUMNS,
                    'upc' => 'eq.' . $alternateUpc,
                    'limit' => 1,
                ]);
            }
        }
        return $rows ? $this->normalize($rows[0]) : null;
    }


    /** Insert a product. $data must come from validate(). Returns the new row. */
    public function create(array $data): array
    {
        $rows = SupaClient::rows('POST', self::TABLE, null, $data);

        // A new product type has no items yet.
        return $this->normalize($rows[0] + ['in_stock' => 0]);
    }

    public function update(int $id, array $data): bool
    {
        // PATCH returns the updated rows; an empty list means no product had that id.
        $rows = SupaClient::rows('PATCH', self::TABLE, ['id' => 'eq.' . $id], $data);

        return $rows !== [];
    }

    /** Delete a product type (its receptions and items go with it, ON DELETE CASCADE). */
    public function delete(int $id): void
    {
        SupaClient::rows('DELETE', self::TABLE, ['id' => 'eq.' . $id]);
    }

    public function validate(array $input, ?int $id = null): array
    {
        $errors = [];

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Please enter the product name.';
        } elseif (mb_strlen($name) > 100) {
            $errors['name'] = 'Name cannot be longer than 100 characters.';
        }

        $category = trim((string) ($input['category'] ?? ''));
        if (!in_array($category, $this->categories(), true)) {
            $errors['category'] = 'Please choose a category from the list.';
        }

        // Accept "2,49" as well as "2.49" (decimal comma).
        $rawPrice = str_replace(',', '.', trim((string) ($input['price'] ?? '')));
        if ($rawPrice === '') {
            $errors['price'] = 'Please enter a price.';
        } elseif (!is_numeric($rawPrice) || (float) $rawPrice < 0 || (float) $rawPrice > self::MAX_PRICE) {
            $errors['price'] = 'The price must be a positive number, for example 2.49.';
        }

        // The barcode identifies the type: scanning it selects this product when receiving stock.
        $upc = trim((string) ($input['upc'] ?? ''));

        if ($upc === '') {
            $errors['upc'] = 'Scan or enter the barcode of this product.';
        } elseif (mb_strlen($upc) > 13 || preg_match('/[\x00-\x1F\x7F]/', $upc)) {
            $errors['upc'] = 'The barcode must contain 1 to 13 printable characters.';
        } elseif (preg_match('/^\d{12,13}$/', $upc) && !self::hasValidCheckDigit(self::normalizeUpc($upc))) {
            // A real UPC/EAN ends with a check digit, so a typo or bad scan is caught here.
            $errors['upc'] = 'This barcode is not valid (wrong last digit). Scan it again or check for a typo.';
        }

        $producer = trim((string) ($input['producer'] ?? ''));
        if ($producer === '') {
            $errors['producer'] = 'Please enter the producer company.';
        } elseif (mb_strlen($producer) > 100) {
            $errors['producer'] = 'Producer cannot be longer than 100 characters.';
        }

        // Optional picture for the dashboard and the checkout screen.
        $imageUrl = trim((string) ($input['image_url'] ?? ''));
        if ($imageUrl !== '' && (!preg_match('#^https?://#i', $imageUrl) || !filter_var($imageUrl, FILTER_VALIDATE_URL) || strlen($imageUrl) > 500)) {
            $errors['image_url'] = 'The image must be a web address starting with http:// or https://.';
        }

        // Only ask the database about duplicates once the format is right.
        if (!isset($errors['upc']) && $this->isTaken('upc', $upc, $id)) {
            $errors['upc'] = 'Another product already uses this UPC.';
        }

        $data = [
            'name' => $name,
            'category' => $category,
            'price' => round((float) $rawPrice, 2),
            'upc' => $upc,
            'producer' => $producer,
            'image_url' => $imageUrl === '' ? null : $imageUrl,
        ];

        return [$data, $errors];
    }

    /** True if another product (not $exceptId) already has this UPC. */
    private function isTaken(string $column, string $value, ?int $exceptId): bool
    {
        $query = [
            'select' => 'id',
            $column => 'eq.' . $value,
            'limit' => 1,
        ];
        if ($exceptId !== null) {
            $query['id'] = 'neq.' . $exceptId;
        }

        return SupaClient::rows('GET', self::TABLE, $query) !== [];
    }

    private static function normalizeUpc(string $upc): string
    {
        return strlen($upc) === 12 ? '0' . $upc : $upc;
    }

    /** EAN-13 check digit*/
    private static function hasValidCheckDigit(string $ean13): bool
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $ean13[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return (10 - $sum % 10) % 10 === (int) $ean13[12];
    }

    /** Make sure every value has a predictable PHP type. */
    private function normalize(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['price'] = (float) $row['price'];
        if (isset($row['in_stock'])) {
            $row['in_stock'] = (int) $row['in_stock'];
            $row['low_stock'] = $row['in_stock'] <= self::LOW_STOCK;
        }

        return $row;
    }
}
