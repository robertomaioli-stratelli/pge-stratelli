<?php
namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Enriquecimento da carteira de municípios para a tela administrativa.
 * Mantém o cálculo fora do JavaScript e usa dados reais do Workflow.
 */
final class MunicipalityCardService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function enrich(array $municipios): array
    {
        if (!$municipios) return [];

        $ids = array_values(array_unique(array_filter(array_map(
            static fn(array $m): int => (int)($m['id'] ?? 0),
            $municipios
        ))));
        if (!$ids) return $municipios;

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $phases = $this->all(
            "SELECT id, municipio_id, ordem, aba, titulo, ativo
             FROM fases
             WHERE ativo=1 AND municipio_id IN ($placeholders)
             ORDER BY municipio_id, ordem, id",
            $ids
        );

        $requirements = $this->all(
            "SELECT r.id, r.municipio_id, r.fase_id, r.obrigatorio,
                    d.status, d.validado_em, d.enviado_em
             FROM requisitos_documentais r
             LEFT JOIN documentos_enviados d
               ON d.id=(
                    SELECT MAX(d2.id)
                    FROM documentos_enviados d2
                    WHERE d2.municipio_id=r.municipio_id
                      AND d2.requisito_id=r.id
               )
             WHERE r.ativo=1
               AND r.obrigatorio=1
               AND r.municipio_id IN ($placeholders)",
            $ids
        );

        $manual = $this->all(
            "SELECT municipio_id, fase_id, data_conclusao_real, status
             FROM cronograma_fases
             WHERE municipio_id IN ($placeholders)",
            $ids
        );

        // v4.20.3 — Checklist de implantação por município.
        // As consultas são consolidadas para evitar N+1 na carteira administrativa.
        $readinessRows = $this->all(
            "SELECT m.id AS municipio_id,
                    (SELECT COUNT(*) FROM usuarios u
                     WHERE u.municipio_id=m.id AND u.ativo=1 AND u.grupo='GESTOR') AS gestores_ativos,
                    (SELECT COUNT(*) FROM usuarios u
                     WHERE u.municipio_id=m.id AND u.ativo=1 AND u.grupo='USUARIO') AS usuarios_comuns_ativos,
                    (SELECT COUNT(*) FROM secretarias s
                     WHERE s.municipio_id=m.id AND s.ativo=1) AS secretarias_ativas,
                    (SELECT COUNT(*) FROM departamentos d
                     WHERE d.municipio_id=m.id AND d.ativo=1) AS departamentos_ativos,
                    (SELECT COUNT(*) FROM fases f
                     WHERE f.municipio_id=m.id AND f.ativo=1) AS fases_ativas,
                    (SELECT COUNT(*) FROM requisitos_documentais r
                     WHERE r.municipio_id=m.id AND r.ativo=1) AS documentos_configurados,
                    (SELECT COUNT(DISTINCT md.requisito_id)
                     FROM modelos_documentos md
                     INNER JOIN requisitos_documentais r2
                       ON r2.id=md.requisito_id AND r2.municipio_id=md.municipio_id
                     WHERE md.municipio_id=m.id AND md.ativo=1 AND r2.ativo=1) AS documentos_com_modelo
             FROM municipios m
             WHERE m.id IN ($placeholders)",
            $ids
        );

        $readinessByMunicipio = [];
        foreach ($readinessRows as $row) {
            $readinessByMunicipio[(int)$row['municipio_id']] = $row;
        }

        $phasesByMunicipio = [];
        foreach ($phases as $phase) {
            $phasesByMunicipio[(int)$phase['municipio_id']][] = $phase;
        }

        $requirementsByPhase = [];
        foreach ($requirements as $req) {
            $requirementsByPhase[(int)$req['municipio_id']][(int)$req['fase_id']][] = $req;
        }

        $manualConclusion = [];
        foreach ($manual as $row) {
            if (!empty($row['data_conclusao_real']) && strtoupper((string)($row['status'] ?? 'ENCERRADA')) === 'ENCERRADA') {
                $manualConclusion[(int)$row['municipio_id']][(int)$row['fase_id']] = (string)$row['data_conclusao_real'];
            }
        }

        foreach ($municipios as &$municipio) {
            $mid = (int)($municipio['id'] ?? 0);
            $municipioPhases = $phasesByMunicipio[$mid] ?? [];
            $totalPhases = count($municipioPhases);
            $completedPhases = 0;
            $currentPhase = null;
            $currentStats = ['total' => 0, 'approved' => 0, 'missing' => 0];

            foreach ($municipioPhases as $phase) {
                $phaseId = (int)$phase['id'];
                $reqs = $requirementsByPhase[$mid][$phaseId] ?? [];
                $totalRequired = count($reqs);
                $approvedRequired = 0;

                foreach ($reqs as $req) {
                    if (($req['status'] ?? null) === 'APROVADO') $approvedRequired++;
                }

                // A partir da v4.17, 100% documental apenas deixa a fase pronta para encerramento.
                // Somente o encerramento formal registrado pela Stratelli conclui a fase.
                $isCompleted = isset($manualConclusion[$mid][$phaseId]);

                if ($isCompleted) {
                    $completedPhases++;
                    continue;
                }

                if ($currentPhase === null) {
                    $currentPhase = $phase;
                    $currentStats = [
                        'total' => $totalRequired,
                        'approved' => $approvedRequired,
                        'missing' => max(0, $totalRequired - $approvedRequired),
                    ];
                }
            }

            $allCompleted = $totalPhases > 0 && $completedPhases === $totalPhases;

            $municipio['catalogo_fases_total'] = $totalPhases;
            $municipio['catalogo_fases_concluidas'] = $completedPhases;
            $municipio['catalogo_etapa_concluida'] = $allCompleted;
            $municipio['catalogo_fase_atual'] = $allCompleted ? null : $currentPhase;
            $municipio['catalogo_documentos_fase_total'] = $allCompleted ? 0 : $currentStats['total'];
            $municipio['catalogo_documentos_fase_aprovados'] = $allCompleted ? 0 : $currentStats['approved'];
            $municipio['catalogo_documentos_faltantes'] = $allCompleted ? 0 : $currentStats['missing'];
            $municipio['catalogo_fase_pronta_encerramento'] = !$allCompleted && $currentPhase !== null && ($currentStats['total']===0 || $currentStats['approved']===$currentStats['total']);

            // Índice de prontidão para implantação/homologação.
            // Inteligência Territorial é informativa: o módulo pode permanecer inativo por decisão comercial/operacional.
            $rr = $readinessByMunicipio[$mid] ?? [];
            $gestoresAtivos = (int)($rr['gestores_ativos'] ?? 0);
            $usuariosComuns = (int)($rr['usuarios_comuns_ativos'] ?? 0);
            $secretariasAtivas = (int)($rr['secretarias_ativas'] ?? 0);
            $departamentosAtivos = (int)($rr['departamentos_ativos'] ?? 0);
            $fasesAtivas = (int)($rr['fases_ativas'] ?? 0);
            $documentosConfigurados = (int)($rr['documentos_configurados'] ?? 0);
            $documentosComModelo = min($documentosConfigurados, (int)($rr['documentos_com_modelo'] ?? 0));

            $institutionalFields = [
                'nome' => trim((string)($municipio['nome'] ?? '')) !== '',
                'uf' => trim((string)($municipio['uf'] ?? '')) !== '',
                'slug' => trim((string)($municipio['slug'] ?? '')) !== '',
                'codigo_ibge' => trim((string)($municipio['codigo_ibge'] ?? '')) !== '',
                'latitude' => $municipio['latitude'] !== null && $municipio['latitude'] !== '',
                'longitude' => $municipio['longitude'] !== null && $municipio['longitude'] !== '',
                'brasao_path' => trim((string)($municipio['brasao_path'] ?? '')) !== '',
            ];
            $institutionalDone = count(array_filter($institutionalFields));
            $institutionalTotal = count($institutionalFields);
            $institutionalRatio = $institutionalTotal ? $institutionalDone / $institutionalTotal : 0.0;
            $modelsRatio = $documentosConfigurados > 0 ? min(1.0, $documentosComModelo / $documentosConfigurados) : 0.0;
            $hasGeojson = trim((string)($municipio['geojson_delimitacao'] ?? '')) !== '';

            $components = [
                $institutionalRatio,
                $gestoresAtivos > 0 ? 1.0 : 0.0,
                $secretariasAtivas > 0 ? 1.0 : 0.0,
                $fasesAtivas > 0 ? 1.0 : 0.0,
                $documentosConfigurados > 0 ? 1.0 : 0.0,
                $modelsRatio,
                $hasGeojson ? 1.0 : 0.0,
                $usuariosComuns > 0 ? 1.0 : 0.0,
            ];
            $readinessPercent = (int)round((array_sum($components) / count($components)) * 100);
            $readinessStatus = $readinessPercent >= 100 ? 'ready' : ($readinessPercent >= 80 ? 'advanced' : ($readinessPercent >= 50 ? 'partial' : 'initial'));
            $readinessLabel = match ($readinessStatus) {
                'ready' => 'PRONTO PARA HOMOLOGAÇÃO',
                'advanced' => 'IMPLANTAÇÃO AVANÇADA',
                'partial' => 'CONFIGURAÇÃO PARCIAL',
                default => 'CONFIGURAÇÃO INICIAL',
            };

            $municipio['implantacao_prontidao'] = $readinessPercent;
            $municipio['implantacao_status'] = $readinessStatus;
            $municipio['implantacao_status_label'] = $readinessLabel;
            $municipio['implantacao_checklist'] = [
                [
                    'key' => 'cadastro', 'label' => 'Cadastro institucional',
                    'done' => $institutionalDone === $institutionalTotal,
                    'partial' => $institutionalDone > 0 && $institutionalDone < $institutionalTotal,
                    'detail' => $institutionalDone . '/' . $institutionalTotal . ' campos essenciais',
                ],
                [
                    'key' => 'gestor', 'label' => 'Gestor cadastrado',
                    'done' => $gestoresAtivos > 0, 'partial' => false,
                    'detail' => $gestoresAtivos . ' gestor(es) ativo(s)',
                ],
                [
                    'key' => 'secretarias', 'label' => 'Secretarias configuradas',
                    'done' => $secretariasAtivas > 0, 'partial' => false,
                    'detail' => $secretariasAtivas . ' secretaria(s) · ' . $departamentosAtivos . ' departamento(s)',
                ],
                [
                    'key' => 'fases', 'label' => 'Fases configuradas',
                    'done' => $fasesAtivas > 0, 'partial' => false,
                    'detail' => $fasesAtivas . ' fase(s) ativa(s)',
                ],
                [
                    'key' => 'documentos', 'label' => 'Documentos configurados',
                    'done' => $documentosConfigurados > 0, 'partial' => false,
                    'detail' => $documentosConfigurados . ' documento(s) ativo(s)',
                ],
                [
                    'key' => 'modelos', 'label' => 'Modelos cadastrados',
                    'done' => $documentosConfigurados > 0 && $documentosComModelo >= $documentosConfigurados,
                    'partial' => $documentosComModelo > 0 && $documentosComModelo < $documentosConfigurados,
                    'detail' => $documentosComModelo . '/' . $documentosConfigurados . ' documento(s) com modelo',
                ],
                [
                    'key' => 'geojson', 'label' => 'GeoJSON cadastrado',
                    'done' => $hasGeojson, 'partial' => false,
                    'detail' => $hasGeojson ? 'Delimitação municipal disponível' : 'Delimitação municipal pendente',
                ],
                [
                    'key' => 'territorial', 'label' => 'Inteligência Territorial',
                    'done' => true, 'partial' => false, 'informational' => true,
                    'detail' => !empty($municipio['inteligencia_territorial_ativa']) ? 'Ativada para usuários municipais' : 'Desativada para usuários municipais',
                ],
                [
                    'key' => 'usuarios', 'label' => 'Usuários criados',
                    'done' => $usuariosComuns > 0, 'partial' => false,
                    'detail' => $usuariosComuns . ' usuário(s) comum(ns) ativo(s)',
                ],
            ];
        }
        unset($municipio);

        return $municipios;
    }

    private function all(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
