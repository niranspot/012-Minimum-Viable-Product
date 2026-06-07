<?php
class CSRF {
    public static function generate(): string {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }

    public static function validate(): bool {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $headers = getallheaders();
        $token   = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? '';
        if (empty($token) || empty($_SESSION['csrf_token'])) return false;
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}