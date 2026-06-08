<?php
require_once __DIR__ . '/../Services/DashboardService.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Helpers/Response.php';

class DashboardController {

    // GET /dashboard/summary
    public static function summary() {
        $auth = AuthMiddleware::handle();
        AuthMiddleware::allowRoles($auth, ['admin', 'doctor']);

        $data = DashboardService::summary($auth);
        Response::success('Dashboard summary fetched', $data);
    }

    // GET /dashboard/appointments?from=YYYY-MM-DD&to=YYYY-MM-DD
    public static function appointments() {
        $auth = AuthMiddleware::handle();
        AuthMiddleware::allowRoles($auth, ['admin', 'doctor']);

        $data = DashboardService::appointments($auth);
        Response::success('Appointment analytics fetched', $data);
    }

    // GET /dashboard/prescriptions
    public static function prescriptions() {
        $auth = AuthMiddleware::handle();
        AuthMiddleware::allowRoles($auth, ['admin', 'doctor']);

        $data = DashboardService::prescriptions($auth);
        Response::success('Prescription analytics fetched', $data);
    }

    // GET /dashboard/tenant-analytics  (Admin only)
    public static function tenantAnalytics() {
        $auth = AuthMiddleware::handle();
        AuthMiddleware::allowRoles($auth, ['admin', 'doctor']);

        $data = DashboardService::tenantAnalytics();
        Response::success('Tenant analytics fetched', $data);
    }
}