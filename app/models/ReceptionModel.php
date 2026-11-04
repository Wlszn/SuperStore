<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\SupaClient;
use DateTimeImmutable;

/**
 * Reception model: the only place that talks to the `receptions` table.
 * A reception is one delivery of a product (quantity + date received).
 */
class ReceptionModel
{
    private const TABLE = 'receptions';
    private const COLUMNS = 'id,product_id,quantity,received_at';

    private const MAX_QUANTITY = 100000;

    /** Every reception of one product, newest first. */
    public function forProduct(int $productId): array
    {
        $rows = SupaClient::rows('GET', self::TABLE, [
            'select'     => self::COLUMNS,
            'product_id' => 'eq.' . $productId,
            'order'      => 'received_at.desc,id.desc',
        ]);

        return array_map([$this, 'normalize'], $rows);
    }

    /** Record a reception. $data must come from validate(). Returns the new row. */
    public function create(array $data): array
    {
        $rows = SupaClient::rows('POST', self::TABLE, null, $data);

        return $this->normalize($rows[0]);
    }

    /**
     * Check and clean the "receive stock" form.
     * Returns [$data, $errors] like ProductModel::validate().
     * The date defaults to today and can't be in the future.
     */
    public function validate(array $input): array
    {
        $errors = [];

        $productId = filter_var($input['product_id'] ?? null, FILTER_VALIDATE_INT);
        if ($productId === false || $productId < 1) {
            $errors['product_id'] = 'Please choose a product.';
        }

        $quantity = filter_var($input['quantity'] ?? null, FILTER_VALIDATE_INT);
        if ($quantity === false || $quantity < 1 || $quantity > self::MAX_QUANTITY) {
            $errors['quantity'] = sprintf('The quantity must be a whole number from 1 to %d.', self::MAX_QUANTITY);
        }

        $today = new DateTimeImmutable('today');
        $rawDate = trim((string) ($input['received_at'] ?? ''));
        $date = $rawDate === '' ? $today : DateTimeImmutable::createFromFormat('!Y-m-d', $rawDate);

        // createFromFormat accepts 2026-02-31 and rolls it over, so compare it back.
        if ($date === false || $date->format('Y-m-d') !== ($rawDate === '' ? $today->format('Y-m-d') : $rawDate)) {
            $errors['received_at'] = 'Please enter a valid date.';
        } elseif ($date > $today) {
            $errors['received_at'] = 'The date received cannot be in the future.';
        }

        $data = [
            'product_id'  => (int) $productId,
            'quantity'    => (int) $quantity,
            'received_at' => $date ? $date->format('Y-m-d') : null,
        ];

        return [$data, $errors];
    }

    /** Make sure every value has a predictable PHP type. */
    private function normalize(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['product_id'] = (int) $row['product_id'];
        $row['quantity'] = (int) $row['quantity'];

        return $row;
    }
}
