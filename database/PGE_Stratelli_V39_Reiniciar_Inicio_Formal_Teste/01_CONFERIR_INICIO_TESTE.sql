-- PGE V39 | Somente leitura. Selecione o banco pge_db no phpMyAdmin.
SELECT DATABASE() AS banco_selecionado;
SELECT m.id AS municipio_id,m.nome,m.slug FROM municipios m WHERE m.slug='maringa';
SELECT i.id,i.municipio_id,i.etapa_execucao_id,e.numero,e.nome AS etapa,i.data_inicio,i.referencia,i.status,i.arquivo_original,i.arquivo_salvo,i.criado_em
FROM pge_inicios_formais i
JOIN municipios m ON m.id=i.municipio_id
LEFT JOIN pge_etapas_execucao e ON e.id=i.etapa_execucao_id
WHERE m.slug='maringa'
ORDER BY i.id DESC;
SELECT cp.municipio_id, cp.data_inicio, cp.criado_em, cp.atualizado_em
FROM cronograma_processos cp
JOIN municipios m ON m.id=cp.municipio_id
WHERE m.slug='maringa';
SELECT nivel,item_id,inicio_previsto,fim_previsto,situacao,calculado_em
FROM pge_prazo_resultados
WHERE municipio_id=(SELECT id FROM municipios WHERE slug='maringa' LIMIT 1)
ORDER BY nivel,item_id;
