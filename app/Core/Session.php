<?php
namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        $cfg = require dirname(__DIR__, 2) . '/config/app.php';
        // Evita adoção de IDs de sessão fornecidos pelo cliente e força sessão via cookie.
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        session_name($cfg['session_name']);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $cfg['session_secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function regenerate(): void { session_regenerate_id(true); }
    public static function get(string $key, mixed $default = null): mixed { return $_SESSION[$key] ?? $default; }
    public static function put(string $key, mixed $value): void { $_SESSION[$key] = $value; }
    public static function forget(string $key): void { unset($_SESSION[$key]); }
    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
    public static function flash(string $key, mixed $value): void { $_SESSION['_flash'][$key] = $value; }
    public static function pullFlash(string $key, mixed $default = null): mixed
    {
        $v = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $v;
    }
}
