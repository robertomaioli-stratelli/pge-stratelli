-- PGE V39 | Reiniciar o marco formal de TESTE de Maringa.
-- Executar SOMENTE no pge_db apos backup completo.
-- Nao exclui PDFs fisicos e preserva registro de auditoria.
-- Requer a migracao V38 concluida (coluna etapa_execucao_id).
-- O script interrompe se encontrar qualquer outro inicio ativo de Maringa.
USE `pge_db`;
DELIMITER $$
DROP PROCEDURE IF EXISTS `pge_v39_retirar_inicio_teste`$$
CREATE PROCEDURE `pge_v39_retirar_inicio_teste`()
BEGIN
  DECLARE v_mid BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_total INT DEFAULT 0;
  DECLARE v_teste INT DEFAULT 0;
  DECLARE v_cp INT DEFAULT 0;
  DECLARE v_etapa BIGINT UNSIGNED DEFAULT NULL;

  SELECT MIN(id) INTO v_mid FROM municipios WHERE slug='maringa';
  SELECT COUNT(*) INTO v_total FROM municipios WHERE slug='maringa';
  IF v_total<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='V39: municipio Maringa nao identificado unicamente'; END IF;

  SELECT COUNT(*) INTO v_total FROM pge_inicios_formais
   WHERE municipio_id=v_mid AND status='ATIVO';
  SELECT COUNT(*) INTO v_teste FROM pge_inicios_formais
   WHERE municipio_id=v_mid AND status='ATIVO'
     AND referencia='Ofício 123/2026' AND data_inicio='2026-11-04';
  IF v_total<>1 OR v_teste<>1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='V39: inicio formal ativo diverge do teste; nenhuma alteracao aplicada';
  END IF;

  -- Confirma que o documento de teste pertence a modalidade inativa, nao a nova Emergencial.
  SELECT i.etapa_execucao_id INTO v_etapa
  FROM pge_inicios_formais i
  WHERE i.municipio_id=v_mid AND i.status='ATIVO'
    AND i.referencia='Ofício 123/2026' AND i.data_inicio='2026-11-04'
  LIMIT 1;
  IF v_etapa IS NULL OR NOT EXISTS (
    SELECT 1 FROM pge_etapas_execucao e
     WHERE e.id=v_etapa AND e.municipio_id=v_mid AND e.habilitada=0
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='V39: o marco nao pertence a modalidade inativa; revisar';
  END IF;

  SELECT COUNT(*) INTO v_cp FROM cronograma_processos WHERE municipio_id=v_mid;
  IF v_cp>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='V39: multiplas datas legadas para Maringa; revisar'; END IF;

  START TRANSACTION;
  -- Invalidacao reversivel, mantendo referencia, metadados, checksum e arquivo para auditoria.
  UPDATE pge_inicios_formais
    SET status='RETIFICADO',
        observacao=CONCAT(COALESCE(NULLIF(observacao,''),''),
          '\n[REINICIALIZACAO V39] Documento utilizado apenas em teste; sem eficacia como marco de inicio. Novo documento formal exigido.')
  WHERE municipio_id=v_mid AND status='ATIVO'
    AND referencia='Ofício 123/2026' AND data_inicio='2026-11-04'
    AND etapa_execucao_id=v_etapa;
  IF ROW_COUNT()<>1 THEN
    ROLLBACK;
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='V39: falha ao retificar marco de teste';
  END IF;

  -- Impede que os modulos antigos herdem um inicio tecnico anterior.
  DELETE FROM cronograma_processos WHERE municipio_id=v_mid;

  -- Resultados calculados a partir de regras ativas nao devem permanecer vigentes.
  -- Somente resultados derivados sao desprogramados; catalogos e regras sao preservados.
  UPDATE pge_prazo_resultados
     SET inicio_previsto=NULL, fim_previsto=NULL,
         situacao='AGUARDANDO_INICIO',
         fundamento_calculo='Aguardando novo documento formal de inicio',
         calculado_em=NOW()
   WHERE municipio_id=v_mid AND situacao IN ('CALCULADO','AGUARDANDO_INICIO');
  COMMIT;
END$$
DELIMITER ;
CALL `pge_v39_retirar_inicio_teste`();
DROP PROCEDURE IF EXISTS `pge_v39_retirar_inicio_teste`;

-- Conferencia final: 0 registros ATIVO para Maringa.
SELECT m.nome, i.id, i.referencia, i.data_inicio, i.status, i.etapa_execucao_id, i.arquivo_salvo
FROM pge_inicios_formais i JOIN municipios m ON m.id=i.municipio_id
WHERE m.slug='maringa' ORDER BY i.id DESC;
SELECT COUNT(*) AS inicios_formais_ativos_maringa
FROM pge_inicios_formais i JOIN municipios m ON m.id=i.municipio_id
WHERE m.slug='maringa' AND i.status='ATIVO';
SELECT COUNT(*) AS cronogramas_legados_maringa
FROM cronograma_processos c JOIN municipios m ON m.id=c.municipio_id
WHERE m.slug='maringa';
