-- PGE Stratelli V38 - ATUALIZACAO INCREMENTAL NO BANCO EXISTENTE
-- Origem: dumps pge_db_PRODUCAO.sql e stratelli_LOCAL.sql fornecidos em 06/11/2026.
-- Objetivo: modalidade ETAPA 5 Emergencial Direta; manter usuarios, credenciais,
-- auditoria, documentos publicados e demais dados exclusivos de producao.
-- MariaDB 10.11 (producao). NAO execute em banco local.
-- ALERTA: ALTER TABLE executa COMMIT implicito; backup previo obrigatorio.
-- NAO reexecute se houver erro ou interrupcao: restaure backup ou solicite revisao.
USE `pge_db`;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- [1] PRE-CHECAGEM BLOQUEANTE (antes de qualquer ALTER TABLE)
DELIMITER $$
DROP PROCEDURE IF EXISTS `pge_v38_precheck`$$
CREATE PROCEDURE `pge_v38_precheck`()
BEGIN
  DECLARE n INT DEFAULT 0;
  SELECT COUNT(*) INTO n FROM information_schema.TABLES
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN
   ('usuarios','municipios','pge_processos_execucao','pge_etapas_execucao','fases','atividades_fase',
    'pge_atividades_ato','pge_inicios_formais','requisitos_documentais','modelos_documentos','historico_documentos',
    'fase_secretarias','atividade_secretarias');
  IF n <> 13 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='V38: tabelas esperadas ausentes; nao prossiga'; END IF;
  SELECT COUNT(*) INTO n FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND
    ((TABLE_NAME='pge_etapas_execucao' AND COLUMN_NAME='habilitada') OR
     (TABLE_NAME='fases' AND COLUMN_NAME='ativo_individual') OR
     (TABLE_NAME='pge_inicios_formais' AND COLUMN_NAME='etapa_execucao_id'));
  IF n <> 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='V38: esquema ja migrado/parcial; nao repetir'; END IF;
  SELECT COUNT(*) INTO n FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pge_etapas_execucao'
      AND INDEX_NAME='uq_pge_etapa_processo_numero';
  IF n <> 2 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='V38: chave unica antiga inesperada'; END IF;
  SELECT COUNT(*) INTO n FROM `usuarios`;
  IF n <> 8 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='V38: usuarios de producao mudaram; revisar antes'; END IF;
  SELECT COUNT(*) INTO n FROM `usuarios`
    WHERE (id=1 AND email='caio.toyoda@stratelli.com.br')
       OR (id=2 AND email='admin@stratelli.com.br')
       OR (id=10 AND email='gestor@maringa.local')
       OR (id=14 AND email='secseg@maringa.local');
  IF n <> 4 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='V38: IDs/emails dos usuarios divergentes'; END IF;
  SELECT COUNT(*) INTO n FROM `municipios` WHERE id=1 AND slug='maringa';
  IF n <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='V38: municipio Maringa divergente'; END IF;
  SELECT COUNT(*) INTO n FROM `pge_processos_execucao` WHERE id=6 AND municipio_id=1;
  IF n <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='V38: processo 6 nao localizado'; END IF;
  SELECT COUNT(*) INTO n FROM `pge_etapas_execucao`
    WHERE id=6 AND processo_id=6 AND municipio_id=1 AND numero=1 AND nome='ETAPA 1';
  IF n <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='V38: ETAPA 1 atual divergente'; END IF;
  SELECT COUNT(*) INTO n FROM `pge_etapas_execucao` WHERE id=8;
  IF n <> 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='V38: ETAPA 8 ja existe'; END IF;
  SELECT COUNT(*) INTO n FROM `fases` WHERE id IN (30,31,32) OR (municipio_id=1 AND codigo IN ('EMDIR-F1','EMDIR-F2','EMDIR-F3'));
  IF n <> 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='V38: fases emergenciais ja existem'; END IF;
  SELECT COUNT(*) INTO n FROM `atividades_fase` WHERE id IN (99,100,101) OR (municipio_id=1 AND codigo IN ('EMDIR-1.1','EMDIR-2.1','EMDIR-3.1'));
  IF n <> 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='V38: atos emergenciais ja existem'; END IF;
  SELECT COUNT(*) INTO n FROM `pge_atividades_ato` WHERE id BETWEEN 23 AND 30;
  IF n <> 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='V38: IDs de atividades emergenciais ocupados'; END IF;
  SELECT COUNT(*) INTO n FROM `pge_inicios_formais` WHERE municipio_id=1;
  IF n <> 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='V38: inicio formal ja registrado na producao; revisar'; END IF;
  SELECT COUNT(*) INTO n FROM `requisitos_documentais` WHERE id=30;
  IF n <> 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='V38: documento 30 ja existe'; END IF;
  SELECT COUNT(*) INTO n FROM `modelos_documentos` WHERE id=3;
  IF n <> 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='V38: modelo 3 ja existe'; END IF;
  SELECT COUNT(*) INTO n FROM `historico_documentos` WHERE id=11;
  IF n <> 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='V38: historico 11 ja existe'; END IF;
