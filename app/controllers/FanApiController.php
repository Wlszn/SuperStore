<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\FanState;
use RuntimeException;

class FanApiController
{
    private FanState $fan;

    public function __construct()
    {
        $this->fan = new FanState();
    }

    /** GET /api/fan */
    public function show(): void
    {
        try {
            jsonResponse(200, ['status' => 'success', 'fan' => $this->fan->current()]);
        } catch (RuntimeException $e) {
            jsonResponse(500, ['status' => 'error', 'message' => 'Could not read the fan state.']);
        }
    }

    /** POST /api/fan with a JSON body: {"on": true} */
    public function update(): void
    {
        $data = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($data) || !isset($data['on']) || !is_bool($data['on'])) {
            jsonResponse(422, [
                'status'  => 'error',
                'message' => 'Send a JSON body like {"on": true} or {"on": false}.',
            ]);
            return;
        }

        try {
            $this->fan->set($data['on']);
            jsonResponse(200, ['status' => 'success', 'fan' => $this->fan->current()]);
        } catch (RuntimeException $e) {
            jsonResponse(500, ['status' => 'error', 'message' => 'Could not change the fan state.']);
        }
    }
}