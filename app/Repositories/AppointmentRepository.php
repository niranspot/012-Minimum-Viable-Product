<?php
require_once __DIR__ . '/../Config/database.php';

class AppointmentRepository {

    // Encrypt notes before storing
    private static function encryptNotes($notes) {
        return !empty($notes) ? AES::encrypt($notes) : null;
    }

    // Decrypt notes after fetching
    private static function decryptNotes($notes) {
        return !empty($notes) ? AES::decrypt($notes) : null;
    }

    // Decrypt notes field in a single row
    private static function decryptRow($row) {
        $row['notes'] = self::decryptNotes($row['notes'] ?? null);
        return $row;
    }

    // Decrypt notes field across all rows
    private static function decryptRows($rows) {
        return array_map([self::class, 'decryptRow'], $rows);
    }

    // Insert new appointment, return new id
    public static function create($data) {
        $db   = getDB();
        $stmt = $db->prepare(
            "INSERT INTO appointments (tenant_id, patient_id, doctor_id, appointment_date, status, notes)
             VALUES (?, ?, ?, ?, 'pending', ?)"
        );
        $stmt->execute([
            $data['tenant_id'],
            $data['patient_id'],
            $data['doctor_id'],
            $data['appointment_date'],
            self::encryptNotes($data['notes'] ?? null)
        ]);
        return (int) $db->lastInsertId();
    }

    // List all appointments for a tenant (doctor/nurse sees all)
    public static function findAllByTenant($tenantId) {
        $db   = getDB();
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
        return self::decryptRows($stmt->fetchAll());
    }

    // List appointments for a specific patient (patient role)
    public static function findByPatientId($patientId, $tenantId) {
        $db   = getDB();
        $stmt = $db->prepare(
            "SELECT a.id, a.patient_id, a.doctor_id, a.appointment_date, a.status, a.notes, a.created_at,
                    u.name AS doctor_name
             FROM appointments a
             JOIN users u ON u.id = a.doctor_id
             WHERE a.patient_id = ? AND a.tenant_id = ?
             ORDER BY a.appointment_date ASC"
        );
        $stmt->execute([$patientId, $tenantId]);
        return self::decryptRows($stmt->fetchAll());
    }

    // Find single appointment by id scoped to tenant
    public static function findById($id, $tenantId) {
        $db   = getDB();
        $stmt = $db->prepare(
            "SELECT * FROM appointments WHERE id = ? AND tenant_id = ?"
        );
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch();
        return $row ? self::decryptRow($row) : false;
    }

    // Update status and/or notes (provider/nurse)
    public static function update($id, $tenantId, $data) {
        $db   = getDB();
        $stmt = $db->prepare(
            "UPDATE appointments
             SET status = ?, notes = ?, appointment_date = ?
             WHERE id = ? AND tenant_id = ?"
        );
        $stmt->execute([
            $data['status'],
            self::encryptNotes($data['notes'] ?? null),
            $data['appointment_date'],
            $id,
            $tenantId,
        ]);
        return $stmt->rowCount() > 0;
    }

    // Fetch appointments within a date range for a tenant (calendar)
    public static function findByDateRange($tenantId, $from, $to) {
        $db   = getDB();
        $stmt = $db->prepare(
            "SELECT a.id, a.patient_id, pu.name AS patient_name, a.doctor_id, u.name AS doctor_name,
                     a.appointment_date, a.status, a.notes
             FROM appointments a
             JOIN users u ON u.id = a.doctor_id
             JOIN patients p ON p.id = a.patient_id
             JOIN users pu ON pu.id = p.user_id
             WHERE a.tenant_id = ?
               AND a.appointment_date BETWEEN ? AND ?
             ORDER BY a.appointment_date ASC"
        );
        $stmt->execute([$tenantId, $from, $to]);
        return self::decryptRows($stmt->fetchAll());
    }

    // Fetch appointments by date range scoped to one patient (patient role calendar)
    public static function findByDateRangeForPatient($tenantId, $patientId, $from, $to) {
        $db   = getDB();
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
        $stmt->execute([$tenantId, $patientId, $from, $to]);
        return self::decryptRows($stmt->fetchAll());
    }
}