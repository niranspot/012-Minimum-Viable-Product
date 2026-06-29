<?php
require_once __DIR__ . '/../app/Config/config.php';
require_once __DIR__ . '/../app/Config/constants.php';
require_once __DIR__ . '/../app/Middleware/CsrfMiddleware.php';
require_once __DIR__ . '/../app/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/Helpers/Response.php';

// Start session
if (session_status() === PHP_SESSION_NONE) session_start();


// CORS headers (for Postman/frontend)
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Run CSRF check
 CsrfMiddleware::handle(); 

// Load routes
require_once __DIR__ . '/../app/Routes/api.php';

// No route matched
Response::error('Route not found', 404);