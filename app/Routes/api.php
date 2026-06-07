<?php
require_once __DIR__ . '/../Controllers/AuthController.php';
require_once __DIR__ . '/../Controllers/StaffController.php';
require_once __DIR__ . '/../Controllers/PrescriptionController.php';

// ─── Static route matcher (your original) ────────────────────────────────────
function route($method, $uri, $handler) {
    $requestMethod = $_SERVER['REQUEST_METHOD'];
    $requestUri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    $basePath   = '/MVP/public';
    $requestUri = str_replace($basePath, '', $requestUri);
    $requestUri = '/' . trim($requestUri, '/');

    if ($requestMethod === strtoupper($method) && $requestUri === $uri) {
        $handler();
        exit;
    }
}

// ─── Dynamic route matcher (for :id segments) ────────────────────────────────
function routeMatch($method, $pattern, $handler) {
    $requestMethod = $_SERVER['REQUEST_METHOD'];
    $requestUri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    $basePath   = '/MVP/public';
    $requestUri = str_replace($basePath, '', $requestUri);
    $requestUri = '/' . trim($requestUri, '/');

    if (strtoupper($method) !== $requestMethod) return;

    // Convert :param → named capture group
    $regex = preg_replace('/:([a-zA-Z_]+)/', '(?P<$1>[^/]+)', $pattern);
    $regex = '#^' . $regex . '$#';

    if (preg_match($regex, $requestUri, $matches)) {
        $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        $params = array_map('intval', $params);
        call_user_func_array($handler, array_values($params));
        exit;
    }
}

// ─── Auth ─────────────────────────────────────────────────────────────────────
route('POST', '/register',      [AuthController::class, 'register']);
route('POST', '/login',         [AuthController::class, 'login']);
route('POST', '/refresh-token', [AuthController::class, 'refresh']);
route('GET',  '/csrf-token',    [AuthController::class, 'csrfToken']);

// ─── Staff ────────────────────────────────────────────────────────────────────
route('GET',  '/staff',  [StaffController::class, 'index']);
route('POST', '/staff',  [StaffController::class, 'store']);
routeMatch('GET',    '/staff/:id',  [StaffController::class, 'show']);
routeMatch('PUT',    '/staff/:id',  [StaffController::class, 'update']);
routeMatch('DELETE', '/staff/:id',  [StaffController::class, 'destroy']);

// ─── Prescriptions ────────────────────────────────────────────────────────────
route('GET',  '/prescriptions',  [PrescriptionController::class, 'index']);
route('POST', '/prescriptions',  [PrescriptionController::class, 'store']);
routeMatch('GET',    '/prescriptions/:id',         [PrescriptionController::class, 'show']);
routeMatch('PUT',    '/prescriptions/:id',         [PrescriptionController::class, 'update']);
routeMatch('PATCH',  '/prescriptions/:id/status',  [PrescriptionController::class, 'updateStatus']);
routeMatch('DELETE', '/prescriptions/:id',         [PrescriptionController::class, 'destroy']);
routeMatch('GET', '/patients/:id/prescriptions',      [PrescriptionController::class, 'byPatient']);
routeMatch('GET', '/appointments/:id/prescription',   [PrescriptionController::class, 'byAppointment']);

