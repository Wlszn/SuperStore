<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class SupaClient
{
    public static function rows(string $method, string $table, ?array $query = null, ?array $body = null): array
    {
        [$code, $decoded] = supa_request($method, $table, $query, $body);

        if ($code < 200 || $code >= 300) {
            error_log(sprintf(
                'Supabase %s %s failed (HTTP %d): %s',
                $method,
                $table,
                $code,
                json_encode($decoded)
            ));
            throw new RuntimeException('The database request failed.');
        }

        return $decoded ?? [];
    }
}