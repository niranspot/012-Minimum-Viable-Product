<?php
require_once __DIR__ . '/../Security/JWT.php';
require_once __DIR__ . '/../Helpers/Response.php';

class AuthMiddleware {
    public static function handle(){
        $headers = getallheaders();
        $header  = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (!$header || !preg_match('/Bearer\s(\S+)/', $header, $matches)) { 
            Response::error('No token provided', 401);
        }
        
        $token   = $matches[1];
        $payload = JWT::validate($token);

        if (empty($payload)) {
            Response::error('Invalid or expired token', 401);
        }

        return $payload;
    }

    public static function allowRoles($payload, $roles){
        if (!in_array($payload['role'], $roles)) {
            Response::error('Access denied', 403);
        }
    }
}