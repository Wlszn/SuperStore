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
use App\Controllers\CheckoutController;
use App\Controllers\AccountController;
use App\Services\Lang;


define('APP_ROOT', dirname(__DIR__));

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

/** Translated text (English/French), see app/lang/. */
function t(string $key, array $vars = []): string
{
    return Lang::t($key, $vars);
}

/** Where a signed-in user lands: admins on the store dashboard, customers on their account. */
function homePath(): string
{
    if (!isset($_SESSION['customer_id'])) {
        return '/login';
    }

    return empty($_SESSION['is_admin']) ? '/account' : '/dashboard';
}

$routes = [
    'GET /' => fn() => header('Location: ' . homePath()),

    'GET /login' => fn() => view('login'),
    'GET /signup' => fn() => view('signup'),
    'GET /dashboard' => fn() => view('dashboard'),

    'POST /register' => [AuthController::class, 'register'],
    'POST /login' => [AuthController::class, 'login'],
    'GET /logout' => [AuthController::class, 'logout'],

    'GET /thresholds' => [ThresholdController::class, 'index'],
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
    'POST /products'         => [ProductController::class, 'store'],
    'POST /products/update'  => [ProductController::class, 'update'],
    'POST /products/delete'  => [ProductController::class, 'delete'],
    'POST /receptions'       => [ProductController::class, 'receive'],

    // Phase 3: scanners (used by management and self-checkout)
    'GET /api/products/lookup' => [ProductController::class, 'lookup'],
    'POST /api/scans'          => [ScanApiController::class, 'store'],
    'GET /api/scans/latest'    => [ScanApiController::class, 'latest'],

    // Phase 3: self-checkout kiosk and customer accounts
    'GET /checkout'            => [CheckoutController::class, 'index'],
    'POST /checkout'           => [CheckoutController::class, 'pay'],
    'GET /api/members/lookup'  => [CheckoutController::class, 'member'],
    'GET /account'             => [AccountController::class, 'index'],
    'GET /receipts/show'       => [AccountController::class, 'receipt'],

    // English / French (bonus): /lang?to=fr&back=/checkout
    'GET /lang' => function (): void {
        Lang::set((string) ($_GET['to'] ?? 'en'));
        $back = (string) ($_GET['back'] ?? '/');
        // Only paths on this site, never another website.
        header('Location: ' . (preg_match('#^/(?![/\\\\])[A-Za-z0-9/_?=&.%-]*$#', $back) ? $back : '/'));
    },
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