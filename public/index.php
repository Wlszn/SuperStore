<?php
declare(strict_types=1);

session_start();

// PHP defaults to UTC: without this, stock received in the evening is dated tomorrow.
date_default_timezone_set('America/Toronto');

use App\Controllers\AuthController;
use App\Controllers\ThresholdController;
use App\Controllers\ReadingApiController;
use App\Controllers\FanApiController;
use App\Controllers\ProductController;
use App\Controllers\ScanApiController;
use App\Controllers\SalesReportController;
use App\Controllers\BleApiController;


define('APP_ROOT', dirname(__DIR__));

// Delivery and order tracking is only stored in the browser tab for now (nothing is
// saved on the server), so it is switched off. Set to true to show it again.
const ORDER_TRACKING = false;

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $parts = explode('\\', substr($class, strlen($prefix)));
    $file = array_pop($parts);
    $dir = $parts ? strtolower(implode('/', $parts)) . '/' : '';

    $path = APP_ROOT . '/app/' . $dir . $file . '.php';
    if (is_file($path)) {
        require $path;
    }
});

require APP_ROOT . '/config/db.php';

function jsonResponse(int $status, array $body): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($body);
}

function view(string $name): void
{
    require APP_ROOT . '/app/views/' . $name . '.php';
}

$routes = [
    'POST /account/membership' => [App\Controllers\CheckoutController::class, 'membership'],
    'POST /account/profile' => [App\Controllers\CheckoutController::class, 'profile'],
    'POST /account/password' => [App\Controllers\CheckoutController::class, 'password'],
    'GET /order-confirmation' => fn() => ORDER_TRACKING ? view('order-confirmation') : header('Location: /account'),
    'GET /checkout' => [App\Controllers\CheckoutController::class, 'index'],
    'POST /checkout' => [App\Controllers\CheckoutController::class, 'pay'],
    'GET /api/members/lookup' => [App\Controllers\CheckoutController::class, 'memberLookup'],
    'GET /account' => [App\Controllers\CheckoutController::class, 'account'],
    'GET /account/orders/export' => [App\Controllers\CheckoutController::class, 'ordersExport'],
    'GET /account/prices/export' => [App\Controllers\CheckoutController::class, 'pricesExport'],
    'GET /receipt' => [App\Controllers\CheckoutController::class, 'receipt'],
    'GET /' => fn() => header('Location: ' . (isset($_SESSION['customer_id']) ? (empty($_SESSION['is_admin']) ? '/account' : '/products') : '/login')),

    'GET /login' => fn() => view('login'),
    'GET /signup' => fn() => view('signup'),
    'GET /dashboard' => [ThresholdController::class, 'index'],

    'POST /register' => [AuthController::class, 'register'],
    'POST /login' => [AuthController::class, 'login'],
    'GET /logout' => [AuthController::class, 'logout'],
    'GET /forgot-password' => [App\Controllers\PasswordResetController::class, 'forgotForm'],
    'POST /forgot-password' => [App\Controllers\PasswordResetController::class, 'sendLink'],
    'GET /reset-password' => [App\Controllers\PasswordResetController::class, 'resetForm'],
    'POST /reset-password' => [App\Controllers\PasswordResetController::class, 'reset'],

    'GET /thresholds' => fn() => header('Location: /dashboard'), // thresholds are edited on the dashboard
    'POST /thresholds' => [ThresholdController::class, 'update'],
    'GET /api/thresholds' => [ThresholdController::class, 'json'],

    'POST /api/readings' => [ReadingApiController::class, 'store'],
    'GET /api/readings/latest' => [ReadingApiController::class, 'latest'],

    'GET /api/fan'     => [FanApiController::class, 'show'],
    'POST /api/fan'    => [FanApiController::class, 'update'],

    // Phase 3: product management and inventory (admin only)
    'GET /products'          => [ProductController::class, 'index'],
    'GET /products/create'   => [ProductController::class, 'create'],
    'GET /products/edit'     => [ProductController::class, 'edit'],
    'GET /products/show'     => [ProductController::class, 'show'],
    'GET /products/receive'  => [ProductController::class, 'receiveForm'],
    'GET /products/export'   => [ProductController::class, 'export'],
    'POST /products'         => [ProductController::class, 'store'],
    'POST /products/update'  => [ProductController::class, 'update'],
    'POST /products/delete'  => [ProductController::class, 'delete'],
    'GET /api/receptions/latest' => [ProductController::class, 'latestReception'],
    'POST /receptions'       => [ProductController::class, 'receive'],
    'GET /api/products/stock' => [ProductController::class, 'stock'],

    // Phase 3: scanners (used by management and self-checkout)
    'GET /api/products/lookup' => [ProductController::class, 'lookup'],
    'GET /api/items/lookup'    => [ProductController::class, 'itemLookup'],
    'POST /api/scans'          => [ScanApiController::class, 'store'],
    'GET /api/scans/latest'    => [ScanApiController::class, 'latest'],

    // Phase 4: 
    //reports (admin only)
    'GET /api/reports/sales' => [SalesReportController::class, 'sales'],
    'GET /customers' => [App\Controllers\CustomerController::class, 'index'],
    'GET /customers/export' => [App\Controllers\CustomerController::class, 'export'],
    'GET /customers/edit' => [App\Controllers\CustomerController::class, 'edit'],
    'POST /customers/update' => [App\Controllers\CustomerController::class, 'update'],
    'POST /customers/reset-password' => [App\Controllers\CustomerController::class, 'sendReset'],
    'GET /reports/sales' => [App\Controllers\SalesReportController::class, 'page'],
    'GET /reports/sales/export' => [SalesReportController::class, 'export'],

    //MSP01 BLE sensor
    'POST /api/ble-readings'       => [BleApiController::class, 'store'],
    'GET /api/ble-readings/latest' => [BleApiController::class, 'latest'],

];

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
if ($path !== '/' && str_ends_with($path, '/')) {
    $path = rtrim($path, '/');
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$handler = $routes["$method $path"] ?? null;

if ($handler === null) {
    jsonResponse(404, ['status' => 'error', 'message' => 'Endpoint not found.']);
    exit;
}

if ($handler instanceof Closure) {
    $handler();
} else {
    [$class, $action] = $handler;
    (new $class())->$action();
}
