-- PGE V35: Etapa 5 - modalidades mutuamente exclusivas por municipio.
-- Requer backup completo. Aplica-se somente a Maringa (slug='maringa').
-- Executar apenas com PHP V35 disponivel no servidor (FormalStartService, OperationalHierarchyService).
-- ALTER TABLE faz COMMIT implicito no MariaDB: teste em homologacao antes.
SET NAMES utf8mb4;
ALTER TABLE pge_etapas_execucao ADD COLUMN IF NOT EXISTS habilitada TINYINT(1) NOT NULL DEFAULT 1 AFTER status;
ALTER TABLE fases ADD COLUMN IF NOT EXISTS ativo_individual TINYINT(1) NOT NULL DEFAULT 1 AFTER ativo;
ALTER TABLE pge_inicios_formais ADD COLUMN IF NOT EXISTS etapa_execucao_id BIGINT UNSIGNED NULL AFTER municipio_id;
-- V35.1: a mesma Etapa 5 pode ter modalidades distintas no MESMO processo.
-- Protege contra modalidade com exatamente o mesmo nome e numero no processo.
-- Substitui a chave legada (processo_id, numero), incompatível com a nova regra.
ALTER TABLE pge_etapas_execucao
  DROP INDEX uq_pge_etapa_processo_numero,
  ADD UNIQUE KEY uq_pge_etapa_modalidade (processo_id, numero, nome);

