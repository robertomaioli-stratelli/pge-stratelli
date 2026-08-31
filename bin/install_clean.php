<?php
declare(strict_types=1);

use App\Core\Database;
use App\Core\Env;

$root = dirname(__DIR__);
require $root.'/app/Core/Env.php';
Env::load($root.'/.env');

spl_autoload_register(function(string $class) use ($root): void {
    $prefix='App\\';
    if(!str_starts_with($class,$prefix)) return;
    $file=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($file)) require $file;
});

function usage(): void {
    echo "INPACTA r.20 - Instalação limpa\n\n";
    echo "Uso:\n";
    echo "  php bin/install_clean.php --name=\"Administrador Stratelli\" --email=admin@stratelli.com.br --password=\"SUA_SENHA\"\n\n";
    echo "O banco informado no .env deve estar vazio.\n";
}

$options = getopt('', ['name:', 'email:', 'password:', 'force']);
if(PHP_SAPI !== 'cli'){ http_response_code(403); exit('Este instalador CLI não pode ser executado pela web.'); }
if(isset($options['help'])) { usage(); exit(0); }

$name = trim((string)($options['name'] ?? ''));
$email = strtolower(trim((string)($options['email'] ?? '')));
$password = (string)($options['password'] ?? '');

if($name==='' || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<10){
    usage();
    fwrite(STDERR,"Nome, e-mail válido e senha com pelo menos 10 caracteres são obrigatórios.\n");
    exit(2);
}

$pdo=Database::connection();
$tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
if($tables){
    fwrite(STDERR,"INSTALAÇÃO ABORTADA: o banco já possui ".count($tables)." tabela(s). Nenhum dado foi alterado.\n");
    exit(3);
}

$sql=file_get_contents($root.'/database/schema.sql');
if($sql===false) throw new RuntimeException('Não foi possível ler database/schema.sql.');
$statements=array_filter(array_map('trim',preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[]));
$pdo->beginTransaction();
try{
    foreach($statements as $statement){ if($statement!=='') $pdo->exec($statement); }
    $algo=defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_DEFAULT;
    $stmt=$pdo->prepare("INSERT INTO usuarios(municipio_id,secretaria_id,departamento_id,nome,email,senha_hash,grupo,administrador_plataforma,ativo,auth_version,criado_em,atualizado_em) VALUES(NULL,NULL,NULL,?,?,?,?,1,1,1,NOW(),NOW())");
    $stmt->execute([$name,$email,password_hash($password,$algo),'ADMINISTRADOR']);
    $pdo->commit();
}catch(Throwable $e){ if($pdo->inTransaction()) $pdo->rollBack(); throw $e; }

foreach(['storage','storage/uploads','storage/backups','storage/archives','storage/logs'] as $dir){
    $path=$root.'/'.$dir;
    if(!is_dir($path) && !mkdir($path,0775,true) && !is_dir($path)) throw new RuntimeException('Não foi possível criar '.$dir);
}

$lock='INSTALACAO_CONCLUIDA_EM='.date('c')."\n".'ADMIN_EMAIL='.$email."\n".'NAO_REEXECUTAR=1\n';
file_put_contents($root.'/storage/.installation.lock',$lock,LOCK_EX);

echo "\n========================================\n INPACTA r.20 - INSTALAÇÃO LIMPA\n========================================\n";
echo "Banco............... OK\nEstrutura........... OK\nStorage............. OK\nAdministrador....... {$email}\nMunicípios.......... 0\nUsuários municipais. 0\nFases............... 0\nDocumentos.......... 0\n========================================\n Plataforma pronta para implantação.\n========================================\n";
