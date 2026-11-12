<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\SupaClient;

class ProductModel
{
    private const TABLE = 'products';
    private const STOCK_VIEW = 'product_stock';
    private const COLUMNS = 'id,name,category,price,upc,epc,producer,image_url,in_stock';

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

    /** The product whose UPC (barcode) or EPC (RFID tag) matches a scan, or null. */
    public function findByCode(string $code): ?array
    {
        $code = strtoupper(str_replace(' ', '', trim($code)));

        // Codes are only digits/hex letters; anything else can't match and
        // would break the `or=(...)` filter syntax.
        if ($code === '' || !ctype_alnum($code)) {
            return null;
        }

        $rows = SupaClient::rows('GET', self::STOCK_VIEW, [
            'select' => self::COLUMNS,
            'or' => sprintf('(upc.eq.%1$s,epc.eq.%1$s)', $code),
            'limit' => 1,
        ]);

        return $rows ? $this->normalize($rows[0]) : null;
    }


    /** Insert a product. $data must come from validate(). Returns the new row. */
    public function create(array $data): array
    {
        $rows = SupaClient::rows('POST', self::TABLE, null, $data);

        // A new product has no receptions yet.
        return $this->normalize($rows[0] + ['in_stock' => 0]);
    }

    public function update(int $id, array $data): bool
    {
        // PATCH returns the updated rows; an empty list means no product had that id.
        $rows = SupaClient::rows('PATCH', self::TABLE, ['id' => 'eq.' . $id], $data);

        return $rows !== [];
    }

    /** Delete a product (its receptions go with it, ON DELETE CASCADE). */
    public function delete(int $id): void
    {
        SupaClient::rows('DELETE', self::TABLE, ['id' => 'eq.' . $id]);
    }

    public function pointsCalculate(float $price): int 
    {
        return (int)$price * 10;
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
        if ($category === '') {
            $errors['category'] = 'Please enter a category, for example Beverage.';
        } elseif (mb_strlen($category) > 50) {
            $errors['category'] = 'Category cannot be longer than 50 characters.';
        }

        // Accept "2,49" as well as "2.49" (decimal comma).
        $rawPrice = str_replace(',', '.', trim((string) ($input['price'] ?? '')));
        if ($rawPrice === '') {
            $errors['price'] = 'Please enter a price.';
        } elseif (!is_numeric($rawPrice) || (float) $rawPrice < 0 || (float) $rawPrice > self::MAX_PRICE) {
            $errors['price'] = 'The price must be a positive number, for example 2.49.';
        }

        // Scanners and readers may add spaces; EPCs are hex, so compare in uppercase.
        $upc = str_replace(' ', '', trim((string) ($input['upc'] ?? '')));
        $epc = strtoupper(str_replace(' ', '', trim((string) ($input['epc'] ?? ''))));

        if ($upc === '' && $epc === '') {
            $errors['upc'] = 'Enter a UPC or an EPC so the product can be scanned.';
        }
        if ($upc !== '' && !preg_match('/^\d{8,13}$/', $upc)) {
            $errors['upc'] = 'The UPC must be 8 to 13 digits.';
        }
        if ($epc !== '' && !preg_match('/^[0-9A-F]{1,24}$/', $epc)) {
            $errors['epc'] = 'The EPC must be up to 24 hexadecimal characters (0-9, A-F).';
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
        if ($upc !== '' && !isset($errors['upc']) && $this->isTaken('upc', $upc, $id)) {
            $errors['upc'] = 'Another product already uses this UPC.';
        }
        if ($epc !== '' && !isset($errors['epc']) && $this->isTaken('epc', $epc, $id)) {
            $errors['epc'] = 'Another product already uses this EPC.';
        }

        $data = [
            'name' => $name,
            'category' => $category,
            'price' => round((float) $rawPrice, 2),
            'upc' => $upc === '' ? null : $upc,
            'epc' => $epc === '' ? null : $epc,
            'producer' => $producer,
            'image_url' => $imageUrl === '' ? null : $imageUrl,
        ];

        return [$data, $errors];
    }

    /** True if another product (not $exceptId) already has this UPC/EPC. */
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
