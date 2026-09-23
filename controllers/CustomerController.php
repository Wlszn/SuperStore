<?php

declare(strict_types=1);

namespace App\Controllers;
use App\Domain\Models\CustomerModel;


class CustomerController
{

    private const GPIO_SCRIPT = __DIR__ . '/../scripts/gpio_control.py';

    public function addCustomer(): void
    {
        header('Content-Type: application/json');

        //Read and decode the JSON input
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');

        $customer = new CustomerModel(null, $name, $email, $phone, $address);

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

    private function triggerGPIO(string $result): void
    {
        $script = escapeshellarg(self::GPIO_SCRIPT);
        $resultArg = escapeshellarg($result);
        shell_exec("python3 {$script} {$resultArg} > /dev/null 2>&1 &");
    }
}