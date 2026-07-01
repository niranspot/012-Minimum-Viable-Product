<?php
require_once __DIR__ . '/../Services/StaffService.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Helpers/Response.php';
require_once __DIR__ . '/../Helpers/Validator.php';

class StaffController {
    // GET /staff
    public static function index() {
        $auth = AuthMiddleware::handle();
        AuthMiddleware::allowRoles($auth, ['admin', 'doctor', 'nurse','Patient']);
        $staff = StaffService::getAll();
        Response::success('Staff list retrieved', $staff);
    }
    
    // GET /staff/{id}
    public static function show($id) {
        $auth = AuthMiddleware::handle();
        AuthMiddleware::allowRoles($auth, ['admin', 'doctor', 'nurse']);
        $staff = StaffService::getById($id, );
        Response::success('Staff retrieved', $staff);
    }
    
    // POST /staff
    public static function store() {
        $auth = AuthMiddleware::handle();
        AuthMiddleware::allowRoles($auth, ['admin']);
        $payload = json_decode(file_get_contents('php://input'), true);
        $v = new Validator($payload);
        $v->required('user_id');
        if ($v->fails()) {
            Response::error(implode(', ', $v->errors()), 400);
        }
        $staff = StaffService::create($payload, );
        Response::success('Staff created', $staff, 201);
    }
    
    // PUT /staff/{id}
    public static function update($id) {
        $auth = AuthMiddleware::handle();
        AuthMiddleware::allowRoles($auth, ['admin']);
        $payload = json_decode(file_get_contents('php://input'), true);
        if (empty($payload)) {
            Response::error('No data provided', 400);
        }
        $staff = StaffService::update($id,  $payload);
        Response::success('Staff updated', $staff);
    }
    
    // DELETE /staff/{id}
    public static function destroy($id) {
        $auth = AuthMiddleware::handle();
        AuthMiddleware::allowRoles($auth, ['admin']);
        StaffService::delete($id, );
        Response::success('Staff deleted');
    }
}