<?php
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/../Helpers/Response.php';
require_once __DIR__ . '/../Helpers/Validator.php';
require_once __DIR__ . '/../Security/CSRF.php';
require_once __DIR__ . '/../Security/AES.php';

class AuthController {
    public static function csrfToken(): void {
        $token = CSRF::generate();
        Response::success('CSRF token generated', ['csrf_token' => $token]);
    }

    public static function register(): void {
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

    public static function login(): void {
        $payload    = json_decode(file_get_contents('php://input'), true);
        // $payload = AES::decrypt($body['payload'] ?? '');

        $v = new Validator($payload);
        $v->required('email')->email('email')
          ->required('password');

        if ($v->fails()) {
            Response::error(implode(', ', $v->errors()), 400);
        }

        $result = AuthService::login($payload);
        Response::success('Login successful', $result);
    }

    public static function refresh(): void {
        $result = AuthService::refresh();
        Response::success('Token refreshed', $result);
    }
}