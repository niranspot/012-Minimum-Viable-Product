<?php
require_once __DIR__ . '/../Services/BillingService.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Helpers/Response.php';
require_once __DIR__ . '/../Helpers/Validator.php';

class BillingController {

    public static function create() {
        $auth = AuthMiddleware::handle();
        AuthMiddleware::allowRoles($auth, ['admin', 'doctor']);

        $payload = json_decode(file_get_contents('php://input'), true);

        $v = new Validator($payload);
        $v->required('patient_id')
          ->required('appointment_id')
          ->required('amount');

        if ($v->fails()) Response::error(implode(', ', $v->errors()), 400);

        $bill = BillingService::create($payload, (int) $auth['tenant_id']);
        Response::success('Invoice generated', $bill, 201);
    }

    public static function list() {
        $auth = AuthMiddleware::handle();
        AuthMiddleware::allowRoles($auth, ['admin', 'doctor', 'patient']);

        $bills = BillingService::getAll((int) $auth['tenant_id']);
        Response::success('Billing list fetched', $bills);
    }

    public static function update($id) {
        $auth = AuthMiddleware::handle();
        AuthMiddleware::allowRoles($auth, ['admin']);

        $payload = json_decode(file_get_contents('php://input'), true);

        if (empty($payload['status'])) Response::error('Status is required', 400);
        if (!in_array($payload['status'], BILLING_STATUS)) {
            Response::error('Status must be: pending or paid', 400);
        }

        $bill = BillingService::update($id, (int) $auth['tenant_id'], $payload);
        Response::success('Payment status updated', $bill);
    }
}