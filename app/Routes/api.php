<?php
require_once __DIR__ . '/../Controllers/AuthController.php';

function route($method,$uri, $handler){
    $requestMethod = $_SERVER['REQUEST_METHOD'];
    $requestUri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    // Remove base path
    $basePath   = '/newphp1/Php_Tasks/012MinimumViableProduct/public';
    $requestUri = str_replace($basePath, '', $requestUri);
    $requestUri = '/' . trim($requestUri, '/');

    if ($requestMethod === strtoupper($method) && $requestUri === $uri) {
        $handler();
        exit;
    }
}

// Auth routes (no auth middleware needed)
route('POST', '/register',      [AuthController::class, 'register']);
route('POST', '/login',         [AuthController::class, 'login']);
route('POST', '/refresh-token', [AuthController::class, 'refresh']);
route('GET',  '/csrf-token',    [AuthController::class, 'csrfToken']);