<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Database;

final class LoginFailureAlert
{
    public static function schedule(string $email, int $attemptId, ?string $municipality): void
    {
        $recipient = trim((string)getenv('LOGIN_ALERT_TO'));
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        register_shutdown_function(static function () use ($recipient, $email, $attemptId, $municipality): void {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            self::notify($recipient, $email, $attemptId, $municipality);
        });
    }

    private static function notify(string $recipient, string $email, int $attemptId, ?string $municipality): void
    {
        $context = [
            'categoria' => 'SEGURANCA',
            'severidade' => 'ALERTA',
            'email' => $email,
        ];

        try {
            $statement = Database::connection()->prepare(
                "SELECT id, sucesso, ip, user_agent, criado_em
                 FROM tentativas_login
                 WHERE email_normalizado = ?
                   AND criado_em >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                   AND (sucesso = 1 OR motivo = 'CREDENCIAIS_INVALIDAS')
                 ORDER BY criado_em DESC, id DESC
                 LIMIT 2"
            );
            $statement->execute([$email]);
            $attempts = $statement->fetchAll();
            if (count($attempts) < 2 || (int)$attempts[0]['id'] !== $attemptId
                || $attempts[0]['sucesso'] || $attempts[1]['sucesso']) {
                return;
            }

            if (!AuthRateLimit::allow('alerta_login_email', $email, 1, 900)
                || !AuthRateLimit::allow('alerta_login_destinatario', strtolower($recipient), 6, 900)) {
                return;
            }

            $context['tentativa_ids'] = array_map('intval', array_column($attempts, 'id'));
            $text = self::message($email, $municipality, array_reverse($attempts));
            $html = '<div style="font-family:sans-serif;white-space:pre-wrap">'
                . nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false)
                . '</div>';
            (new MailService())->send($recipient, 'Tentativa de login falho | PGE Stratelli', $html, $text);
            Audit::logActor(null, 'LOGIN_ALERTA_EMAIL_ENVIADO', 'Aviso de falhas consecutivas enviado à Stratelli.', null, $context);
        } catch (\Throwable $exception) {
            Audit::logActor(
                null,
                'LOGIN_ALERTA_EMAIL_FALHOU',
                'Falha ao processar aviso de tentativas de login: ' . $exception->getMessage(),
                null,
                $context + ['sucesso' => false]
            );
        }
    }

    private static function message(string $email, ?string $municipality, array $attempts): string
    {
        $environment = (string)(getenv('APP_ENV') ?: 'production');
        $hostname = (string)(parse_url((string)getenv('APP_URL'), PHP_URL_HOST) ?: 'não informado');
        $lines = [
            'Foram registradas duas falhas consecutivas de autenticação em até cinco minutos.',
            '',
            'E-mail informado: ' . $email,
            'Ambiente: ' . $environment . ' (' . $hostname . ')',
            'Município: ' . ($municipality ?: 'não identificado'),
            'Rota: POST /login',
            'Horário das tentativas: Brasília',
            '',
        ];

        foreach ($attempts as $attempt) {
            $agent = preg_replace('/[\x00-\x1F\x7F]+/', ' ', (string)$attempt['user_agent']);
            $lines[] = 'Tentativa #' . $attempt['id'] . ' em ' . $attempt['criado_em'];
            $lines[] = 'IP: ' . ($attempt['ip'] ?: 'não informado');
            $lines[] = 'Navegador/dispositivo informado: ' . ($agent ?: 'não informado');
            $lines[] = '';
        }

        $lines[] = 'Confira os eventos LOGIN_FALHA em Auditoria e Segurança da PGE.';
        return implode("\n", $lines);
    }
}
