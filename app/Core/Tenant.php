<?php
namespace App\Core;

use RuntimeException;

final class Tenant
{
    private static ?array $current = null;

    public static function resolveBySlug(string $slug): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM municipios WHERE slug=? AND ativo=1 LIMIT 1');
        $stmt->execute([$slug]);
        $tenant = $stmt->fetch();
        if (!$tenant) throw new RuntimeException('Município não encontrado.');

        $user = Auth::user();
        if (!$user) throw new RuntimeException('Não autenticado.');
        if (!Auth::isPlatformAdmin() && (int)$user['municipio_id'] !== (int)$tenant['id']) {
            throw new RuntimeException('Você não possui acesso a este município.');
        }
        self::$current = $tenant;
        return $tenant;
    }

    public static function current(): ?array { return self::$current; }
    public static function id(): ?int { return self::$current ? (int)self::$current['id'] : null; }
}
