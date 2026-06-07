<?php
require_once __DIR__ . '/../Controllers/AuthController.php';
require_once __DIR__ . '/../Controllers/PatientController.php';
require_once __DIR__ . '/../Controllers/AppointmentController.php';

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



// routeWithId() — matches /segment/{id} and passes id to handler-Mithra
function routeWithId($method, $prefix, $handler) {
    $requestMethod = $_SERVER['REQUEST_METHOD'];
    $requestUri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    $basePath   = '/newphp1/Php_Tasks/012MinimumViableProduct/public';
    $requestUri = str_replace($basePath, '', $requestUri);
    $requestUri = '/' . trim($requestUri, '/');

    if ($requestMethod !== strtoupper($method)) return;

    $pattern = '#^' . preg_quote($prefix, '#') . '/(\d+)$#';
    if (preg_match($pattern, $requestUri, $matches)) {
        $handler((int) $matches[1]);
        exit;
    }
}

// Auth routes (no auth middleware needed)-Niranjan
route('POST', '/register',      [AuthController::class, 'register']);
route('POST', '/login',         [AuthController::class, 'login']);
route('POST', '/refresh-token', [AuthController::class, 'refresh']);
route('GET',  '/csrf-token',    [AuthController::class, 'csrfToken']);
route('POST', '/logout', [AuthController::class, 'logout']);


// Patient routes  (doctor, nurse only — enforced inside controller)-Mithra
route('POST', '/patients',      [PatientController::class, 'create']);
route('GET',  '/patients',      [PatientController::class, 'list']);
routeWithId('PUT',    '/patients', [PatientController::class, 'update']);
routeWithId('DELETE', '/patients', [PatientController::class, 'delete']);


// Appointment routes  (doctor, nurse, patient — enforced inside controller)-Mithra

route('POST', '/appointments',  [AppointmentController::class, 'create']);
route('GET',  '/appointments',  [AppointmentController::class, 'list']);
routeWithId('PUT', '/appointments', [AppointmentController::class, 'update']);


// Calendar route  (all roles — enforced inside controller)-Mithra

route('GET', '/calendar', [AppointmentController::class, 'calendar']);