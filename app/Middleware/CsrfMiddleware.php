<?php
require_once __DIR__ . '/../Security/CSRF.php';
require_once __DIR__ . '/../Helpers/Response.php';

class CsrfMiddleware {
    private static $exclude = [
        '/register',
        '/login',
        '/csrf-token',
        '/refresh-token',
    ];

    public static function handle() {
        $method = $_SERVER['REQUEST_METHOD'];
        if (!in_array($method, ['POST', 'PUT', 'DELETE'])) return;

        $basePath   = '/newphp1/Php_Tasks/012MinimumViableProduct/public';
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $requestUri = '/' . trim(str_replace($basePath, '', $requestUri), '/');

        if (in_array($requestUri, self::$exclude)) return;

        if (!CSRF::validate()) {
            Response::error('Invalid CSRF token', 403);
        }
    }
}