<?php
require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Security/AES.php';
require_once __DIR__ . '/../Helpers/Response.php';

class AppointmentService {

    private static function encryptNotes(?string $notes): ?string {
        return !empty($notes) ? AES::encrypt($notes) : null;
    }

    private static function decryptNotes(?string $notes): ?string {
        return !empty($notes) ? AES::decrypt($notes) : null;
    }

    private static function decryptRow(array $row): array {
        $row['notes'] = self::decryptNotes($row['notes'] ?? null);
        return $row;
    }

    private static function decryptRows(array $rows): array {
        return array_map([self::class, 'decryptRow'], $rows);
    }

    // Helper: resolve patient_id from JWT user_id for 'patient' role
    private static function resolvePatientId(int $userId, int $tenantId): int {
        $db   = getDB();
        $stmt = $db->prepare(
            "SELECT id FROM patients
             WHERE user_id = ? AND tenant_id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$userId, $tenantId]);
        $row = $stmt->fetch();
        if (!$row) {
            Response::error('No patient profile found for your account', 404);
        }
        return (int) $row['id'];
    }

    // ---------------------------------------------------------------
    // POST /appointments — Create appointment
    // ---------------------------------------------------------------
    public static function create(array $data, array $authUser): array {
        $db       = getDB();
        $tenantId = (int) $authUser['tenant_id'];
        $role     = $authUser['role'];
        $userId   = (int) $authUser['user_id'];

        // Resolve patient_id
        if ($role === 'patient') {
            $patientId = self::resolvePatientId($userId, $tenantId);
        } else {
            if (empty($data['patient_id'])) {
                Response::error('patient_id is required', 400);
            }
            $patientId = (int) $data['patient_id'];

            // Verify patient belongs to same tenant and is not deleted
            $stmt = $db->prepare(
                "SELECT id FROM patients
                 WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL"
            );
            $stmt->execute([$patientId, $tenantId]);
            if (!$stmt->fetch()) {
                Response::error('Patient not found in your tenant', 404);
            }
        }

        // Validate doctor_id — must be active doctor in same tenant
        $stmt = $db->prepare(
            "SELECT id FROM users
             WHERE id = ? AND tenant_id = ? AND role = 'doctor' AND status = 'active'"
        );
        $stmt->execute([$data['doctor_id'], $tenantId]);
        if (!$stmt->fetch()) {
            Response::error('doctor_id must be an active doctor in your tenant', 400);
        }

        // Parse and validate appointment_date
        $parsedDate = date('Y-m-d H:i:s', strtotime($data['appointment_date']));
        if (!$parsedDate || $parsedDate === '1970-01-01 00:00:00') {
            Response::error('Invalid appointment_date. Use: YYYY-MM-DD HH:MM:SS', 400);
        }

        // Conflict check — same doctor, same slot, not cancelled
        $stmt = $db->prepare(
            "SELECT id FROM appointments
             WHERE doctor_id = ? AND appointment_date = ? AND tenant_id = ?
               AND status != 'cancelled'"
        );
        $stmt->execute([$data['doctor_id'], $parsedDate, $tenantId]);
        if ($stmt->fetch()) {
            Response::error('Doctor already has an appointment at this date and time', 409);
        }

        $stmt = $db->prepare(
            "INSERT INTO appointments (tenant_id, patient_id, doctor_id, appointment_date, status, notes)
             VALUES (?, ?, ?, ?, 'pending', ?)"
        );
        $stmt->execute([
            $tenantId,
            $patientId,
            (int) $data['doctor_id'],
            $parsedDate,
            self::encryptNotes($data['notes'] ?? null),
        ]);

        return ['appointment_id' => (int) $db->lastInsertId()];
    }

    // ---------------------------------------------------------------
    // GET /appointments — List appointments
    // ---------------------------------------------------------------
    public static function list(array $authUser): array {
        $db       = getDB();
        $tenantId = (int) $authUser['tenant_id'];
        $role     = $authUser['role'];
        $userId   = (int) $authUser['user_id'];

        if ($role === 'patient') {
            $patientId = self::resolvePatientId($userId, $tenantId);

            $stmt = $db->prepare(
                "SELECT a.id, a.patient_id, a.doctor_id, a.appointment_date, a.status, a.notes, a.created_at,
                        u.name AS doctor_name
                 FROM appointments a
                 JOIN users u ON u.id = a.doctor_id
                 WHERE a.patient_id = ? AND a.tenant_id = ?
                 ORDER BY a.appointment_date ASC"
            );
            $stmt->execute([$patientId, $tenantId]);
        } else {
            $stmt = $db->prepare(
                "SELECT a.id, a.patient_id, a.doctor_id, a.appointment_date, a.status, a.notes, a.created_at,
                        u.name AS doctor_name,
                        pu.name AS patient_name
                 FROM appointments a
                 JOIN users u  ON u.id  = a.doctor_id
                 JOIN patients p  ON p.id  = a.patient_id
                 JOIN users pu ON pu.id = p.user_id
                 WHERE a.tenant_id = ?
                 ORDER BY a.appointment_date ASC"
            );
            $stmt->execute([$tenantId]);
        }

        return self::decryptRows($stmt->fetchAll());
    }

    // ---------------------------------------------------------------
    // PUT /appointments/{id} — Update appointment
    // ---------------------------------------------------------------
    public static function update(int $id, array $data, int $tenantId): array {
        $db = getDB();

        // Fetch current appointment
        $stmt = $db->prepare(
            "SELECT * FROM appointments WHERE id = ? AND tenant_id = ?"
        );
        $stmt->execute([$id, $tenantId]);
        $appointment = $stmt->fetch();

        if (!$appointment) {
            Response::error('Appointment not found', 404);
        }

        if ($appointment['status'] === 'completed') {
            Response::error('Cannot modify a completed appointment', 400);
        }

        if (!in_array($data['status'], APPOINTMENT_STATUS)) {
            Response::error('Invalid status. Allowed: ' . implode(', ', APPOINTMENT_STATUS), 400);
        }

        $parsedDate = date('Y-m-d H:i:s', strtotime(
            $data['appointment_date'] ?? $appointment['appointment_date']
        ));

        // Merge notes: use incoming if provided, else keep existing (decrypted)
        $newNotes = array_key_exists('notes', $data)
            ? $data['notes']
            : AES::decrypt($appointment['notes'] ?? '');

        $stmt = $db->prepare(
            "UPDATE appointments
             SET status = ?, notes = ?, appointment_date = ?
             WHERE id = ? AND tenant_id = ?"
        );
        $stmt->execute([
            $data['status'],
            self::encryptNotes($newNotes),
            $parsedDate,
            $id,
            $tenantId,
        ]);

        if ($stmt->rowCount() === 0) {
            Response::error('No changes were made', 400);
        }

        return ['appointment_id' => $id];
    }

    // ---------------------------------------------------------------
    // GET /calendar — Fetch by date range
    // ---------------------------------------------------------------
    public static function calendar(array $authUser, string $from, string $to): array {
        $db       = getDB();
        $tenantId = (int) $authUser['tenant_id'];
        $role     = $authUser['role'];
        $userId   = (int) $authUser['user_id'];

        $fromDate = date('Y-m-d H:i:s', strtotime($from));
        $toDate   = date('Y-m-d H:i:s', strtotime($to));

        if (!$fromDate || $fromDate === '1970-01-01 00:00:00') {
            Response::error('Invalid from date. Use: YYYY-MM-DD', 400);
        }
        if (!$toDate || $toDate === '1970-01-01 00:00:00') {
            Response::error('Invalid to date. Use: YYYY-MM-DD', 400);
        }

        if ($role === 'patient') {
            $patientId = self::resolvePatientId($userId, $tenantId);

            $stmt = $db->prepare(
                "SELECT a.id, a.patient_id, a.doctor_id, a.appointment_date, a.status, a.notes,
                        u.name AS doctor_name
                 FROM appointments a
                 JOIN users u ON u.id = a.doctor_id
                 WHERE a.tenant_id = ?
                   AND a.patient_id = ?
                   AND a.appointment_date BETWEEN ? AND ?
                 ORDER BY a.appointment_date ASC"
            );
            $stmt->execute([$tenantId, $patientId, $fromDate, $toDate]);
        } else {
            $stmt = $db->prepare(
                "SELECT a.id, a.patient_id, a.doctor_id, a.appointment_date, a.status, a.notes,
                        u.name AS doctor_name,
                        pu.name AS patient_name
                 FROM appointments a
                 JOIN users u  ON u.id  = a.doctor_id
                 JOIN patients p  ON p.id  = a.patient_id
                 JOIN users pu ON pu.id = p.user_id
                 WHERE a.tenant_id = ?
                   AND a.appointment_date BETWEEN ? AND ?
                 ORDER BY a.appointment_date ASC"
            );
            $stmt->execute([$tenantId, $fromDate, $toDate]);
        }

        return self::decryptRows($stmt->fetchAll());
    }
}