END$$
DELIMITER ;
CALL `pge_v38_precheck`();
DROP PROCEDURE `pge_v38_precheck`;

-- [2] SOMENTE ALTERACOES ESTRUTURAIS IDENTIFICADAS
-- Alterar a chave para permitir diferentes modalidades do mesmo numero.
ALTER TABLE `pge_etapas_execucao`
 ADD COLUMN `habilitada` TINYINT(1) NOT NULL DEFAULT 1 AFTER `status`,
 DROP INDEX `uq_pge_etapa_processo_numero`,
 ADD UNIQUE KEY `uq_pge_etapa_modalidade` (`processo_id`,`numero`,`nome`);
ALTER TABLE `fases`
 ADD COLUMN `ativo_individual` TINYINT(1) NOT NULL DEFAULT 1 AFTER `ativo`;
ALTER TABLE `pge_inicios_formais`
 ADD COLUMN `etapa_execucao_id` BIGINT(20) UNSIGNED DEFAULT NULL AFTER `municipio_id`;

-- [3] DADOS OPERACIONAIS NOVOS, DENTRO DE UMA TRANSACAO
-- Usuários existentes NAO sao alterados; os IDs referenciais do local foram mapeados.
START TRANSACTION;

-- O antigo dialogo passa a modalidade Etapa 5 inativa.
UPDATE `pge_etapas_execucao`
SET numero=5, nome='ETAPA 5 - CONTRATAÇÃO - DIÁLOGO COMPETITIVO', habilitada=0,
    atualizado_em='2026-11-06 14:46:17'
WHERE id=6 AND processo_id=6 AND municipio_id=1;
UPDATE `fases` SET ativo=0, ativo_individual=1,
    atualizado_em='2026-11-06 14:46:17'
WHERE municipio_id=1 AND id IN (24,25,26,27,28,29) AND etapa_execucao_id=6;

-- Nova modalidade da Etapa 5, com as 3 fases, 3 atos e 8 atividades do banco local.
INSERT INTO `pge_etapas_execucao` (`id`, `processo_id`, `municipio_id`, `modelo_etapa_id`, `numero`, `nome`, `status`, `habilitada`, `criado_em`, `atualizado_em`) VALUES
(8, 6, 1, NULL, 5, 'ETAPA 5 - CONTRATAÇÃO - EMERGENCIAL DIRETA', 'EM_ANDAMENTO', 1, '2026-11-06 14:29:16', '2026-11-06 14:46:17');

INSERT INTO `fases` (`id`, `municipio_id`, `ordem`, `codigo`, `aba`, `titulo`, `descricao`, `responsavel`, `dia_inicio`, `dia_fim`, `entregavel`, `criterio`, `exclusivo_stratelli`, `ativo`, `ativo_individual`, `criado_em`, `atualizado_em`, `etapa_execucao_id`) VALUES
(30, 1, 20, 'EMDIR-F1', '1. FASE PREPARATÓRIA', '1. FASE PREPARATÓRIA', '', '', 1, 1, '', '', 0, 1, 1, '2026-11-06 14:29:16', '2026-11-06 14:46:17', 8),
(31, 1, 21, 'EMDIR-F2', '2. FASE HOMOLOGAÇÃO E CONTRATAÇÃO', '2. FASE HOMOLOGAÇÃO E CONTRATAÇÃO', '', '', 1, 1, '', '', 0, 1, 1, '2026-11-06 14:29:16', '2026-11-06 14:46:17', 8),
(32, 1, 22, 'EMDIR-F3', '3. FASE - GESTÃO CONTRATUAL', '3. FASE - GESTÃO CONTRATUAL', '', '', 1, 1, '', '', 0, 1, 1, '2026-11-06 14:29:16', '2026-11-06 14:46:17', 8);

