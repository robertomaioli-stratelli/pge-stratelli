<?php
declare(strict_types=1);

use App\Core\Database;
use App\Core\Env;

$root=dirname(__DIR__);
require $root.'/app/Core/Env.php';
Env::load($root.'/.env');
spl_autoload_register(function(string $class) use($root): void {
    $prefix='App\\';
    if(!str_starts_with($class,$prefix)) return;
    $file=$root.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($file)) require $file;
});

$lock=$root.'/storage/.installation.lock';
$done=is_file($lock);
if(session_status()!==PHP_SESSION_ACTIVE) session_start();
if(empty($_SESSION['clean_install_token'])) $_SESSION['clean_install_token']=bin2hex(random_bytes(32));

$error=''; $success=false; $summary=[];
if($_SERVER['REQUEST_METHOD']==='POST' && !$done){
    try{
        if(!hash_equals((string)$_SESSION['clean_install_token'],(string)($_POST['_token']??''))) throw new RuntimeException('Token de segurança inválido.');
        $name=trim((string)($_POST['nome']??''));
        $email=strtolower(trim((string)($_POST['email']??'')));
        $password=(string)($_POST['senha']??'');
        $confirmation=(string)($_POST['confirmacao']??'');
        if($name==='' || !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Informe nome e e-mail válidos.');
        if(strlen($password)<10) throw new RuntimeException('A senha deve ter no mínimo 10 caracteres.');
        if($password!==$confirmation) throw new RuntimeException('A confirmação da senha não confere.');
        $pdo=Database::connection();
        $tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        if($tables) throw new RuntimeException('O banco já possui '.count($tables).' tabela(s). Por segurança, a instalação limpa foi bloqueada e nenhum dado foi alterado.');
        $sql=file_get_contents($root.'/database/schema.sql');
        if($sql===false) throw new RuntimeException('Não foi possível ler database/schema.sql.');
        $statements=array_filter(array_map('trim',preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[]));
        // MySQL faz COMMIT implícito em DDL (CREATE TABLE, CREATE TRIGGER etc.).
        // Portanto o schema não pode ser envolvido em uma transação PDO: após o primeiro
        // CREATE TABLE, inTransaction() deixa de ser verdadeiro e commit() causaria
        // "There is no active transaction". O instalador exige banco vazio e, em caso
        // de erro, a recomendação é remover/recriar o banco e executar novamente.
        foreach($statements as $statement){ if($statement!=='') $pdo->exec($statement); }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        $algo=defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_DEFAULT;
        $st=$pdo->prepare("INSERT INTO usuarios(municipio_id,secretaria_id,departamento_id,nome,email,senha_hash,grupo,administrador_plataforma,ativo,auth_version,criado_em,atualizado_em) VALUES(NULL,NULL,NULL,?,?,?,?,1,1,1,NOW(),NOW())");
        $st->execute([$name,$email,password_hash($password,$algo),'ADMINISTRADOR']);
        foreach(['storage','storage/uploads','storage/backups','storage/archives','storage/logs'] as $dir){$path=$root.'/'.$dir;if(!is_dir($path)&&!mkdir($path,0775,true)&&!is_dir($path))throw new RuntimeException('Não foi possível criar '.$dir);}
        file_put_contents($lock,'INSTALACAO_CONCLUIDA_EM='.date('c')."\nADMIN_EMAIL={$email}\nNAO_REEXECUTAR=1\n",LOCK_EX);
        $done=true;$success=true;$summary=['email'=>$email];
        unset($_SESSION['clean_install_token']);
    }catch(Throwable $e){$error=$e->getMessage();}
}
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Instalação limpa — INPACTA r.20</title><style>body{font-family:Arial,sans-serif;background:#f4f7fb;color:#162235;margin:0;padding:40px}.box{max-width:720px;margin:auto;background:#fff;border-radius:14px;padding:30px;box-shadow:0 8px 30px #0001}h1{margin-top:0}.ok{padding:14px;background:#eaf8ef;border:1px solid #9bd4aa;border-radius:8px}.err{padding:14px;background:#fff0f0;border:1px solid #e0a0a0;border-radius:8px}label{display:block;font-weight:600;margin:18px 0 6px}input{width:100%;box-sizing:border-box;padding:12px;border:1px solid #ccd5e0;border-radius:8px;font-size:16px}button{margin-top:22px;padding:12px 20px;border:0;border-radius:8px;background:#176fdd;color:#fff;font-weight:700;cursor:pointer}.warn{background:#fff8e6;border:1px solid #efd58b;padding:14px;border-radius:8px;margin:18px 0}ul{line-height:1.8}</style></head><body><div class="box"><h1>INPACTA r.20</h1><p><strong>Instalação limpa</strong></p><?php if($done):?><div class="ok"><strong>Esta instalação já foi concluída.</strong><br>O instalador está bloqueado para evitar uma segunda execução.</div><?php elseif($error):?><div class="err"><strong>Instalação não realizada:</strong><br><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endif;?><?php if(!$done):?><div class="warn">O banco configurado no <code>.env</code> precisa estar completamente vazio. O instalador não apaga tabelas existentes.</div><ul><li>0 municípios</li><li>0 secretarias e departamentos</li><li>0 fases e documentos</li><li>0 usuários municipais</li><li>1 Administrador da Stratelli</li></ul><form method="post"><input type="hidden" name="_token" value="<?=htmlspecialchars($_SESSION['clean_install_token'],ENT_QUOTES,'UTF-8')?>"><label>Nome do administrador</label><input name="nome" value="Administrador Stratelli" required><label>E-mail</label><input type="email" name="email" required placeholder="admin@stratelli.com.br"><label>Senha</label><input type="password" name="senha" minlength="10" required autocomplete="new-password"><label>Confirmar senha</label><input type="password" name="confirmacao" minlength="10" required autocomplete="new-password"><button type="submit">Instalar plataforma limpa</button></form><?php else:?><p>Administrador criado: <strong><?=htmlspecialchars((string)($summary['email']??''),ENT_QUOTES,'UTF-8')?></strong></p><p>Agora remova ou bloqueie o arquivo <code>public/instalacao_limpa.php</code> antes de colocar esta instalação em produção.</p><?php endif;?></div></body></html>
