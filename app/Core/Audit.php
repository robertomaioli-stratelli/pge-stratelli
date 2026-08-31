<?php
namespace App\Core;

final class Audit
{
    public static function log(string $evento, string $detalhes = '', ?int $municipioId = null, array $contexto = []): void
    {
        $user = null;
        try { $user = Auth::user(); } catch (\Throwable) {}
        self::write($user ? (int)$user['id'] : null, $evento, $detalhes, $municipioId ?? ($user['municipio_id'] ?? null), $contexto, $user);
    }

    public static function logActor(?int $usuarioId, string $evento, string $detalhes = '', ?int $municipioId = null, array $contexto = []): void
    {
        self::write($usuarioId, $evento, $detalhes, $municipioId, $contexto, null);
    }

    private static function write(?int $usuarioId, string $evento, string $detalhes, ?int $municipioId, array $contexto, ?array $knownUser): void
    {
        try {
            $pdo = Database::connection();
            $user = $knownUser;
            if (!$user && $usuarioId) {
                $st = $pdo->prepare('SELECT id,nome,email,municipio_id FROM usuarios WHERE id=? LIMIT 1');
                $st->execute([$usuarioId]);
                $user = $st->fetch() ?: null;
            }
            $categoria = strtoupper((string)($contexto['categoria'] ?? self::categoryFor($evento)));
            $severidade = strtoupper((string)($contexto['severidade'] ?? self::severityFor($evento)));
            $sucesso = array_key_exists('sucesso',$contexto) ? (int)(bool)$contexto['sucesso'] : 1;
            unset($contexto['categoria'],$contexto['severidade'],$contexto['sucesso']);
            $sessionId = session_status() === PHP_SESSION_ACTIVE ? session_id() : '';
            $stmt = $pdo->prepare('INSERT INTO auditoria
                (municipio_id,usuario_id,usuario_nome,usuario_email,evento,categoria,severidade,sucesso,detalhes,ip,user_agent,metodo,rota,referer,session_hash,contexto_json,criado_em)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
            $stmt->execute([
                $municipioId ?: ($user['municipio_id'] ?? null),
                $usuarioId ?: ($user['id'] ?? null),
                (string)($user['nome'] ?? ''),
                (string)($user['email'] ?? ($contexto['email'] ?? '')),
                substr($evento,0,120),$categoria,$severidade,$sucesso,$detalhes,
                self::ip(),substr($_SERVER['HTTP_USER_AGENT'] ?? '',0,500),
                substr($_SERVER['REQUEST_METHOD'] ?? 'CLI',0,10),substr(parse_url($_SERVER['REQUEST_URI'] ?? '',PHP_URL_PATH) ?: '',0,500),
                substr($_SERVER['HTTP_REFERER'] ?? '',0,500),$sessionId!==''?hash('sha256',$sessionId):'',
                $contexto ? json_encode($contexto,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null,
            ]);
        } catch (\Throwable) {
            // A auditoria não pode interromper a operação principal.
        }
    }

    public static function ip(): string
    {
        return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''),0,64);
    }

    private static function categoryFor(string $evento): string
    {
        $e=strtoupper($evento);
        if(str_contains($e,'LOGIN')||str_contains($e,'SENHA')||str_contains($e,'SESSAO')||str_contains($e,'PERMISSAO'))return 'SEGURANCA';
        if(str_contains($e,'DOWNLOAD')||str_contains($e,'EXPORT'))return 'ACESSO_DADOS';
        if(str_contains($e,'USUARIO')||str_contains($e,'MUNICIPIO')||str_contains($e,'CONFIG')||str_contains($e,'TERRITORIAL'))return 'ADMINISTRACAO';
        return 'OPERACIONAL';
    }

    private static function severityFor(string $evento): string
    {
        $e=strtoupper($evento);
        if(str_contains($e,'FALHA')||str_contains($e,'BLOQUE')||str_contains($e,'DIVERG'))return 'ALERTA';
        if(str_contains($e,'PERMISSAO')||str_contains($e,'DESATIV')||str_contains($e,'REABERTA'))return 'ATENCAO';
        return 'INFO';
    }
}
