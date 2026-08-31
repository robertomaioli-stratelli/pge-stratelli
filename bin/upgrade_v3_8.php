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

$st=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME="usuarios" AND COLUMN_NAME="departamento_id"');
$st->execute([$db]);
if(!(int)$st->fetchColumn()){
    $pdo->exec('ALTER TABLE usuarios ADD COLUMN departamento_id BIGINT UNSIGNED NULL AFTER secretaria_id');
    echo "[OK] Coluna usuarios.departamento_id criada.\n";
}else echo "[OK] Coluna usuarios.departamento_id já existe.\n";

$st=$pdo->prepare('SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=? AND TABLE_NAME="usuarios" AND CONSTRAINT_NAME="fk_usuario_departamento"');
$st->execute([$db]);
if(!(int)$st->fetchColumn()){
    $pdo->exec('ALTER TABLE usuarios ADD CONSTRAINT fk_usuario_departamento FOREIGN KEY(departamento_id,municipio_id) REFERENCES departamentos(id,municipio_id) ON DELETE RESTRICT');
    echo "[OK] Vínculo usuário → departamento criado.\n";
}else echo "[OK] Vínculo usuário → departamento já existe.\n";

// Usuário comum de teste solicitado para Maringá.
$email='usuario.seadm@maringa.local';
$password='Maringa@2026!';
$st=$pdo->prepare('SELECT id FROM usuarios WHERE LOWER(email)=LOWER(?) LIMIT 1');
$st->execute([$email]);
if(!$st->fetchColumn()){
    $st=$pdo->prepare("SELECT m.id municipio_id,s.id secretaria_id FROM municipios m JOIN secretarias s ON s.municipio_id=m.id WHERE m.slug='maringa' AND s.sigla='SEADM' LIMIT 1");
    $st->execute();$row=$st->fetch(PDO::FETCH_ASSOC);
    if($row){
        $algo=defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_DEFAULT;
        $pdo->prepare("INSERT INTO usuarios(municipio_id,secretaria_id,departamento_id,nome,email,senha_hash,grupo,administrador_plataforma,ativo,criado_em,atualizado_em) VALUES(?,?,NULL,?,?,?,'USUARIO',0,1,NOW(),NOW())")
            ->execute([(int)$row['municipio_id'],(int)$row['secretaria_id'],'Usuário SEADM - Maringá',$email,password_hash($password,$algo)]);
        echo "[OK] Usuário comum de Maringá criado.\n";
        echo "     Login: {$email}\n";
        echo "     Senha temporária: {$password}\n";
    }else echo "[AVISO] Maringá/SEADM não encontrados; usuário de teste não foi criado.\n";
}else echo "[OK] Usuário comum de Maringá já existe: {$email}\n";

echo "\nUpgrade v3.8 concluído.\n";
