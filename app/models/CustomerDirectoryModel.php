<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\SupaClient;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Admin Customers page: every registered account with its shopping totals.
 * Password hashes are never selected.
 */
class CustomerDirectoryModel
{
    /**
     * Every customer, A to Z: id, name, email, phone, address, membership_number, points,
     * is_admin, joined, purchases (count), spent (total paid) and last_purchase (null if none).
     * Dates are DateTimeImmutable in store time.
     */
    public function all(): array
    {
        $customers = SupaClient::rows('GET', 'customers', [
            'select' => 'id,name,email,phone,address,membership_number,points,is_admin,created_at',
            'order' => 'name.asc',
        ]);
        // Two requests in total: the customers, then every member purchase (guests have no customer_id).
        $purchases = SupaClient::rows('GET', 'purchases', [
            'select' => 'customer_id,total,purchased_at',
            'customer_id' => 'not.is.null',
        ]);

        $local = new DateTimeZone(date_default_timezone_get());
        $stats = [];
        foreach ($purchases as $purchase) {
            $id = (int) $purchase['customer_id'];
            $when = (new DateTimeImmutable($purchase['purchased_at']))->setTimezone($local);
            $stats[$id]['purchases'] = ($stats[$id]['purchases'] ?? 0) + 1;
            $stats[$id]['spent'] = ($stats[$id]['spent'] ?? 0) + (float) $purchase['total'];
            if (!isset($stats[$id]['last']) || $when > $stats[$id]['last']) {
                $stats[$id]['last'] = $when;
            }
        }

        return array_map(function (array $row) use ($stats, $local): array {
            $id = (int) $row['id'];
            return [
                'id' => $id,
                'name' => (string) $row['name'],
                'email' => (string) $row['email'],
                'phone' => (string) ($row['phone'] ?? ''),
                'address' => (string) ($row['address'] ?? ''),
                'membership_number' => (string) ($row['membership_number'] ?? ''),
                'points' => (int) ($row['points'] ?? 0),
                'is_admin' => (bool) ($row['is_admin'] ?? false),
                // created_at is stored in UTC without a zone.
                'joined' => empty($row['created_at']) ? null
                    : (new DateTimeImmutable((string) $row['created_at'], new DateTimeZone('UTC')))->setTimezone($local),
                'purchases' => $stats[$id]['purchases'] ?? 0,
                'spent' => round($stats[$id]['spent'] ?? 0, 2),
                'last_purchase' => $stats[$id]['last'] ?? null,
            ];
        }, $customers);
    }

    /**
     * The widgets at the top of the page, from all(): totals for the last 30 days,
     * the 5 customers who spent the most and the 5 newest accounts.
     */
    public function summary(array $customers): array
    {
        $since = new DateTimeImmutable('-30 days');
        $top = array_filter($customers, fn(array $c): bool => $c['spent'] > 0);
        usort($top, fn(array $a, array $b): int => $b['spent'] <=> $a['spent']);
        $newest = array_filter($customers, fn(array $c): bool => $c['joined'] !== null);
        usort($newest, fn(array $a, array $b): int => $b['joined'] <=> $a['joined']);

        return [
            'total' => count($customers),
            'new' => count(array_filter($customers, fn(array $c): bool => $c['joined'] !== null && $c['joined'] >= $since)),
            'active' => count(array_filter($customers, fn(array $c): bool => $c['last_purchase'] !== null && $c['last_purchase'] >= $since)),
            'points' => array_sum(array_column($customers, 'points')),
            'top' => array_slice($top, 0, 5),
            'newest' => array_slice($newest, 0, 5),
        ];
    }
}
