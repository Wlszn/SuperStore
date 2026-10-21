<?php

declare(strict_types=1);

session_start();

use App\Controllers\CustomerController;
use App\Models\CustomerModel;
use App\config\db;

define('DB_PATH', __DIR__ . '/config/database.php');
define('APP_ROOT', __DIR__);

// Autoloader (simple PSR-4 style for the App namespace)
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

$requestURI = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestURI, PHP_URL_PATH) ?? '/';

// Normalize trailing slash (except root)
if ($path !== '/' && str_ends_with($path, '/')) {
    $path = rtrim($path, '/');
}

$controller = new CustomerController();

switch ($path) {
    case '/':
        echo json_encode([
            'status'  => 'success',
            'message' => 'Welcome to the Customer API. Use /add-customer to add a new customer.',
        ]);
        break;
        
    case '/add-customer':
        $controller->addCustomer();
        break;

    default:
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode([
            'status'  => 'error',
            'message' => 'Endpoint not found.',
        ]);
        break;
}