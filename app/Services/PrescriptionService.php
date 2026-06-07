<?php
require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Security/AES.php';
require_once __DIR__ . '/../Helpers/Response.php';

class PrescriptionService {

    public static function getAll($tenantId) {
        $db   = getDB();
        $stmt = $db->prepare("
            SELECT p.*, u.name AS doctor_name, pt.id AS patient_record_id
            FROM prescriptions p
            JOIN users u ON p.doctor_id = u.id
            JOIN patients pt ON p.patient_id = pt.id
            WHERE p.tenant_id = ?
            ORDER BY p.id DESC
        ");
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll();
        return array_map(fn($r) => self::decrypt($r), $rows);
    }

    public static function getById($id, $tenantId) {
        $db   = getDB();
        $stmt = $db->prepare("
            SELECT p.*, u.name AS doctor_name, pt.id AS patient_record_id
            FROM prescriptions p
            JOIN users u ON p.doctor_id = u.id
            JOIN patients pt ON p.patient_id = pt.id
            WHERE p.id = ? AND p.tenant_id = ?
        ");
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch();
        if (!$row) Response::error('Prescription not found', 404);
        return self::decrypt($row);
    }

    public static function getByPatient($patientId, $tenantId) {
        $db   = getDB();
        $stmt = $db->prepare("
            SELECT p.*, u.name AS doctor_name
            FROM prescriptions p
            JOIN users u ON p.doctor_id = u.id
            WHERE p.patient_id = ? AND p.tenant_id = ?
            ORDER BY p.id DESC
        ");
        $stmt->execute([$patientId, $tenantId]);
        $rows = $stmt->fetchAll();
        return array_map(fn($r) => self::decrypt($r), $rows);
    }

    public static function getByAppointment($appointmentId, $tenantId) {
        $db   = getDB();
        $stmt = $db->prepare("
            SELECT p.*, u.name AS doctor_name
            FROM prescriptions p
            JOIN users u ON p.doctor_id = u.id
            WHERE p.appointment_id = ? AND p.tenant_id = ?
        ");
        $stmt->execute([$appointmentId, $tenantId]);
        $row = $stmt->fetch();
        if (!$row) Response::error('No prescription found for this appointment', 404);
        return self::decrypt($row);
    }

    public static function create($data, $tenantId, $doctorId) {

        if (empty($data['medicines']))      Response::error('medicines is required', 400);
        if (empty($data['appointment_id'])) Response::error('appointment_id is required', 400);
        if (empty($data['patient_id']))     Response::error('patient_id is required', 400);

        $db   = getDB();

        $stmt = $db->prepare("SELECT id FROM patients WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$data['patient_id'], $tenantId]);
        if (!$stmt->fetch()) Response::error('Patient not found', 404);

        $stmt = $db->prepare("SELECT id FROM appointments WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$data['appointment_id'], $tenantId]);
        if (!$stmt->fetch()) Response::error('Appointment not found', 404);
        $stmt = $db->prepare("
            INSERT INTO prescriptions (tenant_id, appointment_id, doctor_id, patient_id, medicines, status)
            VALUES (?, ?, ?, ?, ?, 'created')
        ");
        $stmt->execute([
            $tenantId,
            $data['appointment_id'],
            $doctorId,
            $data['patient_id'],
            AES::encrypt($data['medicines']),
        ]);

        return self::getById((int) $db->lastInsertId(), $tenantId);
    }
    

    public static function update($id, $tenantId, $data) {
        if (isset($data['status']) && !in_array($data['status'], PRESCRIPTION_STATUS)) {
            Response::error('Invalid status. Allowed: ' . implode(', ', PRESCRIPTION_STATUS), 400);
        }

        $db     = getDB();
        $fields = [];
        $params = [];

        if (array_key_exists('medicines', $data)) {
            $fields[] = 'medicines = ?';
            $params[] = AES::encrypt($data['medicines']);
        }
        if (array_key_exists('status', $data)) {
            $fields[] = 'status = ?';
            $params[] = $data['status'];
        }

        if (empty($fields)) Response::error('Nothing to update', 400);

        $params[] = $id;
        $params[] = $tenantId;

        $stmt = $db->prepare("UPDATE prescriptions SET " . implode(', ', $fields) . " WHERE id = ? AND tenant_id = ?");
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) Response::error('Prescription not found or nothing changed', 404);

        return self::getById($id, $tenantId);
    }

    public static function updateStatus($id, $tenantId, $status) {
        if (!in_array($status, PRESCRIPTION_STATUS)) {
            Response::error('Invalid status. Allowed: ' . implode(', ', PRESCRIPTION_STATUS), 400);
        }

        $db   = getDB();
        $stmt = $db->prepare("UPDATE prescriptions SET status = ? WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$status, $id, $tenantId]);

        if ($stmt->rowCount() === 0) Response::error('Prescription not found or status unchanged', 404);

        return self::getById($id, $tenantId);
    }

    public static function delete($id, $tenantId) {
        $db   = getDB();
        $stmt = $db->prepare("DELETE FROM prescriptions WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenantId]);
        if ($stmt->rowCount() === 0) Response::error('Prescription not found', 404);
    }

    // Decrypt medicines field
    private static function decrypt($row) {
        if (!empty($row['medicines'])) {
            $row['medicines'] = AES::decrypt($row['medicines']);
        }
        return $row;
    }
}