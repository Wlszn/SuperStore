<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\SupaClient;
use InvalidArgumentException;
use RuntimeException;

class Alert
{
    private const TABLE = 'alerts';
    private const COLUMNS = 'id,fridge_id,temperature,sent_at,response';
    private const RESPONSES = ['yes', 'no'];

    /**
     * Record that an alert email was sent and return the saved row (its id goes
     * in the email subject so a YES reply can be matched to this alert).
     *
     * @throws RuntimeException if the alert could not be saved
     */
    public function create(int $fridgeId, float $temperature): array
    {
        $rows = SupaClient::rows('POST', self::TABLE, null, [
            'fridge_id'   => $fridgeId,
            'temperature' => round($temperature, 1),
        ]);

        if ($rows === []) {
            throw new RuntimeException('The alert was not saved.');
        }

        return $this->normalize($rows[0]);
    }

    /** One alert by id, or null. */
    public function find(int $id): ?array
    {
        $rows = SupaClient::rows('GET', self::TABLE, [
            'select' => self::COLUMNS,
            'id'     => 'eq.' . $id,
            'limit'  => 1,
        ]);

        return $rows ? $this->normalize($rows[0]) : null;
    }

    /**
     * Cooldown check: the newest alert for this fridge sent within the last
     * $minutes minutes, or null if none. If it returns a row, don't email again.
     */
    public function recentFor(int $fridgeId, int $minutes): ?array
    {
        $since = gmdate('Y-m-d\TH:i:s\Z', time() - ($minutes * 60));

        $rows = SupaClient::rows('GET', self::TABLE, [
            'select'    => self::COLUMNS,
            'fridge_id' => 'eq.' . $fridgeId,
            'sent_at'   => 'gte.' . $since,
            'order'     => 'sent_at.desc',
            'limit'     => 1,
        ]);

        return $rows ? $this->normalize($rows[0]) : null;
    }

    /**
     * Record the user's answer ('yes' or 'no'). Returns true if the alert existed.
     *
     * @throws InvalidArgumentException for any other answer
     */
    public function setResponse(int $id, string $response): bool
    {
        $response = strtolower(trim($response));
        if (!in_array($response, self::RESPONSES, true)) {
            throw new InvalidArgumentException("Response must be 'yes' or 'no'.");
        }

        $rows = SupaClient::rows('PATCH', self::TABLE, ['id' => 'eq.' . $id], [
            'response' => $response,
        ]);

        return $rows !== [];
    }

    private function normalize(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['fridge_id'] = (int) $row['fridge_id'];
        $row['temperature'] = (float) $row['temperature'];

        return $row;
    }
}