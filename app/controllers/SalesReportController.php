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

    //GET /reports/sales: the Sales Report page (admin only)
    public function page(): void
    {
        if (!$this->adminPageAllowed()) return;

        $filters = $this->readFilters();
        [$from, $to] = $this->period($filters);
        $report = null;
        $ranking = null;
        $trend = [];
        $error = null;
        $categories = [];
        try {
            $categories = (new ProductModel())->categories();
            $error = $this->filterError($filters, $from, $to, $categories);
            if ($error === null) {
                $model = new SalesReportModel();
                $category = $filters['category'] ?: null;
                $report = $model->sales($from, $to, $category);
                $ranking = $model->ranking($from, $to, $this->topLimit($filters), $category);   // P4-11
                $trend = $model->trend($from, $to, $category, $filters['group']);              // P4-12
            }
        } catch (RuntimeException $e) {
            $error = 'Could not load the sales report. Please try again.';
        }

        require APP_ROOT . '/app/views/reports/sales.php';
    }

    //GET /reports/sales/export: the sales table as a CSV download (same filters as the page)
    public function export(): void
    {
        if (!$this->adminPageAllowed()) return;

        $filters = $this->readFilters();
        [$from, $to] = $this->period($filters);
        try {
            $error = $this->filterError($filters, $from, $to, (new ProductModel())->categories());
            $report = $error === null
                ? (new SalesReportModel())->sales($from, $to, $filters['category'] ?: null)
                : null;
        } catch (RuntimeException $e) {
            $error = 'Could not load the sales report. Please try again.';
        }
        if ($error !== null) {
            http_response_code(422);
            echo htmlspecialchars($error) . ' <a href="/reports/sales">Back</a>';
            return;
        }

        $period = $from == $to
            ? $from->format('Y-m-d')
            : $from->format('Y-m-d') . ' to ' . $to->format('Y-m-d');
        $name = 'sales-' . $from->format('Y-m-d') . '_' . $to->format('Y-m-d')
            . ($filters['category'] !== '' ? '-' . preg_replace('/[^A-Za-z0-9]+/', '-', $filters['category']) : '');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '.csv"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");   // UTF-8 BOM so Excel shows accents correctly
        $put = fn (array $row) => fputcsv($out, array_map([$this, 'csvCell'], $row), ',', '"', '');

        $put(['Product', 'Category', 'Sold quantity', 'Revenue', 'Period']);
        foreach ($report['products'] as $p) {
            $put([
                $p['name'] . ($p['deleted'] ? ' (deleted)' : ''),
                $p['category'],
                $p['sold_qty'],
                number_format($p['revenue'], 2, '.', ''),
                $period,
            ]);
        }
        fwrite($out, "\n");
        $put(['Total', '', $report['items_sold'], number_format($report['total_sales'], 2, '.', ''), $period]);
        if ($report['amount_paid'] !== null) {
            $put(['Paid after points', '', '', number_format($report['amount_paid'], 2, '.', ''), $period]);
        }
        fclose($out);
    }

    /** Login redirect / 403 for report pages. Returns true when the admin may continue. */
    private function adminPageAllowed(): bool
    {
        if (!isset($_SESSION['customer_id'])) {
            header('Location: /login');
            return false;
        }
        if (empty($_SESSION['is_admin'])) {
            http_response_code(403);
            echo 'Only store administrators can see reports. <a href="/">Back</a>';
            return false;
        }
        return true;
    }

    /** Filters from the query string. Defaults: last 7 days, all categories, top 3, by day. */
    private function readFilters(): array
    {
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        return [
            'mode' => ($_GET['mode'] ?? 'range') === 'day' ? 'day' : 'range',
            'day' => (string) ($_GET['day'] ?? $today),
            'from' => (string) ($_GET['from'] ?? (new DateTimeImmutable('-6 days'))->format('Y-m-d')),
            'to' => (string) ($_GET['to'] ?? $today),
            'category' => trim((string) ($_GET['category'] ?? '')),
            'top' => (string) ($_GET['top'] ?? 3),                                   // P4-11
            'group' => ($_GET['group'] ?? 'day') === 'week' ? 'week' : 'day',        // P4-12
        ];
    }

    /** A single day is just a range where from = to. */
    private function period(array $filters): array
    {
        $single = $filters['mode'] === 'day';
        return [
            $this->date($single ? $filters['day'] : $filters['from']),
            $this->date($single ? $filters['day'] : $filters['to']),
        ];
    }

    /** How many most/least sold items to show: 1 to 20, or false if invalid. */
    private function topLimit(array $filters): int|false
    {
        return filter_var($filters['top'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 20]]);
    }

    private function filterError(array $filters, ?DateTimeImmutable $from, ?DateTimeImmutable $to, array $categories): ?string
    {
        if ($from === null || $to === null || $from > $to) {
            return 'Choose a valid date range (\'from\' must be before \'to\').';
        }
        if ($filters['category'] !== '' && !in_array($filters['category'], $categories, true)) {
            return 'Unknown category.';
        }
        if ($this->topLimit($filters) === false) {
            return 'Choose how many items to show (1 to 20).';
        }
        return null;
    }

    /** Stops Excel from running a product name like "=HYPERLINK(...)" as a formula. */
    private function csvCell(mixed $value): string
    {
        $value = (string) $value;
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }
}
