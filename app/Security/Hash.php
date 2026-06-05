<?php
class Hash {
    public static function make(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    public static function verify(string $password, string $hashed): bool {
        return password_verify($password, $hashed);
    }
}