-- PGE Stratelli - registros de envio de notificacoes (MariaDB 10.11)
-- Executar apos backup. Nao altera dados das tabelas existentes.
CREATE TABLE IF NOT EXISTS pge_email_notificacoes (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 municipio_id BIGINT UNSIGNED NOT NULL,
 etapa_execucao_id BIGINT UNSIGNED NOT NULL,
 requisito_id BIGINT UNSIGNED DEFAULT NULL,
 nivel VARCHAR(15) DEFAULT NULL,
 item_id BIGINT UNSIGNED DEFAULT NULL,
 evento VARCHAR(32) NOT NULL,
 destinatario VARCHAR(254) NOT NULL,
 chave_evento CHAR(64) NOT NULL,
 status ENUM('RESERVADO','ENVIADO','FALHOU') NOT NULL DEFAULT 'RESERVADO',
 detalhe_erro VARCHAR(255) DEFAULT NULL,
 criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 enviado_em DATETIME DEFAULT NULL,
 PRIMARY KEY (id), UNIQUE KEY uq_pge_email_evento_dest (chave_evento,destinatario),
 KEY idx_pge_email_municipio (municipio_id,criado_em),
 KEY idx_pge_email_status (status,criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
