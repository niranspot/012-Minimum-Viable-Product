<?php
require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Helpers/Response.php';
require_once __DIR__ . '/../Security/AES.php';

class StaffService {

    public static function getAll() {
        $db   = getDB();
        $stmt = $db->prepare("
            SELECT s.id, u.name, u.email, s.user_id, u.role, s.specialization, s.status, s.created_at
            FROM staff s
            JOIN users u ON s.user_id = u.id
            ORDER BY s.id DESC
        ");
        $stmt->execute();
        $staff= $stmt->fetchAll();
        return array_map(function($s) {
            $s['specialization'] = AES::decrypt($s['specialization']);
            return $s;
        }, $staff);
    }

    public static function getById($id) {
        $db   = getDB();
        $stmt = $db->prepare("select id from staff where id = ? ");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) Response::error('Staff not found', 404);
        $stmt = $db->prepare("
            SELECT s.id, u.name, s.user_id, u.role, s.specialization, s.status, s.created_at
                     
            FROM staff s
            JOIN users u ON s.user_id = u.id
            WHERE s.id = ? AND s.tenant_id = ?
        ");
        $stmt->execute([$id]);
        $staff = $stmt->fetch();
        $staff['specialization'] = AES::decrypt($staff['specialization']);
        if (!$staff) Response::error('Staff not found', 404);
        return $staff;
    }

    public static function create($data, ) {
        $db = getDB();

        // Check already exists
        $stmt = $db->prepare("SELECT id FROM staff WHERE user_id = ? ");
        $stmt->execute([$data['user_id'], ]);
        if ($stmt->fetch()) Response::error('Staff profile already exists for this user', 400);

        // Validate user exists and is not a patient
        $stmt = $db->prepare("SELECT role FROM users WHERE id = ? ");
        $stmt->execute([$data['user_id'], ]);
        $user = $stmt->fetch();

        if (!$user) Response::error('User not found', 404);
        if ($user['role'] === 'patient') Response::error('Cannot create staff profile for a patient user', 400);

        // Insert staff record
        $stmt = $db->prepare("
            INSERT INTO staff ( user_id, specialization, status)
            VALUES ( ?, ?, ?)
        ");
        $stmt->execute([
            $data['user_id'],
            AES::encrypt($data['specialization'] ?? null),
            'active',
        ]);

        return self::getById((int) $db->lastInsertId(), );
    }

    public static function update($id, $data) {
        $db      = getDB();
        $fields  = [];
        $params  = [];
        $allowed = ['specialization', 'status'];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $fields[] = "$col = ?";
                $params[] = $col === 'specialization' ? AES::encrypt($data[$col]) : $data[$col];
            }
        }

        if (empty($fields)) Response::error('Nothing to update', 400);

        $params[] = $id;

        $stmt = $db->prepare("UPDATE staff SET " . implode(', ', $fields) . " WHERE id = ? ");
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) Response::error('Staff not found or nothing changed', 404);

        // Sync status to users table
        $stmt = $db->prepare("SELECT user_id, status FROM staff WHERE id = ? ");
        $stmt->execute([$id, ]);
        $staff = $stmt->fetch();

        $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$staff['status'], $staff['user_id']]);

        return self::getById($id, );
    }

    public static function delete($id, ) {
        $db   = getDB();
        $stmt = $db->prepare("DELETE FROM staff WHERE id = ? ");
        $stmt->execute([$id, ]);
        if ($stmt->rowCount() === 0) Response::error('Staff not found', 404);
    }
}