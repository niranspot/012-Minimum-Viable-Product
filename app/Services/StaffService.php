<?php
require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Helpers/Response.php';
require_once __DIR__ . '/../Security/AES.php';

class StaffService {
    private PDO $db;

    public function __construct() {
        $this->db = getDB();
    }

    // ─── Private DB methods ──────────────────────────────────────────

    private function findAll(int $tenantId): array {
        $stmt = $this->db->prepare("
            SELECT s.id, s.user_id, s.specialization, s.status, s.created_at,
                   u.name, u.email, u.role
            FROM staff s
            JOIN users u ON s.user_id = u.id
            WHERE s.tenant_id = ?
            ORDER BY s.id DESC
        ");
        $stmt->execute([$tenantId]);
        $staff= $stmt->fetchAll();
        return array_map(function($s) {
            $s['specialization'] = AES::decrypt($s['specialization']);
            return $s;
        }, $staff);
    }

    private function findById(int $id, int $tenantId): array|false {
        $stmt = $this->db->prepare("
            SELECT s.id, s.user_id, s.specialization, s.status, s.created_at,
                   u.name, u.email, u.role
            FROM staff s
            JOIN users u ON s.user_id = u.id
            WHERE s.id = ? AND s.tenant_id = ?
        ");
        $stmt->execute([$id, $tenantId]);
        $staff = $stmt->fetch();
        $staff['specialization'] = AES::decrypt($staff['specialization']);
        if (!$staff) Response::error('Staff not found', 404);
        return $staff;
    }

    public static function create($data, $tenantId) {
        $db = getDB();

        // Check already exists
        $stmt = $db->prepare("SELECT id FROM staff WHERE user_id = ? AND tenant_id = ?");
        $stmt->execute([$data['user_id'], $tenantId]);
        if ($stmt->fetch()) Response::error('Staff profile already exists for this user', 400);

        // Validate user exists and is not a patient
        $stmt = $db->prepare("SELECT role FROM users WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$data['user_id'], $tenantId]);
        $user = $stmt->fetch();

        if (!$user) Response::error('User not found', 404);
        if ($user['role'] === 'patient') Response::error('Cannot create staff profile for a patient user', 400);

        // Insert staff record
        $stmt = $db->prepare("
            INSERT INTO staff (tenant_id, user_id, specialization, status)
            VALUES (:tenant_id, :user_id, :specialization, :status)
        ");
        $stmt->execute([
            $tenantId,
            $data['user_id'],
            AES::encrypt($data['specialization'] ?? null),
            'active',
        ]);
        return (int) $this->db->lastInsertId();
    }

    private function updateRecord(int $id, int $tenantId, array $data): bool {
        $fields = [];
        $params = [];
        $allowed = ['specialization', 'status'];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $fields[]      = "$col = :$col";
                $params[":$col"] = $data[$col];
            }
        }

        if (empty($fields)) return false;

        $params[':id']        = $id;
        $params[':tenant_id'] = $tenantId;

        $stmt = $this->db->prepare(
            "UPDATE staff SET " . implode(', ', $fields) . " WHERE id = :id AND tenant_id = :tenant_id"
        );
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    private function deleteRecord(int $id, int $tenantId): bool {
        $stmt = $this->db->prepare("DELETE FROM staff WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenantId]);
        return $stmt->rowCount() > 0;
    }

    // ─── Public service methods (called by Controller) ───────────────

    public static function getAll(int $tenantId): array {
        return (new self())->findAll($tenantId);
    }

    public static function getById(int $id, int $tenantId): array {
        $staff = (new self())->findById($id, $tenantId);
        if (!$staff) Response::error('Staff not found', 404);
        return $staff;
    }

    public static function create(array $data, int $tenantId): array {
        $self = new self();

        // Validate the user_id belongs to tenant and has a staff role
        $existing = $self->findByUserId($data['user_id'], $tenantId);
        if ($existing) Response::error('Staff profile already exists for this user', 400);

        $data['tenant_id'] = $tenantId;
        $id = $self->createRecord($data);
        return self::getById($id, $tenantId);
    }

    public static function update(int $id, int $tenantId, array $data): array {
        $updated = (new self())->updateRecord($id, $tenantId, $data);
        if (!$updated) Response::error('Staff not found or nothing changed', 404);
        return self::getById($id, $tenantId);
    }

    public static function delete(int $id, int $tenantId): void {
        $deleted = (new self())->deleteRecord($id, $tenantId);
        if (!$deleted) Response::error('Staff not found', 404);
    }
}