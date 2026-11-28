<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\CustomerModel;
use App\Models\PasswordResetModel;
use RuntimeException;

/**
 * "Forgot your password?" from the sign-in page. One view (password.php) with four steps:
 * request (type your email), sent, choose (new password from the emailed link), done;
 * plus invalid when a link is expired or already used.
 *
 *   GET  /forgot-password          ask for the email
 *   POST /forgot-password          email a link (the same answer whether the account exists or not)
 *   GET  /reset-password?token=... choose a new password
 *   POST /reset-password           save it
 */
class PasswordResetController
{
    public function forgotForm(): void
    {
        $this->show('request');
    }

    public function sendLink(): void
    {
        $email = trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
            $this->show('request', ['email' => $email, 'error' => 'This email address does not look valid. Example: name@example.com']);
            return;
        }
        // At most 3 requests per browser every 10 minutes.
        $now = time();
        $_SESSION['reset_requests'] = array_values(array_filter($_SESSION['reset_requests'] ?? [], static fn (int $at): bool => $at > $now - 600));
        if (count($_SESSION['reset_requests']) >= 3) {
            $this->show('request', ['email' => $email, 'error' => 'Too many requests. Wait a few minutes, then try again.']);
            return;
        }
        $_SESSION['reset_requests'][] = $now;

        try {
            $customer = CustomerModel::findByEmail($email);
            if ($customer !== null) {
                (new PasswordResetModel())->emailLink((int) $customer->getId(), $customer->getName(), $customer->getEmail(), false);
            }
        } catch (RuntimeException $error) {
            $this->show('request', ['email' => $email, 'error' => 'Something went wrong on our side. Please try again.']);
            return;
        }
        // Same answer either way, so this page cannot be used to find out who has an account.
        $this->show('sent', ['email' => $email]);
    }

    public function resetForm(): void
    {
        $token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
        try {
            $reset = (new PasswordResetModel())->findValid($token);
        } catch (RuntimeException $error) {
            $reset = null;
        }
        $this->show($reset === null ? 'invalid' : 'choose', ['token' => $token]);
    }

    public function reset(): void
    {
        $token = is_string($_POST['token'] ?? null) ? $_POST['token'] : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $confirm = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
        try {
            $resets = new PasswordResetModel();
            $reset = $resets->findValid($token);
            if ($reset === null) {
                $this->show('invalid');
                return;
            }
            $errors = CustomerModel::validatePassword($password, $confirm);
            if ($errors !== []) {
                $this->show('choose', ['token' => $token, 'errors' => $errors]);
                return;
            }
            if (!$resets->complete($reset, password_hash($password, PASSWORD_DEFAULT))) {
                $this->show('invalid');
                return;
            }
        } catch (RuntimeException $error) {
            $this->show('choose', ['token' => $token, 'error' => 'Something went wrong on our side. Please try again.']);
            return;
        }
        $this->show('done');
    }

    private function show(string $step, array $data = []): void
    {
        $email = $data['email'] ?? '';
        $token = $data['token'] ?? '';
        $error = $data['error'] ?? null;
        $errors = $data['errors'] ?? [];
        require APP_ROOT . '/app/views/password.php';
    }
}
