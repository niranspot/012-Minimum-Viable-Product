<?php

// Load .env file
function loadEnv($path) {
    if (!file_exists($path)) {
        die('.env file not found');
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (str_starts_with(trim($line), '#')) continue;

        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

// Load .env from root
loadEnv(dirname(__DIR__, 2) . '/.env');

// App
define('APP_NAME',  getenv('APP_NAME'));
define('APP_ENV',   getenv('APP_ENV'));
define('APP_URL',   getenv('APP_URL'));
define('APP_BASE_PATH',  getenv('APP_BASE_PATH'));

// Database
define('DB_HOST',   getenv('DB_HOST'));
define('DB_NAME',   getenv('DB_NAME'));
define('DB_USER',   getenv('DB_USER'));
define('DB_PASS',   getenv('DB_PASS'));

// AES
if (empty($_ENV['AES_KEY'])) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "AES_KEY not configured."]);
    exit;
}
define('AES_KEY', getenv('AES_KEY'));

// JWT
if (empty($_ENV['JWT_SECRET'])) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "JWT_SECRET not configured."]);
    exit;
}
define('JWT_SECRET', getenv('JWT_SECRET'));

define('JWT_ACCESS_EXPIRE',   getenv('JWT_ACCESS_EXPIRE'));
define('JWT_REFRESH_EXPIRE',   getenv('JWT_REFRESH_EXPIRE'));