-- Backups de referencia, uma vez, antes da importacao de novas modalidades.
CREATE TABLE IF NOT EXISTS pge_v35_backup_etapas AS SELECT * FROM pge_etapas_execucao WHERE 1=0;
CREATE TABLE IF NOT EXISTS pge_v35_backup_fases AS SELECT * FROM fases WHERE 1=0;
-- Preencher backups historicos apenas na primeira execucao; reruns abortam no procedimento.
INSERT INTO pge_v35_backup_etapas SELECT * FROM pge_etapas_execucao WHERE NOT EXISTS(SELECT 1 FROM pge_v35_backup_etapas LIMIT 1);
INSERT INTO pge_v35_backup_fases SELECT * FROM fases WHERE NOT EXISTS(SELECT 1 FROM pge_v35_backup_fases LIMIT 1);
-- IMPORTANTE: os valores individuais das Fases atuais sao copiados ANTES de desativa-las.
DELIMITER $$
DROP PROCEDURE IF EXISTS pge_v35_migrar_etapa5$$
CREATE PROCEDURE pge_v35_migrar_etapa5()
BEGIN
  DECLARE v_mid BIGINT UNSIGNED;
  DECLARE v_old BIGINT UNSIGNED;
  DECLARE v_new BIGINT UNSIGNED;
  DECLARE v_pid BIGINT UNSIGNED;
  DECLARE v_fid BIGINT UNSIGNED;
  DECLARE v_aid BIGINT UNSIGNED;
  DECLARE v_max INT DEFAULT 0;
  DECLARE v_exists INT DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT id INTO v_mid FROM municipios WHERE slug='maringa' LIMIT 1;
  IF v_mid IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Municipio Maringa nao encontrado'; END IF;
  SELECT COUNT(*) INTO v_exists FROM pge_etapas_execucao WHERE municipio_id=v_mid AND nome='ETAPA 5 - CONTRATAÇÃO - EMERGENCIAL DIRETA' AND status<>'ARQUIVADO';
  IF v_exists>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Emergencial Direta ja existe. Nao reimporte.'; END IF;
  SELECT COUNT(*) INTO v_exists FROM pge_etapas_execucao WHERE municipio_id=v_mid AND (nome='ETAPA 1' OR nome='ETAPA 5 - CONTRATAÇÃO - DIÁLOGO COMPETITIVO') AND status<>'ARQUIVADO';
  IF v_exists<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Etapa anterior nao identificada de modo unico'; END IF;
  SELECT id,processo_id INTO v_old,v_pid FROM pge_etapas_execucao WHERE municipio_id=v_mid AND (nome='ETAPA 1' OR nome='ETAPA 5 - CONTRATAÇÃO - DIÁLOGO COMPETITIVO') AND status<>'ARQUIVADO' LIMIT 1 FOR UPDATE;
  UPDATE fases SET ativo_individual=ativo WHERE municipio_id=v_mid AND etapa_execucao_id=v_old;
  UPDATE pge_etapas_execucao SET nome='ETAPA 5 - CONTRATAÇÃO - DIÁLOGO COMPETITIVO',numero=5,habilitada=0,atualizado_em=NOW() WHERE id=v_old AND municipio_id=v_mid;
  -- Documento formal legado pertence ao Dialogo Competitivo e nao e herdado pela Emergencial.
  UPDATE pge_inicios_formais SET etapa_execucao_id=v_old WHERE municipio_id=v_mid AND etapa_execucao_id IS NULL;
  -- Etapa Emergencial do mesmo municipio, sem processo superior visivel.
  INSERT INTO pge_etapas_execucao(processo_id,municipio_id,modelo_etapa_id,numero,nome,status,habilitada,criado_em,atualizado_em) VALUES(v_pid,v_mid,NULL,5,'ETAPA 5 - CONTRATAÇÃO - EMERGENCIAL DIRETA','EM_ANDAMENTO',1,NOW(),NOW());
  SET v_new=LAST_INSERT_ID();
  UPDATE pge_etapas_execucao SET habilitada=0 WHERE municipio_id=v_mid AND id<>v_new AND status<>'ARQUIVADO';
  UPDATE fases SET ativo=0,atualizado_em=NOW() WHERE municipio_id=v_mid AND etapa_execucao_id IN (SELECT id FROM pge_etapas_execucao WHERE municipio_id=v_mid AND habilitada=0 AND status<>'ARQUIVADO');
  SELECT COALESCE(MAX(ordem),0) INTO v_max FROM fases WHERE municipio_id=v_mid;
  SET v_max=v_max+1;
  INSERT INTO fases(municipio_id,etapa_execucao_id,ordem,codigo,aba,titulo,descricao,responsavel,dia_inicio,dia_fim,entregavel,criterio,exclusivo_stratelli,ativo,ativo_individual,criado_em,atualizado_em) VALUES(v_mid,v_new,v_max,'EMDIR-F1','1. FASE PREPARATÓRIA','1. FASE PREPARATÓRIA','','',1,1,'','',0,1,1,NOW(),NOW());
  SET v_fid=LAST_INSERT_ID();
  INSERT INTO atividades_fase(municipio_id,fase_id,ordem,codigo,titulo,descricao,responsavel,prazo_tipo,prazo_referencia,fundamento_normativo,entregavel,obrigatoria,status_execucao,ativo,criado_em,atualizado_em) VALUES(v_mid,v_fid,1,'EMDIR-1.1','1.1. Preparação Documental da fase interna do certame','','','SEM_PRAZO','Necessário à maturidade do processo, aprovações externas jurídicas e de órgãos de controle','Edital cap. 2; art. 32, art 53','',1,'PENDENTE',1,NOW(),NOW());
  SET v_aid=LAST_INSERT_ID();
  INSERT INTO pge_atividades_ato(municipio_id,ato_legado_id,ordem,codigo,titulo,descricao,responsavel,prazo_texto,status_execucao,ativo,criado_em,atualizado_em) VALUES(v_mid,v_aid,1,'1.1.1','1.1.1. Ofício InPACTA à prefeitura. (modelo no drive com número 1)','','',NULL,'PENDENTE',1,NOW(),NOW());
  INSERT INTO pge_atividades_ato(municipio_id,ato_legado_id,ordem,codigo,titulo,descricao,responsavel,prazo_texto,status_execucao,ativo,criado_em,atualizado_em) VALUES(v_mid,v_aid,2,'1.1.2','1.1.2. Ofício da Prefeitura à UASG que executará o processo.','','',NULL,'PENDENTE',1,NOW(),NOW());
  INSERT INTO pge_atividades_ato(municipio_id,ato_legado_id,ordem,codigo,titulo,descricao,responsavel,prazo_texto,status_execucao,ativo,criado_em,atualizado_em) VALUES(v_mid,v_aid,3,'1.1.3','1.1.3. Memorando do chefe da UASG ao dirigente da UASG solicitando abertura do certame.','','',NULL,'PENDENTE',1,NOW(),NOW());
  INSERT INTO pge_atividades_ato(municipio_id,ato_legado_id,ordem,codigo,titulo,descricao,responsavel,prazo_texto,status_execucao,ativo,criado_em,atualizado_em) VALUES(v_mid,v_aid,4,'1.1.4','1.1.4. Despacho de abertura do dirigente.','','',NULL,'PENDENTE',1,NOW(),NOW());
  INSERT INTO pge_atividades_ato(municipio_id,ato_legado_id,ordem,codigo,titulo,descricao,responsavel,prazo_texto,status_execucao,ativo,criado_em,atualizado_em) VALUES(v_mid,v_aid,5,'1.1.5','1.1.5. DFD – ETP – Matriz de Risco - TR','','',NULL,'PENDENTE',1,NOW(),NOW());
  INSERT INTO pge_atividades_ato(municipio_id,ato_legado_id,ordem,codigo,titulo,descricao,responsavel,prazo_texto,status_execucao,ativo,criado_em,atualizado_em) VALUES(v_mid,v_aid,6,'1.1.6','1.1.6. Ofício encaminhando para análise jurídica','','',NULL,'PENDENTE',1,NOW(),NOW());
  INSERT INTO pge_atividades_ato(municipio_id,ato_legado_id,ordem,codigo,titulo,descricao,responsavel,prazo_texto,status_execucao,ativo,criado_em,atualizado_em) VALUES(v_mid,v_aid,7,'1.1.7','1.1.7. Parecer Jurídico','','',NULL,'PENDENTE',1,NOW(),NOW());
  INSERT INTO pge_atividades_ato(municipio_id,ato_legado_id,ordem,codigo,titulo,descricao,responsavel,prazo_texto,status_execucao,ativo,criado_em,atualizado_em) VALUES(v_mid,v_aid,8,'1.1.8','1.1.8. Despacho de saneamento do dirigente para contratação','','',NULL,'PENDENTE',1,NOW(),NOW());
  SET v_max=v_max+1;
  INSERT INTO fases(municipio_id,etapa_execucao_id,ordem,codigo,aba,titulo,descricao,responsavel,dia_inicio,dia_fim,entregavel,criterio,exclusivo_stratelli,ativo,ativo_individual,criado_em,atualizado_em) VALUES(v_mid,v_new,v_max,'EMDIR-F2','2. FASE HOMOLOGAÇÃO E CONTRATAÇÃO','2. FASE HOMOLOGAÇÃO E CONTRATAÇÃO','','',1,1,'','',0,1,1,NOW(),NOW());
  SET v_fid=LAST_INSERT_ID();
  INSERT INTO atividades_fase(municipio_id,fase_id,ordem,codigo,titulo,descricao,responsavel,prazo_tipo,prazo_referencia,fundamento_normativo,entregavel,obrigatoria,status_execucao,ativo,criado_em,atualizado_em) VALUES(v_mid,v_fid,1,'EMDIR-2.1','2.1. Homologação, convocação para assinatura do contrato, POC','','','SEM_PRAZO',NULL,'Edital; art. 107, §6º','',1,'PENDENTE',1,NOW(),NOW());
  SET v_aid=LAST_INSERT_ID();
  SET v_max=v_max+1;
  INSERT INTO fases(municipio_id,etapa_execucao_id,ordem,codigo,aba,titulo,descricao,responsavel,dia_inicio,dia_fim,entregavel,criterio,exclusivo_stratelli,ativo,ativo_individual,criado_em,atualizado_em) VALUES(v_mid,v_new,v_max,'EMDIR-F3','3. FASE - GESTÃO CONTRATUAL','3. FASE - GESTÃO CONTRATUAL','','',1,1,'','',0,1,1,NOW(),NOW());
  SET v_fid=LAST_INSERT_ID();
  INSERT INTO atividades_fase(municipio_id,fase_id,ordem,codigo,titulo,descricao,responsavel,prazo_tipo,prazo_referencia,fundamento_normativo,entregavel,obrigatoria,status_execucao,ativo,criado_em,atualizado_em) VALUES(v_mid,v_fid,1,'EMDIR-3.1','3.1. GERL e comissão de fiscalização; gestão de riscos e LGPD','','','SEM_PRAZO','vigência do contrato','Art. 117; LGPD','',1,'PENDENTE',1,NOW(),NOW());
  SET v_aid=LAST_INSERT_ID();
  COMMIT;
