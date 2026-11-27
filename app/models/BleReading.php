<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\SupaClient;
use InvalidArgumentException;
use RuntimeException;

/**
 * BleReading model: the only place that talks to the `ble_readings` table.
 * One row per reading of the Minew MSP01 sensor, forwarded by scripts/ble.py.
 * Every value is optional (null = the sensor has not sent it yet).
 */
class BleReading
{
    private const TABLE = 'ble_readings';
    private const COLUMNS = 'id,mac,temperature,humidity,illuminance,motion,battery,recorded_at';

    // Generous limits: only reject values that can't be real sensor output.
    private const MIN_TEMP = -40.0;
    private const MAX_TEMP = 85.0;
    private const MAX_LUX = 200000.0;

    /** "C3:00:45:5D:A5:12" or "c300455da512" -> "c300455da512", or null if it is not a MAC address. */
    public static function normalizeMac(string $mac): ?string
    {
        $mac = strtolower(str_replace([':', '-'], '', trim($mac)));

        return preg_match('/^[0-9a-f]{12}$/', $mac) === 1 ? $mac : null;
    }

    /**
     * Store a new reading and return the saved row.
     *
     * @throws InvalidArgumentException if a value is out of range, or every value is missing
     * @throws RuntimeException if the database rejects the insert
     */
    public function record(string $mac, ?float $temperature, ?float $humidity,
                           ?float $illuminance, ?bool $motion, ?int $battery): array
    {
        if ($temperature === null && $humidity === null && $illuminance === null
            && $motion === null && $battery === null) {
            throw new InvalidArgumentException('The reading has no values.');
        }
        if ($temperature !== null && ($temperature < self::MIN_TEMP || $temperature > self::MAX_TEMP)) {
            throw new InvalidArgumentException('Temperature is outside the possible sensor range.');
        }
        if ($humidity !== null && ($humidity < 0.0 || $humidity > 100.0)) {
            throw new InvalidArgumentException('Humidity must be between 0 and 100.');
        }
        if ($illuminance !== null && ($illuminance < 0.0 || $illuminance > self::MAX_LUX)) {
            throw new InvalidArgumentException('Light must be between 0 and 200000 lux.');
        }
        if ($battery !== null && ($battery < 0 || $battery > 100)) {
            throw new InvalidArgumentException('Battery must be between 0 and 100.');
        }

        $rows = SupaClient::rows('POST', self::TABLE, null, [
            'mac'         => $mac,
            'temperature' => $temperature === null ? null : round($temperature, 1),
            'humidity'    => $humidity === null ? null : round($humidity, 1),
            'illuminance' => $illuminance === null ? null : round($illuminance, 1),
            'motion'      => $motion,
            'battery'     => $battery,
        ]);

        if ($rows === []) {
            throw new RuntimeException('The reading was not saved.');
        }

        return $this->normalize($rows[0]);
    }

    /** The newest reading (of one sensor if $mac is given), or null if there is none yet. */
    public function latest(?string $mac = null): ?array
    {
        $query = [
            'select' => self::COLUMNS,
            'order'  => 'recorded_at.desc',
            'limit'  => 1,
        ];
        if ($mac !== null) {
            $query['mac'] = 'eq.' . $mac;
        }

        $rows = SupaClient::rows('GET', self::TABLE, $query);

        return $rows ? $this->normalize($rows[0]) : null;
    }

    /** Make sure every value has a predictable PHP type (null stays null). */
    private function normalize(array $row): array
    {
        return [
            'id'          => (int) $row['id'],
            'mac'         => $row['mac'],
            'temperature' => $row['temperature'] === null ? null : (float) $row['temperature'],
            'humidity'    => $row['humidity'] === null ? null : (float) $row['humidity'],
            'illuminance' => $row['illuminance'] === null ? null : (float) $row['illuminance'],
            'motion'      => $row['motion'] === null ? null : (bool) $row['motion'],
            'battery'     => $row['battery'] === null ? null : (int) $row['battery'],
            'recorded_at' => $row['recorded_at'],
        ];
    }
}