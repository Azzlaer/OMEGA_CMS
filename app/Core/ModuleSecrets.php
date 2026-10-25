<?php
declare(strict_types=1);
namespace Omega\Core;

final class ModuleSecrets
{
    public static function encrypt(string $plain): string
    {
        if ($plain === '') return '';
        $appKey = (string)Env::get('APP_KEY', '');
        if ($appKey === '' || !extension_loaded('openssl')) {
            // Do not silently store a secret unencrypted.
            throw new \RuntimeException('APP_KEY/OpenSSL no disponible para proteger secretos de módulos.');
        }
        $key = hash('sha256', $appKey, true);
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($cipher === false) throw new \RuntimeException('No fue posible cifrar el secreto del módulo.');
        return 'enc:v1:' . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $stored): string
    {
        if ($stored === '' || !str_starts_with($stored, 'enc:v1:')) return $stored;
        $raw = base64_decode(substr($stored, 7), true);
        if ($raw === false || strlen($raw) < 29) return '';
        $appKey = (string)Env::get('APP_KEY', '');
        if ($appKey === '' || !extension_loaded('openssl')) return '';
        $key = hash('sha256', $appKey, true);
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '');
        return $plain === false ? '' : $plain;
    }

    public static function decodeConfig(array $config, array $schema): array
    {
        $types = [];
        foreach ($schema as $field) {
            $key = (string)($field['key'] ?? '');
            if ($key !== '') $types[$key] = (string)($field['type'] ?? 'text');
        }
        foreach ($config as $key => $value) {
            if (($types[$key] ?? '') === 'secret' && is_string($value)) {
                $config[$key] = self::decrypt($value);
            }
        }
        return $config;
    }
}
