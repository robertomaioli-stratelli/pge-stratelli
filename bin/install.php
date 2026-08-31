<?php
declare(strict_types=1);

use App\Core\Env;
use App\Core\Database;

$root=dirname(__DIR__);
require $root.'/app/Core/Env.php';
Env::load($root.'/.env');
spl_autoload_register(function(string $class) use($root){$p='App\\';if(!str_starts_with($class,$p))return;$f=$root.'/app/'.str_replace('\\','/',substr($class,strlen($p))).'.php';if(is_file($f))require$f;});

$pdo=Database::connection();
$sql=file_get_contents($root.'/database/schema.sql');
foreach(array_filter(array_map('trim',preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[])) as $statement){
    if($statement==='')continue;
    $pdo->exec($statement);
}

function randomPassword(): string { return rtrim(strtr(base64_encode(random_bytes(12)),'+/','-_'),'=').'!9a'; }
function hashPassword(string $password): string { $algo=defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_DEFAULT; return password_hash($password,$algo); }
function slugify(string $v): string {$a=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$v)?:$v;$a=strtolower((string)preg_replace('/[^a-zA-Z0-9]+/','-',$a));return trim($a,'-');}

$adminEmail=strtolower(trim(getenv('SEED_ADMIN_EMAIL')?:'admin@stratelli.com.br'));
$adminName=trim(getenv('SEED_ADMIN_NAME')?:'Administrador Stratelli');
$adminPass=(string)(getenv('SEED_ADMIN_PASSWORD')?:randomPassword());
$st=$pdo->prepare('SELECT id FROM usuarios WHERE email=?');$st->execute([$adminEmail]);
if(!$st->fetchColumn()){
    $pdo->prepare("INSERT INTO usuarios(municipio_id,secretaria_id,nome,email,senha_hash,grupo,administrador_plataforma,ativo,criado_em,atualizado_em) VALUES(NULL,NULL,?,?,?,?,1,1,NOW(),NOW())")
        ->execute([$adminName,$adminEmail,hashPassword($adminPass),'ADMINISTRADOR']);
    echo "Admin Stratelli criado: {$adminEmail} | senha: {$adminPass}\n";
}else echo "Admin Stratelli já existe: {$adminEmail}\n";

$clientes=[
    ['Maringá','PR','maringa','ATIVO'],
    ['Uberlândia','MG','uberlandia','IMPLANTACAO'],
    ['São Bernardo do Campo','SP','sao-bernardo-do-campo','IMPLANTACAO'],
];
$managerPasswords=[];
foreach($clientes as [$nome,$uf,$slug,$status]){
    $st=$pdo->prepare('SELECT id FROM municipios WHERE slug=?');$st->execute([$slug]);$mid=(int)($st->fetchColumn()?:0);
    if(!$mid){$pdo->prepare('INSERT INTO municipios(nome,uf,slug,status,ativo,criado_em,atualizado_em) VALUES(?,?,?,?,1,NOW(),NOW())')->execute([$nome,$uf,$slug,$status]);$mid=(int)$pdo->lastInsertId();}
    $pdo->prepare('INSERT IGNORE INTO parametros_instancia(municipio_id,criado_em,atualizado_em) VALUES(?,NOW(),NOW())')->execute([$mid]);
    $st=$pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE municipio_id=? AND grupo='GESTOR' AND ativo=1");$st->execute([$mid]);
    if(!(int)$st->fetchColumn()){
        $email='gestor.'.slugify($nome).'@example.invalid';$pass=randomPassword();
        $pdo->prepare("INSERT INTO usuarios(municipio_id,secretaria_id,nome,email,senha_hash,grupo,administrador_plataforma,ativo,criado_em,atualizado_em) VALUES(?,NULL,?,?,?,'GESTOR',0,1,NOW(),NOW())")
            ->execute([$mid,'Gestor Municipal - '.$nome,$email,hashPassword($pass)]);
        $managerPasswords[]="{$nome}: {$email} | senha temporária: {$pass}";
    }
}

// Estrutura operacional completa de Maringá baseada no MVP mais recente.
(new \App\Services\MvpSeedService())->seedMaringa();

// Usuário comum inicial de demonstração de Maringá/SEADM.
$commonEmail='usuario.seadm@maringa.local';$commonPassword='Maringa@2026!';
$st=$pdo->prepare('SELECT id FROM usuarios WHERE LOWER(email)=LOWER(?) LIMIT 1');$st->execute([$commonEmail]);
if(!$st->fetchColumn()){
    $st=$pdo->query("SELECT m.id municipio_id,s.id secretaria_id FROM municipios m JOIN secretarias s ON s.municipio_id=m.id WHERE m.slug='maringa' AND s.sigla='SEADM' LIMIT 1");$row=$st->fetch(PDO::FETCH_ASSOC);
    if($row){$pdo->prepare("INSERT INTO usuarios(municipio_id,secretaria_id,departamento_id,nome,email,senha_hash,grupo,administrador_plataforma,ativo,criado_em,atualizado_em) VALUES(?,?,NULL,?,?,?,'USUARIO',0,1,NOW(),NOW())")->execute([(int)$row['municipio_id'],(int)$row['secretaria_id'],'Usuário SEADM - Maringá',$commonEmail,hashPassword($commonPassword)]);$managerPasswords[]="Usuário comum Maringá (SEADM): {$commonEmail} | senha temporária: {$commonPassword}";}
}

echo "\nInstalação concluída.\n";
if($managerPasswords){echo "\nCredenciais temporárias dos gestores (trocar no primeiro acesso):\n".implode("\n",$managerPasswords)."\n";}
