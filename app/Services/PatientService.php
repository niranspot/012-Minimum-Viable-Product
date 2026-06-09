<?php
require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Security/AES.php';
require_once __DIR__ . '/../Helpers/Response.php';

class PatientService {

    // Sensitive fields — encrypted before storing, decrypted after fetching
    private static array $encryptedFields = [
        'blood_group',
        'dob',
        'address',
        'emergency_contact',
    ];

    private static function encryptFields(array $data): array {
        foreach (self::$encryptedFields as $field) {
            if (!empty($data[$field])) {
                $data[$field] = AES::encrypt((string) $data[$field]);
            }
        }
        return $data;
    }

    private static function decryptRow(array $row): array {
        foreach (self::$encryptedFields as $field) {
            if (!empty($row[$field])) {
                $row[$field] = AES::decrypt($row[$field]);
            }
        }
        return $row;
    }

    private static function decryptRows(array $rows): array {
        return array_map([self::class, 'decryptRow'], $rows);
    }

    // ---------------------------------------------------------------
    // POST /patients — Create patient profile
    // ---------------------------------------------------------------
    public static function create(array $data, int $tenantId): array {
        $db = getDB();

        // user_id must be an active patient-role user in this tenant
        $stmt = $db->prepare(
            "SELECT id FROM users
             WHERE id = ? AND tenant_id = ? AND role = 'patient' AND status = 'active'"
        );
        $stmt->execute([$data['user_id'], $tenantId]);
        if (!$stmt->fetch()) {
            Response::error('user_id must be an active patient user in your tenant', 400);
        }

        // Prevent duplicate patient profile for same user
        $stmt = $db->prepare(
            "SELECT id FROM patients
             WHERE user_id = ? AND tenant_id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$data['user_id'], $tenantId]);
        if ($stmt->fetch()) {
            Response::error('Patient profile already exists for this user', 400);
        }

        // Encrypt sensitive fields before storing
        $data = self::encryptFields($data);

        $stmt = $db->prepare(
            "INSERT INTO patients (tenant_id, user_id, blood_group, dob, gender, address, emergency_contact)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $tenantId,
            $data['user_id'],
            $data['blood_group']       ?? null,
            $data['dob']               ?? null,
            $data['gender']            ?? null,
            $data['address']           ?? null,
            $data['emergency_contact'] ?? null,
        ]);

        return ['patient_id' => (int) $db->lastInsertId()];
    }

    // ---------------------------------------------------------------
    // GET /patients — List all patients in tenant
    // ---------------------------------------------------------------
    public static function list(int $tenantId): array {
        $db   = getDB();
        $stmt = $db->prepare(
            "SELECT p.id, p.user_id, p.blood_group, p.dob, p.gender, p.address,
                    p.emergency_contact, p.created_at,
                    u.name, u.email
             FROM patients p
             JOIN users u ON u.id = p.user_id
             WHERE p.tenant_id = ? AND p.deleted_at IS NULL"
        );
        $stmt->execute([$tenantId]);
        return self::decryptRows($stmt->fetchAll());
    }

    // GET /patients/{id} — Fetch single patient
    public static function getById(int $id, int $tenantId): array {
    $db = getDB();

    $stmt = $db->prepare(
        "SELECT p.id, p.user_id, p.blood_group, p.dob, p.gender, p.address,
                p.emergency_contact, p.created_at,
                u.name, u.email
         FROM patients p
         JOIN users u ON u.id = p.user_id
         WHERE p.id = ? AND p.tenant_id = ? AND p.deleted_at IS NULL"
    );
    $stmt->execute([$id, $tenantId]);
    $patient = $stmt->fetch();

    if (!$patient) {
        Response::error('Patient not found', 404);
    }

    return self::decryptRow($patient);
    }

    // ---------------------------------------------------------------
    // PUT /patients/{id} — Update patient
    // ---------------------------------------------------------------
    public static function update(int $id, array $data, int $tenantId): array {
        $db = getDB();

        // Check patient exists in this tenant and is not deleted
        $stmt = $db->prepare(
            "SELECT id FROM patients
             WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$id, $tenantId]);
        if (!$stmt->fetch()) {
            Response::error('Patient not found', 404);
        }

        // Encrypt sensitive fields before storing
        $data = self::encryptFields($data);

        $stmt = $db->prepare(
            "UPDATE patients
             SET blood_group = ?, dob = ?, gender = ?, address = ?, emergency_contact = ?
             WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([
            $data['blood_group']       ?? null,
            $data['dob']               ?? null,
            $data['gender']            ?? null,
            $data['address']           ?? null,
            $data['emergency_contact'] ?? null,
            $id,
            $tenantId,
        ]);

        if ($stmt->rowCount() === 0) {
            Response::error('No changes were made', 400);
        }

        return ['patient_id' => $id];
    }

    // ---------------------------------------------------------------
    // DELETE /patients/{id} — Soft delete
    // ---------------------------------------------------------------
    public static function delete(int $id, int $tenantId): array {
        $db = getDB();

        // Check patient exists
        $stmt = $db->prepare(
            "SELECT id FROM patients
             WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$id, $tenantId]);
        if (!$stmt->fetch()) {
            Response::error('Patient not found', 404);
        }

        $stmt = $db->prepare(
            "UPDATE patients SET deleted_at = NOW()
             WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$id, $tenantId]);

        if ($stmt->rowCount() === 0) {
            Response::error('Could not delete patient', 400);
        }

        return ['patient_id' => $id];
    }
}