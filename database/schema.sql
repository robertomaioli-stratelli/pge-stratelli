SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS municipios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(180) NOT NULL,
    uf CHAR(2) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    codigo_ibge VARCHAR(20) NULL,
    populacao BIGINT UNSIGNED NULL,
    area_km2 DECIMAL(12,3) NULL,
    site_oficial VARCHAR(255) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    brasao_path VARCHAR(255) NULL,
    geojson_delimitacao LONGTEXT NULL,
    inteligencia_territorial_ativa TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('NEGOCIACAO','APRESENTACAO','IMPLANTACAO','ATIVO','SUSPENSO','DESATIVADO') NOT NULL DEFAULT 'IMPLANTACAO',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL,
    atualizado_em DATETIME NOT NULL,
    INDEX idx_municipios_status(status,ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS parametros_instancia (
    municipio_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    prazo_alerta_dias INT NOT NULL DEFAULT 3,
    tamanho_maximo_arquivo_mb INT NOT NULL DEFAULT 20,
    extensoes_permitidas VARCHAR(500) NOT NULL DEFAULT 'pdf,doc,docx,xls,xlsx,odt,ods,rtf,txt,png,jpg,jpeg,webp,csv,zip',
    notificacoes_ativas TINYINT(1) NOT NULL DEFAULT 1,
    observacao_envio_obrigatoria TINYINT(1) NOT NULL DEFAULT 0,
    observacao_aprovacao_obrigatoria TINYINT(1) NOT NULL DEFAULT 0,
    observacao_encerramento_obrigatoria TINYINT(1) NOT NULL DEFAULT 1,
    observacao_reabertura_obrigatoria TINYINT(1) NOT NULL DEFAULT 1,
    nome_etapa_atual VARCHAR(120) NOT NULL DEFAULT 'Etapa 1',
    cor_primaria CHAR(7) NOT NULL DEFAULT '#082A55',
    cor_secundaria CHAR(7) NOT NULL DEFAULT '#176FDD',
    estilo_decoracao_cabecalho VARCHAR(20) NOT NULL DEFAULT 'semicirculo',
    criado_em DATETIME NOT NULL,
    atualizado_em DATETIME NOT NULL,
    CONSTRAINT fk_parametros_instancia_municipio FOREIGN KEY(municipio_id) REFERENCES municipios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS secretarias (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    municipio_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(180) NOT NULL,
    sigla VARCHAR(30) NOT NULL DEFAULT '',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL,
    atualizado_em DATETIME NOT NULL,
    UNIQUE KEY uq_secretaria_municipio_nome(municipio_id,nome),
    UNIQUE KEY uq_secretaria_id_municipio(id,municipio_id),
    CONSTRAINT fk_secretaria_municipio FOREIGN KEY(municipio_id) REFERENCES municipios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS departamentos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    municipio_id BIGINT UNSIGNED NOT NULL,
    secretaria_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(180) NOT NULL,
    sigla VARCHAR(30) NOT NULL DEFAULT '',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL,
    atualizado_em DATETIME NOT NULL,
    UNIQUE KEY uq_departamento_municipio_nome(municipio_id,secretaria_id,nome),
    UNIQUE KEY uq_departamento_id_municipio(id,municipio_id),
    CONSTRAINT fk_departamento_secretaria FOREIGN KEY(secretaria_id,municipio_id) REFERENCES secretarias(id,municipio_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuarios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    municipio_id BIGINT UNSIGNED NULL,
    secretaria_id BIGINT UNSIGNED NULL,
    departamento_id BIGINT UNSIGNED NULL,
    nome VARCHAR(180) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    grupo ENUM('ADMINISTRADOR','GESTOR','USUARIO') NOT NULL,
    administrador_plataforma TINYINT(1) NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ultimo_login_em DATETIME NULL,
    criado_em DATETIME NOT NULL,
    atualizado_em DATETIME NOT NULL,
    INDEX idx_usuarios_tenant(municipio_id,grupo,ativo),
    CONSTRAINT fk_usuario_municipio FOREIGN KEY(municipio_id) REFERENCES municipios(id) ON DELETE CASCADE,
    CONSTRAINT fk_usuario_secretaria FOREIGN KEY(secretaria_id,municipio_id) REFERENCES secretarias(id,municipio_id) ON DELETE RESTRICT,
    CONSTRAINT fk_usuario_departamento FOREIGN KEY(departamento_id,municipio_id) REFERENCES departamentos(id,municipio_id) ON DELETE RESTRICT,
    CONSTRAINT chk_usuario_escopo CHECK (
        (administrador_plataforma=1 AND municipio_id IS NULL)
        OR (administrador_plataforma=0 AND municipio_id IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    municipio_id BIGINT UNSIGNED NOT NULL,
    ordem INT NOT NULL,
    codigo VARCHAR(80) NOT NULL,
    aba VARCHAR(120) NOT NULL,
    titulo VARCHAR(180) NOT NULL,
    descricao TEXT NOT NULL,
    responsavel VARCHAR(180) NOT NULL DEFAULT '',
    dia_inicio INT NOT NULL DEFAULT 1,
    dia_fim INT NOT NULL DEFAULT 1,
    entregavel VARCHAR(255) NOT NULL DEFAULT '',
    criterio TEXT NULL,
    exclusivo_stratelli TINYINT(1) NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL,
    atualizado_em DATETIME NOT NULL,
    UNIQUE KEY uq_fase_ordem(municipio_id,ordem),
    UNIQUE KEY uq_fase_codigo(municipio_id,codigo),
    UNIQUE KEY uq_fase_id_municipio(id,municipio_id),
    CONSTRAINT fk_fase_municipio FOREIGN KEY(municipio_id) REFERENCES municipios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fase_secretarias (
    municipio_id BIGINT UNSIGNED NOT NULL,
    fase_id BIGINT UNSIGNED NOT NULL,
    secretaria_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY(municipio_id,fase_id,secretaria_id),
    CONSTRAINT fk_fs_fase FOREIGN KEY(fase_id,municipio_id) REFERENCES fases(id,municipio_id) ON DELETE CASCADE,
    CONSTRAINT fk_fs_secretaria FOREIGN KEY(secretaria_id,municipio_id) REFERENCES secretarias(id,municipio_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tipos_documento (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    municipio_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(180) NOT NULL,
    descricao TEXT NULL,
    extensoes VARCHAR(255) NOT NULL DEFAULT 'pdf,doc,docx,xls,xlsx,odt,ods,rtf,txt',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL,
    atualizado_em DATETIME NOT NULL,
    UNIQUE KEY uq_tipo_doc_nome(municipio_id,nome),
    UNIQUE KEY uq_tipo_doc_id_municipio(id,municipio_id),
    CONSTRAINT fk_tipo_doc_municipio FOREIGN KEY(municipio_id) REFERENCES municipios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS requisitos_documentais (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    municipio_id BIGINT UNSIGNED NOT NULL,
    fase_id BIGINT UNSIGNED NOT NULL,
    secretaria_id BIGINT UNSIGNED NOT NULL,
    departamento_id BIGINT UNSIGNED NULL,
    tipo_documento_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(180) NOT NULL,
    descricao TEXT NULL,
    perfil_envio ENUM('STRATELLI','MUNICIPIO') NOT NULL DEFAULT 'MUNICIPIO',
    obrigatorio TINYINT(1) NOT NULL DEFAULT 1,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ordem INT NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL,
    atualizado_em DATETIME NOT NULL,
    UNIQUE KEY uq_req_id_municipio(id,municipio_id),
    INDEX idx_req_tenant_fase(municipio_id,fase_id,ativo),
    CONSTRAINT fk_req_fase FOREIGN KEY(fase_id,municipio_id) REFERENCES fases(id,municipio_id) ON DELETE CASCADE,
    CONSTRAINT fk_req_secretaria FOREIGN KEY(secretaria_id,municipio_id) REFERENCES secretarias(id,municipio_id) ON DELETE CASCADE,
    CONSTRAINT fk_req_departamento FOREIGN KEY(departamento_id,municipio_id) REFERENCES departamentos(id,municipio_id) ON DELETE RESTRICT,
    CONSTRAINT fk_req_tipo FOREIGN KEY(tipo_documento_id,municipio_id) REFERENCES tipos_documento(id,municipio_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS modelos_documentos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    municipio_id BIGINT UNSIGNED NOT NULL,
    requisito_id BIGINT UNSIGNED NOT NULL,
    modelo_anterior_id BIGINT UNSIGNED NULL,
    versao INT NOT NULL DEFAULT 1,
    arquivo_original VARCHAR(255) NOT NULL,
    arquivo_salvo VARCHAR(500) NOT NULL,
    tamanho BIGINT NOT NULL DEFAULT 0,
    mime_type VARCHAR(160) NULL,
    checksum_sha256 CHAR(64) NULL,
    usuario_id BIGINT UNSIGNED NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL,
    INDEX idx_modelo_tenant_req(municipio_id,requisito_id,ativo),
    UNIQUE KEY uq_modelo_versao(municipio_id,requisito_id,versao),
    INDEX idx_modelo_checksum(municipio_id,checksum_sha256),
    CONSTRAINT fk_modelo_req FOREIGN KEY(requisito_id,municipio_id) REFERENCES requisitos_documentais(id,municipio_id) ON DELETE RESTRICT,
    CONSTRAINT fk_modelo_anterior FOREIGN KEY(modelo_anterior_id) REFERENCES modelos_documentos(id) ON DELETE RESTRICT,
    CONSTRAINT fk_modelo_usuario FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documentos_enviados (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    municipio_id BIGINT UNSIGNED NOT NULL,
    requisito_id BIGINT UNSIGNED NOT NULL,
    documento_anterior_id BIGINT UNSIGNED NULL,
    versao INT NOT NULL DEFAULT 1,
    arquivo_original VARCHAR(255) NOT NULL,
    arquivo_salvo VARCHAR(500) NOT NULL,
    tamanho BIGINT NOT NULL DEFAULT 0,
    mime_type VARCHAR(160) NULL,
    checksum_sha256 CHAR(64) NULL,
    observacao_envio TEXT NULL,
    status ENUM('AGUARDANDO','APROVADO','CORRECAO') NOT NULL DEFAULT 'AGUARDANDO',
    observacao_validacao TEXT NULL,
    enviado_por_usuario_id BIGINT UNSIGNED NULL,
    enviado_em DATETIME NOT NULL,
    validado_por_usuario_id BIGINT UNSIGNED NULL,
    validado_em DATETIME NULL,
    INDEX idx_documento_tenant_req(municipio_id,requisito_id,id),
    UNIQUE KEY uq_documento_versao(municipio_id,requisito_id,versao),
    INDEX idx_documento_checksum(municipio_id,checksum_sha256),
    UNIQUE KEY uq_documento_id_municipio(id,municipio_id),
    CONSTRAINT fk_doc_req FOREIGN KEY(requisito_id,municipio_id) REFERENCES requisitos_documentais(id,municipio_id) ON DELETE RESTRICT,
    CONSTRAINT fk_doc_anterior FOREIGN KEY(documento_anterior_id) REFERENCES documentos_enviados(id) ON DELETE RESTRICT,
    CONSTRAINT fk_doc_enviado_usuario FOREIGN KEY(enviado_por_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_doc_validado_usuario FOREIGN KEY(validado_por_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS historico_documentos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    municipio_id BIGINT UNSIGNED NOT NULL,
    fase_id BIGINT UNSIGNED NULL,
    requisito_id BIGINT UNSIGNED NULL,
    documento_id BIGINT UNSIGNED NULL,
    usuario_id BIGINT UNSIGNED NULL,
    evento VARCHAR(180) NOT NULL,
    tipo_arquivo VARCHAR(60) NOT NULL DEFAULT '',
    arquivo_original VARCHAR(255) NOT NULL DEFAULT '',
    arquivo_salvo VARCHAR(500) NOT NULL DEFAULT '',
    arquivo_anterior_original VARCHAR(255) NOT NULL DEFAULT '',
    tamanho BIGINT NOT NULL DEFAULT 0,
    mime_type VARCHAR(160) NOT NULL DEFAULT '',
    checksum_sha256 CHAR(64) NOT NULL DEFAULT '',
    versao INT NULL,
    motivo TEXT NULL,
    status VARCHAR(60) NOT NULL DEFAULT '',
    criado_em DATETIME NOT NULL,
    INDEX idx_historico_tenant_data(municipio_id,criado_em),
    INDEX idx_historico_documento(documento_id),
    CONSTRAINT fk_hist_municipio FOREIGN KEY(municipio_id) REFERENCES municipios(id) ON DELETE CASCADE,
    CONSTRAINT fk_hist_fase FOREIGN KEY(fase_id,municipio_id) REFERENCES fases(id,municipio_id) ON DELETE RESTRICT,
    CONSTRAINT fk_hist_req FOREIGN KEY(requisito_id,municipio_id) REFERENCES requisitos_documentais(id,municipio_id) ON DELETE RESTRICT,
    CONSTRAINT fk_hist_documento FOREIGN KEY(documento_id) REFERENCES documentos_enviados(id) ON DELETE RESTRICT,
    CONSTRAINT fk_hist_usuario FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TRIGGER IF EXISTS trg_documentos_enviados_no_delete;
CREATE TRIGGER trg_documentos_enviados_no_delete BEFORE DELETE ON documentos_enviados FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Documento auditado: exclusão física não permitida.';

DROP TRIGGER IF EXISTS trg_historico_documentos_no_delete;
CREATE TRIGGER trg_historico_documentos_no_delete BEFORE DELETE ON historico_documentos FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Histórico documental auditado: exclusão física não permitida.';

CREATE TABLE IF NOT EXISTS cronograma_processos (
    municipio_id BIGINT UNSIGNED PRIMARY KEY,
    data_inicio DATE NOT NULL,
    criado_em DATETIME NOT NULL,
    atualizado_em DATETIME NOT NULL,
    CONSTRAINT fk_crono_processo_municipio FOREIGN KEY(municipio_id) REFERENCES municipios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cronograma_fases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    municipio_id BIGINT UNSIGNED NOT NULL,
    fase_id BIGINT UNSIGNED NOT NULL,
    data_conclusao_real DATE NOT NULL,
    concluido_por_usuario_id BIGINT UNSIGNED NULL,
    observacao TEXT NULL,
    status ENUM('ENCERRADA','REABERTA') NOT NULL DEFAULT 'ENCERRADA',
    snapshot_documental LONGTEXT NULL,
    snapshot_sha256 CHAR(64) NULL,
    encerrado_em DATETIME NULL,
    reaberto_por_usuario_id BIGINT UNSIGNED NULL,
    reaberto_em DATETIME NULL,
    motivo_reabertura TEXT NULL,
    criado_em DATETIME NOT NULL,
    atualizado_em DATETIME NOT NULL,
    UNIQUE KEY uq_crono_fase(municipio_id,fase_id),
    INDEX idx_crono_fase_status(municipio_id,status,fase_id),
    CONSTRAINT fk_crono_fase FOREIGN KEY(fase_id,municipio_id) REFERENCES fases(id,municipio_id) ON DELETE RESTRICT,
    CONSTRAINT fk_crono_usuario FOREIGN KEY(concluido_por_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_crono_reaberto_usuario FOREIGN KEY(reaberto_por_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS historico_fases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    municipio_id BIGINT UNSIGNED NOT NULL,
    fase_id BIGINT UNSIGNED NOT NULL,
    cronograma_fase_id BIGINT UNSIGNED NULL,
    evento ENUM('ENCERRAMENTO','REABERTURA') NOT NULL,
    usuario_id BIGINT UNSIGNED NULL,
    data_referencia DATE NULL,
    observacao TEXT NULL,
    snapshot_documental LONGTEXT NULL,
    snapshot_sha256 CHAR(64) NULL,
    criado_em DATETIME NOT NULL,
    INDEX idx_hist_fase_tenant(municipio_id,fase_id,criado_em),
    CONSTRAINT fk_hist_fase_municipio FOREIGN KEY(municipio_id) REFERENCES municipios(id) ON DELETE RESTRICT,
    CONSTRAINT fk_hist_fase_fase FOREIGN KEY(fase_id,municipio_id) REFERENCES fases(id,municipio_id) ON DELETE RESTRICT,
    CONSTRAINT fk_hist_fase_crono FOREIGN KEY(cronograma_fase_id) REFERENCES cronograma_fases(id) ON DELETE RESTRICT,
    CONSTRAINT fk_hist_fase_usuario FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TRIGGER IF EXISTS trg_historico_fases_no_delete;
CREATE TRIGGER trg_historico_fases_no_delete BEFORE DELETE ON historico_fases FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Histórico de encerramento de fase: exclusão física não permitida.';

DROP TRIGGER IF EXISTS trg_historico_fases_no_update;
CREATE TRIGGER trg_historico_fases_no_update BEFORE UPDATE ON historico_fases FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Histórico de encerramento de fase: alteração de evento auditado não permitida.';

CREATE TABLE IF NOT EXISTS arquivamentos_etapa (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    municipio_id BIGINT UNSIGNED NOT NULL,
    etapa_numero TINYINT UNSIGNED NOT NULL DEFAULT 1,
    nome VARCHAR(180) NOT NULL,
    versao_pacote INT UNSIGNED NOT NULL DEFAULT 1,
    arquivo_path VARCHAR(500) NOT NULL,
    arquivo_nome VARCHAR(255) NOT NULL,
    checksum_sha256 CHAR(64) NOT NULL,
    tamanho BIGINT UNSIGNED NOT NULL DEFAULT 0,
    resumo_json LONGTEXT NULL,
    manifesto_json LONGTEXT NULL,
    arquivado_por_usuario_id BIGINT UNSIGNED NULL,
    arquivado_em DATETIME NOT NULL,
    criado_em DATETIME NOT NULL,
    UNIQUE KEY uq_arquivamento_etapa(municipio_id,etapa_numero),
    INDEX idx_arquivamento_municipio(municipio_id,etapa_numero,arquivado_em),
    CONSTRAINT fk_arquivamento_municipio FOREIGN KEY(municipio_id) REFERENCES municipios(id) ON DELETE RESTRICT,
    CONSTRAINT fk_arquivamento_usuario FOREIGN KEY(arquivado_por_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auditoria (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    municipio_id BIGINT UNSIGNED NULL,
    usuario_id BIGINT UNSIGNED NULL,
    evento VARCHAR(120) NOT NULL,
    detalhes TEXT NULL,
    ip VARCHAR(64) NOT NULL DEFAULT '',
    user_agent VARCHAR(500) NOT NULL DEFAULT '',
    criado_em DATETIME NOT NULL,
    INDEX idx_auditoria_tenant_data(municipio_id,criado_em),
    CONSTRAINT fk_auditoria_municipio FOREIGN KEY(municipio_id) REFERENCES municipios(id) ON DELETE SET NULL,
    CONSTRAINT fk_auditoria_usuario FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS notificacoes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id BIGINT UNSIGNED NOT NULL,
    municipio_id BIGINT UNSIGNED NULL,
    tipo VARCHAR(60) NOT NULL,
    icone VARCHAR(30) NOT NULL DEFAULT '•',
    titulo VARCHAR(180) NOT NULL,
    mensagem TEXT NOT NULL,
    link VARCHAR(500) NOT NULL DEFAULT '',
    prioridade INT NOT NULL DEFAULT 100,
    chave_evento VARCHAR(190) NULL,
    lida_em DATETIME NULL,
    criado_em DATETIME NOT NULL,
    UNIQUE KEY uq_notificacao_usuario_evento(usuario_id,chave_evento),
    INDEX idx_notificacao_usuario_lida(usuario_id,lida_em,criado_em),
    INDEX idx_notificacao_municipio(municipio_id,criado_em),
    INDEX idx_notificacao_tipo(usuario_id,tipo,criado_em),
    CONSTRAINT fk_notificacao_usuario FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_notificacao_municipio FOREIGN KEY(municipio_id) REFERENCES municipios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS camadas_territoriais (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    municipio_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(180) NOT NULL,
    descricao TEXT NULL,
    categoria VARCHAR(120) NOT NULL DEFAULT '',
    tipo_geometria ENUM('POINT','LINESTRING','POLYGON','MIXED') NOT NULL DEFAULT 'POINT',
    secretaria_id BIGINT UNSIGNED NULL,
    icone VARCHAR(60) NOT NULL DEFAULT 'pin',
    cor VARCHAR(16) NOT NULL DEFAULT '#176fdd',
    ordem INT NOT NULL DEFAULT 1,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL,
    atualizado_em DATETIME NOT NULL,
    UNIQUE KEY uq_camada_territorial_nome(municipio_id,nome),
    UNIQUE KEY uq_camada_territorial_id_municipio(id,municipio_id),
    INDEX idx_camada_territorial_tenant(municipio_id,ativo,ordem),
    CONSTRAINT fk_camada_territorial_municipio FOREIGN KEY(municipio_id) REFERENCES municipios(id) ON DELETE CASCADE,
    CONSTRAINT fk_camada_territorial_secretaria FOREIGN KEY(secretaria_id,municipio_id) REFERENCES secretarias(id,municipio_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS objetos_territoriais (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    municipio_id BIGINT UNSIGNED NOT NULL,
    camada_id BIGINT UNSIGNED NOT NULL,
    secretaria_id BIGINT UNSIGNED NULL,
    departamento_id BIGINT UNSIGNED NULL,
    nome VARCHAR(220) NOT NULL,
    descricao TEXT NULL,
    endereco VARCHAR(300) NOT NULL DEFAULT '',
    status ENUM('ATIVO','ATENCAO','CRITICO','CONCLUIDO','INATIVO') NOT NULL DEFAULT 'ATIVO',
    geometria_geojson LONGTEXT NOT NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    atributos_json LONGTEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_por_usuario_id BIGINT UNSIGNED NULL,
    atualizado_por_usuario_id BIGINT UNSIGNED NULL,
    criado_em DATETIME NOT NULL,
    atualizado_em DATETIME NOT NULL,
    UNIQUE KEY uq_objeto_territorial_id_municipio(id,municipio_id),
    INDEX idx_objeto_territorial_tenant_camada(municipio_id,camada_id,ativo),
    INDEX idx_objeto_territorial_secretaria(municipio_id,secretaria_id,departamento_id),
    INDEX idx_objeto_territorial_status(municipio_id,status,ativo),
    CONSTRAINT fk_objeto_territorial_municipio FOREIGN KEY(municipio_id) REFERENCES municipios(id) ON DELETE CASCADE,
    CONSTRAINT fk_objeto_territorial_camada FOREIGN KEY(camada_id,municipio_id) REFERENCES camadas_territoriais(id,municipio_id) ON DELETE CASCADE,
    CONSTRAINT fk_objeto_territorial_secretaria FOREIGN KEY(secretaria_id,municipio_id) REFERENCES secretarias(id,municipio_id) ON DELETE RESTRICT,
    CONSTRAINT fk_objeto_territorial_departamento FOREIGN KEY(departamento_id,municipio_id) REFERENCES departamentos(id,municipio_id) ON DELETE RESTRICT,
    CONSTRAINT fk_objeto_territorial_criador FOREIGN KEY(criado_por_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_objeto_territorial_atualizador FOREIGN KEY(atualizado_por_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vinculos_territoriais (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    municipio_id BIGINT UNSIGNED NOT NULL,
    objeto_territorial_id BIGINT UNSIGNED NOT NULL,
    fase_id BIGINT UNSIGNED NULL,
    requisito_id BIGINT UNSIGNED NULL,
    documento_id BIGINT UNSIGNED NULL,
    tipo_vinculo VARCHAR(40) NOT NULL DEFAULT 'FASE',
    observacao TEXT NULL,
    criado_por_usuario_id BIGINT UNSIGNED NULL,
    criado_em DATETIME NOT NULL,
    INDEX idx_vinculo_territorial_objeto(municipio_id,objeto_territorial_id,tipo_vinculo),
    INDEX idx_vinculo_territorial_fase(municipio_id,fase_id),
    CONSTRAINT fk_vinculo_territorial_municipio FOREIGN KEY(municipio_id) REFERENCES municipios(id) ON DELETE CASCADE,
    CONSTRAINT fk_vinculo_territorial_objeto FOREIGN KEY(objeto_territorial_id,municipio_id) REFERENCES objetos_territoriais(id,municipio_id) ON DELETE CASCADE,
    CONSTRAINT fk_vinculo_territorial_fase FOREIGN KEY(fase_id,municipio_id) REFERENCES fases(id,municipio_id) ON DELETE CASCADE,
    CONSTRAINT fk_vinculo_territorial_requisito FOREIGN KEY(requisito_id,municipio_id) REFERENCES requisitos_documentais(id,municipio_id) ON DELETE CASCADE,
    CONSTRAINT fk_vinculo_territorial_documento FOREIGN KEY(documento_id,municipio_id) REFERENCES documentos_enviados(id,municipio_id) ON DELETE CASCADE,
    CONSTRAINT fk_vinculo_territorial_usuario FOREIGN KEY(criado_por_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;

-- v4.18 - Auditoria e Segurança
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS auth_version INT NOT NULL DEFAULT 1 AFTER ativo;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS senha_alterada_em DATETIME NULL AFTER ultimo_login_em;
ALTER TABLE auditoria ADD COLUMN IF NOT EXISTS usuario_nome VARCHAR(180) NOT NULL DEFAULT '' AFTER usuario_id;
ALTER TABLE auditoria ADD COLUMN IF NOT EXISTS usuario_email VARCHAR(190) NOT NULL DEFAULT '' AFTER usuario_nome;
ALTER TABLE auditoria ADD COLUMN IF NOT EXISTS categoria VARCHAR(40) NOT NULL DEFAULT 'OPERACIONAL' AFTER evento;
ALTER TABLE auditoria ADD COLUMN IF NOT EXISTS severidade VARCHAR(20) NOT NULL DEFAULT 'INFO' AFTER categoria;
ALTER TABLE auditoria ADD COLUMN IF NOT EXISTS sucesso TINYINT(1) NOT NULL DEFAULT 1 AFTER severidade;
ALTER TABLE auditoria ADD COLUMN IF NOT EXISTS metodo VARCHAR(10) NOT NULL DEFAULT '' AFTER user_agent;
ALTER TABLE auditoria ADD COLUMN IF NOT EXISTS rota VARCHAR(500) NOT NULL DEFAULT '' AFTER metodo;
ALTER TABLE auditoria ADD COLUMN IF NOT EXISTS referer VARCHAR(500) NOT NULL DEFAULT '' AFTER rota;
ALTER TABLE auditoria ADD COLUMN IF NOT EXISTS session_hash CHAR(64) NOT NULL DEFAULT '' AFTER referer;
ALTER TABLE auditoria ADD COLUMN IF NOT EXISTS contexto_json LONGTEXT NULL AFTER session_hash;
ALTER TABLE historico_documentos ADD COLUMN IF NOT EXISTS ip VARCHAR(64) NOT NULL DEFAULT '' AFTER status;
ALTER TABLE historico_documentos ADD COLUMN IF NOT EXISTS user_agent VARCHAR(500) NOT NULL DEFAULT '' AFTER ip;
CREATE TABLE IF NOT EXISTS tentativas_login (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,usuario_id BIGINT UNSIGNED NULL,email_normalizado VARCHAR(190) NOT NULL DEFAULT '',ip VARCHAR(64) NOT NULL DEFAULT '',user_agent VARCHAR(500) NOT NULL DEFAULT '',sucesso TINYINT(1) NOT NULL DEFAULT 0,motivo VARCHAR(80) NOT NULL DEFAULT '',criado_em DATETIME NOT NULL,INDEX idx_login_email_data(email_normalizado,criado_em),INDEX idx_login_ip_data(ip,criado_em),CONSTRAINT fk_tentativa_usuario FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS password_reset_tokens (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,usuario_id BIGINT UNSIGNED NOT NULL,token_hash CHAR(64) NOT NULL UNIQUE,expira_em DATETIME NOT NULL,usado_em DATETIME NULL,requested_ip VARCHAR(64) NOT NULL DEFAULT '',requested_user_agent VARCHAR(500) NOT NULL DEFAULT '',criado_em DATETIME NOT NULL,INDEX idx_reset_user(usuario_id,usado_em,expira_em),CONSTRAINT fk_reset_usuario FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TRIGGER IF EXISTS trg_auditoria_no_delete;
CREATE TRIGGER trg_auditoria_no_delete BEFORE DELETE ON auditoria FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Auditoria: exclusão física não permitida.';
DROP TRIGGER IF EXISTS trg_auditoria_no_update;
CREATE TRIGGER trg_auditoria_no_update BEFORE UPDATE ON auditoria FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Auditoria: alteração de evento auditado não permitida.';

CREATE TABLE IF NOT EXISTS importacoes_estrutura (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    municipio_id BIGINT UNSIGNED NOT NULL,
    origem_municipio_id BIGINT UNSIGNED NULL,
    origem_tipo ENUM('MUNICIPIO','PLANILHA') NOT NULL,
    arquivo_nome VARCHAR(255) NULL,
    estrategia_conflito ENUM('IGNORAR','ATUALIZAR') NOT NULL DEFAULT 'IGNORAR',
    resumo_json LONGTEXT NULL,
    itens_json LONGTEXT NULL,
    status ENUM('CONCLUIDA','DESFEITA') NOT NULL DEFAULT 'CONCLUIDA',
    criado_por_usuario_id BIGINT UNSIGNED NULL,
    criado_em DATETIME NOT NULL,
    desfeita_em DATETIME NULL,
    INDEX idx_import_estrutura_municipio(municipio_id,status,criado_em),
    CONSTRAINT fk_import_estrutura_municipio FOREIGN KEY(municipio_id) REFERENCES municipios(id) ON DELETE RESTRICT,
    CONSTRAINT fk_import_estrutura_origem FOREIGN KEY(origem_municipio_id) REFERENCES municipios(id) ON DELETE SET NULL,
    CONSTRAINT fk_import_estrutura_usuario FOREIGN KEY(criado_por_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS pontos_restauracao_instancia (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    municipio_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(180) NOT NULL,
    origem ENUM('SALVO','IMPORTADO','AUTOMATICO') NOT NULL DEFAULT 'SALVO',
    arquivo_path VARCHAR(500) NOT NULL,
    arquivo_nome VARCHAR(255) NOT NULL,
    checksum_sha256 CHAR(64) NOT NULL,
    tamanho BIGINT UNSIGNED NOT NULL DEFAULT 0,
    resumo_json LONGTEXT NULL,
    status ENUM('DISPONIVEL','REMOVIDO') NOT NULL DEFAULT 'DISPONIVEL',
    criado_por_usuario_id BIGINT UNSIGNED NULL,
    criado_em DATETIME NOT NULL,
    restaurado_por_usuario_id BIGINT UNSIGNED NULL,
    restaurado_em DATETIME NULL,
    restauracoes INT UNSIGNED NOT NULL DEFAULT 0,
    INDEX idx_ponto_restauracao_municipio(municipio_id,status,criado_em),
    CONSTRAINT fk_ponto_restauracao_municipio FOREIGN KEY(municipio_id) REFERENCES municipios(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ponto_restauracao_criador FOREIGN KEY(criado_por_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_ponto_restauracao_restaurador FOREIGN KEY(restaurado_por_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
