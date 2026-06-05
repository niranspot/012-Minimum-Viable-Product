<?php
require_once __DIR__ . '/../Security/AES.php';

class Response {
    public static function send(int $httpCode, string $status, string $message, array $data = []): void {
        http_response_code($httpCode);
        header('Content-Type: application/json');

        // $payload = [
        //     'status'  => $status,
        //     'message' => $message,
        //     'data'    => $data,
        // ];

        echo json_encode([
            // 'payload' => AES::encrypt($payload)

            'status'  => $status,
            'message' => $message,
            'data'    => $data,
        ]);
        exit;
    }

    public static function success(string $message, array $data = [], int $code = 200): void {
        self::send($code, 'success', $message, $data);
    }

    public static function error(string $message, int $code = 400): void {
        self::send($code, 'error', $message);
    }
}