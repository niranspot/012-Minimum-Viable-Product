<?php
require_once __DIR__ . '/../Services/UserService.php';

class UserController {

    public static function listUsers() {
        $auth = AuthMiddleware::handle();
        AuthMiddleware::allowRoles($auth, ['admin']);

        $users = UserService::listUsers();
        Response::success('Users retrieved', $users);
    }

    public static function updateStatus($id) {
        $auth = AuthMiddleware::handle();
        AuthMiddleware::allowRoles($auth, ['admin']);

        $payload = json_decode(file_get_contents('php://input'), true);

        $v = new Validator($payload);
        $v->required('status');
        if ($v->fails()) {
            Response::error(implode(', ', $v->errors()), 400);
        }

        $result = UserService::updateStatus($id, $payload['status']);
        Response::success('User status updated', $result);
    }
}