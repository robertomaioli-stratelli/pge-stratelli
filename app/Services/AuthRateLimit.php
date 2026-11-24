<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class AuthRateLimit
{
    public static function allow(string $scope, string $subject, int $limit, int $seconds): bool
    {
        $pdo = Database::connection();
        $statement = $pdo->prepare(
            'INSERT INTO auth_rate_limits (chave, tentativas, expira_em)
             VALUES (UNHEX(?), 1, UNIX_TIMESTAMP() + ?)
             ON DUPLICATE KEY UPDATE
                tentativas = CASE
                    WHEN expira_em <= UNIX_TIMESTAMP() THEN 1
                    WHEN tentativas < ? THEN tentativas + 1
                    ELSE tentativas
                END,
                expira_em = CASE
                    WHEN expira_em <= UNIX_TIMESTAMP() THEN VALUES(expira_em)
                    ELSE expira_em
                END'
        );
        $statement->execute([hash('sha256', $scope . ':' . $subject), $seconds, $limit]);

        // O PDO MySQL usa linhas alteradas com a configuração atual de Database.
        // Quando o contador já atingiu o limite, o UPDATE não altera valores.
        $allowed = $statement->rowCount() > 0;

        if (random_int(1, 32) === 1) {
            $pdo->exec('DELETE FROM auth_rate_limits
                        WHERE expira_em <= UNIX_TIMESTAMP() ORDER BY expira_em LIMIT 200');
        }

        return $allowed;
    }
}
