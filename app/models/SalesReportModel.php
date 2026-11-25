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

    /** The $limit most and least sold product (ties at the cut-off included) */
    public function ranking(DateTimeImmutable $from, DateTimeImmutable $to, int $limit, ?string $category = null): array
    {
        [$status, $ranking ] = supa_request('POST', 'rpc/sales_ranking', null, [
            'p_from' => $from->format('Y-m-d'),
            'p_to' => $to->format('Y-m-d'),
            'p_limit' => $limit,
            'p_category' => $category,
        ]);
        if($status !== 200 || !is_array($ranking)) {
            error_log('sales_ranking failed (HTTP ' . $status . '): ' . json_encode($ranking));
            throw new RuntimeException('Could not load the most and least sold items');
        }

        $clean = fn(array $p) => [
            'rank' => (int) $p['rank'],
            'product_id' => $p['product_id'] === null ? null : (int) $p['product_id'],
            'name' => $p['name'],
            'category' => $p['category'],
            'deleted' => (bool) $p['deleted'],
            'sold_qty' => (int) $p['sold_qty'],
        ];
        $ranking['most'] = array_map($clean, $ranking['most']);
        $ranking['least'] = array_map($clean, $ranking['least']);
        $ranking['limit'] = (int) $ranking['limit'];

        return $ranking;
    }
}