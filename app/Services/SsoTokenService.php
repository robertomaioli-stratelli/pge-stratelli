<?php
namespace App\Services;

use App\Core\Database;

/**
 * SSO Stratelli -> PGE por token assinado (HMAC-SHA256), de vida curta e uso único.
 * Formato: base64url(payload JSON) . "." . base64url(hmac)
 * Payload: {"iss":"stratelli","aud":"pge","e":"<email>","exp":<unix>,"jti":"<nonce>"}
 */
final class SsoTokenService
{
    private const ISSUER = 'stratelli';
    private const AUDIENCE = 'pge';
    private const MAX_TTL_SECONDS = 300;

    private function secret(): string
    {
        $s = (string)(getenv('SSO_STRATELLI_SECRET') ?: '');
        // Segredo curto demais não oferece segurança real; melhor tratar como desativado.
        if (strlen($s) < 32) throw new \RuntimeException('SSO não configurado nesta instalação.');
        return $s;
    }

    /** Gera um token para o e-mail informado (usado pela origem e pelo gerador local de testes). */
    public function generate(string $email, int $ttlSeconds = 60): string
    {
        $payload = [
            'iss' => self::ISSUER,
            'aud' => self::AUDIENCE,
            'e' => strtolower(trim($email)),
            'exp' => time() + max(10, min(self::MAX_TTL_SECONDS, $ttlSeconds)),
            'jti' => bin2hex(random_bytes(16)),
        ];
        $b64 = self::b64(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
        return $b64 . '.' . self::b64(hash_hmac('sha256', $b64, $this->secret(), true));
    }

    /** Valida o token e o consome (uso único). Retorna o e-mail autenticado; lança exceção se inválido. */
    public function consume(string $token): string
    {
        [$jti, $email] = $this->decode($token);
        $this->markUsed($jti, $email);
        return $email;
    }

    /** Valida o token SEM consumi-lo (pré-checagem de conta pela origem). Retorna o e-mail. */
    public function peek(string $token): string
    {
        return $this->decode($token)[1];
    }

    /** @return array{0:string,1:string} [jti, email] */
    private function decode(string $token): array
    {
        $parts = explode('.', trim($token));
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') throw new \RuntimeException('Token malformado.');
        [$b64, $sig] = $parts;
        $expected = self::b64(hash_hmac('sha256', $b64, $this->secret(), true));
        if (!hash_equals($expected, $sig)) throw new \RuntimeException('Assinatura inválida.');
        $payload = json_decode(self::unb64($b64) ?: '', true);
        if (!is_array($payload)) throw new \RuntimeException('Payload inválido.');
        if (($payload['iss'] ?? '') !== self::ISSUER || ($payload['aud'] ?? '') !== self::AUDIENCE) {
            throw new \RuntimeException('Token emitido para outro destino.');
        }
        if ((int)($payload['exp'] ?? 0) < time()) throw new \RuntimeException('Token expirado.');
        $jti = (string)($payload['jti'] ?? '');
        $email = strtolower(trim((string)($payload['e'] ?? '')));
        if ($jti === '' || strlen($jti) > 64 || $email === '') throw new \RuntimeException('Token incompleto.');
        return [$jti, $email];
    }

    private function markUsed(string $jti, string $email): void
    {
        $pdo = Database::connection();
        $pdo->exec('CREATE TABLE IF NOT EXISTS sso_token_consumido (jti VARCHAR(64) NOT NULL PRIMARY KEY, email VARCHAR(255) NOT NULL, consumido_em DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        try {
            $pdo->prepare('INSERT INTO sso_token_consumido (jti, email, consumido_em) VALUES (?,?,NOW())')->execute([$jti, $email]);
        } catch (\PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1062) throw new \RuntimeException('Token já utilizado.');
            throw $e;
        }
        // Higiene: tokens expiram em minutos, registros de mais de 1 dia já não protegem nada.
        try { $pdo->exec("DELETE FROM sso_token_consumido WHERE consumido_em < DATE_SUB(NOW(), INTERVAL 1 DAY)"); } catch (\Throwable) {}
    }

    private static function b64(string $raw): string { return rtrim(strtr(base64_encode($raw), '+/', '-_'), '='); }
    private static function unb64(string $b64): string|false { return base64_decode(strtr($b64, '-_', '+/')); }
}
