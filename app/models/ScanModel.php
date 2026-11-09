<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\SupaClient;

class ScanModel
{
    private const TABLE = 'rfid_scans';
    private const COLUMNS = 'id,epc,scanned_at';

    /** Most tags accepted in one request (the reader can see many at once at checkout). */
    private const MAX_BATCH = 100;

    /**
     * Check and clean a list of EPCs.
     * Returns [$epcs, $errors]: $epcs is uppercase, without spaces or duplicates.
     */
    public function validate(array $epcs): array
    {
        $clean = [];
        $errors = [];

        if ($epcs === []) {
            $errors['epc'] = 'Send at least one EPC.';
        } elseif (count($epcs) > self::MAX_BATCH) {
            $errors['epc'] = sprintf('Send at most %d EPCs at a time.', self::MAX_BATCH);
        }

        foreach ($epcs as $epc) {
            $epc = is_string($epc) ? strtoupper(str_replace(' ', '', trim($epc))) : '';
            if (!preg_match('/^[0-9A-F]{1,24}$/', $epc)) {
                $errors['epc'] = 'Every EPC must be up to 24 hexadecimal characters (0-9, A-F).';
                break;
            }
            $clean[$epc] = true; // keys remove duplicates
        }

        return [array_keys($clean), $errors];
    }

    /** Save tags read together. $epcs must come from validate(). Returns the new rows. */
    public function record(array $epcs): array
    {
        $rows = SupaClient::rows('POST', self::TABLE, null, array_map(
            fn(string $epc): array => ['epc' => $epc],
            $epcs
        ));

        return array_map([$this, 'normalize'], $rows);
    }

    /** The most recent scan, or null if nothing has been scanned yet. */
    public function latest(): ?array
    {
        $rows = SupaClient::rows('GET', self::TABLE, [
            'select' => self::COLUMNS,
            'order' => 'id.desc',
            'limit' => 1,
        ]);

        return $rows ? $this->normalize($rows[0]) : null;
    }

    /** Every scan newer than $id, oldest first (the page sends the last id it saw). */
    public function after(int $id): array
    {
        $rows = SupaClient::rows('GET', self::TABLE, [
            'select' => self::COLUMNS,
            'id' => 'gt.' . $id,
            'order' => 'id.asc',
            'limit' => self::MAX_BATCH,
        ]);

        return array_map([$this, 'normalize'], $rows);
    }

    /** Make sure every value has a predictable PHP type. */
    private function normalize(array $row): array
    {
        $row['id'] = (int) $row['id'];

        return $row;
    }
}
