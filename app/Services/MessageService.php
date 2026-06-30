<?php
require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Helpers/Response.php';

class MessageService {

    public static function getByAppointment($appointmentId, ) {
        $db   = getDB();
        $stmt = $db->prepare("
            SELECT m.*,
                   u.name AS sender_name,
                   u.role AS sender_role
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.appointment_id = ? 
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$appointmentId, ]);
        $data= $stmt->fetchAll();
        return array_map(fn($d) => [
            'id' => (int) $d['id'],
            'appointment_id' => (int) $d['appointment_id'],
            'sender_id' => (int) $d['sender_id'],
            'sender_name' => $d['sender_name'],
            'sender_role' => $d['sender_role'],
            'message' => AES::decrypt($d['message']),
            'created_at' => $d['created_at']
        ], $data);
    }

    public static function create($data, $senderId) {
        $db = getDB();

        // Check appointment exists
        $stmt = $db->prepare("SELECT id FROM appointments WHERE id = ? ");
        $stmt->execute([$data['appointment_id'] ]);
        if (!$stmt->fetch()) Response::error('Appointment not found', 404);

        $stmt = $db->prepare("
            INSERT INTO messages ( appointment_id, sender_id, message)
            VALUES ( ?, ?, ?)
        ");
        $stmt->execute([
            $data['appointment_id'],
            $senderId,
            AES::encrypt($data['message'])
        ]);

        return ['message_id' => (int) $db->lastInsertId()];
    }
}