INSERT INTO `atividades_fase` (`id`, `municipio_id`, `fase_id`, `ordem`, `codigo`, `titulo`, `descricao`, `responsavel`, `prazo_quantidade`, `prazo_tipo`, `prazo_referencia`, `fundamento_normativo`, `entregavel`, `criterio`, `obrigatoria`, `status_execucao`, `concluida_em`, `concluida_por_usuario_id`, `ativo`, `criado_em`, `atualizado_em`, `prazo_direcao`, `prazo_marco_data`, `inicio_previsto`, `fim_previsto`, `inicio_real`, `fim_real`) VALUES
(99, 1, 30, 1, 'EMDIR-1.1', '1.1. Preparação Documental da fase interna do certame', '', '', NULL, 'SEM_PRAZO', 'Necessário à maturidade do processo, aprovações externas jurídicas e de órgãos de controle', 'Edital cap. 2; art. 32, art 53', '', NULL, 1, 'PENDENTE', NULL, NULL, 1, '2026-11-06 14:29:16', '2026-11-06 14:29:16', 'NAO_APLICAVEL', NULL, NULL, NULL, NULL, NULL),
(100, 1, 31, 1, 'EMDIR-2.1', '2.1. Homologação, convocação para assinatura do contrato, POC', '', '', NULL, 'SEM_PRAZO', NULL, 'Edital; art. 107, §6º', '', NULL, 1, 'PENDENTE', NULL, NULL, 1, '2026-11-06 14:29:16', '2026-11-06 14:29:16', 'NAO_APLICAVEL', NULL, NULL, NULL, NULL, NULL),
(101, 1, 32, 1, 'EMDIR-3.1', '3.1. GERL e comissão de fiscalização; gestão de riscos e LGPD', '', '', NULL, 'SEM_PRAZO', 'vigência do contrato', 'Art. 117; LGPD', '', NULL, 1, 'PENDENTE', NULL, NULL, 1, '2026-11-06 14:29:16', '2026-11-06 14:29:16', 'NAO_APLICAVEL', NULL, NULL, NULL, NULL, NULL);

INSERT INTO `pge_atividades_ato` (`id`, `municipio_id`, `ato_legado_id`, `modelo_atividade_id`, `ordem`, `codigo`, `titulo`, `descricao`, `responsavel`, `prazo_texto`, `status_execucao`, `concluida_em`, `concluida_por_usuario_id`, `ativo`, `criado_em`, `atualizado_em`) VALUES
(23, 1, 99, NULL, 1, '1.1.1', '1.1.1. Ofício InPACTA à prefeitura. (modelo no drive com número 1)', '', '', NULL, 'PENDENTE', NULL, NULL, 1, '2026-11-06 14:29:16', '2026-11-06 14:29:16'),
(24, 1, 99, NULL, 2, '1.1.2', '1.1.2. Ofício da Prefeitura à UASG que executará o processo.', '', '', NULL, 'PENDENTE', NULL, NULL, 1, '2026-11-06 14:29:16', '2026-11-06 14:29:16'),
(25, 1, 99, NULL, 3, '1.1.3', '1.1.3. Memorando do chefe da UASG ao dirigente da UASG solicitando abertura do certame.', '', '', NULL, 'PENDENTE', NULL, NULL, 1, '2026-11-06 14:29:16', '2026-11-06 14:29:16'),
(26, 1, 99, NULL, 4, '1.1.4', '1.1.4. Despacho de abertura do dirigente.', '', '', NULL, 'PENDENTE', NULL, NULL, 1, '2026-11-06 14:29:16', '2026-11-06 14:29:16'),
(27, 1, 99, NULL, 5, '1.1.5', '1.1.5. DFD – ETP – Matriz de Risco - TR', '', '', NULL, 'PENDENTE', NULL, NULL, 1, '2026-11-06 14:29:16', '2026-11-06 14:29:16'),
(28, 1, 99, NULL, 6, '1.1.6', '1.1.6. Ofício encaminhando para análise jurídica', '', '', NULL, 'PENDENTE', NULL, NULL, 1, '2026-11-06 14:29:16', '2026-11-06 14:29:16'),
(29, 1, 99, NULL, 7, '1.1.7', '1.1.7. Parecer Jurídico', '', '', NULL, 'PENDENTE', NULL, NULL, 1, '2026-11-06 14:29:16', '2026-11-06 14:29:16'),
(30, 1, 99, NULL, 8, '1.1.8', '1.1.8. Despacho de saneamento do dirigente para contratação', '', '', NULL, 'PENDENTE', NULL, NULL, 1, '2026-11-06 14:29:16', '2026-11-06 14:29:16');

-- Vínculos locais da fase e do ato 75 com a Secretaria de Seguranca.
INSERT INTO `fase_secretarias` (`municipio_id`, `fase_id`, `secretaria_id`) VALUES
(1, 24, 2),
(1, 24, 50);

INSERT INTO `atividade_secretarias` (`municipio_id`, `atividade_id`, `secretaria_id`) VALUES
(1, 75, 2),
(1, 75, 50);

