<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\ProductModel;
use App\Models\SalesReportModel;
use DateTimeImmutable;
use RuntimeException;

/**
 *   GET /api/reports/sales?from=2026-11-01&to=2026-11-15[&category=Dairy]   (JSON, admin only)
 *   For one day, send the same date as from and to.
 */
class SalesReportController
{
    public function sales(): void
    {
        //checks whether user can see reports
        if (empty($_SESSION['customer_id']) || empty($_SESSION['is_admin'])) {
            jsonResponse(403, ['status' => 'error', 'message' => 'Only store administrators can see reports.']);
            return;
        }

        //date range
        $from = $this->date($_GET['from'] ?? '');
        $to = $this->date($_GET['to'] ?? '');
        
        //checks if user inputted valid date range
        if ($from === null || $to === null || $from > $to) {
            jsonResponse(422, ['status' => 'error', 'message' => 'Choose a valid date range (from must be before to).']);
            return;
        }

        try {
            $category = trim((string) ($_GET['category'] ?? ''));
            //checks whether category exists
            if ($category !== '' && !in_array($category, (new ProductModel())->categories(), true)) {
                jsonResponse(422, ['status' => 'error', 'message' => 'Unknown category.']);
                return;
            }

            $report = (new SalesReportModel())->sales($from, $to, $category === '' ? null : $category);
        } catch (RuntimeException $e) {
            jsonResponse(500, ['status' => 'error', 'message' => 'Could not load the sales report.']);
            return;
        }

        jsonResponse(200, ['status' => 'ok'] + $report);
    }

    /** "2026-11-20" -> date, anything else -> null. */
    private function date(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }
}