<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Fridge;
use InvalidArgumentException;
use RuntimeException;

/**
 * Per-fridge temperature thresholds.
 *
 *   GET  /thresholds      settings page (login required)
 *   POST /thresholds      save one fridge's threshold (login + CSRF token required)
 *   GET  /api/thresholds  thresholds as JSON (read by the alert service on the Pi)
 */
class ThresholdController
{
    private Fridge $fridges;

    public function __construct()
    {
        $this->fridges = new Fridge();
    }

    /** GET /thresholds: render the settings page. */
    public function index(): void
    {
        if (!$this->isLoggedIn()) {
            header('Location: /login');
            exit;
        }

        try {
            $fridges = $this->fridges->all();
        } catch (RuntimeException $e) {
            http_response_code(500);
            echo 'Could not load the thresholds. Please try again.';
            return;
        }

        $csrfToken = $this->csrfToken();

        // $fridges and $csrfToken are visible inside the view.
        require APP_ROOT . '/app/views/thresholds.php';
    }

    /** GET /api/thresholds: all fridges with their current threshold. */
    public function json(): void
    {
        try {
            $this->respond(200, [
                'status'  => 'success',
                'fridges' => $this->fridges->all(),
            ]);
        } catch (RuntimeException $e) {
            $this->respond(500, [
                'status'  => 'error',
                'message' => 'Could not load the thresholds.',
            ]);
        }
    }

    /** POST /thresholds: change one fridge's threshold. */
    public function update(): void
    {
        if (!$this->isLoggedIn()) {
            $this->respond(401, ['status' => 'error', 'message' => 'Please sign in first.']);
            return;
        }

        if (!hash_equals($this->csrfToken(), (string) ($_POST['csrf_token'] ?? ''))) {
            $this->respond(403, ['status' => 'error', 'message' => 'Your session expired. Reload the page and try again.']);
            return;
        }

        // 1. Validate the input, one clear message per problem.
        $errors = [];

        $fridgeId = filter_var($_POST['fridge_id'] ?? null, FILTER_VALIDATE_INT);
        if ($fridgeId === false || $fridgeId < 1) {
            $errors['fridge_id'] = 'Unknown fridge.';
        }

        // Accept "8,5" as well as "8.5" (decimal comma).
        $rawTemp = str_replace(',', '.', trim((string) ($_POST['max_temp'] ?? '')));
        if ($rawTemp === '') {
            $errors['max_temp'] = 'Please enter a temperature.';
        } elseif (!is_numeric($rawTemp)) {
            $errors['max_temp'] = 'The temperature must be a number, for example 8 or 8.5.';
        }

        if ($errors !== []) {
            $this->respond(422, [
                'status'  => 'error',
                'message' => 'Please fix the highlighted fields and try again.',
                'errors'  => $errors,
            ]);
            return;
        }

        $maxTemp = (float) $rawTemp;

        // 2. Save it.
        try {
            $fridge = $this->fridges->find($fridgeId);
            if ($fridge === null || !$this->fridges->updateMaxTemp($fridgeId, $maxTemp)) {
                $this->respond(404, ['status' => 'error', 'message' => 'That fridge does not exist.']);
                return;
            }
        } catch (InvalidArgumentException $e) {
            $this->respond(422, [
                'status'  => 'error',
                'message' => $e->getMessage(),
                'errors'  => ['max_temp' => $e->getMessage()],
            ]);
            return;
        } catch (RuntimeException $e) {
            $this->respond(500, [
                'status'  => 'error',
                'message' => 'Something went wrong on our side. Please try again.',
            ]);
            return;
        }

        $this->respond(200, [
            'status'  => 'success',
            'message' => sprintf('Threshold for %s is now %s °C.', $fridge['name'], round($maxTemp, 1)),
            'fridge'  => ['id' => $fridgeId, 'name' => $fridge['name'], 'max_temp' => round($maxTemp, 1)],
        ]);
    }

    private function isLoggedIn(): bool
    {
        return isset($_SESSION['customer_id']);
    }

    /** One random token per session; the page sends it back with every save. */
    private function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    private function respond(int $status, array $body): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($body);
    }
}