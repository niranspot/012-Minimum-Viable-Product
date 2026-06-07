<?php
require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Security/AES.php';
require_once __DIR__ . '/../Helpers/Response.php';

class PrescriptionService {
    private PDO $db;

    public function __construct() {
          $this->db = getDB();
    }

    // ─── AES helpers ─────────────────────────────────────────────────

    private function encrypt(string $value): string {
        return AES::encrypt($value);
    }

    private function decrypt(string $value): string {
        return AES::decrypt($value);
    }

    private function decryptRow(array $row): array {
        if (!empty($row['medicines'])) {
            $row['medicines'] = $this->decrypt($row['medicines']);
        }
        return $row;
    }

    // ─── Private DB methods ──────────────────────────────────────────

    private function findAll(int $tenantId): array {
        $stmt = $this->db->prepare("
            SELECT p.*,
                   u.name  AS doctor_name,
                   pt.id   AS patient_record_id
            FROM prescriptions p
            JOIN users u     ON p.doctor_id  = u.id
            JOIN patients pt ON p.patient_id = pt.id
            WHERE p.tenant_id = ?
            ORDER BY p.id DESC
        ");
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($r) => $this->decryptRow($r), $rows);
    }

    private function findById(int $id, int $tenantId): array|false {
        $stmt = $this->db->prepare("
            SELECT p.*,
                   u.name  AS doctor_name,
                   pt.id   AS patient_record_id
            FROM prescriptions p
            JOIN users u     ON p.doctor_id  = u.id
            JOIN patients pt ON p.patient_id = pt.id
            WHERE p.id = ? AND p.tenant_id = ?
        ");
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->decryptRow($row) : false;
    }

    private function findByPatient(int $patientId, int $tenantId): array {
        $stmt = $this->db->prepare("
            SELECT p.*, u.name AS doctor_name
            FROM prescriptions p
            JOIN users u ON p.doctor_id = u.id
            WHERE p.patient_id = ? AND p.tenant_id = ?
            ORDER BY p.id DESC
        ");
        $stmt->execute([$patientId, $tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($r) => $this->decryptRow($r), $rows);
    }

    private function findByAppointment(int $appointmentId, int $tenantId): array|false {
        $stmt = $this->db->prepare("
            SELECT p.*, u.name AS doctor_name
            FROM prescriptions p
            JOIN users u ON p.doctor_id = u.id
            WHERE p.appointment_id = ? AND p.tenant_id = ?
        ");
        $stmt->execute([$appointmentId, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->decryptRow($row) : false;
    }

    private function createRecord(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO prescriptions
                (tenant_id, appointment_id, doctor_id, patient_id, medicines, status)
            VALUES
                (:tenant_id, :appointment_id, :doctor_id, :patient_id, :medicines, :status)
        ");
        $stmt->execute([
            ':tenant_id'      => $data['tenant_id'],
            ':appointment_id' => $data['appointment_id'],
            ':doctor_id'      => $data['doctor_id'],
            ':patient_id'     => $data['patient_id'],
            ':medicines'      => $this->encrypt($data['medicines']),
            ':status'         => $data['status'] ?? 'created',
        ]);
        return (int) $this->db->lastInsertId();
    }

    private function updateRecord(int $id, int $tenantId, array $data): bool {
        $fields = [];
        $params = [];

        if (array_key_exists('medicines', $data)) {
            $fields[]           = 'medicines = :medicines';
            $params[':medicines'] = $this->encrypt($data['medicines']);
        }
        if (array_key_exists('status', $data)) {
            $fields[]         = 'status = :status';
            $params[':status'] = $data['status'];
        }

        if (empty($fields)) return false;

        $params[':id']        = $id;
        $params[':tenant_id'] = $tenantId;

        $stmt = $this->db->prepare(
            "UPDATE prescriptions SET " . implode(', ', $fields) . " WHERE id = :id AND tenant_id = :tenant_id"
        );
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    private function updateStatusRecord(int $id, int $tenantId, string $status): bool {
        $stmt = $this->db->prepare("
            UPDATE prescriptions SET status = ? WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$status, $id, $tenantId]);
        return $stmt->rowCount() > 0;
    }

    private function deleteRecord(int $id, int $tenantId): bool {
        $stmt = $this->db->prepare("DELETE FROM prescriptions WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenantId]);
        return $stmt->rowCount() > 0;
    }

    // ─── Public service methods (called by Controller) ───────────────

    public static function getAll(int $tenantId): array {
        return (new self())->findAll($tenantId);
    }

    public static function getById(int $id, int $tenantId): array {
        $rx = (new self())->findById($id, $tenantId);
        if (!$rx) Response::error('Prescription not found', 404);
        return $rx;
    }

    public static function getByPatient(int $patientId, int $tenantId): array {
        return (new self())->findByPatient($patientId, $tenantId);
    }

    public static function getByAppointment(int $appointmentId, int $tenantId): array {
        $rx = (new self())->findByAppointment($appointmentId, $tenantId);
        if (!$rx) Response::error('No prescription found for this appointment', 404);
        return $rx;
    }

    public static function create(array $data, int $tenantId, int $doctorId): array {
        if (empty($data['medicines'])) {
            Response::error('medicines field is required', 400);
        }
        if (empty($data['appointment_id'])) {
            Response::error('appointment_id is required', 400);
        }
        if (empty($data['patient_id'])) {
            Response::error('patient_id is required', 400);
        }

        $data['tenant_id'] = $tenantId;
        $data['doctor_id'] = $doctorId;
        $data['status']    = 'created';

        $id = (new self())->createRecord($data);
        return self::getById($id, $tenantId);
    }

    public static function update(int $id, int $tenantId, array $data): array {
        if (isset($data['status']) && !in_array($data['status'], PRESCRIPTION_STATUS)) {
            Response::error('Invalid status. Allowed: ' . implode(', ', PRESCRIPTION_STATUS), 400);
        }

        $updated = (new self())->updateRecord($id, $tenantId, $data);
        if (!$updated) Response::error('Prescription not found or nothing changed', 404);
        return self::getById($id, $tenantId);
    }

    public static function updateStatus(int $id, int $tenantId, string $status): array {
        if (!in_array($status, PRESCRIPTION_STATUS)) {
            Response::error('Invalid status. Allowed: ' . implode(', ', PRESCRIPTION_STATUS), 400);
        }
        $updated = (new self())->updateStatusRecord($id, $tenantId, $status);
        if (!$updated) Response::error('Prescription not found or status unchanged', 404);
        return self::getById($id, $tenantId);
    }

    public static function delete(int $id, int $tenantId): void {
        $deleted = (new self())->deleteRecord($id, $tenantId);
        if (!$deleted) Response::error('Prescription not found', 404);
    }
}