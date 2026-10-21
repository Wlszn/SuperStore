<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\CustomerModel;

class AuthController
{
    private const GPIO_SCRIPT = __DIR__ . '/../../scripts/gpio_signal.py';
    public function register(): void
    {
        header('Content-Type: application/json');

        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        // 1. Validate every field and collect one clear message per problem.
        $errors = CustomerModel::validateData($name, $email, $phone, $address);

        if ($password === '') {
            $errors['password'] = 'Please choose a password.';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters long.';
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $errors['password'] = 'Password must contain at least one letter and one number.';
        }

        if ($confirmPassword === '') {
            $errors['confirm_password'] = 'Please type your password again to confirm it.';
        } elseif ($password !== '' && $password !== $confirmPassword) {
            $errors['confirm_password'] = 'The two passwords do not match. Please type them again.';
        }

        if ($errors !== []) {
            $this->triggerGPIO('fail');
            $this->respondWithErrors('Please fix the highlighted fields and try again.', $errors);
            return;
        }

        // 2. The email must not already be registered.
        if (CustomerModel::findByEmail($email) !== null) {
            $this->triggerGPIO('fail');
            http_response_code(409);
            echo json_encode([
                'status' => 'error',
                'message' => 'An account with this email already exists.',
                'errors' => ['email' => 'This email is already registered. Try signing in instead.'],
            ]);
            return;
        }

        // 3. Everything is valid: create the account and log the user in.
        $customer = new CustomerModel(null, $name, $email, $phone, $address);
        $customer->setPassword($password);

        if (!$customer->save()) {
            $this->triggerGPIO('fail');
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Something went wrong on our side while creating your account. Please try again.',
            ]);
            return;
        }

        // Account created: light the blue LED.
        $this->triggerGPIO('success');

        session_regenerate_id(true);
        $_SESSION['customer_id'] = $customer->getId();
        $_SESSION['customer_name'] = $customer->getName();

        http_response_code(201);
        echo json_encode([
            'status' => 'success',
            'message' => 'Your account was created. Taking you to your dashboard...',
            'redirect' => '/dashboard',
        ]);
    }

    public function login(): void
    {
        header('Content-Type: application/json');

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        // 1. Basic field checks with one clear message per problem.
        $errors = [];

        if ($email === '') {
            $errors['email'] = 'Please enter your email address.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'This email address does not look valid. Example: name@example.com';
        }

        if ($password === '') {
            $errors['password'] = 'Please enter your password.';
        }

        if ($errors !== []) {
            $this->respondWithErrors('Please fix the highlighted fields and try again.', $errors);
            return;
        }

        // 2. Look up the account and check the password.
        $customer = CustomerModel::findByEmail($email);

        if ($customer !== null && $customer->getPasswordHash() === '') {
            http_response_code(409);
            echo json_encode([
                'status' => 'error',
                'message' => 'This account was created before passwords existed. Please create a new account.',
            ]);
            return;
        }

        // One generic message on purpose: we never reveal whether the
        // email or the password was the wrong part.
        if ($customer === null || !$customer->verifyPassword($password)) {
            http_response_code(401);
            echo json_encode([
                'status' => 'error',
                'message' => 'The email or password you entered is incorrect. Please try again.',
            ]);
            return;
        }

        // 3. Success: start a fresh session for this user.
        session_regenerate_id(true);
        $_SESSION['customer_id'] = $customer->getId();
        $_SESSION['customer_name'] = $customer->getName();

        echo json_encode([
            'status' => 'success',
            'message' => 'Welcome back, ' . $customer->getName() . '! Signing you in...',
            'redirect' => '/dashboard',
        ]);
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        header('Location: /login');
        exit;
    }

    private function respondWithErrors(string $message, array $errors): void
    {
        http_response_code(422);
        echo json_encode([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
        ]);
    }

    private function triggerGPIO(string $result): void
    {
        $script = escapeshellarg(self::GPIO_SCRIPT);
        $resultArg = escapeshellarg($result);
        shell_exec("python3 {$script} {$resultArg} > /dev/null 2>&1 &");
    }
}
