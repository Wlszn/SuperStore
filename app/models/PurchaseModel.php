<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\SupaClient;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Purchase model: the only place that talks to `purchases` and `purchase_items`.
 * A purchase is one receipt; its items are the receipt lines.
 */
class PurchaseModel
{
    private const TABLE = 'purchases';
    private const RECEIPT_COLUMNS = 'id,customer_id,total,points_earned,payment_method,purchased_at,'
        . 'items:purchase_items(name,unit_price,quantity),'
        . 'customer:customers(name,email,membership_number,points)';

    /**
     * Run a checkout through the checkout() database function, so the stock
     * check, the purchase, its items and the points are saved all at once.
     *
     * $items: [['product_id' => 1, 'quantity' => 2], ...]
     * Returns ['ok' => true, 'purchase_id' => .., 'total' => .., 'points_earned' => ..]
     *      or ['ok' => false, 'error' => 'out_of_stock', ...].
     */
    public function checkout(?int $customerId, array $items, string $paymentMethod): array
    {
        return SupaClient::rows('POST', 'rpc/checkout', null, [
            'p_customer_id'    => $customerId,
            'p_items'          => $items,
            'p_payment_method' => $paymentMethod,
        ]);
    }

    /** One receipt with its lines and its customer (null for a guest), or null. */
    public function find(int $id): ?array
    {
        $rows = SupaClient::rows('GET', self::TABLE, [
            'select'               => self::RECEIPT_COLUMNS,
            'id'                   => 'eq.' . $id,
            'items.order'          => 'name.asc',
            'limit'                => 1,
        ]);

        return $rows ? $this->normalize($rows[0]) : null;
    }

    /** Every receipt of one customer, newest first (purchase history). */
    public function forCustomer(int $customerId): array
    {
        $rows = SupaClient::rows('GET', self::TABLE, [
            'select'      => self::RECEIPT_COLUMNS,
            'customer_id' => 'eq.' . $customerId,
            'items.order' => 'name.asc',
            'order'       => 'purchased_at.desc,id.desc',
        ]);

        return array_map([$this, 'normalize'], $rows);
    }

    /** Predictable types, line totals, and the date in the store's time zone. */
    private function normalize(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['customer_id'] = $row['customer_id'] === null ? null : (int) $row['customer_id'];
        $row['total'] = (float) $row['total'];
        $row['points_earned'] = (int) $row['points_earned'];

        // Supabase answers in UTC; receipts show the local date and time.
        $row['date'] = (new DateTimeImmutable($row['purchased_at']))
            ->setTimezone(new DateTimeZone(date_default_timezone_get()))
            ->format('Y-m-d H:i');

        $row['item_count'] = 0;
        foreach ($row['items'] as &$item) {
            $item['unit_price'] = (float) $item['unit_price'];
            $item['quantity'] = (int) $item['quantity'];
            $item['line_total'] = round($item['unit_price'] * $item['quantity'], 2);
            $row['item_count'] += $item['quantity'];
        }
        unset($item);

        if ($row['customer'] !== null) {
            $row['customer']['points'] = (int) $row['customer']['points'];
        }

        return $row;
    }
}
