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
    private static function resolvePatientId(int $userId, ): int {
        $db   = getDB();
        $stmt = $db->prepare(
            "SELECT id FROM patients
             WHERE user_id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$userId, ]);
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
        $patientId = $data['patient_id'] ?? null;
        $role     = $authUser['role'];
        $userId   = (int) $authUser['user_id'];

        // Resolve patient_id
        if ($role === 'patient') {
            $patientId = self::resolvePatientId($userId);
        } else {
            if (empty($data['patient_id'])) {
                Response::error('patient_id is required', 400);
            }
        }

        // Validate doctor_id — must be active doctor in same tenant
        $stmt = $db->prepare(
            "SELECT id FROM users
             WHERE id = ? AND role = 'doctor' AND status = 'active'"
        );
        $stmt->execute([$data['doctor_id']]);
        if (!$stmt->fetch()) {
            Response::error('doctor must be active', 400);
        }

        // Parse and validate appointment_date
        $parsedDate = date('Y-m-d H:i:s', strtotime($data['appointment_date']));
        if (!$parsedDate || $parsedDate === '1970-01-01 00:00:00') {
            Response::error('Invalid appointment_date. Use: YYYY-MM-DD HH:MM:SS', 400);
        }

        // Conflict check — same doctor, same slot, not cancelled
        $stmt = $db->prepare(
            "SELECT id FROM appointments
             WHERE doctor_id = ? AND appointment_date = ?
               AND status != 'cancelled'"
        );
        $stmt->execute([$data['doctor_id'], $parsedDate]);
        if ($stmt->fetch()) {
            Response::error('Doctor already has an appointment at this date and time', 409);
        }

        $stmt = $db->prepare(
            "INSERT INTO appointments ( patient_id, doctor_id, appointment_date, status, notes)
             VALUES (?, ?, ?, 'pending', ?)"
        );
        $stmt->execute([
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
        $role     = $authUser['role'];
        $userId   = (int) $authUser['user_id'];

        if ($role === 'patient') {
            $patientId = self::resolvePatientId($userId, );

            $stmt = $db->prepare(
                "SELECT a.id, a.patient_id, a.doctor_id, a.appointment_date, a.status, a.notes, a.created_at,
                        u.name AS doctor_name
                 FROM appointments a
                 JOIN users u ON u.id = a.doctor_id
                 WHERE a.patient_id = ?
                 ORDER BY a.appointment_date ASC"
            );
            $stmt->execute([$patientId]);
        } else {
            $stmt = $db->prepare(
                "SELECT a.id, a.patient_id, a.doctor_id, a.appointment_date, a.status, a.notes, a.created_at,
                        u.name AS doctor_name,
                        pu.name AS patient_name
                 FROM appointments a
                 JOIN users u  ON u.id  = a.doctor_id
                 JOIN patients p  ON p.id  = a.patient_id
                 JOIN users pu ON pu.id = p.user_id
                 ORDER BY a.appointment_date ASC"
            );
            $stmt->execute();
        }

        return self::decryptRows($stmt->fetchAll());
    }

    // ---------------------------------------------------------------
    // PUT /appointments/{id} — Update appointment
    // ---------------------------------------------------------------
    public static function update(int $id, array $data, array $authUser): array {
        $db       = getDB();
        $role     = $authUser['role'];

        // Fetch current appointment
        $stmt = $db->prepare(
            "SELECT * FROM appointments WHERE id = ? "
        );
        $stmt->execute([$id]);
        $appointment = $stmt->fetch();

        if (!$appointment) {
            Response::error('Appointment not found', 404);
        }

        // ── Patient-role guard ────────────────────────────────────────
        if ($role === 'patient') {
            // 1. Patient can only cancel — no rescheduling, no status changes to
            //    confirmed/completed/pending
            if ($data['status'] !== 'cancelled') {
                Response::error('Patients can only cancel their own appointments', 403);
            }

            // 2. Verify this appointment belongs to the logged-in patient's profile
            $patientId = self::resolvePatientId((int) $authUser['user_id'], );
            if ((int) $appointment['patient_id'] !== $patientId) {
                Response::error('You can only cancel your own appointments', 403);
            }
        }
        
        if ($appointment['status'] === 'completed') {
            Response::error('Cannot modify a completed appointment', 400);
        }

        if ($appointment['status'] === 'cancelled') {
            Response::error('Cannot modify an already cancelled appointment', 400);
        }

        if (!in_array($data['status'], APPOINTMENT_STATUS)) {
            Response::error('Invalid status. Allowed: ' . implode(', ', APPOINTMENT_STATUS), 400);
        }

        $parsedDate = date('Y-m-d H:i:s', strtotime(
            $data['appointment_date'] ?? $appointment['appointment_date']
        ));

        // Merge notes: use incoming if provided, else keep existing encrypted value
        $newNotes = array_key_exists('notes', $data)
            ? $data['notes']
            : AES::decrypt($appointment['notes'] ?? '');

        $stmt = $db->prepare(
            "UPDATE appointments
             SET status = ?, notes = ?, appointment_date = ?
             WHERE id = ? "
        );
        $stmt->execute([
            $data['status'],
            self::encryptNotes($newNotes),
            $parsedDate,
            $id,
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
            $patientId = self::resolvePatientId($userId);

            $stmt = $db->prepare(
                "SELECT a.id, a.patient_id, a.doctor_id, a.appointment_date, a.status, a.notes,
                        u.name AS doctor_name
                 FROM appointments a
                 JOIN users u ON u.id = a.doctor_id
                 WHERE  a.patient_id = ?
                   AND a.appointment_date BETWEEN ? AND ?
                 ORDER BY a.appointment_date ASC"
            );
            $stmt->execute([ $patientId, $fromDate, $toDate]);
        } else {
            $stmt = $db->prepare(
                "SELECT a.id, a.patient_id, a.doctor_id, a.appointment_date, a.status, a.notes,
                        u.name AS doctor_name,
                        pu.name AS patient_name
                 FROM appointments a
                 JOIN users u  ON u.id  = a.doctor_id
                 JOIN patients p  ON p.id  = a.patient_id
                 JOIN users pu ON pu.id = p.user_id
                 WHERE a.appointment_date BETWEEN ? AND ?
                 ORDER BY a.appointment_date ASC"
            );
            $stmt->execute([ $fromDate, $toDate]);
        }

        return self::decryptRows($stmt->fetchAll());
    }
}