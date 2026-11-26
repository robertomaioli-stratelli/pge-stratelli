-- PGE Stratelli - Etapa 1: contatos de secretarias e departamentos
-- Backup obrigatorio antes de executar no banco correto (pge_db).
-- Execute uma unica vez. Preserva os dados existentes.
ALTER TABLE `secretarias`
  ADD COLUMN `cargo_responsavel` VARCHAR(180) NOT NULL DEFAULT '' AFTER `sigla`,
  ADD COLUMN `emails_responsaveis` TEXT NULL AFTER `cargo_responsavel`;

ALTER TABLE `departamentos`
  ADD COLUMN `cargo_responsavel` VARCHAR(180) NOT NULL DEFAULT '' AFTER `sigla`,
  ADD COLUMN `emails_responsaveis` TEXT NULL AFTER `cargo_responsavel`;

SELECT 'secretarias' AS origem, COUNT(*) AS registros FROM `secretarias`
UNION ALL
SELECT 'departamentos', COUNT(*) FROM `departamentos`;
