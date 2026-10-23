<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\FanState;
use App\Models\Fridge;
use App\Models\Reading;
use InvalidArgumentException;
use RuntimeException;

class ReadingApiController
{
    private Fridge $fridges;
    private Reading $readings;
    private FanState $fan;

    public function __construct()
    {
        $this->fridges = new Fridge();
        $this->readings = new Reading();
        $this->fan = new FanState();
    }

    /** POST /api/readings with a JSON body: {"fridge": "Frig1", "temp": 4.0, "hum": 55.0} */
    public function store(): void
    {
        $data = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($data)) {
            jsonResponse(400, [
                'status' => 'error',
                'message' => 'Send a JSON body like {"fridge":"Frig1","temp":4.0,"hum":55.0}.',
            ]);
            return;
        }

        // 1. Validate the input, one clear message per problem.
        $errors = [];

        if (!isset($data['fridge']) || !is_string($data['fridge']) || trim($data['fridge']) === '') {
            $errors['fridge'] = "'fridge' must be a fridge name such as Frig1.";
        }
        foreach (['temp', 'hum'] as $field) {
            if (!isset($data[$field]) || !is_numeric($data[$field])) {
                $errors[$field] = "'$field' must be a number.";
            }
        }

        if ($errors !== []) {
            jsonResponse(422, [
                'status' => 'error',
                'message' => 'The reading is incomplete.',
                'errors' => $errors,
            ]);
            return;
        }

        // 2. Save it.
        try {
            $fridge = $this->fridges->findByName(trim($data['fridge']));
            if ($fridge === null) {
                jsonResponse(404, ['status' => 'error', 'message' => 'That fridge does not exist.']);
                return;
            }

            $reading = $this->readings->record($fridge['id'], (float) $data['temp'], (float) $data['hum']);
        } catch (InvalidArgumentException $e) {
            jsonResponse(422, ['status' => 'error', 'message' => $e->getMessage()]);
            return;
        } catch (RuntimeException $e) {
            jsonResponse(500, ['status' => 'error', 'message' => 'Could not save the reading.']);
            return;
        }

        jsonResponse(201, [
            'status' => 'success',
            'reading' => $reading,
            'max_temp' => $fridge['max_temp'],
            'over_threshold' => $reading['temperature'] > $fridge['max_temp'],
        ]);
    }

    /** GET /api/readings/latest: everything the dashboard needs in one call. */
    public function latest(): void
    {
        try {
            $fridges = array_map(function (array $fridge): array {
                $reading = $this->readings->latestByFridge($fridge['id']);

                return [
                    'id' => $fridge['id'],
                    'name' => $fridge['name'],
                    'max_temp' => $fridge['max_temp'],
                    'reading' => $reading,   // null until the fridge has sent something
                    'over_threshold' => $reading !== null && $reading['temperature'] > $fridge['max_temp'],
                ];
            }, $this->fridges->all());

            jsonResponse(200, [
                'status' => 'success',
                'fridges' => $fridges,
                'fan' => $this->fan->current(),
            ]);
        } catch (RuntimeException $e) {
            jsonResponse(500, ['status' => 'error', 'message' => 'Could not load the latest readings.']);
        }
    }
}