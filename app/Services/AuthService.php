<?php
require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Security/Hash.php';
require_once __DIR__ . '/../Security/JWT.php';
require_once __DIR__ . '/../Helpers/Response.php';

class AuthService {
    public static function tenantSignup($data) {
        $db = getMasterDB();

        $subdomain = preg_replace('/[^a-zA-Z0-9-]/', '', strtolower($data['subdomain']));
        if (empty($subdomain)) {
            Response::error('Invalid subdomain format', 400);
        }

        // Check if subdomain exists in Master DB
        $stmt = $db->prepare("SELECT id FROM tenants WHERE subdomain = ?");
        $stmt->execute([$subdomain]);
        if ($stmt->fetch()) {
            Response::error('Subdomain already exists', 400);
        }

        // Check if email exists in Master DB (tenants owner email)
        $stmt = $db->prepare("SELECT id FROM tenants WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            Response::error('Email already registered for another tenant', 400);
        }

        // Generate a tenant_id (UUID-like or simple code based on subdomain/time)
        $tenantId = 'TNT' . strtoupper(substr(uniqid(), -8));

        // Generate tenant database name
        $dbName = 'tenant_' . $subdomain . '_db';

        // Hash owner password
        $hashedPassword = Hash::make($data['password']);

        // Handle initial theme settings
        $themeSettings = isset($data['theme_settings']) 
            ? (is_array($data['theme_settings']) ? json_encode($data['theme_settings']) : (string) $data['theme_settings'])
            : null;

        // Insert into Master DB tenants table
        $stmt = $db->prepare("
            INSERT INTO tenants (tenant_id, company_name, subdomain, email, password, plan, db_name, theme_settings, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([
            $tenantId,
            $data['company_name'],
            $subdomain,
            $data['email'],
            $hashedPassword,
            $data['plan'] ?? 'basic',
            $dbName,
            $themeSettings
        ]);

        return [
            'tenant_id' => $tenantId,
            'company_name' => $data['company_name'],
            'subdomain' => $subdomain,
            'db_name' => $dbName,
            'email' => $data['email'],
            'plan' => $data['plan'] ?? 'basic',
            'theme_settings' => $themeSettings ? json_decode($themeSettings, true) : null
        ];
    }

    public static function getTenantConfig($subdomain) {
        $db = getMasterDB();
        $stmt = $db->prepare("SELECT id, tenant_id, company_name, subdomain, plan, theme_settings, status FROM tenants WHERE subdomain = ? OR tenant_id = ?");
        $stmt->execute([$subdomain, $subdomain]);
        $tenant = $stmt->fetch();

        if (!$tenant) {
            Response::error('Tenant not found', 404);
        }

        $theme = null;
        if (!empty($tenant['theme_settings'])) {
            $theme = json_decode($tenant['theme_settings'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $theme = $tenant['theme_settings'];
            }
        }

        return [
            'tenant_id' => $tenant['tenant_id'],
            'company_name' => $tenant['company_name'],
            'subdomain' => $tenant['subdomain'],
            'plan' => $tenant['plan'],
            'status' => $tenant['status'],
            'theme_settings' => $theme
        ];
    }

    public static function updateTheme($subdomain, $themeSettings) {
        $db = getMasterDB();
        $stmt = $db->prepare("UPDATE tenants SET theme_settings = ? WHERE subdomain = ?");
        $stmt->execute([$themeSettings, $subdomain]);

        return ['subdomain' => $subdomain, 'theme_settings' => $themeSettings];
    }

    public static function register( $data) {
        $db = getDB();

        // Check email exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            Response::error('Email already exists', 400);
        }


        $stmt = $db->prepare("INSERT INTO users ( name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $data['name'],
            $data['email'],
            Hash::make($data['password']),
            $data['role']
        ]);

        return ['user_id' => $db->lastInsertId()];
    }

    public static function login($data){
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? ");
        $stmt->execute([$data['email']]);
        $user = $stmt->fetch();



        if (!$user || !Hash::verify($data['password'], $user['password'])) {
            Response::error('Invalid credentials', 401);
        }

            if ($user['status'] !== 'active') {
                Response::error('Your account is pending admin approval', 403);
            }

        $accessToken  = JWT::generateAccess([
            'user_id'   => $user['id'],
            'role'      => $user['role'],
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
            'user_id'      => $user['id'],
            'role'         => $user['role'],
            'access_token' => $accessToken,
        ];
    }

    public static function refresh() {
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


    public static function changePassword($auth, $data) {
        $db = getDB();

        // Get user by id from token
        $stmt = $db->prepare("SELECT id,email,password FROM users WHERE id = ? AND status = 'active'");
        $stmt->execute([$auth['user_id']]);
        $user = $stmt->fetch();

        if (!$user) Response::error('User not found', 404);

        // Email must match logged in user
        if ($user['email'] !== $data['email']) {
            Response::error('Email does not match logged in user', 403);
        }

        // Verify old password
        if (!Hash::verify($data['old_password'], $user['password'])) {
            Response::error('Old password is incorrect', 401);
        }

        // Update new password
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([Hash::make($data['new_password']), $user['id']]);
    }

    public static function logout( $userId){
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

        // Clear CSRF token from session
        // if (session_status() === PHP_SESSION_NONE) session_start();
        // unset($_SESSION['csrf_token']);
        // session_destroy();
    }
}