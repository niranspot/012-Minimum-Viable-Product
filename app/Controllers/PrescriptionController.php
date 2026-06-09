<?php
require_once __DIR__ . '/../Services/PrescriptionService.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Helpers/Response.php';
require_once __DIR__ . '/../Helpers/Validator.php';
class PrescriptionController {
    // GET /prescriptions
    public static function index(): void {
        $auth = AuthMiddleware::handle();
        AuthMiddleware::allowRoles($auth, ['admin', 'doctor', 'pharmacist']);
        $list = PrescriptionService::getAll((int) $auth['tenant_id']);
        Response::success('Prescriptions retrieved', $list);
    }
    // GET /prescriptions/{id}
    public static function show(int $id): void {
        $auth = AuthMiddleware::handle();
        AuthMiddleware::allowRoles($auth, ['admin', 'doctor', 'pharmacist', 'nurse']);
        $rx = PrescriptionService::getById($id, (int) $auth['tenant_id']);
        Response::success('Prescription retrieved', $rx);
    }
    // GET /patients/{id}/prescriptions
    public static function byPatient(int $patientId): void {
        $auth = AuthMiddleware::handle();
        AuthMiddleware::allowRoles($auth, ['admin', 'doctor', 'pharmacist', 'nurse']);
        $list = PrescriptionService::getByPatient($patientId, (int) $auth['tenant_id']);
        Response::success('Patient prescriptions retrieved', $list);
    }
    // GET /appointments/{id}/prescription
    public static function byAppointment(int $appointmentId): void {
        $auth = AuthMiddleware::handle();
        AuthMiddleware::allowRoles($auth, ['admin', 'doctor', 'pharmacist', 'nurse']);
        $rx = PrescriptionService::getByAppointment($appointmentId, (int) $auth['tenant_id']);
        Response::success('Appointment prescription retrieved', $rx);
    }
    // POST /prescriptions
    public static function store(): void {
        $auth = AuthMiddleware::handle();
        AuthMiddleware::allowRoles($auth, ['doctor']);
        $payload = json_decode(file_get_contents('php://input'), true);
        $v = new Validator($payload);
        $v->required('patient_id')
        ->required('appointment_id')
        ->required('medicines');
        if ($v->fails()) {
        Response::error(implode(', ', $v->errors()), 400);
        }
        $rx = PrescriptionService::create($payload, (int) $auth['tenant_id'], (int) $auth['user_id']);
        Response::success('Prescription created', $rx, 201);
    }
    // PUT /prescriptions/{id}
    public static function update(int $id): void {
        $auth = AuthMiddleware::handle();
        AuthMiddleware::allowRoles($auth, ['doctor', 'admin']);
        $payload = json_decode(file_get_contents('php://input'), true);
        if (empty($payload)) {
        Response::error('No data provided', 400);
        }
        $rx = PrescriptionService::update($id, (int) $auth['tenant_id'], $payload);
        Response::success('Prescription updated', $rx);
    }
    // PATCH /prescriptions/{id}/status
    public static function updateStatus(int $id): void {
        $auth = AuthMiddleware::handle();
        AuthMiddleware::allowRoles($auth, ['doctor', 'pharmacist', 'admin']);
        $payload = json_decode(file_get_contents('php://input'), true);
        if (empty($payload['status'])) {
        Response::error('Status is required', 400);
        }
        $rx = PrescriptionService::updateStatus($id, (int) $auth['tenant_id'], $payload['status']);
        Response::success('Prescription status updated', $rx);
    }
    // DELETE /prescriptions/{id}
    public static function destroy(int $id): void {
        $auth = AuthMiddleware::handle();
        AuthMiddleware::allowRoles($auth, ['admin', 'doctor']);
        PrescriptionService::delete($id, (int) $auth['tenant_id']);
        Response::success('Prescription deleted');
    }
}
