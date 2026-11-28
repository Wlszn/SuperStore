<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\BleReading;
use InvalidArgumentException;
use RuntimeException;

class BleApiController
{
    private BleReading $readings;

    public function __construct()
    {
        $this->readings = new BleReading();
    }

    /**
     * POST /api/ble-readings with a JSON body, sent by scripts/ble.py:
     * {"mac": "c300455da512", "temp": 22.4, "hum": 41.0, "lux": 310, "motion": true, "battery": 95}
     * Only "mac" is required; a value the sensor has not sent yet is null or left out.
     */
    public function store(): void
    {
        $data = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($data)) {
            jsonResponse(400, [
                'status'  => 'error',
                'message' => 'Send a JSON body like {"mac":"c300455da512","temp":22.4,"hum":41.0,"lux":310,"motion":true,"battery":95}.',
            ]);
            return;
        }

        // 1. Validate the input, one clear message per problem.
        $errors = [];

        $mac = isset($data['mac']) && is_string($data['mac']) ? BleReading::normalizeMac($data['mac']) : null;
        if ($mac === null) {
            $errors['mac'] = "'mac' must be the sensor's MAC address, such as C3:00:45:5D:A5:12.";
        }
        foreach (['temp', 'hum', 'lux'] as $field) {
            if (isset($data[$field]) && !is_int($data[$field]) && !is_float($data[$field])) {
                $errors[$field] = "'$field' must be a number or null.";
            }
        }
        if (isset($data['battery']) && !is_int($data['battery'])) {
            $errors['battery'] = "'battery' must be a whole number or null.";
        }
        if (isset($data['motion']) && !is_bool($data['motion'])) {
            $errors['motion'] = "'motion' must be true, false or null.";
        }

        if ($errors !== []) {
            jsonResponse(422, [
                'status'  => 'error',
                'message' => 'The reading is incomplete.',
                'errors'  => $errors,
            ]);
            return;
        }

        // 2. Save it.
        try {
            $reading = $this->readings->record(
                $mac,
                isset($data['temp']) ? (float) $data['temp'] : null,
                isset($data['hum']) ? (float) $data['hum'] : null,
                isset($data['lux']) ? (float) $data['lux'] : null,
                $data['motion'] ?? null,
                $data['battery'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            jsonResponse(422, ['status' => 'error', 'message' => $e->getMessage()]);
            return;
        } catch (RuntimeException $e) {
            jsonResponse(500, ['status' => 'error', 'message' => 'Could not save the reading.']);
            return;
        }

        jsonResponse(201, ['status' => 'success', 'reading' => $reading]);
    }

    /**
     * GET /api/ble-readings/latest[?mac=c300455da512]: the newest reading.
     * 'reading' is null until the sensor has sent something.
     */
    public function latest(): void
    {
        $mac = null;
        if (isset($_GET['mac']) && $_GET['mac'] !== '') {
            $mac = is_string($_GET['mac']) ? BleReading::normalizeMac($_GET['mac']) : null;
            if ($mac === null) {
                jsonResponse(422, ['status' => 'error', 'message' => "'mac' must be a MAC address."]);
                return;
            }
        }

        try {
            jsonResponse(200, ['status' => 'success', 'reading' => $this->readings->latest($mac)]);
        } catch (RuntimeException $e) {
            jsonResponse(500, ['status' => 'error', 'message' => 'Could not load the latest reading.']);
        }
    }
}