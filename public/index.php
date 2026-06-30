<?php
require_once __DIR__ . '/../app/Config/config.php';
require_once __DIR__ . '/../app/Config/constants.php';
require_once __DIR__ . '/../app/Middleware/CsrfMiddleware.php';
require_once __DIR__ . '/../app/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/Helpers/Response.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// ── CORS ───────────────────────────────────────────────
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = ['http://localhost:3000', 'http://lvh.me:3000'];

if (preg_match('/^http:\/\/[a-z0-9-]+\.lvh\.me:3000$/', $origin) || in_array($origin, $allowed)) {
    header("Access-Control-Allow-Origin: $origin");
}

header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-Tenant');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

CsrfMiddleware::handle();
require_once __DIR__ . '/../app/Routes/api.php';
Response::error('Route not found', 404);