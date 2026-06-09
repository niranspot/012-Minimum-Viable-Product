<?php
require_once __DIR__ . '/../Security/CSRF.php';
require_once __DIR__ . '/../Helpers/Response.php';

class CsrfMiddleware {
    private static $exclude = [
        // Auth routes — no CSRF needed since they use JWT, not sessions-Niranjan
        '/register',
        '/login',
        '/csrf-token',
        '/refresh-token',

        // Patient routes — protected by JWT (AuthMiddleware), not session CSRF-Mithra
        // '/patients',
        // Appointment routes — protected by JWT-Mithra
        // '/appointments',
        // Calendar — GET, so skipped anyway, but listed for clarity-Mithra
        // '/calendar',
    ];

    public static function handle() {
        $method = $_SERVER['REQUEST_METHOD'];

        // CSRF only matters for state-changing methods
        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) return;

        $basePath   = '/newphp1/Php_Tasks/012MinimumViableProduct/public';
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $requestUri = '/' . trim(str_replace($basePath, '', $requestUri), '/');


        // Strip trailing /123 from dynamic routes like /patients/5-Mithra
        $baseSegment = preg_replace('#/\d+$#', '', $requestUri);

        if (in_array($requestUri, self::$exclude) || in_array($baseSegment, self::$exclude)) return;

        if (!CSRF::validate()) {
            Response::error('Invalid CSRF token', 403);
        }
    }
}