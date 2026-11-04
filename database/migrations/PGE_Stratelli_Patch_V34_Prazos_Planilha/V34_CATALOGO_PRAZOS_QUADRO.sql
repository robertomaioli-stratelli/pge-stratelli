-- PGE V34 — importacao do catalogo de prazos do quadro sinoptico (29/10/2026).
-- Bancos com V32 instalada. Alvo: municipio slug 'maringa'.
-- NAO substitui documentacao, usuarios, regras manuais nem datas ja alteradas.
-- Todos os prazos textuais sao armazenados; regras automaticas somente com marco inequivoco.
-- Requer BACKUP. Testar em homologacao primeiro.
SET NAMES utf8mb4;
SET @pge_v34_mid := (SELECT id FROM municipios WHERE slug COLLATE utf8mb4_unicode_ci = _utf8mb4'maringa' COLLATE utf8mb4_unicode_ci AND ativo=1 LIMIT 1);
SELECT IF(@pge_v34_mid IS NULL,'PARAR: municipio nao encontrado','Municipio localizado') AS verificacao;
-- ATENCAO: se a consulta acima indicar PARAR, NAO execute os comandos seguintes.
CREATE TABLE IF NOT EXISTS pge_prazo_catalogo (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 municipio_id BIGINT UNSIGNED NOT NULL,
 nivel ENUM('ATO','ATIVIDADE') NOT NULL,
 item_id BIGINT UNSIGNED NOT NULL,
 codigo_item VARCHAR(80) NOT NULL,
 prazo_original TEXT NOT NULL,
 fundamento_normativo TEXT NULL,
 quantidade INT UNSIGNED NULL,
 unidade ENUM('DIAS_UTEIS','DIAS_CORRIDOS') NULL,
 direcao ENUM('APOS','ANTES') NOT NULL DEFAULT 'APOS',
 classificacao VARCHAR(40) NOT NULL,
 fonte VARCHAR(150) NOT NULL DEFAULT 'Quadro Sinoptico 29.10.2026',
 atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_catalogo_prazo (municipio_id,nivel,item_id),
 INDEX idx_catalogo_codigo (municipio_id,nivel,codigo_item)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
START TRANSACTION;

CREATE TEMPORARY TABLE pge_v34_quadro (
 nivel VARCHAR(12) NOT NULL,
 codigo VARCHAR(80) NOT NULL,
 prazo_texto TEXT NOT NULL,
 fundamento TEXT NULL,
 quantidade INT NULL,
 unidade VARCHAR(20) NULL,
 direcao VARCHAR(10) NOT NULL,
 classificacao VARCHAR(40) NOT NULL,
 PRIMARY KEY (nivel,codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO pge_v34_quadro (nivel,codigo,prazo_texto,fundamento,quantidade,unidade,direcao,classificacao) VALUES
('ATO','1.1','Necessário à maturidade do processo, aprovações externas jurídicas e de órgãos de controle','Edital cap. 2; art. 32, art 53',NULL,NULL,'APOS','SEM_DURACAO_NUMERICA'),
('ATO','2.1','25 dias úteis','Item 5.2 do edital; art. 18, 
I, da Lei 14.133/2021',25,'DIAS_UTEIS','APOS','QUANTITATIVO'),
('ATO','2.2','',NULL,NULL,NULL,'APOS','SEM_PRAZO_ESPECIFICADO'),
('ATO','2.3','até 3 dias úteis antes da abertura','Item 5.3 do edital; art. 164',3,'DIAS_UTEIS','ANTES','QUANTITATIVO'),
('ATO','2.4','dia/em data horária fixada','Item 5.4 do edital',NULL,NULL,'APOS','DATA_A_DEFINIR'),
('ATO','2.5','até 5 dias úteis após a abertura','Art. 32, §§ 3º e 4º',5,'DIAS_UTEIS','APOS','QUANTITATIVO'),
('ATO','2.6','3 dias úteis','Item 8.1 do edital; art. 165, II',3,'DIAS_UTEIS','APOS','QUANTITATIVO'),
('ATO','2.7','contrarrazões: 3 dias úteis; proferimento e publicação: 5 dias úteis','Edital; arts. 165 e 166',NULL,NULL,'APOS','PRAZO_COMPOSTO'),
('ATO','3.1','',NULL,NULL,NULL,'APOS','SEM_PRAZO_ESPECIFICADO'),
('ATO','3.2','3 dias úteis após publicação dos pré-selecionados','Item 5.19 do edital',3,'DIAS_UTEIS','APOS','QUANTITATIVO'),
('ATO','3.3','até 60 dias corridos','Item 5.20 do edital; art. 32, §1º, IV e V',60,'DIAS_CORRIDOS','APOS','QUANTITATIVO'),
('ATO','3.4','durante os diálogos','Edital; Anexos',NULL,NULL,'APOS','DURANTE_EVENTO'),
('ATO','3.5','imediatamente ao término da sessão','Item 5.21 do edital',0,'DIAS_CORRIDOS','APOS','MARCO_EVENTO'),
('ATO','3.6','até 5 dias úteis após término da última sessão','Edital; art. 32, §1º, V',5,'DIAS_UTEIS','APOS','QUANTITATIVO'),
('ATO','3.7','até 5 dias úteis após emissão do relatório final','Edital; art. 32, §1º, VIII',5,'DIAS_UTEIS','APOS','QUANTITATIVO'),
('ATO','3.8','até 10 dias úteis','Edital; art. 53',10,'DIAS_UTEIS','APOS','QUANTITATIVO'),
('ATO','4.1','dia/em data horária fixada','Art. 32, §1º, VI',NULL,NULL,'APOS','DATA_A_DEFINIR'),
('ATO','4.2','até 3 dias úteis antes da abertura','Edital; art. 164',3,'DIAS_UTEIS','ANTES','QUANTITATIVO'),
('ATO','4.3','dia/em data horária fixada','Edital',NULL,NULL,'APOS','DATA_A_DEFINIR'),
('ATO','4.4','conforme cronograma','Edital; art. 36, III',NULL,NULL,'APOS','CONFORME_CRONOGRAMA'),
('ATO','4.5','3 dias úteis','Edital; arts. 164-165',3,'DIAS_UTEIS','APOS','QUANTITATIVO'),
('ATO','4.6','contrarrazões: 3 dias úteis; 
proferimento e publicação: 5 dias úteis',NULL,NULL,NULL,'APOS','PRAZO_COMPOSTO'),
('ATO','5.1','','Edital; art. 107, §6º',NULL,NULL,'APOS','SEM_PRAZO_ESPECIFICADO'),
('ATO','6.1','vigência do contrato','Art. 117; LGPD',NULL,NULL,'APOS','DURANTE_VIGENCIA'),
('ATIVIDADE','1.1.1','Necessário à maturidade do processo, aprovações externas jurídicas e de órgãos de controle','Edital cap. 2; art. 32, art 53',NULL,NULL,'APOS','SEM_DURACAO_NUMERICA'),
('ATIVIDADE','1.1.2','',NULL,NULL,NULL,'APOS','SEM_PRAZO_ESPECIFICADO'),
('ATIVIDADE','1.1.3','',NULL,NULL,NULL,'APOS','SEM_PRAZO_ESPECIFICADO'),
('ATIVIDADE','1.1.4','',NULL,NULL,NULL,'APOS','SEM_PRAZO_ESPECIFICADO'),
('ATIVIDADE','1.1.5','',NULL,NULL,NULL,'APOS','SEM_PRAZO_ESPECIFICADO'),
('ATIVIDADE','1.1.6','',NULL,NULL,NULL,'APOS','SEM_PRAZO_ESPECIFICADO'),
('ATIVIDADE','1.1.7','',NULL,NULL,NULL,'APOS','SEM_PRAZO_ESPECIFICADO'),
('ATIVIDADE','1.1.8','',NULL,NULL,NULL,'APOS','SEM_PRAZO_ESPECIFICADO'),
('ATIVIDADE','1.1.9','',NULL,NULL,NULL,'APOS','SEM_PRAZO_ESPECIFICADO'),
('ATIVIDADE','2.1.1','25 dias úteis','Item 5.2 do edital; art. 18, 
I, da Lei 14.133/2021',25,'DIAS_UTEIS','APOS','QUANTITATIVO'),
('ATIVIDADE','2.5.1','até 5 dias úteis após a abertura','Art. 32, §§ 3º e 4º',5,'DIAS_UTEIS','APOS','QUANTITATIVO');

-- O codigo de ato operacional e QS11-<numero> e deve estar ativo.
-- Caso haja regra personalizada preexistente, a migracao nao a substitui.
INSERT IGNORE INTO pge_prazo_catalogo
(municipio_id,nivel,item_id,codigo_item,prazo_original,fundamento_normativo,quantidade,unidade,direcao,classificacao)
SELECT a.municipio_id,'ATO',a.id,q.codigo,q.prazo_texto,q.fundamento,q.quantidade,q.unidade,q.direcao,q.classificacao
FROM atividades_fase a
JOIN pge_v34_quadro q ON q.nivel='ATO' AND a.codigo COLLATE utf8mb4_unicode_ci = CONCAT('QS11-',q.codigo) COLLATE utf8mb4_unicode_ci
JOIN fases f ON f.id=a.fase_id AND f.municipio_id=a.municipio_id AND f.ativo=1
WHERE a.ativo=1 AND a.municipio_id=@pge_v34_mid;

INSERT IGNORE INTO pge_prazo_catalogo
(municipio_id,nivel,item_id,codigo_item,prazo_original,fundamento_normativo,quantidade,unidade,direcao,classificacao)
SELECT s.municipio_id,'ATIVIDADE',s.id,q.codigo,q.prazo_texto,q.fundamento,q.quantidade,q.unidade,q.direcao,q.classificacao
FROM pge_atividades_ato s
JOIN atividades_fase a ON a.id=s.ato_legado_id AND a.municipio_id=s.municipio_id AND a.ativo=1
JOIN pge_v34_quadro q ON q.nivel='ATIVIDADE' AND s.codigo COLLATE utf8mb4_unicode_ci=q.codigo COLLATE utf8mb4_unicode_ci
WHERE s.ativo=1 AND s.municipio_id=@pge_v34_mid;

-- Alinhar somente campos vazios; preservar tudo que foi editado manualmente.
UPDATE atividades_fase a JOIN pge_prazo_catalogo c ON c.municipio_id=a.municipio_id AND c.nivel='ATO' AND c.item_id=a.id
SET a.prazo_referencia=CASE WHEN COALESCE(TRIM(a.prazo_referencia),'')='' THEN LEFT(c.prazo_original,255) ELSE a.prazo_referencia END,
    a.fundamento_normativo=CASE WHEN COALESCE(TRIM(a.fundamento_normativo),'')='' THEN LEFT(c.fundamento_normativo,255) ELSE a.fundamento_normativo END,
    a.prazo_quantidade=COALESCE(a.prazo_quantidade,c.quantidade),
    a.prazo_tipo=CASE WHEN a.prazo_tipo='SEM_PRAZO' AND c.unidade IS NOT NULL THEN c.unidade ELSE a.prazo_tipo END
WHERE a.municipio_id=@pge_v34_mid AND a.ativo=1;
UPDATE pge_atividades_ato s JOIN pge_prazo_catalogo c ON c.municipio_id=s.municipio_id AND c.nivel='ATIVIDADE' AND c.item_id=s.id
SET s.prazo_texto=CASE WHEN COALESCE(TRIM(s.prazo_texto),'')='' THEN LEFT(c.prazo_original,255) ELSE s.prazo_texto END
WHERE s.municipio_id=@pge_v34_mid AND s.ativo=1;

-- Ato 3.2: prazo apos conclusao do Ato 2.5, conforme texto do quadro.
INSERT IGNORE INTO pge_prazo_regras
(municipio_id,nivel,item_id,marco_tipo,marco_id,data_fixa,quantidade,unidade,direcao,titulo_referencia,usuario_id)
SELECT a.municipio_id,'ATO',a.id,'CONCLUSAO_ATO',b.id,NULL,3,'DIAS_UTEIS','APOS',
  'Auto V34: a contagem depende da conclusao registrada do Ato 2.5',NULL
FROM atividades_fase a
JOIN atividades_fase b ON b.municipio_id=a.municipio_id AND b.ativo=1 AND b.codigo='QS11-2.5'
WHERE a.municipio_id=@pge_v34_mid AND a.ativo=1 AND a.codigo='QS11-3.2';

-- Ato 3.7: prazo apos conclusao do Ato 3.6, conforme texto do quadro.
INSERT IGNORE INTO pge_prazo_regras
(municipio_id,nivel,item_id,marco_tipo,marco_id,data_fixa,quantidade,unidade,direcao,titulo_referencia,usuario_id)
SELECT a.municipio_id,'ATO',a.id,'CONCLUSAO_ATO',b.id,NULL,5,'DIAS_UTEIS','APOS',
  'Auto V34: a contagem depende da conclusao registrada do Ato 3.6',NULL
FROM atividades_fase a
JOIN atividades_fase b ON b.municipio_id=a.municipio_id AND b.ativo=1 AND b.codigo='QS11-3.6'
WHERE a.municipio_id=@pge_v34_mid AND a.ativo=1 AND a.codigo='QS11-3.7';

-- Nenhum prazo relativo a abertura, diario oficial, sessao ou outro evento sem data comprovada
-- recebe artificialmente a data do documento formal. A regra pode ser completada no painel.
COMMIT;

SELECT 'Atos catalogados' AS item, COUNT(*) AS total FROM pge_prazo_catalogo
 WHERE municipio_id=@pge_v34_mid AND nivel='ATO'
UNION ALL
SELECT 'Atividades catalogadas', COUNT(*) FROM pge_prazo_catalogo
 WHERE municipio_id=@pge_v34_mid AND nivel='ATIVIDADE'
UNION ALL
SELECT 'Regras automaticas cadastradas',COUNT(*) FROM pge_prazo_regras
 WHERE municipio_id=@pge_v34_mid AND nivel='ATO' AND item_id IN
 (SELECT id FROM atividades_fase WHERE municipio_id=@pge_v34_mid AND codigo IN ('QS11-3.2','QS11-3.7') AND ativo=1);
SELECT c.codigo_item,c.prazo_original,c.classificacao,
       CASE WHEN r.id IS NOT NULL THEN 'REGRA CONFIGURADA' ELSE 'AGUARDANDO MARCO/REGRA' END AS configuracao
FROM pge_prazo_catalogo c LEFT JOIN pge_prazo_regras r
 ON r.municipio_id=c.municipio_id AND r.nivel=c.nivel AND r.item_id=c.item_id
WHERE c.municipio_id=@pge_v34_mid ORDER BY c.nivel,c.codigo_item;
