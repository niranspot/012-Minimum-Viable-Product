<?php
require_once __DIR__ . '/app/Config/config.php';
require_once __DIR__ . '/app/Security/AES.php';

// ✅ Encrypt - use this to generate Postman body
$data ="admin@test.com";
$encrypted = AES::encrypt($data);
echo "=== ENCRYPT ===\n";
echo "Postman Body:\n";
echo json_encode(['payload' => $encrypted], JSON_PRETTY_PRINT);

echo "\n\n";

// ✅ Decrypt - paste encrypted payload here to verify
$decrypted = AES::decrypt($encrypted);
echo "=== DECRYPT ===\n";
print_r($decrypted);
echo "</br>";

// echo bin2hex(random_bytes(16)); 