<?php
declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;
use PDO;

class Fridge
{
    //limits for a fridge threshold (°C).
    private const MIN_TEMP = -30.0;
    private const MAX_TEMP = 50.0;

    public function __construct(private PDO $pdo)
    {
    }

    /** All fridges, e.g. for the threshold settings page. */
    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT id, name, max_temp FROM fridge ORDER BY id');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'normalize'], $rows);
    }

    /** One fridge by id, or null if it doesn't exist. */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, max_temp FROM fridge WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->normalize($row);
    }

    /** One fridge by name ('Frig1' / 'Frig2'), matching the MQTT topic. */
    public function findByName(string $name): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, max_temp FROM fridge WHERE name = :name');
        $stmt->execute(['name' => $name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->normalize($row);
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

        $stmt = $this->pdo->prepare('UPDATE fridge SET max_temp = :max_temp WHERE id = :id');
        $stmt->execute(['max_temp' => $maxTemp, 'id' => $id]);

        return $stmt->rowCount() > 0;
    }

    private function normalize(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['max_temp'] = (float) $row['max_temp'];

        return $row;
    }
}