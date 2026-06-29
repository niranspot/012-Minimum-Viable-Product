<?php
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/../Helpers/Response.php';
require_once __DIR__ . '/../Helpers/Validator.php';
require_once __DIR__ . '/../Security/CSRF.php';
require_once __DIR__ . '/../Security/AES.php';

class AuthController {

    public static function register(){
        $payload    = json_decode(file_get_contents('php://input'), true);
        // $payload = AES::decrypt($body['payload'] ?? '');

        $v = new Validator($payload);
        $v->required('name')
          ->required('email')->email('email')
          ->required('password')->min('password', 6)
          ->required('role')->in('role', ROLES)
          ->required('tenant_id');

        if ($v->fails()) {
            Response::error(implode(', ', $v->errors()), 400);
        }

        $result = AuthService::register($payload);
        Response::success('User registered', $result, 201);
    }

    public static function login(){
        $payload    = json_decode(file_get_contents('php://input'), true);

        $v = new Validator($payload);
        $v->required('email')->email('email')
          ->required('password');

        if ($v->fails()) {
            Response::error(implode(', ', $v->errors()), 400);
        }

        $result = AuthService::login($payload);
        // Generate a new CSRF token on login and include it in the response. The frontend can store this and send it in the X-CSRF-Token header for subsequent requests.
        $token = CSRF::generate();
        $result['csrf_token'] = $token;
        Response::success('Login successful', $result);

    }

    public static function csrf(){
        $token = csrf::generate();
        $csrftoken['csrf_token'] = $token;
        Response::success('CSRF TOKEN', $csrftoken);
    }

    public static function refresh(): void {
        $result = AuthService::refresh();
        Response::success('Token refreshed', $result);
    }

    public static function changePassword() {
        $auth    = AuthMiddleware::handle();
        $payload = json_decode(file_get_contents('php://input'), true);

        $v = new Validator($payload);
        $v->required('email')->email('email')
        ->required('old_password')
        ->required('new_password')->min('new_password', 6);
        if ($v->fails()) Response::error(implode(', ', $v->errors()), 400);


        if ($payload['old_password'] === $payload['new_password']) {
            Response::error('New password cannot be the same as old password', 400);
        }

        AuthService::changePassword($auth, $payload);
        Response::success('Password changed successfully');
    }

    public static function logout(): void {
        $user = AuthMiddleware::handle();
        AuthService::logout($user['user_id']);
        Response::success('Logged out successfully');
    }
}