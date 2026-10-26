<?php

declare(strict_types=1);

$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

define('SUPABASE_URL', rtrim($_ENV['SUPABASE_URL'] ?? '', '/'));
//define('SUPABASE_KEY', $_ENV['SUPABASE_KEY'] ?? $_ENV['SUPABASE_SECRET_KEY'] ?? '');
define('SUPABASE_KEY', $_ENV['SUPABASE_SECRET_KEY'] ?? $_ENV['SUPABASE_KEY'] ?? '');

if (SUPABASE_URL === '' || SUPABASE_KEY === '') {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

/**
 * Minimal HTTP helper using cURL. Returns [statusCode, decodedBody].
 */
function supa_request(string $method, string $path, ?array $query = null, ?array $body = null): array
{
    $url = SUPABASE_URL . '/rest/v1/' . $path;
    if ($query) {
        $url .= '?' . http_build_query($query);
    }

    $headers = [
        'apikey: ' . SUPABASE_KEY,
        'Content-Type: application/json',
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15,
    ]);

    if ($body !== null) {
        // Return the affected row(s) so we get the new id back on insert.
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, ['Prefer: return=representation']));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);

    if ($raw === false) {
        error_log('Supabase request error: ' . $err);
        return [0, null];
    }

    $decoded = json_decode($raw, true);
    return [$code, is_array($decoded) ? $decoded : null];
}

/** Fetch one customer row by email, or null. */
function supa_find_customer_by_email(string $email): ?array
{
    [$code, $rows] = supa_request('GET', 'customers', [
        'select' => '*',
        'email' => 'eq.' . $email,
        'limit' => 1,
    ]);
    return ($code === 200 && !empty($rows)) ? $rows[0] : null;
}

/** Fetch one customer row by id, or null. */
function supa_find_customer_by_id(int $id): ?array
{
    [$code, $rows] = supa_request('GET', 'customers', [
        'select' => '*',
        'id' => 'eq.' . $id,
        'limit' => 1,
    ]);
    return ($code === 200 && !empty($rows)) ? $rows[0] : null;
}

/** Insert a customer row. Returns the created row, or null on failure. */
function supa_insert_customer(array $row): ?array
{
    [$code, $rows] = supa_request('POST', 'customers', null, $row);
    return (($code === 201 || $code === 200) && !empty($rows)) ? $rows[0] : null;
}

/** Update a customer row by id. Returns true on success. */
function supa_update_customer(int $id, array $row): bool
{
    [$code] = supa_request('PATCH', 'customers', ['id' => 'eq.' . $id], $row);
    return $code === 200 || $code === 204;
}

/** Delete a customer row by id. Returns true on success. */
function supa_delete_customer(int $id): bool
{
    [$code] = supa_request('DELETE', 'customers', ['id' => 'eq.' . $id]);
    return $code === 200 || $code === 204;
}
