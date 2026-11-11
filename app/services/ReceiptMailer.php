<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Emails a receipt by running scripts/send_receipt.py (same Gmail settings as
 * the Phase 2 fridge alerts). The receipt goes to the script as JSON on stdin.
 */
final class ReceiptMailer
{
    private const SCRIPT = APP_ROOT . '/scripts/send_receipt.py';

    /** Returns true when the email was accepted by the mail server. */
    public static function send(array $receipt, string $lang): bool
    {
        $process = proc_open(
            ['python3', self::SCRIPT],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );

        if (!is_resource($process)) {
            error_log('Receipt email: could not start python3.');
            return false;
        }

        fwrite($pipes[0], json_encode(['receipt' => $receipt, 'lang' => $lang]));
        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            error_log('Receipt email not sent: ' . trim($output));
        }

        return $exitCode === 0;
    }

    /** w***@yahoo.com: shown on the kiosk screen, so the full address isn't. */
    public static function maskEmail(string $email): string
    {
        [$user, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($user, 0, 1) . '***@' . $domain;
    }
}
