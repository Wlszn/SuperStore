<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\SupaClient;
use DateTimeImmutable;
use DateTimeZone;

/**
 * One customer's purchase history (spec section 3, customer-side reports).
 * Always takes the customer id from the caller (the session), never from the URL.
 */
class PurchaseHistoryModel
{
    /**
     * The customer's receipts from $from to $to (both included, store days in Montreal time),
     * newest first, each with its lines, in ONE request.
     *
     * Each receipt: id, purchased_at, total (amount paid), points_earned, points_redeemed,
     * payment_method, subtotal (before the points discount) and items [name, unit_price, quantity, amount].
     */
    public function receipts(int $customerId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $rows = SupaClient::rows('GET', 'purchases', [
            'select' => 'id,total,points_earned,points_redeemed,payment_method,purchased_at,'
                . 'purchase_items(name,unit_price,quantity)',
            'customer_id' => 'eq.' . $customerId,
            // Two conditions on the same column: [start of $from, start of the day after $to).
            'and' => '(purchased_at.gte.' . $this->utc($from) . ',purchased_at.lt.' . $this->utc($to->modify('+1 day')) . ')',
            'order' => 'purchased_at.desc',
        ]);

        return array_map(function (array $row): array {
            $items = array_map(fn(array $line): array => [
                'name' => $line['name'],
                'unit_price' => (float) $line['unit_price'],
                'quantity' => (int) $line['quantity'],
                'amount' => round((float) $line['unit_price'] * (int) $line['quantity'], 2),
            ], $row['purchase_items'] ?? []);

            return [
                'id' => (int) $row['id'],
                'purchased_at' => $row['purchased_at'],
                'total' => (float) $row['total'],
                'subtotal' => round(array_sum(array_column($items, 'amount')), 2),
                'points_earned' => (int) $row['points_earned'],
                'points_redeemed' => (int) ($row['points_redeemed'] ?? 0),
                'payment_method' => $row['payment_method'] ?? 'card',
                'items' => $items,
            ];
        }, $rows);
    }

    /**
     * Total spent and a spending series for one period (P4-18), computed from the receipts()
     * of that period, so the totals always match the receipts listed on the page.
     * Up to 62 days: one point per day; longer: one per month. Periods without purchases are 0.
     */
    public function spending(array $receipts, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $byMonth = $from->diff($to)->days > 62;
        $key = $byMonth ? 'Y-m' : 'Y-m-d';

        $series = [];
        $start = $byMonth ? $from->modify('first day of this month') : $from;
        for ($period = $start; $period <= $to; $period = $period->modify($byMonth ? '+1 month' : '+1 day')) {
            $series[$period->format($key)] = 0.0;
        }

        $local = new DateTimeZone(date_default_timezone_get());
        foreach ($receipts as $receipt) {
            $period = (new DateTimeImmutable($receipt['purchased_at']))->setTimezone($local)->format($key);
            if (isset($series[$period])) {
                $series[$period] = round($series[$period] + $receipt['total'], 2);
            }
        }

        $total = round(array_sum(array_column($receipts, 'total')), 2);
        $count = count($receipts);

        return [
            'total' => $total,                     // amount paid, after any points discount
            'count' => $count,
            'average' => $count > 0 ? round($total / $count, 2) : 0.0,
            'points_earned' => (int) array_sum(array_column($receipts, 'points_earned')),
            'by' => $byMonth ? 'month' : 'day',
            'series' => $series,                   // "2026-11-20" (or "2026-11") => amount
        ];
    }

    /** Midnight of a Montreal store day, as a UTC timestamp Supabase can compare: 2026-11-20T05:00:00Z. */
    private function utc(DateTimeImmutable $day): string
    {
        $midnight = new DateTimeImmutable($day->format('Y-m-d') . ' 00:00:00', new DateTimeZone(date_default_timezone_get()));

        return $midnight->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
