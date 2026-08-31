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
$st=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME="municipios" AND COLUMN_NAME="inteligencia_territorial_ativa"');
$st->execute([$db]);
if(!(int)$st->fetchColumn()){
    $pdo->exec('ALTER TABLE municipios ADD COLUMN inteligencia_territorial_ativa TINYINT(1) NOT NULL DEFAULT 0 AFTER geojson_delimitacao');
    echo "[OK] Controle de ativação da Inteligência Territorial criado.\n";
}else echo "[OK] Controle de ativação da Inteligência Territorial já existe.\n";

// Regra solicitada: todas as instâncias permanecem inativas até liberação explícita da Stratelli.
$pdo->exec('UPDATE municipios SET inteligencia_territorial_ativa=0 WHERE inteligencia_territorial_ativa IS NULL');
echo "[OK] Novos municípios e instalações permanecem com o módulo inativo por padrão.\n";
echo "[OK] Nenhuma camada, objeto ou geometria territorial existente foi removida.\n";
echo "\nUpgrade v4.7 concluído. Ative o módulo individualmente em Administração > Municípios > Cadastro do Município.\n";
