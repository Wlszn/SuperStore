<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\Mailer;
use App\Services\SupaClient;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Password reset links (table password_resets, config/password_resets.sql).
 * The link carries a random token; only its SHA-256 hash is stored. A link works once
 * and expires after one hour; asking for a new one cancels the older ones.
 */
class PasswordResetModel
{
    private const LIFETIME = '+1 hour';
    private const WAIT_SECONDS = 60;   // at most one new link per account per minute

    /**
     * Creates a link for this customer and emails it. Returns true when the email was sent,
     * false when it could not be sent, and null when a link was already sent less than a minute ago.
     */
    public function emailLink(int $customerId, string $name, string $email, bool $byAdmin): ?bool
    {
        $recent = SupaClient::rows('GET', 'password_resets', [
            'select' => 'id',
            'customer_id' => 'eq.' . $customerId,
            'created_at' => 'gt.' . $this->utc(new DateTimeImmutable('-' . self::WAIT_SECONDS . ' seconds')),
            'limit' => 1,
        ]);
        if ($recent !== []) {
            return null;
        }

        // Only the newest link works: cancel the ones not used yet.
        SupaClient::rows('PATCH', 'password_resets', [
            'customer_id' => 'eq.' . $customerId,
            'used_at' => 'is.null',
        ], ['used_at' => $this->utc(new DateTimeImmutable())]);

        $token = bin2hex(random_bytes(32));
        SupaClient::rows('POST', 'password_resets', null, [
            'customer_id' => $customerId,
            'token_hash' => hash('sha256', $token),
            'expires_at' => $this->utc(new DateTimeImmutable(self::LIFETIME)),
        ]);

        $link = rtrim($_ENV['APP_URL'] ?? 'http://localhost:8000', '/') . '/reset-password?token=' . $token;
        return Mailer::send($email, $name, 'Reset your SuperStore password / Réinitialisez votre mot de passe SuperStore', Mailer::render('password-reset', [
            'customer' => ['name' => $name],
            'link' => $link,
            'byAdmin' => $byAdmin,
        ]));
    }

    /** The reset row for a token that is unused and not expired: [id, customer_id], or null. */
    public function findValid(string $token): ?array
    {
        if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
            return null;
        }
        return SupaClient::rows('GET', 'password_resets', [
            'select' => 'id,customer_id',
            'token_hash' => 'eq.' . hash('sha256', $token),
            'used_at' => 'is.null',
            'expires_at' => 'gt.' . $this->utc(new DateTimeImmutable()),
            'limit' => 1,
        ])[0] ?? null;
    }

    /**
     * Uses the link: marks it used (only if it still was unused, so it cannot work twice),
     * then saves the new password hash. Returns false if the link was used in the meantime.
     */
    public function complete(array $reset, string $passwordHash): bool
    {
        $claimed = SupaClient::rows('PATCH', 'password_resets', [
            'id' => 'eq.' . $reset['id'],
            'used_at' => 'is.null',
        ], ['used_at' => $this->utc(new DateTimeImmutable())]);
        if ($claimed === []) {
            return false;
        }
        SupaClient::rows('PATCH', 'customers', ['id' => 'eq.' . $reset['customer_id']], ['password_hash' => $passwordHash]);
        return true;
    }

    private function utc(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
