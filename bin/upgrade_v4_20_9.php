<?php
use App\Core\Database;
use App\Core\Env;

$root=dirname(__DIR__);
require $root.'/app/Core/Env.php';
require $root.'/app/Core/Database.php';
Env::load($root.'/.env');
$pdo=Database::connection();

try{
    $st=$pdo->query("SHOW COLUMNS FROM parametros_instancia LIKE 'estilo_decoracao_cabecalho'");
    if(!$st->fetch()){
        $pdo->exec("ALTER TABLE parametros_instancia ADD COLUMN estilo_decoracao_cabecalho VARCHAR(20) NOT NULL DEFAULT 'semicirculo' AFTER cor_secundaria");
        echo "[OK] Campo estilo_decoracao_cabecalho criado.\n";
    }else{
        echo "[OK] Campo estilo_decoracao_cabecalho já existe.\n";
    }
    $pdo->exec("UPDATE parametros_instancia SET estilo_decoracao_cabecalho='semicirculo' WHERE estilo_decoracao_cabecalho IS NULL OR estilo_decoracao_cabecalho='' OR estilo_decoracao_cabecalho NOT IN ('semicirculo','nenhuma')");
    echo "INPACTA v4.20.9 - Identidade visual dos cabeçalhos instalada.\n";
}catch(Throwable $e){
    fwrite(STDERR,"Erro: ".$e->getMessage()."\n");
    exit(1);
}
