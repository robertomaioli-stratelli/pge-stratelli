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
    $pdo->exec("CREATE TABLE IF NOT EXISTS pontos_restauracao_instancia (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $dir=$root.'/storage/backups';
    if(!is_dir($dir) && !mkdir($dir,0770,true) && !is_dir($dir)){
        throw new RuntimeException('Não foi possível criar o diretório storage/backups.');
    }

    echo "[OK] v4.20.13 aplicada: pontos de restauração da instância disponíveis.\n";
}catch(Throwable $e){
    fwrite(STDERR,"[ERRO] ".$e->getMessage()."\n");
    exit(1);
}
