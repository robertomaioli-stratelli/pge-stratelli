<?php
namespace App\Services;

use RuntimeException;

final class MailService
{
    public function send(string $to, string $subject, string $html, ?string $text = null): void
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Destinatário de e-mail inválido.');
        }
        if (!filter_var(getenv('MAIL_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException('Serviço de e-mail não está habilitado.');
        }

        $transport = strtolower(trim((string)(getenv('MAIL_TRANSPORT') ?: 'smtp')));
        if ($transport !== 'smtp') {
            throw new RuntimeException('Transporte de e-mail não suportado. Configure MAIL_TRANSPORT=smtp.');
        }

        $host = trim((string)(getenv('MAIL_HOST') ?: ''));
        $port = (int)(getenv('MAIL_PORT') ?: 587);
        $encryption = strtolower(trim((string)(getenv('MAIL_ENCRYPTION') ?: 'tls')));
        $username = trim((string)(getenv('MAIL_USERNAME') ?: ''));
        $password = (string)(getenv('MAIL_PASSWORD') ?: '');
        $from = trim((string)(getenv('MAIL_FROM') ?: $username));
        $fromName = trim((string)(getenv('MAIL_FROM_NAME') ?: 'INPACTA by Stratelli'));
        $timeout = max(5, (int)(getenv('MAIL_TIMEOUT') ?: 20));
        $verifyPeer = filter_var(getenv('MAIL_VERIFY_PEER') ?: 'true', FILTER_VALIDATE_BOOLEAN);

        if ($host === '' || $port < 1 || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Configuração SMTP incompleta. Verifique MAIL_HOST, MAIL_PORT e MAIL_FROM.');
        }
        if (!in_array($encryption, ['tls', 'ssl', 'none', ''], true)) {
            throw new RuntimeException('MAIL_ENCRYPTION deve ser tls, ssl ou none.');
        }

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => $verifyPeer,
                'verify_peer_name' => $verifyPeer,
                'allow_self_signed' => !$verifyPeer,
                'SNI_enabled' => true,
                'peer_name' => $host,
            ],
        ]);
        $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $errno = 0; $errstr = '';
        $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            throw new RuntimeException('Não foi possível conectar ao servidor SMTP.');
        }
        stream_set_timeout($socket, $timeout);

        try {
            $this->expect($socket, [220]);
            $ehlo = preg_replace('/[^a-zA-Z0-9.\-]/', '', (string)(gethostname() ?: 'inpacta')) ?: 'inpacta';
            $this->command($socket, 'EHLO ' . $ehlo, [250]);

            if ($encryption === 'tls') {
                $this->command($socket, 'STARTTLS', [220]);
                if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Não foi possível estabelecer a conexão TLS com o SMTP.');
                }
                $this->command($socket, 'EHLO ' . $ehlo, [250]);
            }

            if ($username !== '') {
                $this->command($socket, 'AUTH LOGIN', [334]);
                $this->command($socket, base64_encode($username), [334]);
                $this->command($socket, base64_encode($password), [235]);
            }

            $this->command($socket, 'MAIL FROM:<' . $from . '>', [250]);
            $this->command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            $this->command($socket, 'DATA', [354]);

            $boundary = 'b_' . bin2hex(random_bytes(12));
            $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
            $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
            $text = $text ?: trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $messageIdHost = preg_replace('/[^a-zA-Z0-9.\-]/', '', (string)(parse_url(getenv('APP_URL') ?: '', PHP_URL_HOST) ?: $host)) ?: $host;
            $headers = [
                'Date: ' . date(DATE_RFC2822),
                'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $messageIdHost . '>',
                'From: ' . $encodedFromName . ' <' . $from . '>',
                'To: <' . $to . '>',
                'Subject: ' . $encodedSubject,
                'MIME-Version: 1.0',
                'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            ];
            $body = implode("\r\n", $headers) . "\r\n\r\n" .
                '--' . $boundary . "\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n" . $text . "\r\n\r\n" .
                '--' . $boundary . "\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n" . $html . "\r\n\r\n" .
                '--' . $boundary . "--\r\n";
            $body = preg_replace('/(?m)^\./', '..', $body);
            fwrite($socket, $body . ".\r\n");
            $this->expect($socket, [250]);
            $this->command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    public function sendPasswordReset(string $to, string $name, string $url, int $minutes): void
    {
        $safeName = htmlspecialchars($name !== '' ? $name : 'usuário', ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $brand = 'INPACTA by Stratelli';
        $subject = 'Recuperação de senha | ' . $brand;
        $html = '<!doctype html><html><body style="margin:0;background:#eef3f8;font-family:Arial,Helvetica,sans-serif;color:#082a55">'
            . '<div style="max-width:640px;margin:32px auto;background:#fff;border:1px solid #dce6f1;border-radius:18px;overflow:hidden">'
            . '<div style="background:#0b3d6d;padding:28px 32px;color:#fff"><div style="font-size:30px;font-weight:800">INPACTA <span style="font-size:16px;font-weight:600;opacity:.9">by Stratelli</span></div><div style="margin-top:6px;font-size:13px;opacity:.9">PGE - Plataforma de Governança Executiva</div></div>'
            . '<div style="padding:32px"><h2 style="margin:0 0 16px;font-size:24px">Recuperação de senha</h2>'
            . '<p style="font-size:15px;line-height:1.6">Olá, <strong>' . $safeName . '</strong>.</p>'
            . '<p style="font-size:15px;line-height:1.6">Recebemos uma solicitação para redefinir a senha da sua conta. O link abaixo é individual, de uso único e válido por <strong>' . $minutes . ' minutos</strong>.</p>'
            . '<p style="margin:26px 0"><a href="' . $safeUrl . '" style="display:inline-block;background:#0d4b7f;color:#fff;text-decoration:none;font-weight:700;padding:14px 22px;border-radius:10px">Redefinir minha senha</a></p>'
            . '<p style="font-size:12px;line-height:1.6;color:#64748b">Se o botão não funcionar, copie este endereço no navegador:<br><span style="word-break:break-all">' . $safeUrl . '</span></p>'
            . '<div style="margin-top:24px;padding:14px 16px;background:#f6f8fb;border-radius:10px;font-size:12px;line-height:1.6;color:#5b6f84"><strong>Segurança:</strong> se você não solicitou esta recuperação, ignore esta mensagem. Sua senha atual continuará válida.</div>'
            . '</div><div style="padding:18px 32px;background:#f8fafc;border-top:1px solid #e5ebf2;font-size:11px;color:#718096">Mensagem automática do ' . $brand . '. Não responda a este e-mail.</div></div></body></html>';
        $text = "Olá, {$name}.\n\nRecebemos uma solicitação para redefinir a senha da sua conta INPACTA by Stratelli.\n\nUse o link abaixo em até {$minutes} minutos:\n{$url}\n\nSe você não solicitou esta recuperação, ignore esta mensagem.";
        $this->send($to, $subject, $html, $text);
    }

    private function command($socket, string $command, array $expected): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->expect($socket, $expected);
    }

    private function expect($socket, array $expected): string
    {
        $response = '';
        while (($line = fgets($socket, 8192)) !== false) {
            $response .= $line;
            if (preg_match('/^(\d{3})\s/', $line, $m)) {
                $code = (int)$m[1];
                if (!in_array($code, $expected, true)) {
                    throw new RuntimeException('Servidor SMTP recusou a operação (código ' . $code . ').');
                }
                return $response;
            }
        }
        throw new RuntimeException('Resposta incompleta do servidor SMTP.');
    }
}
