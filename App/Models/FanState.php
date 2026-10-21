<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\SupaClient;

//FanState model: the single fan is one row (id = 1) in `fan_state`.

class FanState
{
    private const TABLE = 'fan_state';
    private const ROW_ID = 1;

    /**
     * Current state: ['is_on' => bool, 'updated_at' => string|null].
     * If the row is missing, the fan is reported as off.
     */
    public function current(): array
    {
        $rows = SupaClient::rows('GET', self::TABLE, [
            'select' => 'is_on,updated_at',
            'id'     => 'eq.' . self::ROW_ID,
            'limit'  => 1,
        ]);

        if ($rows === []) {
            return ['is_on' => false, 'updated_at' => null];
        }

        return [
            'is_on'      => (bool) $rows[0]['is_on'],
            'updated_at' => $rows[0]['updated_at'] ?? null,
        ];
    }

    public function isOn(): bool
    {
        return $this->current()['is_on'];
    }

    /** Turn the fan on or off (creates the row if it somehow doesn't exist). */
    public function set(bool $isOn): void
    {
        $data = [
            'is_on'      => $isOn,
            'updated_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        $rows = SupaClient::rows('PATCH', self::TABLE, ['id' => 'eq.' . self::ROW_ID], $data);

        if ($rows === []) {
            SupaClient::rows('POST', self::TABLE, null, ['id' => self::ROW_ID] + $data);
        }
    }
}