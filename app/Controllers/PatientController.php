<?php

require_once __DIR__ . '/../Services/PatientService.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Helpers/Response.php';
require_once __DIR__ . '/../Helpers/Validator.php';

class PatientController {

    // POST /patients
    public static function create() {
        $authUser = AuthMiddleware::handle();
        AuthMiddleware::allowRoles($authUser, ['doctor', 'nurse']);

        $payload = json_decode(file_get_contents('php://input'), true);

        $v = new Validator($payload);
        $v->required('user_id');

        // gender is optional but if provided must be valid
        if (!empty($payload['gender'])) {
            $v->in('gender', ['male', 'female', 'other']);
        }

        if ($v->fails()) {
            Response::error(implode(', ', $v->errors()), 400);
        }

        $result = PatientService::create($payload,);
        Response::success('Patient created', $result, 201);
    }

    // GET /patients
    public static function list(){
        $authUser = AuthMiddleware::handle();
        AuthMiddleware::allowRoles($authUser, ['doctor', 'nurse']);

        $patients = PatientService::list();
        Response::success('Patients fetched', $patients);
    }

    // GET /patients/{id}
    public static function show($id) {
    $authUser = AuthMiddleware::handle();
    AuthMiddleware::allowRoles($authUser, ['doctor', 'nurse']);

    $patient = PatientService::getById($id,);
    Response::success('Patient fetched', $patient);
    }

    // PUT /patients/{id}
    public static function update($id) {
        $authUser = AuthMiddleware::handle();
        AuthMiddleware::allowRoles($authUser, ['doctor', 'nurse']);

        $payload = json_decode(file_get_contents('php://input'), true);

        // gender is optional but if provided must be valid
        $v = new Validator($payload);
        if (!empty($payload['gender'])) {
            $v->in('gender', ['male', 'female', 'other']);
        }

        if ($v->fails()) {
            Response::error(implode(', ', $v->errors()), 400);
        }

        $result = PatientService::update($id, $payload, );
        Response::success('Patient updated', $result);
    }

    // DELETE /patients/{id}
    public static function delete($id) {
        $authUser = AuthMiddleware::handle();
        AuthMiddleware::allowRoles($authUser, ['doctor', 'nurse']);

        $result = PatientService::delete($id, );
        Response::success('Patient deleted', $result);
    }
}