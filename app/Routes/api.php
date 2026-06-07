<?php
require_once __DIR__ . '/../Controllers/AuthController.php';
require_once __DIR__ . '/../Controllers/StaffController.php';
require_once __DIR__ . '/../Controllers/PrescriptionController.php';
require_once __DIR__ . '/../Controllers/BillingController.php';
require_once __DIR__ . '/../Controllers/MessageController.php';

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


//------------------------------------------------------------------------------------------------------
//EndPoints
// Define API routes here. Use route() and routeWithId() for simplicity.


//------------------------------------------------------------------------------------------------------
//-Niranjan
// Auth routes (no auth middleware needed)-Niranjan
route('POST', '/register',      [AuthController::class, 'register']);
route('POST', '/login',         [AuthController::class, 'login']);
route('POST', '/refresh-token', [AuthController::class, 'refresh']);
route('GET',  '/csrf-token',    [AuthController::class, 'csrfToken']);
route('POST', '/logout', [AuthController::class, 'logout']);


//billing routes (admin, doctor only — enforced inside controller)
route('POST', '/billing',           [BillingController::class, 'create']);
route('GET',  '/billing',           [BillingController::class, 'list']);
routeWithId('PUT', '/billing',      [BillingController::class, 'update']);

// Messaging routes (all roles — enforced inside controller)
route('POST', '/messages',          [MessageController::class, 'create']);
routeWithId('GET', '/messages',     [MessageController::class, 'list']);


//-------------------------------------------------------------------------------------------------------
//-Mithra
// Patient routes  (doctor, nurse only — enforced inside controller)
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


//----------------------------------------------------------------------------------------
//-Vasuki
// ─── Staff ────────────────────────────────────────────────────────────────────
route('GET',  '/staff',  [StaffController::class, 'index']);
route('POST', '/staff',  [StaffController::class, 'store']);
routeMatch('GET',    '/staff/:id',  [StaffController::class, 'show']);
routeMatch('PUT',    '/staff/:id',  [StaffController::class, 'update']);
routeMatch('DELETE', '/staff/:id',  [StaffController::class, 'destroy']);

// ─── Prescriptions ────────────────────────────────────────────────────────────
route('GET', '/prescriptions', [PrescriptionController::class, 'index']);
route('POST', '/prescriptions', [PrescriptionController::class, 'store']);
routeMatch('GET', '/prescriptions/:id', [PrescriptionController::class, 'show']);
routeMatch('PUT', '/prescriptions/:id', [PrescriptionController::class, 'update']);
routeMatch('PATCH', '/prescriptions/:id/status', [PrescriptionController::class, 'updateStatus']);
routeMatch('DELETE', '/prescriptions/:id', [PrescriptionController::class, 'destroy']);
routeMatch('GET', '/patients/:id/prescriptions', [PrescriptionController::class, 'byPatient']);
routeMatch('GET', '/appointments/:id/prescription', [PrescriptionController::class, 'byAppointment']);


