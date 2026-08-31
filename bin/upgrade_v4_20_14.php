<?php
declare(strict_types=1);

use App\Core\Database;
use App\Core\Env;

$root=dirname(__DIR__);
require $root.'/app/Core/Env.php';
Env::load($root.'/.env');
spl_autoload_register(function(string $class)use($root):void{
    $prefix='App\\';
    if(!str_starts_with($class,$prefix))return;
    $file=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($file))require $file;
});

try{
    $pdo=Database::connection();
    $pdo->exec("CREATE TABLE IF NOT EXISTS arquivamentos_etapa (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $dir=$root.'/storage/archives';
    if(!is_dir($dir) && !mkdir($dir,0770,true) && !is_dir($dir))throw new RuntimeException('Não foi possível criar o diretório storage/archives.');
    echo "[OK] v4.20.14 aplicada: arquivamento da Etapa 1 disponível.\n";
}catch(Throwable $e){
    fwrite(STDERR,"[ERRO] ".$e->getMessage()."\n");
    exit(1);
}
