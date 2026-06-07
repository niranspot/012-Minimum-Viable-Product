<?php
require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Helpers/Response.php';

class MessageService {

    public static function getByAppointment($appointmentId, $tenantId) {
        $db   = getDB();
        $stmt = $db->prepare("
            SELECT m.*,
                   u.name AS sender_name,
                   u.role AS sender_role
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.appointment_id = ? AND m.tenant_id = ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$appointmentId, $tenantId]);
        return $stmt->fetchAll();
    }

    public static function create($data, $tenantId, $senderId) {
        $db = getDB();

        // Check appointment exists
        $stmt = $db->prepare("SELECT id FROM appointments WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$data['appointment_id'], $tenantId]);
        if (!$stmt->fetch()) Response::error('Appointment not found', 404);

        $stmt = $db->prepare("
            INSERT INTO messages (tenant_id, appointment_id, sender_id, message)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $tenantId,
            $data['appointment_id'],
            $senderId,
            $data['message'],
        ]);

        return ['message_id' => (int) $db->lastInsertId()];
    }
}