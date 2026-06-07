<?php
require_once __DIR__ . '/../Config/database.php';

class PatientRepository {
    // Sensitive fields that must be encrypted before storing, decrypted after fetching
    private static $encryptedFields = [
        'blood_group',
        'dob',
        'address',
        'emergency_contact',
    ];

    // Encrypt sensitive fields in a data array before writing to DB
    private static function encryptFields($data) {
        foreach (self::$encryptedFields as $field) {
            if (!empty($data[$field])) {
                $data[$field] = AES::encrypt($data[$field]);
            }
        }
        return $data;
    }

    // Decrypt sensitive fields in a fetched row before returning to service
    private static function decryptRow($row) {
        foreach (self::$encryptedFields as $field) {
            if (!empty($row[$field])) {
                $row[$field] = AES::decrypt($row[$field]);
            }
        }
        return $row;
    }

    // Decrypt an array of rows
    private static function decryptRows($rows) {
        return array_map([self::class, 'decryptRow'], $rows);
    }

    // Insert a new patient record, return new patient id
    public static function create($data) {
        $db   = getDB();
        $data = self::encryptFields($data);

        $stmt = $db->prepare(
            "INSERT INTO patients (tenant_id, user_id, blood_group, dob, gender, address, emergency_contact)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['tenant_id'],
            $data['user_id'],
            $data['blood_group']        ?? null,
            $data['dob']                ?? null,
            $data['gender']             ?? null,
            $data['address']            ?? null,
            $data['emergency_contact']  ?? null,
        ]);
        return (int) $db->lastInsertId();
    }

    // List all non-deleted patients for a tenant
    public static function findAllByTenant($tenantId) {
        $db   = getDB();
        $stmt = $db->prepare(
            "SELECT u.name, p.id, p.user_id, p.blood_group, p.dob, p.gender, p.address,
                    p.emergency_contact, p.created_at,
                     u.email
             FROM patients p
             JOIN users u ON u.id = p.user_id
             WHERE p.tenant_id = ? AND p.deleted_at IS NULL"
        );
        $stmt->execute([$tenantId]);
        return self::decryptRows($stmt->fetchAll());
    }

    // Find a single patient by id, must belong to tenant and not be deleted
    public static function findById($id, $tenantId) {
        $db   = getDB();
        $stmt = $db->prepare(
            "SELECT p.id, p.user_id, p.blood_group, p.dob, p.gender, p.address,
                    p.emergency_contact, p.created_at,
                    u.name, u.email
             FROM patients p
             JOIN users u ON u.id = p.user_id
             WHERE p.id = ? AND p.tenant_id = ? AND p.deleted_at IS NULL"
        );
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch();
        return $row ? self::decryptRow($row) : false;
    }

    // Find patient record by user_id (used when patient role books appointment)
    public static function findByUserId($userId, $tenantId) {
        $db   = getDB();
        $stmt = $db->prepare(
            "SELECT * FROM patients
             WHERE user_id = ? AND tenant_id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$userId, $tenantId]);
        return $stmt->fetch();
    }

    // Update allowed patient fields
    public static function update($id, $tenantId, $data) {
        $db   = getDB();
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
        return $stmt->rowCount() > 0;
    }

    // Soft delete: set deleted_at timestamp
    public static function softDelete($id, $tenantId) {
        $db   = getDB();
        $stmt = $db->prepare(
            "UPDATE patients
             SET deleted_at = NOW()
             WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$id, $tenantId]);
        return $stmt->rowCount() > 0;
    }
}