<?php
require_once __DIR__ . '/../Security/AES.php';

class Response {
    public static function send($httpCode, $status, $message,  $data = []) {
        http_response_code($httpCode);
        header('Content-Type: application/json');

        echo json_encode([
            'status'  => $status,
            'message' => $message,
            'data'    => $data,
        ]);
        exit;
    }

    public static function success( $message, $data = [],  $code = 200){
        self::send($code, 'success', $message, $data);
    }

    public static function error( $message, $code = 400){
        self::send($code, 'error', $message);
    }
}