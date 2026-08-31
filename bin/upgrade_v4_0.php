<?php
declare(strict_types=1);
use App\Core\Env;
use App\Core\Database;

$root=dirname(__DIR__);
require $root.'/app/Core/Env.php';
Env::load($root.'/.env');
spl_autoload_register(function(string $class)use($root):void{
    $prefix='App\\';
    if(!str_starts_with($class,$prefix))return;
    $file=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($file))require $file;
});

$pdo=Database::connection();
$db=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
$st=$pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME="documentos_enviados" AND INDEX_NAME="uq_documento_id_municipio"');
$st->execute([$db]);
if(!(int)$st->fetchColumn()){
    $pdo->exec('ALTER TABLE documentos_enviados ADD UNIQUE KEY uq_documento_id_municipio(id,municipio_id)');
    echo "[OK] Índice multi-tenant de documentos criado.\n";
}else echo "[OK] Índice multi-tenant de documentos já existe.\n";

$sql=<<<'SQL'
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
SQL;

foreach(array_filter(array_map('trim',preg_split('/;\s*(?:\r?\n|$)/',$sql))) as $statement){$pdo->exec($statement);}
echo "[OK] Estrutura territorial v4.0 criada/validada.\n";

echo "[OK] Nenhum dado existente foi removido.\n";
