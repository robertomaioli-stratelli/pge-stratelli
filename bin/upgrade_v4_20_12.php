<?php
declare(strict_types=1);

use App\Core\Database;
use App\Core\Env;

$root=dirname(__DIR__);
require $root.'/app/Core/Env.php';
Env::load($root.'/.env');
spl_autoload_register(function(string $class)use($root):void{$prefix='App\\';if(!str_starts_with($class,$prefix))return;$file=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require$file;});

try{
    $pdo=Database::connection();
    $pdo->exec("CREATE TABLE IF NOT EXISTS importacoes_estrutura (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "[OK] v4.20.12 aplicada: importação e replicação de estruturas disponível.\n";
}catch(Throwable $e){fwrite(STDERR,"[ERRO] ".$e->getMessage()."\n");exit(1);}
