<?php
require_once __DIR__ . '/../Config/config.php';

class AES {
    private static $cipher = 'AES-256-CBC';

    public static function encrypt($plainText) {
        // Generate random IV every time
        $iv        = random_bytes(openssl_cipher_iv_length(self::$cipher));
        $encrypted = openssl_encrypt($plainText, self::$cipher, AES_KEY, 0, $iv);

        // Combine IV + encrypted, then base64 encode
        return base64_encode($iv . $encrypted);
    }

    public static function decrypt($encryptedData) {
        $data     = base64_decode($encryptedData);
        $ivLength = openssl_cipher_iv_length(self::$cipher);

        // Extract IV from first 16 bytes
        $iv         = substr($data, 0, $ivLength);
        $cipherText = substr($data, $ivLength);

        return openssl_decrypt($cipherText, self::$cipher, AES_KEY, 0, $iv);
    }
}