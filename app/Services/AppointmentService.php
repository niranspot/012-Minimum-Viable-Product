<?php
require_once __DIR__ . '/../Repositories/AppointmentRepository.php';
require_once __DIR__ . '/../Repositories/PatientRepository.php';
require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Helpers/Response.php';

class AppointmentService {

    // Create a new appointment
    // If caller is a 'patient' role, patient_id is auto-derived from their user record
    // If caller is doctor/nurse, they must supply patient_id
    public static function create($data, $authUser) {
        $db        = getDB();
        $tenantId  = $authUser['tenant_id'];
        $role      = $authUser['role'];
        $userId    = $authUser['user_id'];

        // Resolve patient_id
        if ($role === 'patient') {
            $patientRecord = PatientRepository::findByUserId($userId, $tenantId);
            if (!$patientRecord) {
                Response::error('No patient profile found for your account', 404);
            }
            $patientId = $patientRecord['id'];
        } else {
            if (empty($data['patient_id'])) {
                Response::error('patient_id is required', 400);
            }
            $patientId = $data['patient_id'];
            // Verify patient belongs to same tenant
            if (!PatientRepository::findById($patientId, $tenantId)) {
                Response::error('Patient not found in your tenant', 404);
            }
        }

        // Validate doctor_id exists and has role 'doctor' in this tenant
        $stmt = $db->prepare(
            "SELECT id FROM users
             WHERE id = ? AND tenant_id = ? AND role = 'doctor' AND status = 'active'"
        );
        $stmt->execute([$data['doctor_id'], $tenantId]);
        if (!$stmt->fetch()) {
            Response::error('doctor_id must be an active doctor in your tenant', 400);
        }

        // Validate appointment_date format (must be Y-m-d H:i:s or Y-m-d H:i)
        $parsedDate = date('Y-m-d H:i:s', strtotime($data['appointment_date']));
        if (!$parsedDate || $parsedDate === '1970-01-01 00:00:00') {
            Response::error('Invalid appointment_date format. Use: YYYY-MM-DD HH:MM:SS', 400);
        }

        // Check double booking: same doctor, same date/time, same tenant
        $stmt = $db->prepare(
            "SELECT id FROM appointments
             WHERE doctor_id = ? AND appointment_date = ? AND tenant_id = ?
               AND status NOT IN ('cancelled')"
        );
        $stmt->execute([$data['doctor_id'], $parsedDate, $tenantId]);
        if ($stmt->fetch()) {
            Response::error('Doctor already has an appointment at this date and time', 409);
        }

        $appointmentId = AppointmentRepository::create([
            'tenant_id'        => $tenantId,
            'patient_id'       => $patientId,
            'doctor_id'        => $data['doctor_id'],
            'appointment_date' => $parsedDate,
            'notes'            => $data['notes'] ?? null,
        ]);

        return ['appointment_id' => $appointmentId];
    }

    // List appointments — patient sees only their own, doctor/nurse see all in tenant
    public static function list($authUser) {
        $tenantId = (int) $authUser['tenant_id'];
        $role     = $authUser['role'];
        $userId   = (int) $authUser['user_id'];

        if ($role === 'patient') {
            $patientRecord = PatientRepository::findByUserId($userId, $tenantId);
            if (!$patientRecord) {
                Response::error('No patient profile found for your account', 404);
            }
            return AppointmentRepository::findByPatientId((int)$patientRecord['id'], $tenantId);
        }

        return AppointmentRepository::findAllByTenant($tenantId);
    }

    // Update appointment — only doctor/nurse allowed
    public static function update($id, $data, $tenantId) {
        $appointment = AppointmentRepository::findById($id, $tenantId);
        if (!$appointment) {
            Response::error('Appointment not found', 404);
        }

        // Cannot modify a completed appointment
        if ($appointment['status'] === 'completed') {
            Response::error('Cannot modify a completed appointment', 400);
        }

        // Validate status value
        if (!in_array($data['status'], APPOINTMENT_STATUS)) {
            Response::error('Invalid status. Allowed: ' . implode(', ', APPOINTMENT_STATUS), 400);
        }

        // Validate date if provided
        $parsedDate = date('Y-m-d H:i:s', strtotime($data['appointment_date'] ?? $appointment['appointment_date']));

        $updated = AppointmentRepository::update($id, $tenantId, [
            'status'           => $data['status'],
            'notes'            => $data['notes'] ?? $appointment['notes'],
            'appointment_date' => $parsedDate,
        ]);

        if (!$updated) {
            Response::error('No changes were made', 400);
        }

        return ['appointment_id' => $id];
    }

    // Calendar: fetch by date range — patient sees only their own
    public static function calendar($authUser, $from, $to) {
        $tenantId = (int) $authUser['tenant_id'];
        $role     = $authUser['role'];
        $userId   = (int) $authUser['user_id'];

        // Validate date format
        $fromDate = date('Y-m-d H:i:s', strtotime($from));
        $toDate   = date('Y-m-d H:i:s', strtotime($to));
        if (!$fromDate || $fromDate === '1970-01-01 00:00:00') {
            Response::error('Invalid from date. Use: YYYY-MM-DD', 400);
        }
        if (!$toDate || $toDate === '1970-01-01 00:00:00') {
            Response::error('Invalid to date. Use: YYYY-MM-DD', 400);
        }

        if ($role === 'patient') {
            $patientRecord = PatientRepository::findByUserId($userId, $tenantId);
            if (!$patientRecord) {
                Response::error('No patient profile found for your account', 404);
            }
            return AppointmentRepository::findByDateRangeForPatient(
                $tenantId, (int)$patientRecord['id'], $fromDate, $toDate
            );
        }

        return AppointmentRepository::findByDateRange($tenantId, $fromDate, $toDate);
    }
}