<?php
declare(strict_types=1);

session_start();

use App\Controllers\AuthController;

// Project root = one level above public/
define('APP_ROOT', dirname(__DIR__));

// ---------------------------------------------------------------------------
// Autoloader: App\Models\Fridge -> app/models/Fridge.php
// Folder names are lowercased (they are lowercase on disk and Linux is
// case-sensitive); the class file name keeps its case.
// ---------------------------------------------------------------------------
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $parts = explode('\\', substr($class, strlen($prefix)));       // ['Models', 'Fridge']
    $file  = array_pop($parts);                                    // 'Fridge'
    $dir   = $parts ? strtolower(implode('/', $parts)) . '/' : ''; // 'models/'

    $path = APP_ROOT . '/app/' . $dir . $file . '.php';
    if (is_file($path)) {
        require $path;
    }
});

// Database / Supabase helper functions (supa_* ...)
require APP_ROOT . '/config/db.php';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
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
    'GET /' => fn() => jsonResponse(200, ['status' => 'success', 'message' => 'SuperStore API']),

    // Pages
    'GET /login'     => fn() => view('login'),
    'GET /signup'    => fn() => view('signup'),
    'GET /dashboard' => fn() => view('dashboard'),

    // Actions
    'POST /register' => [AuthController::class, 'register'],
    'POST /login'    => [AuthController::class, 'login'],
    'GET /logout'    => [AuthController::class, 'logout'],

    // Added as each controller is written:
    // 'GET /thresholds'  => [ThresholdController::class, 'index'],
    // 'POST /api/readings' => [ReadingApiController::class, 'store'],
    // 'GET /api/thresholds' => [ThresholdController::class, 'json'],
    // 'GET /api/fan'     => [FanApiController::class, 'show'],
    // 'POST /api/fan'    => [FanApiController::class, 'update'],
];

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
if ($path !== '/' && str_ends_with($path, '/')) {
    $path = rtrim($path, '/');
}

$method  = $_SERVER['REQUEST_METHOD'] ?? 'GET';
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