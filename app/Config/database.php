<?php
require_once __DIR__ . '/config.php';

function getSubdomain() {
    // 1. Check HTTP Header (case-insensitive check)
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $key => $val) {
            if (strcasecmp($key, 'X-Tenant') === 0) {
                return trim($val);
            }
        }
    } else {
        if (!empty($_SERVER['HTTP_X_TENANT'])) {
            return trim($_SERVER['HTTP_X_TENANT']);
        }
    }

    // 2. Check Query Parameter
    if (!empty($_GET['tenant'])) {
        return trim($_GET['tenant']);
    }

    // 3. Check Subdomain from Host
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $parts = explode('.', $host);
    if (count($parts) > 1) {
        $sub = $parts[0];
        // Ignore localhost, common subdomains
        if (!in_array(strtolower($sub), ['www', 'api', 'localhost'])) {
            return $sub;
        }
    }
    return null;
}

function getMasterDB() {
    static $masterPdo = null;
    if ($masterPdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $masterPdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['status' => 'error', 'message' => 'Master DB connection failed']));
        }
    }
    return $masterPdo;
}

function getDB() {
    static $tenantPdo = null;
    if ($tenantPdo === null) {
        $subdomain = getSubdomain();
        if (!$subdomain) {
            $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            $basePath   = '/012-Minimum-Viable-Product/public';
            $requestUri = str_replace($basePath, '', $requestUri);
            $requestUri = '/' . trim($requestUri, '/');

            // Allow master-level operations
            if ($requestUri === '/tenant/signup' || $requestUri === '/csrf-token') {
                return getMasterDB();
            }

            http_response_code(400);
            die(json_encode([
                'status' => 'error', 
                'message' => 'Tenant context required. Use subdomain (e.g. acme.localhost), X-Tenant header, or ?tenant=acme query parameter.'
            ]));
        }

        $masterDb = getMasterDB();
        $stmt = $masterDb->prepare("SELECT * FROM tenants WHERE subdomain = ? OR tenant_id = ?");
        $stmt->execute([$subdomain, $subdomain]);
        $tenant = $stmt->fetch();

        if (!$tenant) {
            http_response_code(404);
            die(json_encode(['status' => 'error', 'message' => "Tenant '$subdomain' not found"]));
        }

        if ($tenant['status'] !== 'active') {
            http_response_code(403);
            die(json_encode(['status' => 'error', 'message' => 'Tenant is inactive or suspended']));
        }

        $dbName = $tenant['db_name'];
        if (empty($dbName)) {
            $dbName = 'tenant_' . preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($subdomain)) . '_db';
        }

        try {
            // Attempt to connect to the tenant database
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . $dbName . ";charset=utf8mb4";
            $tenantPdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Connection failed because the database probably doesn't exist. Let's create it.
            try {
                // Connect to MySQL server without a dbname to create it safely
                $dsnNoDb = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
                $tempPdo = new PDO($dsnNoDb, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $tempPdo = null; // Close temp connection

                // Update Master DB with the db_name
                $up = $masterDb->prepare("UPDATE tenants SET db_name = ? WHERE id = ?");
                $up->execute([$dbName, $tenant['id']]);

                // Now connect to the new tenant DB
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . $dbName . ";charset=utf8mb4";
                $tenantPdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);

                // Run migrations and seed owner user
                runTenantMigrations($tenantPdo, $tenant);

            } catch (PDOException $ex) {
                http_response_code(500);
                die(json_encode(['status' => 'error', 'message' => 'Failed to provision tenant database: ' . $ex->getMessage()]));
            }
        }
    }
    return $tenantPdo;
}

function runTenantMigrations($pdo, $tenant) {
    $schemaPath = dirname(__DIR__, 2) . '/tenant_schema.sql';
    if (!file_exists($schemaPath)) {
        throw new Exception('tenant_schema.sql file not found');
    }
    $sql = file_get_contents($schemaPath);
    
    // Split statements by semicolon and run them one by one
    $queries = explode(';', $sql);
    foreach ($queries as $query) {
        $trimmed = trim($query);
        if ($trimmed !== '') {
            $pdo->exec($trimmed);
        }
    }

    // Seed the owner/admin user in the tenant DB
    $stmt = $pdo->prepare("INSERT INTO users (tenant_id, name, email, password, role, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        (int) $tenant['id'],
        $tenant['company_name'],
        $tenant['email'],
        $tenant['password'], // Already hashed
        'admin',
        'active'
    ]);
}