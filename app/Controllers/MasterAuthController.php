<?php
require_once __DIR__ . '/../Helpers/Response.php';
require_once __DIR__ . '/../Helpers/Validator.php';
require_once __DIR__ . '/../Security/Hash.php';

class MasterAuthController {

    public static function login() {
        $payload = json_decode(file_get_contents('php://input'), true);

        $v = new Validator($payload);
        $v->required('email')->email('email')
          ->required('password');

        if ($v->fails()) {
            Response::error(implode(', ', $v->errors()), 400);
        }

        $db   = getMasterDB();
        $stmt = $db->prepare("SELECT * FROM tenants WHERE email = ?");
        $stmt->execute([$payload['email']]);
        $tenant = $stmt->fetch();

        if (!$tenant || !Hash::verify($payload['password'], $tenant['password'])) {
            Response::error('Invalid credentials', 401);
        }

        if ($tenant['status'] !== 'active') {
            Response::error('Your tenant account is suspended', 403);
        }

        $theme = null;
        if (!empty($tenant['theme_settings'])) {
            $theme = $tenant['theme_settings'];
        }

        Response::success('Login successful', [
            'tenant_id'    => $tenant['tenant_id'],
            'company_name' => $tenant['company_name'],
            'subdomain'    => $tenant['subdomain'],
            'email'        => $tenant['email'],
            'plan'         => $tenant['plan'],
            'status'       => $tenant['status'],
            'theme_settings' => $theme,
        ]);
    }
}