<?php
namespace App\Core;

/** Numeração exibida da fase, independente da ordem global legada. */
final class PhaseNumber
{
    public static function from(array $fase): int
    {
        // O número de negócio é o prefixo do título: "1. FASE PREPARATÓRIA".
        $titulo = trim((string)($fase['titulo'] ?? ''));
        if (preg_match('/^([1-9][0-9]*)\s*[.\-–)]\s*/u', $titulo, $match)) {
            return (int)$match[1];
        }
        // Cadastros manuais sem número no título usam a posição relativa à Etapa,
        // quando ela estiver disponível na consulta. Nunca substituir IDs internos.
        foreach (['numero_fase', 'numero_na_etapa', 'ordem_na_etapa'] as $campo) {
            if (isset($fase[$campo]) && (int)$fase[$campo] > 0) {
                return (int)$fase[$campo];
            }
        }
        return (int)($fase['ordem'] ?? 0);
    }
}
