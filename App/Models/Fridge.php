<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\SupaClient;
use InvalidArgumentException;

/**
 * Fridge model: the only place that talks to the `fridge` table.
 * Controllers call these methods and never build requests themselves.
 */
class Fridge
{
    private const TABLE = 'fridge';
    private const COLUMNS = 'id,name,max_temp';

    // Sanity limits for a fridge threshold (°C). The column is NUMERIC(4,1).
    private const MIN_TEMP = -30.0;
    private const MAX_TEMP = 50.0;

    /** All fridges, e.g. for the threshold settings page. */
    public function all(): array
    {
        $rows = SupaClient::rows('GET', self::TABLE, [
            'select' => self::COLUMNS,
            'order'  => 'id.asc',
        ]);

        return array_map([$this, 'normalize'], $rows);
    }

    /** One fridge by id, or null if it doesn't exist. */
    public function find(int $id): ?array
    {
        $rows = SupaClient::rows('GET', self::TABLE, [
            'select' => self::COLUMNS,
            'id'     => 'eq.' . $id,
            'limit'  => 1,
        ]);

        return $rows ? $this->normalize($rows[0]) : null;
    }

    /** One fridge by name ('Frig1' / 'Frig2'), matching the MQTT topic. */
    public function findByName(string $name): ?array
    {
        $rows = SupaClient::rows('GET', self::TABLE, [
            'select' => self::COLUMNS,
            'name'   => 'eq.' . $name,
            'limit'  => 1,
        ]);

        return $rows ? $this->normalize($rows[0]) : null;
    }

    /**
     * Change a fridge's temperature threshold.
     * Returns true if a fridge with that id existed and was updated.
     *
     * @throws InvalidArgumentException if the value is outside the allowed range
     */
    public function updateMaxTemp(int $id, float $maxTemp): bool
    {
        if ($maxTemp < self::MIN_TEMP || $maxTemp > self::MAX_TEMP) {
            throw new InvalidArgumentException(
                sprintf('Threshold must be between %.0f and %.0f °C.', self::MIN_TEMP, self::MAX_TEMP)
            );
        }

        // PATCH returns the updated rows; an empty list means no fridge had that id.
        $rows = SupaClient::rows('PATCH', self::TABLE, ['id' => 'eq.' . $id], [
            'max_temp' => round($maxTemp, 1),
        ]);

        return $rows !== [];
    }

    /** Make sure every value has a predictable PHP type. */
    private function normalize(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['max_temp'] = (float) $row['max_temp'];

        return $row;
    }
}