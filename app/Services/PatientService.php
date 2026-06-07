<?php
require_once __DIR__ . '/../Repositories/PatientRepository.php';
require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Helpers/Response.php';

class PatientService {

    // Create a new patient profile linked to an existing user
    public static function create( $data, $tenantId) {
        $db = getDB();

        // The user_id must belong to this tenant and have role 'patient'
        $stmt = $db->prepare(
            "SELECT id FROM users
             WHERE id = ? AND tenant_id = ? AND role = 'patient' AND status = 'active'"
        );
        $stmt->execute([$data['user_id'], $tenantId]);
        if (!$stmt->fetch()) {
            Response::error('user_id must be an active patient user in your tenant', 400);
        }

        // Prevent duplicate patient profile for same user
        $existing = PatientRepository::findByUserId($data['user_id'], $tenantId);
        if ($existing) {
            Response::error('Patient profile already exists for this user', 400);
        }

        $data['tenant_id'] = $tenantId;
        $patientId = PatientRepository::create($data);

        return ['patient_id' => $patientId];
    }

    // List all patients in the tenant
    public static function list($tenantId){
        return PatientRepository::findAllByTenant($tenantId);
    }

    // Update a patient's medical info
    public static function update( $id, $data, $tenantId) {
        $patient = PatientRepository::findById($id, $tenantId);
        if (!$patient) {
            Response::error('Patient not found', 404);
        }

        $updated = PatientRepository::update($id, $tenantId, $data);
        if (!$updated) {
            Response::error('No changes were made', 400);
        }

        return ['patient_id' => $id];
    }

    // Soft delete a patient record
    public static function delete($id, $tenantId) {
        $patient = PatientRepository::findById($id, $tenantId);
        if (!$patient) {
            Response::error('Patient not found', 404);
        }

        $deleted = PatientRepository::softDelete($id, $tenantId);
        if (!$deleted) {
            Response::error('Could not delete patient', 400);
        }

        return ['patient_id' => $id];
    }
}