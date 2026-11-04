<?php
declare(strict_types=1);

use App\Core\Env;
use App\Core\Response;
use App\Core\Session;

$root = dirname(__DIR__);
require $root . '/app/Core/Env.php';
Env::load($root . '/.env');

// Impede acesso por host alternativo (ex.: localhost/caminho-fisico/public).
// A URL canônica vem de APP_URL e pode ser desativada com APP_ENFORCE_HOST=false.
$enforceHost = filter_var(getenv('APP_ENFORCE_HOST') ?: 'true', FILTER_VALIDATE_BOOLEAN);
$appUrl = rtrim((string)(getenv('APP_URL') ?: ''), '/');
if ($enforceHost && $appUrl !== '') {
    $expectedHost = strtolower((string)(parse_url($appUrl, PHP_URL_HOST) ?: ''));
    $requestHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $requestHost = preg_replace('/:\d+$/', '', $requestHost);
    if ($expectedHost !== '' && $requestHost !== $expectedHost) {
        header('Location: ' . $appUrl . '/login', true, 302);
        exit;
    }
}

spl_autoload_register(function(string $class) use ($root): void {
    $prefix='App\\';
    if(!str_starts_with($class,$prefix)) return;
    $relative=str_replace('\\','/',substr($class,strlen($prefix)));
    $file=$root.'/app/'.$relative.'.php';
    if(is_file($file)) require $file;
});

date_default_timezone_set('America/Sao_Paulo');
header_remove('X-Powered-By');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
Session::start();

try {
    $router = require $root . '/routes.php';
    $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
} catch (Throwable $e) {
    $debug=filter_var(getenv('APP_DEBUG')?:'false',FILTER_VALIDATE_BOOLEAN);
    Response::abort(500,$debug?$e->getMessage():'Ocorreu um erro interno.');
}
