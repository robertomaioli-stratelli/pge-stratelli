-- PGE Stratelli: diagnostico de visibilidade de fase/documentos (SOMENTE LEITURA)
-- Selecionar banco correto pge_db (producao) ou stratelli (local) no phpMyAdmin ANTES de executar.
-- Nao modifica registros.
SET @pge_email = 'secseg@maringa.local';
SET @pge_slug = 'maringa';

SELECT DATABASE() AS banco_analisado;

-- 1. Usuario, secretaria e departamento realmente associados.
SELECT u.id AS usuario_id,u.email,u.grupo,u.ativo AS usuario_ativo,
       u.municipio_id,u.secretaria_id,u.departamento_id,
       s.nome AS secretaria_usuario,s.ativo AS secretaria_ativa,
       d.nome AS departamento_usuario
FROM usuarios u
LEFT JOIN secretarias s ON s.id=u.secretaria_id AND s.municipio_id=u.municipio_id
LEFT JOIN departamentos d ON d.id=u.departamento_id AND d.municipio_id=u.municipio_id
JOIN municipios m ON m.id=u.municipio_id
WHERE u.email COLLATE utf8mb4_unicode_ci = @pge_email COLLATE utf8mb4_unicode_ci
  AND m.slug COLLATE utf8mb4_unicode_ci = @pge_slug COLLATE utf8mb4_unicode_ci;

-- 2. Fases ativas e quantos documentos de envio municipal o usuario pode visualizar nelas.
SELECT f.id AS fase_id,f.aba,f.ordem,f.ativo AS fase_ativa,
       f.exclusivo_stratelli,
       COUNT(DISTINCT CASE WHEN r.ativo=1 AND r.perfil_envio='MUNICIPIO' THEN r.id END) AS docs_municipais_na_fase,
       COUNT(DISTINCT CASE WHEN r.ativo=1 AND r.perfil_envio='MUNICIPIO'
                     AND r.secretaria_id=u.secretaria_id
                     AND (u.departamento_id IS NULL OR r.departamento_id IS NULL OR r.departamento_id=u.departamento_id)
                     THEN r.id END) AS docs_visiveis_para_usuario,
       COUNT(DISTINCT CASE WHEN af.ativo=1 AND ass.secretaria_id=u.secretaria_id THEN af.id END) AS atos_vinculados_secretaria
FROM usuarios u
JOIN municipios m ON m.id=u.municipio_id
JOIN fases f ON f.municipio_id=u.municipio_id AND f.ativo=1
LEFT JOIN requisitos_documentais r ON r.municipio_id=f.municipio_id AND r.fase_id=f.id
LEFT JOIN atividades_fase af ON af.municipio_id=f.municipio_id AND af.fase_id=f.id
LEFT JOIN atividade_secretarias ass ON ass.municipio_id=af.municipio_id AND ass.atividade_id=af.id
WHERE u.email COLLATE utf8mb4_unicode_ci = @pge_email COLLATE utf8mb4_unicode_ci
  AND m.slug COLLATE utf8mb4_unicode_ci = @pge_slug COLLATE utf8mb4_unicode_ci
GROUP BY f.id,f.aba,f.ordem,f.ativo,f.exclusivo_stratelli
ORDER BY f.ordem,f.id;

-- 3. Documentos configurados para a fase preparatoria ativa: motivos possíveis de exclusão.
SELECT r.id AS documento_id,r.nome AS documento,r.ativo AS documento_ativo,
       r.perfil_envio,r.fase_id,f.titulo AS fase,r.secretaria_id,
       s.nome AS secretaria_documento,r.departamento_id,
       u.secretaria_id AS secretaria_usuario,u.departamento_id AS departamento_usuario,
       CASE
         WHEN r.ativo<>1 THEN 'BLOQUEADO: documento inativo'
         WHEN f.ativo<>1 THEN 'BLOQUEADO: fase inativa'
         WHEN f.exclusivo_stratelli=1 THEN 'BLOQUEADO: fase exclusiva Stratelli'
         WHEN r.perfil_envio<>'MUNICIPIO' THEN 'BLOQUEADO: perfil de envio nao municipal'
         WHEN r.secretaria_id<>u.secretaria_id THEN 'BLOQUEADO: secretaria nao corresponde'
         WHEN u.departamento_id IS NOT NULL AND r.departamento_id IS NOT NULL AND r.departamento_id<>u.departamento_id THEN 'BLOQUEADO: departamento nao corresponde'
         ELSE 'ELEGIVEL (verificar acesso sequencial)'
       END AS diagnostico
FROM usuarios u
JOIN municipios m ON m.id=u.municipio_id
JOIN requisitos_documentais r ON r.municipio_id=u.municipio_id
JOIN fases f ON f.id=r.fase_id AND f.municipio_id=r.municipio_id
LEFT JOIN secretarias s ON s.id=r.secretaria_id AND s.municipio_id=r.municipio_id
WHERE u.email COLLATE utf8mb4_unicode_ci = @pge_email COLLATE utf8mb4_unicode_ci
  AND m.slug COLLATE utf8mb4_unicode_ci = @pge_slug COLLATE utf8mb4_unicode_ci
  AND f.ativo=1
ORDER BY f.ordem,r.id;
