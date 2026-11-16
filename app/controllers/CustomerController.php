<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\CustomerModel;
use App\Services\Cart;

class CustomerController
{
    private Cart $cart;

    private const GPIO_SCRIPT = __DIR__ . '/../scripts/gpio_signal.py';

    public function __construct()
    {
        $this->cart = new Cart();
    }

    public function addCustomer(): void
    {
        header('Content-Type: application/json');

        //Read and decode the JSON input
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $membershipNumber = trim($_POST['membership_number'] ?? '');

        $customer = new CustomerModel(
            null,
            $name,
            $email,
            $phone,
            $address,
            null,
            '',
            false,
            $membershipNumber,
            0
        );

        //build model
        if (!$customer->isValid()) {
            $this->triggerGPIO('fail');
            http_response_code(422);
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid customer data.'
            ]);

            return;
        }
        $success = $customer->save();

        if ($success) {
            $this->triggerGPIO('success');
            http_response_code(201);
            echo json_encode([
                'status' => 'success',
                'message' => 'Customer added successfully.',
                'customer' => $customer->toArray()
            ]);
        } else {
            $this->triggerGPIO('fail');
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to add customer.'
            ]);
        }

        
    }

    public function pointsEarned(): void
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['customer_id'])) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Please sign in first.']);
            return;
        }

        if ($this->cart->isEmpty()) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'The cart is empty.']);
            return;
        }

        $customer = CustomerModel::findById((int) $_SESSION['customer_id']);
        if ($customer === null) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Customer account not found.']);
            return;
        }

        $total = $this->cart->total();
        $pointsEarned = (int) $total * 10;
        $customer->setPoints($customer->getPoints() + $pointsEarned);

        if (!$customer->save()) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Could not update customer points.']);
            return;
        }

        $this->cart->clear();

        echo json_encode([
            'status' => 'success',
            'total' => $total,
            'points_earned' => $pointsEarned,
            'points_total' => $customer->getPoints(),
        ]);
    }

    private function triggerGPIO(string $result): void
    {
        $script = escapeshellarg(self::GPIO_SCRIPT);
        $resultArg = escapeshellarg($result);
        shell_exec("python3 {$script} {$resultArg} > /dev/null 2>&1 &");
    }
}