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
$columns=[
    'codigo_ibge'=>'VARCHAR(20) NULL AFTER slug',
    'populacao'=>'BIGINT UNSIGNED NULL AFTER codigo_ibge',
    'area_km2'=>'DECIMAL(12,3) NULL AFTER populacao',
    'site_oficial'=>'VARCHAR(255) NULL AFTER area_km2',
    'latitude'=>'DECIMAL(10,7) NULL AFTER site_oficial',
    'longitude'=>'DECIMAL(10,7) NULL AFTER latitude',
    'brasao_path'=>'VARCHAR(255) NULL AFTER longitude',
    'geojson_delimitacao'=>'LONGTEXT NULL AFTER brasao_path',
];
foreach($columns as $name=>$definition){
    $st=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME="municipios" AND COLUMN_NAME=?');
    $st->execute([$db,$name]);
    if(!(int)$st->fetchColumn()){
        $pdo->exec('ALTER TABLE municipios ADD COLUMN '.$name.' '.$definition);
        echo "[OK] Coluna municipios.$name criada.\n";
    }else echo "[OK] Coluna municipios.$name já existe.\n";
}
echo "\nUpgrade v3.3 concluído.\n";
