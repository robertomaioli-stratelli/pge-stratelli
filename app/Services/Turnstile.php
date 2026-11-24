<?php
declare(strict_types=1);

namespace App\Services;

final class Turnstile
{
    public static function enabled(): bool
    {
        return filter_var(getenv('TURNSTILE_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== false;
    }

    public static function siteKey(): string
    {
        return trim((string)getenv('TURNSTILE_SITE_KEY'));
    }

    public static function verify(mixed $token, string $action): string
    {
        $secret = trim((string)getenv('TURNSTILE_SECRET_KEY'));
        $hostname = strtolower((string)parse_url((string)getenv('APP_URL'), PHP_URL_HOST));
        if ($secret === '' || self::siteKey() === '' || $hostname === '' || !function_exists('curl_init')) {
            return 'indisponivel';
        }
        if (!is_string($token) || $token === '' || strlen($token) > 2048) {
            return 'invalido';
        }

        $curl = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        if ($curl === false) {
            return 'indisponivel';
        }

        try {
            $options = [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query(['secret' => $secret, 'response' => $token]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => 5,
            ];
            if (!curl_setopt_array($curl, $options)) {
                return 'indisponivel';
            }
            $body = curl_exec($curl);
            if (!is_string($body) || curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== 200) {
                return 'indisponivel';
            }
        } finally {
            unset($curl);
        }

        $result = json_decode($body, true);
        if (!is_array($result) || !is_bool($result['success'] ?? null)) {
            return 'indisponivel';
        }
        if (array_intersect(['internal-error', 'invalid-input-secret', 'missing-input-secret'], (array)($result['error-codes'] ?? []))) {
            return 'indisponivel';
        }
        if (!$result['success'] || ($result['action'] ?? '') !== $action
            || strtolower((string)($result['hostname'] ?? '')) !== $hostname) {
            return 'invalido';
        }

        return 'valido';
    }
}
