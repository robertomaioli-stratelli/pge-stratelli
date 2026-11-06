-- PGE V35.1 - Recuperacao do erro #1062 uq_pge_etapa_processo_numero
-- Executar somente depois de fazer BACKUP COMPLETO e confirmar que o
-- V35 original parou na instrução CALL pge_v35_migrar_etapa5().
-- Nao apaga ETAPAS, usuarios, Fases, Atos, Atividades ou documentos.
-- Alteracao de indice tem COMMIT implicito; exige backup.

-- Confira o banco correto ANTES de prosseguir.
SELECT DATABASE() AS banco_selecionado;

-- Conferencia da existencia da procedure criada na tentativa anterior.
SELECT ROUTINE_NAME FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_TYPE='PROCEDURE'
  AND ROUTINE_NAME='pge_v35_migrar_etapa5';

-- Execute as instrucoes abaixo SOMENTE se aparecer a procedure acima e
-- se ainda existir a chave unica antiga uq_pge_etapa_processo_numero.

ALTER TABLE pge_etapas_execucao
  DROP INDEX uq_pge_etapa_processo_numero,
  ADD UNIQUE KEY uq_pge_etapa_modalidade (processo_id, numero, nome);

-- Retoma a migracao que foi revertida pelo tratamento de excecao.
CALL pge_v35_migrar_etapa5();

-- Remove a procedure temporaria apos sucesso.
DROP PROCEDURE IF EXISTS pge_v35_migrar_etapa5;

-- Confere modalidades e sua estrutura, inclusive as desabilitadas.
SELECT e.id,e.nome,e.numero,e.habilitada,
 COUNT(DISTINCT f.id) AS fases,COUNT(DISTINCT a.id) AS atos,
 COUNT(DISTINCT v.id) AS atividades
FROM pge_etapas_execucao e
LEFT JOIN fases f ON f.etapa_execucao_id=e.id
LEFT JOIN atividades_fase a ON a.fase_id=f.id
LEFT JOIN pge_atividades_ato v ON v.ato_legado_id=a.id
WHERE e.municipio_id=(SELECT id FROM municipios WHERE slug='maringa' LIMIT 1)
  AND e.status <> 'ARQUIVADO'
GROUP BY e.id,e.nome,e.numero,e.habilitada
ORDER BY e.habilitada DESC,e.id;

-- Conferencia de vinculos dos documentos formais.
SELECT e.id,e.nome,e.habilitada,COUNT(i.id) AS inicios_formais
FROM pge_etapas_execucao e
LEFT JOIN pge_inicios_formais i ON i.etapa_execucao_id=e.id AND i.status='ATIVO'
WHERE e.municipio_id=(SELECT id FROM municipios WHERE slug='maringa' LIMIT 1)
 AND e.status <> 'ARQUIVADO'
GROUP BY e.id,e.nome,e.habilitada;
