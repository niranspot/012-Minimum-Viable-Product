<?php
require_once __DIR__ . '/../Controllers/AuthController.php';
require_once __DIR__ . '/../Controllers/PatientController.php';
require_once __DIR__ . '/../Controllers/AppointmentController.php';
require_once __DIR__ . '/../Controllers/StaffController.php';
require_once __DIR__ . '/../Controllers/PrescriptionController.php';
require_once __DIR__ . '/../Controllers/BillingController.php';
require_once __DIR__ . '/../Controllers/MessageController.php';
require_once __DIR__ . '/../Controllers/DashboardController.php';
require_once __DIR__ . '/../Controllers/UserController.php';


//-Niranjan
function route($method,$uri, $handler){
    $requestMethod = $_SERVER['REQUEST_METHOD'];
    $requestUri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    // Remove base path
    $basePath   = '/012-Minimum-Viable-Product/public';
    $requestUri = str_replace($basePath, '', $requestUri);
    $requestUri = '/' . trim($requestUri, '/');

    if ($requestMethod === strtoupper($method) && $requestUri === $uri) {
        $handler();
        exit;
    }
}


//-Mithra
// routeWithId() — matches /segment/{id} and passes id to handler-Mithra
function routeWithId($method, $prefix, $handler) {
    $requestMethod = $_SERVER['REQUEST_METHOD'];
    $requestUri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    $basePath   = '/012-Minimum-Viable-Product/public';
    $requestUri = str_replace($basePath, '', $requestUri);
    $requestUri = '/' . trim($requestUri, '/');

    if ($requestMethod !== strtoupper($method)) return;

    $pattern = '#^' . preg_quote($prefix, '#') . '/(\d+)$#';
    if (preg_match($pattern, $requestUri, $matches)) {
        $handler((int) $matches[1]);
        exit;
    }
}


//--Vasuki
function routeMatch($method, $pattern, $handler) {
    $requestMethod = $_SERVER['REQUEST_METHOD'];
    $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    $basePath = '/012-Minimum-Viable-Product/public';
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
route('POST', '/tenant/signup', [AuthController::class, 'tenantSignup']);
route('POST', '/register',      [AuthController::class, 'register']);
route('POST', '/login',         [AuthController::class, 'login']);
route('POST', '/refresh-token', [AuthController::class, 'refresh']);
route('POST', '/change-password', [AuthController::class, 'changePassword']);
route('POST', '/logout', [AuthController::class, 'logout']);
route('GET', '/csrf-token',         [AuthController::class, 'csrf']);
route('GET', '/tenant/config',      [AuthController::class, 'tenantConfig']);
route('PUT', '/tenant/theme',       [AuthController::class, 'updateTheme']);

route('GET', '/users', [UserController::class, 'listUsers']);
routeMatch('PUT', '/users/:id/status', [UserController::class, 'updateStatus']);


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
routeWithId('GET', '/patients', [PatientController::class, 'show']);
routeWithId('PUT',    '/patients', [PatientController::class, 'update']);
routeWithId('DELETE', '/patients', [PatientController::class, 'delete']);


// Appointment routes  (doctor, nurse, patient — enforced inside controller)-Mithra

route('POST', '/appointments',  [AppointmentController::class, 'create']);
route('GET',  '/appointments',  [AppointmentController::class, 'list']);
routeWithId('PUT', '/appointments', [AppointmentController::class, 'update']);


// Calendar route  (all roles — enforced inside controller)-Mithra

route('GET', '/calendar', [AppointmentController::class, 'calendar']);


// Dashboard & Reports routes (doctor, admin only — enforced inside controller)
route('GET', '/dashboard/summary',          [DashboardController::class, 'summary']);
route('GET', '/dashboard/appointments',     [DashboardController::class, 'appointments']);
route('GET', '/dashboard/prescriptions',    [DashboardController::class, 'prescriptions']);


//----------------------------------------------------------------------------------------
//-Vasuki
// ─── Staff ────────────────────────────────────────────────────────────────────
route('GET', '/staff', [StaffController::class, 'index']);
route('POST', '/staff', [StaffController::class, 'store']);
routeMatch('GET', '/staff/:id', [StaffController::class, 'show']);
routeMatch('PUT', '/staff/:id', [StaffController::class, 'update']);
routeMatch('DELETE', '/staff/:id', [StaffController::class, 'destroy']);


// ─── Prescriptions ────────────────────────────────────────────────────────────
route('GET', '/prescriptions', [PrescriptionController::class, 'index']);
route('POST', '/prescriptions', [PrescriptionController::class, 'store']);
routeMatch('GET', '/prescriptions/:id', [PrescriptionController::class, 'show']);
routeMatch('PUT', '/prescriptions/:id', [PrescriptionController::class, 'update']);
routeMatch('PATCH', '/prescriptions/:id/status', [PrescriptionController::class, 'updateStatus']);
routeMatch('DELETE', '/prescriptions/:id', [PrescriptionController::class, 'destroy']);
routeMatch('GET', '/patients/:id/prescriptions', [PrescriptionController::class, 'byPatient']);
routeMatch('GET', '/appointments/:id/prescriptions', [PrescriptionController::class, 'byAppointment']);