END$$
DELIMITER ;
CALL pge_v35_migrar_etapa5();
DROP PROCEDURE IF EXISTS pge_v35_migrar_etapa5;
-- Conferencia final (esperado: 1 ativa, 1 inativa; 3 fases, 3 atos e 8 atividades na Emergencial).
SELECT e.id,e.nome,e.numero,e.habilitada,COUNT(DISTINCT f.id) fases,COUNT(DISTINCT a.id) atos,COUNT(DISTINCT v.id) atividades FROM pge_etapas_execucao e LEFT JOIN fases f ON f.etapa_execucao_id=e.id LEFT JOIN atividades_fase a ON a.fase_id=f.id LEFT JOIN pge_atividades_ato v ON v.ato_legado_id=a.id WHERE e.municipio_id=(SELECT id FROM municipios WHERE slug='maringa' LIMIT 1) AND e.status<>'ARQUIVADO' GROUP BY e.id,e.nome,e.numero,e.habilitada ORDER BY e.habilitada DESC,e.id;
-- Checagem de prazo formal por modalidade (a nova deve ficar sem documento ate novo registro).
SELECT e.id,e.nome,e.habilitada,COUNT(i.id) inicios_formais FROM pge_etapas_execucao e LEFT JOIN pge_inicios_formais i ON i.etapa_execucao_id=e.id AND i.status='ATIVO' WHERE e.municipio_id=(SELECT id FROM municipios WHERE slug='maringa' LIMIT 1) AND e.status<>'ARQUIVADO' GROUP BY e.id,e.nome,e.habilitada;
