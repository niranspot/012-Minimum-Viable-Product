<?php
require_once __DIR__ . '/../Services/AppointmentService.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Helpers/Response.php';
require_once __DIR__ . '/../Helpers/Validator.php';

class AppointmentController {

    // POST /appointments
    public static function create() {
        $authUser = AuthMiddleware::handle();
        AuthMiddleware::allowRoles($authUser, ['doctor', 'nurse', 'patient']);

        $payload = json_decode(file_get_contents('php://input'), true);

        $v = new Validator($payload);
        $v->required('doctor_id')
          ->required('appointment_date');

        // patient_id required only for doctor/nurse (patient role auto-derives it)
        if ($authUser['role'] !== 'patient') {
            $v->required('patient_id');
        }

        if ($v->fails()) {
            Response::error(implode(', ', $v->errors()), 400);
        }

        $result = AppointmentService::create($payload, $authUser);
        Response::success('Appointment created', $result, 201);
    }

    // GET /appointments
    public static function list(): void {
        $authUser = AuthMiddleware::handle();
        AuthMiddleware::allowRoles($authUser, ['doctor', 'nurse', 'patient']);

        $appointments = AppointmentService::list($authUser);
        Response::success('Appointments fetched', $appointments);
    }

    // PUT /appointments/{id}
    public static function update(int $id): void {
        $authUser = AuthMiddleware::handle();
        AuthMiddleware::allowRoles($authUser, ['doctor', 'nurse', 'patient']);

        $payload = json_decode(file_get_contents('php://input'), true);

        $v = new Validator($payload);
        $v->required('status');

        // Doctor/nurse must supply appointment_date (they can reschedule).
        // Patient only cancels — appointment_date is not required for them.
        if ($authUser['role'] !== 'patient') {
            $v->required('appointment_date');
        }

        if ($v->fails()) {
            Response::error(implode(', ', $v->errors()), 400);
        }

        $result = AppointmentService::update($id, $payload, $authUser);
        Response::success('Appointment updated', $result);
    }

    // GET /calendar?from=YYYY-MM-DD&to=YYYY-MM-DD
    public static function calendar(): void {
        $authUser = AuthMiddleware::handle();
        // All roles allowed — no allowRoles restriction

        $from = $_GET['from'] ?? '';
        $to   = $_GET['to']   ?? '';
        // var_dump($_GET);

        if (empty($from) || empty($to)) {
            Response::error('Query params "from" and "to" are required (YYYY-MM-DD)', 400);
        }

        $appointments = AppointmentService::calendar($authUser, $from, $to);
        Response::success('Calendar fetched', $appointments);
    }
}