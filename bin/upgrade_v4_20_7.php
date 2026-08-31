<?php
declare(strict_types=1);

use App\Core\Database;
use App\Core\Env;

$root=dirname(__DIR__);
require $root.'/app/Core/Env.php';
require $root.'/app/Core/Database.php';
Env::load($root.'/.env');
$pdo=Database::connection();

try{
    $pdo->exec("CREATE TABLE IF NOT EXISTS parametros_instancia (
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
        criado_em DATETIME NOT NULL,
        atualizado_em DATETIME NOT NULL,
        CONSTRAINT fk_parametros_instancia_municipio FOREIGN KEY(municipio_id) REFERENCES municipios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec('INSERT IGNORE INTO parametros_instancia(municipio_id,criado_em,atualizado_em) SELECT id,NOW(),NOW() FROM municipios');
    echo "INPACTA v4.20.7 - Central de Parâmetros da Instância instalada.\n";
    echo "Municípios existentes inicializados com os valores padrão.\n";
}catch(Throwable $e){fwrite(STDERR,"Erro: ".$e->getMessage()."\n");exit(1);}