-- Cadastro documental adicional do local (relacionado ao dialogo inativo).
-- O arquivo fisico do modelo deve ser transferido separadamente para o servidor.
INSERT INTO `requisitos_documentais` (`id`, `municipio_id`, `fase_id`, `atividade_id`, `secretaria_id`, `departamento_id`, `tipo_documento_id`, `nome`, `descricao`, `perfil_envio`, `obrigatorio`, `ativo`, `ordem`, `criado_em`, `atualizado_em`, `atividade_ato_id`) VALUES
(30, 1, 24, 75, 2, NULL, 3, 'Estudo técnico', 'Teste de entrada', 'MUNICIPIO', 1, 1, 1, '2026-11-04 16:45:48', '2026-11-04 17:09:12', NULL);

INSERT INTO `modelos_documentos` (`id`, `municipio_id`, `requisito_id`, `modelo_anterior_id`, `versao`, `arquivo_original`, `arquivo_salvo`, `tamanho`, `mime_type`, `checksum_sha256`, `usuario_id`, `ativo`, `criado_em`) VALUES
(3, 1, 30, NULL, 1, 'pg_0001.pdf', 'modelos/1/modelo-grupo-30_20261104_164548_4c5faeb46d_pg_0001.pdf', 355873, 'application/pdf', 'e0b01e6a7be7ecaf0211de0ff4834ebddb18e148b1ef40abfbafd63d0e794d5e', 2, 1, '2026-11-04 16:45:48');

INSERT INTO `historico_documentos` (`id`, `municipio_id`, `fase_id`, `requisito_id`, `documento_id`, `usuario_id`, `evento`, `tipo_arquivo`, `arquivo_original`, `arquivo_salvo`, `arquivo_anterior_original`, `tamanho`, `mime_type`, `checksum_sha256`, `versao`, `motivo`, `status`, `ip`, `user_agent`, `criado_em`) VALUES
(11, 1, 24, 30, NULL, 2, 'Modelo disponibilizado', 'modelo', 'pg_0001.pdf', 'modelos/1/modelo-grupo-30_20261104_164548_4c5faeb46d_pg_0001.pdf', '', 355873, 'application/pdf', 'e0b01e6a7be7ecaf0211de0ff4834ebddb18e148b1ef40abfbafd63d0e794d5e', 1, 'Modelo disponibilizado pela Stratelli.', 'MODELO', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-11-04 16:45:48');

-- O inicio formal registrado no ambiente local pertence a Etapa 6 (Dialogo Competitivo).
-- Ele NAO ativa o cronograma da nova Emergencial Direta.
INSERT INTO `pge_inicios_formais` (`id`, `municipio_id`, `etapa_execucao_id`, `data_inicio`, `referencia`, `observacao`, `arquivo_original`, `arquivo_salvo`, `arquivo_mime`, `arquivo_tamanho`, `checksum_sha256`, `usuario_id`, `status`, `criado_em`) VALUES
(1, 1, 6, '2026-11-04', 'Ofício 123/2026', 'Teste inicial', '0729GatewayCertificaPDF.aspx.pdf', 'marcos_formais/1/inicio-formal_20261104_163925_836f5945ea_0729GatewayCertificaPDF.aspx.pdf', 'application/pdf', 315654, '5bf03ef4706715e6224849d7194be17791b6edeeee18a99af1d6c2b0c3da2e9b', 2, 'ATIVO', '2026-11-04 16:39:25');

COMMIT;

-- [4] RESULTADOS ESPERADOS (apos COMMIT)
SELECT id,processo_id,numero,nome,habilitada,status
FROM `pge_etapas_execucao` WHERE municipio_id=1 AND processo_id=6 ORDER BY id;
SELECT e.nome, COUNT(DISTINCT f.id) AS fases,
 (SELECT COUNT(*) FROM atividades_fase a JOIN fases ff ON ff.id=a.fase_id WHERE ff.etapa_execucao_id=e.id) AS atos,
 (SELECT COUNT(*) FROM pge_atividades_ato v JOIN atividades_fase a ON a.id=v.ato_legado_id JOIN fases ff ON ff.id=a.fase_id WHERE ff.etapa_execucao_id=e.id) AS atividades
FROM pge_etapas_execucao e LEFT JOIN fases f ON f.etapa_execucao_id=e.id
WHERE e.id IN (6,8) GROUP BY e.id,e.nome ORDER BY e.id;
SELECT COUNT(*) AS usuarios_preservados FROM usuarios;
SELECT id,municipio_id,etapa_execucao_id,data_inicio,referencia FROM pge_inicios_formais;
-- Nao transfere PDFs, modelos ou brasoes fisicos; copiar storage/uploads separadamente.
