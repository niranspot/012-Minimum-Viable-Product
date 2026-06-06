<?php
require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Security/Hash.php';
require_once __DIR__ . '/../Security/JWT.php';
require_once __DIR__ . '/../Helpers/Response.php';

class AuthService {
    public static function register(array $data): array {
        $db = getDB();

        // Check email exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            Response::error('Email already exists', 400);
        }

        // Check tenant exists
        $stmt = $db->prepare("SELECT id FROM tenants WHERE id = ? AND status = 'active'");
        $stmt->execute([$data['tenant_id']]);
        if (!$stmt->fetch()) {
            Response::error('Invalid tenant', 400);
        }

        // Insert user
        $stmt = $db->prepare("INSERT INTO users (tenant_id, name, email, password, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['tenant_id'],
            $data['name'],
            $data['email'],
            Hash::make($data['password']),
            $data['role']
        ]);

        return ['user_id' => $db->lastInsertId()];
    }

    public static function login(array $data): array {
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$data['email']]);
        $user = $stmt->fetch();

        if (!$user || !Hash::verify($data['password'], $user['password'])) {
            Response::error('Invalid credentials', 401);
        }

        $accessToken  = JWT::generateAccess([
            'user_id'   => $user['id'],
            'role'      => $user['role'],
            'tenant_id' => $user['tenant_id']
        ]);
        $refreshToken = bin2hex(random_bytes(32));
        $expiresAt    = date('Y-m-d H:i:s', time() + JWT_REFRESH_EXPIRE);

        // Store refresh token in DB
        $stmt = $db->prepare("UPDATE users SET refresh_token = ?, refresh_token_expires_at = ? WHERE id = ?");
        $stmt->execute([$refreshToken, $expiresAt, $user['id']]);

        // Set refresh token in HttpOnly cookie
        setcookie('refresh_token', $refreshToken, [
            'expires'  => time() + JWT_REFRESH_EXPIRE,
            'httponly' => true,
            'path'     => '/',
            'samesite' => 'Strict',
        ]);

        return [
            'access_token' => $accessToken,
            'role'         => $user['role'],
            'user_id'      => $user['id'],
        ];
    }

    public static function refresh(): array {
        $db = getDB();

        // Get refresh token from cookie
        $refreshToken = $_COOKIE['refresh_token'] ?? '';
        if (empty($refreshToken)) Response::error('No refresh token', 401);

        $stmt = $db->prepare("SELECT * FROM users WHERE refresh_token = ? AND refresh_token_expires_at > NOW() AND status = 'active'");
        $stmt->execute([$refreshToken]);
        $user = $stmt->fetch();

        if (!$user) Response::error('Invalid or expired refresh token', 401);

        $newAccessToken  = JWT::generateAccess([
            'user_id'   => $user['id'],
            'role'      => $user['role'],
            'tenant_id' => $user['tenant_id'],
        ]);
        $newRefreshToken = bin2hex(random_bytes(32));
        $expiresAt       = date('Y-m-d H:i:s', time() + JWT_REFRESH_EXPIRE);

        $stmt = $db->prepare("UPDATE users SET refresh_token = ?, refresh_token_expires_at = ? WHERE id = ?");
        $stmt->execute([$newRefreshToken, $expiresAt, $user['id']]);

        // Rotate cookie
        setcookie('refresh_token', $newRefreshToken, [
            'expires'  => time() + JWT_REFRESH_EXPIRE,
            'httponly' => true,
            'path'     => '/',
            'samesite' => 'Strict',
        ]);

        return [
            'access_token' => $newAccessToken,
        ];
    }

    public static function logout(int $userId): void {
        $db   = getDB();
        $stmt = $db->prepare("UPDATE users SET refresh_token = NULL, refresh_token_expires_at = NULL WHERE id = ?");
        $stmt->execute([$userId]);

        // Clear cookie
        setcookie('refresh_token', '', [
            'expires'  => time() - 3600,
            'httponly' => true,
            'path'     => '/',
            'samesite' => 'Strict',
        ]);
    }
}