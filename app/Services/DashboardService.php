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
        $tenantId = (int) $authUser['tenant_id'];

        // Total active patients
        $stmt = $db->prepare(
            "SELECT COUNT(id) AS total_patients
             FROM patients
             WHERE tenant_id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$tenantId]);
        $patientsRow = $stmt->fetch();

        // Appointments grouped by status
        $stmt = $db->prepare(
            "SELECT status, COUNT(id) AS count
             FROM appointments
             WHERE tenant_id = ?
             GROUP BY status"
        );
        $stmt->execute([$tenantId]);
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
             WHERE tenant_id = ?
             GROUP BY status"
        );
        $stmt->execute([$tenantId]);
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
        $db       = getDB();
        $tenantId = (int) $authUser['tenant_id'];
        $from     = $_GET['from'] ?? null;
        $to       = $_GET['to']   ?? null;

        $dateFilter = '';
        $params     = [$tenantId];

        if ($from && $to) {
            $dateFilter = "AND DATE(a.appointment_date) BETWEEN ? AND ?";
            $params[]   = $from;
            $params[]   = $to;
        }

        // Appointments per day
        $stmt = $db->prepare(
            "SELECT DATE(appointment_date) AS date, COUNT(*) AS count
             FROM appointments a
             WHERE a.tenant_id = ? $dateFilter
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
             WHERE a.tenant_id = ? $dateFilter
             GROUP BY a.doctor_id
             ORDER BY total DESC"
        );
        $stmt->execute($params);
        $perDoctor = $stmt->fetchAll();

        return [
            'per_day'    => $perDay,
            'per_doctor' => $perDoctor,
        ];
    }

    // ---------------------------------------------------------------
    // GET /dashboard/prescriptions  — Provider & Admin
    // Returns prescription breakdown and top medicines
    // ---------------------------------------------------------------
    public static function prescriptions($authUser) {
        $db       = getDB();
        $tenantId = (int) $authUser['tenant_id'];

        // Prescriptions by status with doctor name
        $stmt = $db->prepare(
            "SELECT p.status, COUNT(*) AS count,
                    u.name AS doctor_name, p.doctor_id
             FROM prescriptions p
             JOIN users u ON p.doctor_id = u.id
             WHERE p.tenant_id = ?
             GROUP BY p.status, p.doctor_id
             ORDER BY count DESC"
        );
        $stmt->execute([$tenantId]);
        $byStatusDoctor = $stmt->fetchAll();

        // Total per doctor (summary)
        $stmt = $db->prepare(
            "SELECT u.name AS doctor_name, p.doctor_id, COUNT(*) AS total_prescriptions
             FROM prescriptions p
             JOIN users u ON p.doctor_id = u.id
             WHERE p.tenant_id = ?
             GROUP BY p.doctor_id
             ORDER BY total_prescriptions DESC"
        );
        $stmt->execute([$tenantId]);
        $perDoctor = $stmt->fetchAll();

        return [
            'by_status_and_doctor' => $byStatusDoctor,
            'per_doctor'           => $perDoctor,
        ];
    }

    // ---------------------------------------------------------------
    // GET /dashboard/tenant-analytics  — Admin only
    // Returns cross-tenant comparison: patients, appointments, prescriptions
    // ---------------------------------------------------------------
    public static function tenantAnalytics() {
        $db = getDB();

        $stmt = $db->prepare(
            "SELECT
                t.id AS tenant_id,
                t.name AS tenant_name,
                t.status AS tenant_status,
                (SELECT COUNT(*) FROM patients p
                 WHERE p.tenant_id = t.id AND p.deleted_at IS NULL) AS total_patients,
                (SELECT COUNT(*) FROM appointments a
                 WHERE a.tenant_id = t.id) AS total_appointments,
                (SELECT COUNT(*) FROM appointments a
                 WHERE a.tenant_id = t.id AND a.status = 'completed') AS completed_appointments,
                (SELECT COUNT(*) FROM prescriptions rx
                 WHERE rx.tenant_id = t.id) AS total_prescriptions,
                (SELECT COUNT(*) FROM users u
                 WHERE u.tenant_id = t.id AND u.status = 'active') AS active_users
             FROM tenants t
             ORDER BY t.id ASC"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return ['tenants' => $rows];
    }
}