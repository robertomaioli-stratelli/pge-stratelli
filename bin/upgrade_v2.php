<?php
declare(strict_types=1);
use App\Core\Env;use App\Core\Database;use App\Services\MvpSeedService;
$root=dirname(__DIR__);require $root.'/app/Core/Env.php';Env::load($root.'/.env');spl_autoload_register(function(string$class)use($root){$p='App\\';if(!str_starts_with($class,$p))return;$f=$root.'/app/'.str_replace('\\','/',substr($class,strlen($p))).'.php';if(is_file($f))require$f;});
$pdo=Database::connection();$sql=file_get_contents($root.'/database/schema.sql');foreach(array_filter(array_map('trim',preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[]))as$statement){if($statement!=='')$pdo->exec($statement);} (new MvpSeedService())->seedMaringa();
echo "Upgrade v2 concluído. Estrutura do MVP migrada/atualizada de forma idempotente.\n";
