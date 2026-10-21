<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\SupaClient;
use InvalidArgumentException;
use RuntimeException;

//Reading model: one row per sensor message (temperature + humidity of one fridge).
class Reading
{
    private const TABLE = 'readings';
    private const COLUMNS = 'id,fridge_id,temperature,humidity,recorded_at';

    // Generous limits: only reject values that can't be real sensor output.
    private const MIN_TEMP = -40.0;
    private const MAX_TEMP = 80.0;

    /**
     * Store a new reading and return the saved row.
     *
     * @throws InvalidArgumentException if a value is outside the possible range
     * @throws RuntimeException if the database rejects the insert (e.g. unknown fridge_id)
     */
    public function record(int $fridgeId, float $temperature, float $humidity): array
    {
        if ($temperature < self::MIN_TEMP || $temperature > self::MAX_TEMP) {
            throw new InvalidArgumentException('Temperature is outside the possible sensor range.');
        }
        if ($humidity < 0.0 || $humidity > 100.0) {
            throw new InvalidArgumentException('Humidity must be between 0 and 100.');
        }

        $rows = SupaClient::rows('POST', self::TABLE, null, [
            'fridge_id'   => $fridgeId,
            'temperature' => round($temperature, 1),
            'humidity'    => round($humidity, 1),
        ]);

        if ($rows === []) {
            throw new RuntimeException('The reading was not saved.');
        }

        return $this->normalize($rows[0]);
    }

    /** Most recent reading of one fridge, or null if it has none yet. */
    public function latestByFridge(int $fridgeId): ?array
    {
        $rows = SupaClient::rows('GET', self::TABLE, [
            'select'    => self::COLUMNS,
            'fridge_id' => 'eq.' . $fridgeId,
            'order'     => 'recorded_at.desc',
            'limit'     => 1,
        ]);

        return $rows ? $this->normalize($rows[0]) : null;
    }

    /**
     * Latest reading of every fridge, keyed by fridge id (for the dashboard gauges).
     * A fridge with no readings yet maps to null.
     *
     * @return array<int, array|null>
     */
    public function latestAll(): array
    {
        $latest = [];
        foreach ((new Fridge())->all() as $fridge) {
            $latest[$fridge['id']] = $this->latestByFridge($fridge['id']);
        }

        return $latest;
    }

    private function normalize(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['fridge_id'] = (int) $row['fridge_id'];
        $row['temperature'] = (float) $row['temperature'];
        $row['humidity'] = (float) $row['humidity'];

        return $row;
    }
}