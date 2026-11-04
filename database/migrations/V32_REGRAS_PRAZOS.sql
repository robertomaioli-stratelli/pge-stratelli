-- PGE V32: Motor de prazo parametrizavel, sem alteracoes destrutivas.
-- Aplicar uma vez apos backup do banco. Uso do municipio da sessao.
CREATE TABLE IF NOT EXISTS pge_prazo_regras (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  municipio_id BIGINT UNSIGNED NOT NULL,
  nivel ENUM('FASE','ATO','ATIVIDADE') NOT NULL,
  item_id BIGINT UNSIGNED NOT NULL,
  marco_tipo ENUM('INICIO_FORMAL','DATA_FIXA','CONCLUSAO_ATO','CONCLUSAO_FASE') NOT NULL,
  marco_id BIGINT UNSIGNED NULL,
  data_fixa DATE NULL,
  quantidade INT UNSIGNED NOT NULL DEFAULT 0,
  unidade ENUM('DIAS_CORRIDOS','DIAS_UTEIS') NOT NULL DEFAULT 'DIAS_CORRIDOS',
  direcao ENUM('APOS','ANTES') NOT NULL DEFAULT 'APOS',
  titulo_referencia VARCHAR(255) NULL,
  usuario_id BIGINT UNSIGNED NULL,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pge_prazo_item (municipio_id,nivel,item_id),
  KEY idx_pge_prazo_marco (municipio_id,marco_tipo,marco_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS pge_prazo_resultados (
  municipio_id BIGINT UNSIGNED NOT NULL,
  nivel ENUM('FASE','ATO','ATIVIDADE') NOT NULL,
  item_id BIGINT UNSIGNED NOT NULL,
  inicio_previsto DATE NULL,
  fim_previsto DATE NULL,
  situacao ENUM('CALCULADO','AGUARDANDO_MARCO','AGUARDANDO_INICIO','REGRA_INCOMPLETA') NOT NULL,
  fundamento_calculo VARCHAR(255) NULL,
  calculado_em DATETIME NOT NULL,
  PRIMARY KEY(municipio_id,nivel,item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
