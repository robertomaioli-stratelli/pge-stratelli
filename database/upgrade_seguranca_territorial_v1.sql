-- PGE - Inteligência Territorial
-- Histórico de Situação de Segurança Pública
-- Opcional: a aplicação também cria esta tabela automaticamente ao abrir o módulo territorial.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS historico_status_territorial (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    municipio_id BIGINT UNSIGNED NOT NULL,
    objeto_territorial_id BIGINT UNSIGNED NOT NULL,
    status_anterior ENUM('ATIVO','ATENCAO','CRITICO','CONCLUIDO','INATIVO') NULL,
    status_novo ENUM('ATIVO','ATENCAO','CRITICO','CONCLUIDO','INATIVO') NOT NULL,
    categoria_motivo VARCHAR(80) NOT NULL,
    motivo VARCHAR(500) NOT NULL,
    observacao TEXT NULL,
    data_ocorrencia DATE NOT NULL,
    usuario_id BIGINT UNSIGNED NULL,
    criado_em DATETIME NOT NULL,
    INDEX idx_hist_status_territorial_objeto(municipio_id,objeto_territorial_id,id),
    INDEX idx_hist_status_territorial_status(municipio_id,status_novo,data_ocorrencia),
    CONSTRAINT fk_hist_status_territorial_municipio FOREIGN KEY(municipio_id) REFERENCES municipios(id) ON DELETE CASCADE,
    CONSTRAINT fk_hist_status_territorial_objeto FOREIGN KEY(objeto_territorial_id,municipio_id) REFERENCES objetos_territoriais(id,municipio_id) ON DELETE CASCADE,
    CONSTRAINT fk_hist_status_territorial_usuario FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
