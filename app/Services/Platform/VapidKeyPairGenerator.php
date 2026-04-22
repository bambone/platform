<?php

declare(strict_types=1);

namespace App\Services\Platform;

use RuntimeException;

/**
 * OpenSSL-only VAPID key pair for Web Push (P-256 / prime256v1), base64url as used by browser Push API.
 *
 * Class is not final so tests can mock {@see self::generate()} without depending on host OpenSSL.
 */
class VapidKeyPairGenerator
{
    /**
     * @return array{public: string, private: string}
     */
    public function generate(): array
    {
        $options = [
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ];

        $config = $this->resolveOpenSslConfigPath();
        if ($config !== null) {
            $options['config'] = $config;
        }

        $key = openssl_pkey_new($options);
        if ($key === false) {
            $errors = '';
            while (($msg = openssl_error_string()) !== false) {
                $errors .= $msg.' ';
            }

            throw new RuntimeException('OpenSSL could not generate an EC key for VAPID. '.trim($errors));
        }

        $details = openssl_pkey_get_details($key);
        if ($details === false || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_EC) {
            throw new RuntimeException('Unexpected OpenSSL key details for VAPID.');
        }

        /** @var array{x: string, y: string, d: string} $ec */
        $ec = $details['ec'];
        $x = str_pad($ec['x'], 32, "\x00", STR_PAD_LEFT);
        $y = str_pad($ec['y'], 32, "\x00", STR_PAD_LEFT);
        $d = str_pad($ec['d'], 32, "\x00", STR_PAD_LEFT);
        $uncompressed = "\x04".$x.$y;

        return [
            'public' => $this->base64UrlEncode($uncompressed),
            'private' => $this->base64UrlEncode($d),
        ];
    }

    private function base64UrlEncode(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    private function resolveOpenSslConfigPath(): ?string
    {
        $env = getenv('OPENSSL_CONF');
        if (is_string($env) && $env !== '' && is_readable($env)) {
            return $env;
        }

        $phpDir = dirname(PHP_BINARY);
        foreach ([
            $phpDir.DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf',
            $phpDir.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf',
        ] as $candidate) {
            if (is_readable($candidate)) {
                return $candidate;
            }
        }

        foreach (['/etc/ssl/openssl.cnf', '/usr/lib/ssl/openssl.cnf'] as $unix) {
            if (is_readable($unix)) {
                return $unix;
            }
        }

        $locations = openssl_get_cert_locations();
        if (is_array($locations)) {
            foreach (['default_default_cert_area', 'default_cert_dir'] as $k) {
                if (empty($locations[$k])) {
                    continue;
                }
                $base = rtrim((string) $locations[$k], '/\\');
                $try = $base.DIRECTORY_SEPARATOR.'openssl.cnf';
                if (is_readable($try)) {
                    return $try;
                }
            }
        }

        return null;
    }
}
