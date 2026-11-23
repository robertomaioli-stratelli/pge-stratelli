<?php
// Gerador de token SSO de TESTE (doc "SSO Stratelli -> PGE", seção 9.3).
// Uso: php bin/sso_token_teste.php usuario@dominio [ttl_segundos]
// Requer SSO_STRATELLI_SECRET no .env; imprime o token e a URL pronta para abrir no navegador.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);
require $root . '/app/Core/Env.php';
App\Core\Env::load($root . '/.env');
spl_autoload_register(function (string $class) use ($root): void {
    if (!str_starts_with($class, 'App\\')) return;
    $file = $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) require $file;
});

$email = (string)($argv[1] ?? '');
if ($email === '') { fwrite(STDERR, "Uso: php bin/sso_token_teste.php usuario@dominio [ttl_segundos]\n"); exit(1); }
$ttl = max(10, (int)($argv[2] ?? 60));

try {
    $token = (new App\Services\SsoTokenService())->generate($email, $ttl);
} catch (\Throwable $e) {
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . "\n");
    exit(1);
}
$base = rtrim((string)(getenv('APP_URL') ?: ''), '/');
echo "Token (valido por {$ttl}s, uso unico):\n{$token}\n\nURL:\n" . ($base !== '' ? $base : 'https://<pge>') . '/sso?token=' . rawurlencode($token) . "\n";
