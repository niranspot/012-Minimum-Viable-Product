<?php
require_once __DIR__ . '/../Config/config.php';

class JWT {
    public static function generateAccess($payload) {
        return self::generate($payload);
    }

    private static function generate($payload) {
        $header  = self::base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload['iat'] = time();
        $payload['exp'] = time() + JWT_ACCESS_EXPIRE;
        $body    = self::base64url(json_encode($payload));
        $sig     = self::base64url(hash_hmac('sha256', "$header.$body", JWT_SECRET, true));
        return "$header.$body.$sig";
    }

    public static function validate($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return [];

        [$header, $body, $sig] = $parts;
        $expected = self::base64url(hash_hmac('sha256', "$header.$body", JWT_SECRET, true));

        if (!hash_equals($expected, $sig)) return [];

        $payload = json_decode(base64_decode(strtr($body, '-_', '+/')), true);
        if (!$payload || $payload['exp'] < time()) return [];

        return $payload;
    }

    private static function base64url($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}