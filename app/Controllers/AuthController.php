<?php
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/../Helpers/Response.php';
require_once __DIR__ . '/../Helpers/Validator.php';
require_once __DIR__ . '/../Security/CSRF.php';
require_once __DIR__ . '/../Security/AES.php';

class AuthController {
    public static function csrfToken() {
        $token = CSRF::generate();
        Response::success('CSRF token generated', ['csrf_token' => $token]);
    }

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
        // $payload = AES::decrypt($body['payload'] ?? '');

        $v = new Validator($payload);
        $v->required('email')->email('email')
          ->required('password');

        if ($v->fails()) {
            Response::error(implode(', ', $v->errors()), 400);
        }

        $result = AuthService::login($payload);
        $token = CSRF::generate();
        $result['csrf_token'] = $token;
        Response::success('Login successful', $result);

    }

    public static function refresh(): void {
        $result = AuthService::refresh();
        $token = CSRF::generate();
        $result['csrf_token'] = $token;
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

        AuthService::changePassword($auth, $payload);
        Response::success('Password changed successfully');
    }

    public static function logout(): void {
        $user = AuthMiddleware::handle();
        AuthService::logout($user['user_id']);
        Response::success('Logged out successfully');
    }
}