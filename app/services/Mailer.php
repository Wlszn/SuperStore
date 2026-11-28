<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Sends email through Brevo's transactional email API (free plan: 300 emails a day).
 * One HTTPS request per email, so no library is needed.
 *
 * .env:
 *   BREVO_API_KEY=xkeysib-...           Brevo > SMTP & API > API keys
 *   MAIL_FROM_EMAIL=you@example.com     a sender verified in Brevo (Senders, domains & IPs)
 *   MAIL_FROM_NAME=SuperStore
 *   APP_URL=http://192.168.1.50:8000    the address customers open, used in links
 */
final class Mailer
{
    private const API_URL = 'https://api.brevo.com/v3/smtp/email';

    /** True when the .env has what is needed to send. */
    public static function configured(): bool
    {
        return ($_ENV['BREVO_API_KEY'] ?? '') !== '' && ($_ENV['MAIL_FROM_EMAIL'] ?? '') !== '';
    }

    /**
     * Sends one HTML email. Returns false (and logs why) instead of throwing, so a mail
     * problem never breaks the page that triggered it (a checkout, a reset request...).
     */
    public static function send(string $toEmail, string $toName, string $subject, string $html): bool
    {
        if (!self::configured()) {
            error_log('Email not sent: BREVO_API_KEY or MAIL_FROM_EMAIL is missing in .env.');
            return false;
        }
        $body = json_encode([
            'sender' => ['email' => $_ENV['MAIL_FROM_EMAIL'], 'name' => $_ENV['MAIL_FROM_NAME'] ?? 'SuperStore'],
            'to' => [['email' => $toEmail, 'name' => $toName !== '' ? $toName : $toEmail]],
            'subject' => $subject,
            'htmlContent' => $html,
        ]);
        $curl = curl_init($_ENV['BREVO_API_URL'] ?? self::API_URL);   // BREVO_API_URL: only to test without Brevo
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['accept: application/json', 'content-type: application/json', 'api-key: ' . $_ENV['BREVO_API_KEY']],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($response === false || $status < 200 || $status >= 300) {
            error_log(sprintf('Email to %s not sent (HTTP %d): %s', $toEmail, $status, $error !== '' ? $error : (string) $response));
            return false;
        }
        return true;
    }

    /**
     * Renders app/views/emails/<name>.php with the given variables and returns the HTML.
     * $base is the address used in links (APP_URL in .env).
     */
    public static function render(string $name, array $data): string
    {
        $base = rtrim($_ENV['APP_URL'] ?? 'http://localhost:8000', '/');
        $e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $money = static fn ($value): string => '$' . number_format((float) $value, 2);
        extract($data, EXTR_SKIP);
        ob_start();
        require APP_ROOT . '/app/views/emails/' . $name . '.php';
        return (string) ob_get_clean();
    }
}
