<?php
require_once __DIR__ . '/../Security/CSRF.php';
require_once __DIR__ . '/../Helpers/Response.php';

class CsrfMiddleware {
    private static $exclude = [
        // Auth routes — no CSRF needed since they use JWT, not sessions-Niranjan
        '/register',
        '/login',
        '/refresh-token',
        '/csrf-token',
        '/tenant/signup'
    ];

    public static function handle() {
        $method = $_SERVER['REQUEST_METHOD'];

        // CSRF only matters for state-changing methods
        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) return;

        $basePath   = '/012-Minimum-Viable-Product/public';
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $requestUri = '/' . trim(str_replace($basePath, '', $requestUri), '/');


        // Strip trailing /123 from dynamic routes like /patients/5-Mithra
        // $baseSegment = preg_replace('#/\d+$#', '', $requestUri);
        // if (in_array($requestUri, self::$exclude) || in_array($baseSegment, self::$exclude)) return;
        if (in_array($requestUri, self::$exclude)) return;

        if (!CSRF::validate()) {
            Response::error('Invalid CSRF token', 403);
        }
    }
}