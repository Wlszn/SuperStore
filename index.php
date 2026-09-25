<?php

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/models/CustomerModel.php';
require_once __DIR__ . '/controllers/CustomerController.php';

use App\Domain\Models\CustomerModel;
use App\Controllers\CustomerController;

CustomerModel::setDbConnection($pdo);

$action = $_GET['action'] ?? '';

switch ($action) {
    case '':
        header('Location: views/add_customer.php');
        exit;
    case 'addCustomer':
        $controller = new CustomerController();
        $controller->addCustomer();
        break;
    default:
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
        break;
}