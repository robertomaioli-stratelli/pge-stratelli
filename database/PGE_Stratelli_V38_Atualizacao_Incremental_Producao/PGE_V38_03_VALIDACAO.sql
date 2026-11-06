USE `pge_db`;
SELECT id,email,grupo,ativo FROM usuarios ORDER BY id;
SELECT id,numero,nome,habilitada,status FROM pge_etapas_execucao WHERE municipio_id=1 ORDER BY id;
SELECT numero,COUNT(*) AS modalidades_habilitadas FROM pge_etapas_execucao
WHERE municipio_id=1 AND processo_id=6 AND habilitada=1 AND status='EM_ANDAMENTO'
GROUP BY numero HAVING COUNT(*)>1;
SELECT f.id,f.titulo,f.ativo,f.ativo_individual,e.nome AS etapa
FROM fases f JOIN pge_etapas_execucao e ON e.id=f.etapa_execucao_id
WHERE f.id IN (24,25,26,27,28,29,30,31,32) ORDER BY f.id;
SELECT e.nome AS etapa,
 (SELECT COUNT(*) FROM fases f WHERE f.etapa_execucao_id=e.id) AS fases,
 (SELECT COUNT(*) FROM atividades_fase a JOIN fases f ON f.id=a.fase_id WHERE f.etapa_execucao_id=e.id) AS atos,
 (SELECT COUNT(*) FROM pge_atividades_ato v JOIN atividades_fase a ON a.id=v.ato_legado_id JOIN fases f ON f.id=a.fase_id WHERE f.etapa_execucao_id=e.id) AS atividades
FROM pge_etapas_execucao e WHERE e.id IN (6,8);
SELECT id,etapa_execucao_id,data_inicio,referencia FROM pge_inicios_formais;
SELECT COUNT(*) AS documentos FROM requisitos_documentais;
