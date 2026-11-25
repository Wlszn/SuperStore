<?php
declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use RuntimeException;
use function config\supa_request;

/** Sales report (spec 2.2): sold items and revenue per product for a date range. */
class SalesReportModel{
    /**$category null = all categories. Both dates are included. */
    public function sales(DateTimeImmutable $from, DateTimeImmutable $to, ?string $category = null): array
    {
        [$status, $report] = supa_request('POST', 'rpc/sales_report', null, [
            'p_from' => $from -> format('Y-m-d'),
            'p_to' => $to -> format('Y-m-d'),
            'p_category' => $category
        ]);
        if($status !== 200 || !is_array($report)){
            error_log('sales-report failed (HTTP ' . $status . '): ' . json_encode($report));
            throw new RuntimeException('Could not load the sales report');
        }

        //Postgress numerics arrive as string or floats: give PHP predictable types.
        $report['products'] = array_map(fn(array $p) =>
        [
            'product_id' => $p['product_id'] === null ? null : (int) $p['product_id'],
            'name' => $p['name'],
            'category' => $p['category'],
            'deleted' => (bool) $p['deleted'],
            'sold_qty' => (int) $p['sold_qty'],
            'revenue' => (float) $p['revenue'],
        ], $report['products']);
        $report['items_sold'] = (int) $report['items_sold'];
        $report['total_sales'] = (float) $report['total_sales'];
        $report['receipt_count'] = (int) $report['receipt_count'];
        $report['amount_paid'] = $report['amount_paid'] === null ? null : (float) $report['amount_paid'];
    
        return $report;
    }

    /**Revenye and items sold per day or per week. Empty days/weeks come back as 0 */
    public function trend(DateTimeImmutable $from, DateTimeImmutable $to, ?string $category, string $group): array{
        [$status, $rows] = supa_request('POST', 'rpc/sales_trend', null, [
            'p_from' => $from -> format('Y-m-d'),
            'p_to' => $to -> format('Y-m-d'),
            'p_category' => $category,
            'p_group' => $group === 'week' ? 'week' : 'day',
        ]);
        if ($status !== 200 || !is_array($rows)){
            error_log('sales-trend failed (HTTP ' . $status . '): ' . json_encode($rows));
            throw new RuntimeException('Could not load the sales trend');
        }

        return array_map(fn (array $r) => [
            'period' => (string) $r['period'],      // "2026-11-03"
            'items_sold' => (int) $r['items_sold'],
            'revenue' => (float) $r['revenue'],
        ], $rows);
    }
}