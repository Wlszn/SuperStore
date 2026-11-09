<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\ScanModel;
use RuntimeException;

/**
 * RFID tags read by scripts/rfid.py on the Pi.
 *
 *   POST /api/scans                  one tag, or every tag the reader saw at once
 *   GET  /api/scans/latest           the most recent scan (start from its id)
 *   GET  /api/scans/latest?after=12  every scan newer than id 12, oldest first
 */
class ScanApiController
{
    private ScanModel $scans;

    public function __construct()
    {
        $this->scans = new ScanModel();
    }

    /** POST /api/scans: one tag, or every tag the reader saw at once. */
    public function store(): void
    {
        $data = json_decode((string) file_get_contents('php://input'), true);

        if (is_array($data) && isset($data['epc'])) {
            $epcs = [$data['epc']];
        } elseif (is_array($data) && isset($data['epcs']) && is_array($data['epcs'])) {
            $epcs = array_values($data['epcs']);
        } else {
            jsonResponse(400, [
                'status' => 'error',
                'message' => 'Send a JSON body like {"epc":"E28011700000020A1B2C3D01"} or {"epcs":[...]}.',
            ]);
            return;
        }

        [$epcs, $errors] = $this->scans->validate($epcs);
        if ($errors !== []) {
            jsonResponse(422, ['status' => 'error', 'message' => 'The scan is not valid.', 'errors' => $errors]);
            return;
        }

        try {
            $saved = $this->scans->record($epcs);
        } catch (RuntimeException $e) {
            jsonResponse(500, ['status' => 'error', 'message' => 'Could not save the scan.']);
            return;
        }

        jsonResponse(201, ['status' => 'success', 'scans' => $saved]);
    }

    /** GET /api/scans/latest[?after=ID]: what the pages poll for new tags. */
    public function latest(): void
    {
        $after = filter_var($_GET['after'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        try {
            if ($after === false || $after === null) {
                $latest = $this->scans->latest();
                $scans = $latest === null ? [] : [$latest];
            } else {
                $scans = $this->scans->after($after);
            }
        } catch (RuntimeException $e) {
            jsonResponse(500, ['status' => 'error', 'message' => 'Could not load the scans.']);
            return;
        }

        jsonResponse(200, [
            'status' => 'success',
            'scans' => $scans,
            'last_id' => $scans === [] ? ($after ?: 0) : end($scans)['id'],
        ]);
    }
}
