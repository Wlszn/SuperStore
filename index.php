<?php

declare(strict_types=1);

use App\Controllers\CustomerController;
use App\Models\CustomerModel;
use App\config\db;


$dbPath = __DIR__ . '/config/database.php';

$basepath = __DIR__ . '/';

$requestURI = $_SERVER['REQUEST_URI'];

$path = parse_url($requestURI, PHP_URL_PATH);


require __DIR__ . '/../views/home.php';

switch($path) {
    case '/add-customer':
        $controller = new CustomerController();
        $controller->addCustomer();
        break;
    default:
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Endpoint not found.'
        ]);
        break;
}









?>