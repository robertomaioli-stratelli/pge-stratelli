-- PGE V38 | CONFERENCIA SOMENTE LEITURA | comparar com os dumps 06-07/11/2026
-- Execute no phpMyAdmin, no banco pge_db. Nao altera dados.
USE `pge_db`;
SELECT DATABASE() AS banco_atual, VERSION() AS versao_mariadb;
SELECT 'usuarios_producao' AS item, COUNT(*) AS total FROM usuarios
UNION ALL SELECT 'municipios', COUNT(*) FROM municipios
UNION ALL SELECT 'fases', COUNT(*) FROM fases
UNION ALL SELECT 'atos', COUNT(*) FROM atividades_fase
UNION ALL SELECT 'atividades', COUNT(*) FROM pge_atividades_ato
UNION ALL SELECT 'etapas', COUNT(*) FROM pge_etapas_execucao;
SELECT id, email, grupo, ativo, municipio_id, secretaria_id
FROM usuarios ORDER BY id;
SELECT e.id,e.processo_id,e.municipio_id,e.numero,e.nome,e.status
FROM pge_etapas_execucao e WHERE e.municipio_id=1 ORDER BY e.id;
SELECT COLUMN_NAME FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA='pge_db' AND TABLE_NAME='pge_etapas_execucao' AND COLUMN_NAME='habilitada';
SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS colunas
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA='pge_db' AND TABLE_NAME='pge_etapas_execucao'
GROUP BY INDEX_NAME ORDER BY INDEX_NAME;
-- O esperado no dump: 8 usuarios, 3 municipios, 28 fases, 75 atos, 22 atividades, 4 etapas.
-- A coluna habilitada NAO deve existir antes da migracao e a chave antiga uq_pge_etapa_processo_numero deve existir.
