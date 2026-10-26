<?php

declare(strict_types=1);

namespace app\controllers;

use App\Models\Alert;
use PDO;
use RuntimeException;

class AlertController
{
    private const TEMP_THRESHOLD = 30.0;
    private const EMAIL_SCRIPT   = __DIR__ . '/../../scripts/email.py';

    public function __construct(private PDO $db) {}

    public function checkTemperature(): void
    {
        $temperature = $this->getLatestTemperature();

        if ($temperature === null) {
            throw new RuntimeException('No temperature reading found in alert table.');
        }

        if ($temperature > self::TEMP_THRESHOLD) {
            $this->sendAlertEmail($temperature);
        }
    }

    private function getLatestTemperature(): ?float
    {
        $row = $this->db
            ->query('SELECT temperature FROM alert ORDER BY created_at DESC LIMIT 1')
            ->fetch(PDO::FETCH_ASSOC);

        return $row ? (float) $row['temperature'] : null;
    }

    private function sendAlertEmail(float $temperature): void
    {
        if (!is_file(self::EMAIL_SCRIPT)) {
            throw new RuntimeException('email.py not found at ' . self::EMAIL_SCRIPT);
        }

        // trim() collapses the whitespace left by sprintf formatting.
        $cmd = trim(sprintf(
            'python3 %s %s 2>&1',
            escapeshellarg(self::EMAIL_SCRIPT),
            escapeshellarg((string) $temperature)
        ));

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException(
                "email.py failed (exit {$exitCode}): " . implode("\n", $output)
            );
        }
    }
}

