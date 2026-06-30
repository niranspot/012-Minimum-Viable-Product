<?php
require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Helpers/Response.php';

class BillingService {

    public static function getAll() {
        // For simplicity, we join with patients and appointments to get more useful info in one query.
        $db   = getDB();
        $stmt = $db->prepare("
            SELECT  u.name AS patient_name,
                    b.id AS billing_id,
                    b.patient_id,
                    b.appointment_id,
                    b.amount,
                    b.status,
                    a.appointment_date
            FROM billing b
            JOIN patients p ON b.patient_id = p.id
            JOIN users u ON p.user_id = u.id
            JOIN appointments a ON b.appointment_id = a.id
            ORDER BY b.id DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function getById($id) {
        
        $db   = getDB();
        $stmt = $db->prepare("
            SELECT b.*,
                   u.name AS patient_name,
                   a.appointment_date
            FROM billing b
            JOIN patients p ON b.patient_id = p.id
            JOIN users u ON p.user_id = u.id
            JOIN appointments a ON b.appointment_id = a.id
            WHERE b.id = ? 
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) Response::error('Billing not found', 404);
        return $row;
    }
    
    public static function getByPatient($patientId) {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT u.name AS patient_name,
               b.id AS billing_id,
               b.patient_id,
               b.appointment_id,
               b.amount,
               b.status, 
               a.appointment_date
        FROM billing b
        JOIN patients p ON b.patient_id = p.id
        JOIN users u ON p.user_id = u.id
        JOIN appointments a ON b.appointment_id = a.id
        WHERE b.patient_id = ?
        ORDER BY b.id DESC
    ");
    $stmt->execute([ $patientId]);
    return $stmt->fetchAll();
}

    public static function create($data, ) {
        $db = getDB();

        // Check appointment exists
        $stmt = $db->prepare("SELECT id FROM appointments WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$data['appointment_id'], ]);
        if (!$stmt->fetch()) Response::error('Appointment not found', 404);

        // Check billing already exists for appointment
        $stmt = $db->prepare("SELECT id FROM billing WHERE appointment_id = ? AND tenant_id = ?");
        $stmt->execute([$data['appointment_id'], ]);
        if ($stmt->fetch()) Response::error('Billing already exists for this appointment', 400);

        $stmt = $db->prepare("
            INSERT INTO billing ( patient_id, appointment_id, amount, status)
            VALUES ( ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $data['patient_id'],
            $data['appointment_id'],
            $data['amount'],
        ]);

        return self::getById((int) $db->lastInsertId() );
    }

    public static function update($id, $data) {
        $db   = getDB();
        $stmt = $db->prepare("UPDATE billing SET status = ? WHERE id = ? ");
        $stmt->execute([$data['status'], $id, ]);
        if ($stmt->rowCount() === 0) Response::error('Billing not found or nothing changed', 404);
        return self::getById($id);
    }
}