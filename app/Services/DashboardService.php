<?php
require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Helpers/Response.php';

class DashboardService {

    // ---------------------------------------------------------------
    // GET /dashboard/summary  — Provider & Admin
    // Returns: total patients, appointments (by status), prescriptions (by status)
    // ---------------------------------------------------------------
    public static function summary($authUser) {
        $db       = getDB();

        // Total active patients
        $stmt = $db->prepare(
            "SELECT COUNT(id) AS total_patients
             FROM patients
             WHERE deleted_at IS NULL"
        );
        $stmt->execute();
        $patientsRow = $stmt->fetch();

        // Appointments grouped by status
        $stmt = $db->prepare(
            "SELECT status, COUNT(id) AS count
             FROM appointments
             GROUP BY status"
        );
        $stmt->execute();
        $apptRows = $stmt->fetchAll();

        $appointmentStats = [
            'total'     => 0,
            'pending'   => 0,
            'confirmed' => 0,
            'cancelled' => 0,
            'completed' => 0,
        ];
        foreach ($apptRows as $row) {
            $appointmentStats[$row['status']] = (int) $row['count'];
            $appointmentStats['total'] += (int) $row['count'];
        }

        // Prescriptions grouped by status
        $stmt = $db->prepare(
            "SELECT status, COUNT(id) AS count
             FROM prescriptions
             GROUP BY status"
        );
        $stmt->execute();
        $rxRows = $stmt->fetchAll();

        $prescriptionSummary = [
            'total'     => 0,
            'created'   => 0,
            'verified'  => 0,
            'dispensed' => 0,
        ];
        foreach ($rxRows as $row) {
            $prescriptionSummary[$row['status']] = (int) $row['count'];
            $prescriptionSummary['total'] += (int) $row['count'];
        }

        return [
            'total_patients'      => (int) $patientsRow['total_patients'],
            'appointment_stats'   => $appointmentStats,
            'prescription_summary'=> $prescriptionSummary,
        ];
    }

    // ---------------------------------------------------------------
    // GET /dashboard/appointments  — Provider & Admin
    // Optional query params: ?from=YYYY-MM-DD&to=YYYY-MM-DD
    // Returns appointment counts per day and per doctor in date range
    // ---------------------------------------------------------------
public static function appointments($authUser) {
    $db   = getDB();
    $from = $_GET['from'] ?? null;
    $to   = $_GET['to']   ?? null;

    $dateFilter = '';
    $params     = [];

    if ($from && $to) {
        $dateFilter = "WHERE DATE(a.appointment_date) BETWEEN ? AND ?";
        $params[]   = $from;
        $params[]   = $to;
    }

    // Appointments per day
    $stmt = $db->prepare(
        "SELECT DATE(appointment_date) AS date, COUNT(*) AS count
         FROM appointments a
         $dateFilter
         GROUP BY DATE(appointment_date)
         ORDER BY date ASC"
    );
    $stmt->execute($params);
    $perDay = $stmt->fetchAll();

    // Appointments per doctor
    $stmt = $db->prepare(
        "SELECT u.name AS doctor_name, u.id AS doctor_id, COUNT(*) AS total,
                SUM(a.status = 'completed') AS completed,
                SUM(a.status = 'cancelled') AS cancelled
         FROM appointments a
         JOIN users u ON a.doctor_id = u.id
         $dateFilter
         GROUP BY a.doctor_id
         ORDER BY total DESC"
    );
    $stmt->execute($params);
    $perDoctor = $stmt->fetchAll();

    return ['per_day' => $perDay, 'per_doctor' => $perDoctor];
}

    // ---------------------------------------------------------------
    // GET /dashboard/prescriptions  — Provider & Admin
    // Returns prescription breakdown and top medicines
    // ---------------------------------------------------------------
    public static function prescriptions($authUser) {
        $db       = getDB();

        // Prescriptions by status with doctor name
        $stmt = $db->prepare(
            "SELECT p.status, COUNT(*) AS count,
                    u.name AS doctor_name, p.doctor_id
             FROM prescriptions p
             JOIN users u ON p.doctor_id = u.id
             GROUP BY p.status, p.doctor_id
             ORDER BY count DESC"
        );
        $stmt->execute();
        $byStatusDoctor = $stmt->fetchAll();

        // Total per doctor (summary)
        $stmt = $db->prepare(
            "SELECT u.name AS doctor_name, p.doctor_id, COUNT(*) AS total_prescriptions
             FROM prescriptions p
             JOIN users u ON p.doctor_id = u.id
             GROUP BY p.doctor_id
             ORDER BY total_prescriptions DESC"
        );
        $stmt->execute();
        $perDoctor = $stmt->fetchAll();

        return [
            'by_status_and_doctor' => $byStatusDoctor,
            'per_doctor'           => $perDoctor,
        ];
    }

    
}