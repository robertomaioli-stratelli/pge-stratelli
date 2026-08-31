<?php
namespace App\Core;

use PDO;

final class Auth
{
    private static ?array $user = null;

    public static function attempt(string $email, string $password): bool
    {
        $email=strtolower(trim($email));$ip=Audit::ip();$pdo=Database::connection();
        if(self::isLoginBlocked($email,$ip)){
            self::recordAttempt($email,$ip,null,false,'LIMITE_DE_TENTATIVAS');
            Audit::logActor(null,'LOGIN_BLOQUEADO','Tentativas excessivas para '.$email,null,['categoria'=>'SEGURANCA','severidade'=>'ALERTA','sucesso'=>0,'email'=>$email]);
            return false;
        }
        $stmt=$pdo->prepare('SELECT u.*,m.slug municipio_slug,m.nome municipio_nome,m.uf municipio_uf,m.inteligencia_territorial_ativa municipio_territorial_ativa,s.nome secretaria_nome,s.sigla secretaria_sigla,d.nome departamento_nome,d.sigla departamento_sigla FROM usuarios u LEFT JOIN municipios m ON m.id=u.municipio_id LEFT JOIN secretarias s ON s.id=u.secretaria_id AND s.municipio_id=u.municipio_id LEFT JOIN departamentos d ON d.id=u.departamento_id AND d.municipio_id=u.municipio_id WHERE LOWER(u.email)=LOWER(?) AND u.ativo=1 LIMIT 1');
        $stmt->execute([$email]);$user=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$user||!password_verify($password,(string)$user['senha_hash'])){
            self::recordAttempt($email,$ip,$user?(int)$user['id']:null,false,'CREDENCIAIS_INVALIDAS');
            Audit::logActor($user?(int)$user['id']:null,'LOGIN_FALHA','Falha de autenticação para '.$email,$user['municipio_id']??null,['categoria'=>'SEGURANCA','severidade'=>'ALERTA','sucesso'=>0,'email'=>$email]);
            return false;
        }
        Session::regenerate();Session::put('user_id',(int)$user['id']);Session::put('auth_version',(int)($user['auth_version']??1));Session::put('auth_started_at',time());Session::put('auth_last_activity_at',time());self::$user=null;
        $pdo->prepare('UPDATE usuarios SET ultimo_login_em=NOW() WHERE id=?')->execute([$user['id']]);
        self::recordAttempt($email,$ip,(int)$user['id'],true,'SUCESSO');
        Audit::logActor((int)$user['id'],'LOGIN_SUCESSO','Autenticação realizada',(int)($user['municipio_id']??0)?:null,['categoria'=>'SEGURANCA']);
        return true;
    }

    public static function user(): ?array
    {
        if(self::$user)return self::$user;
        $id=(int)Session::get('user_id',0);if(!$id)return null;
        $cfg=require dirname(__DIR__,2).'/config/app.php';$now=time();$started=(int)Session::get('auth_started_at',$now);$last=(int)Session::get('auth_last_activity_at',$now);
        $idle=(int)($cfg['session_idle_seconds']??0);$absolute=(int)($cfg['session_absolute_seconds']??0);
        if(($idle>0&&$now-$last>$idle)||($absolute>0&&$now-$started>$absolute)){
            Audit::logActor($id,'SESSAO_EXPIRADA','Sessão expirada por política de segurança',null,['categoria'=>'SEGURANCA','severidade'=>'ATENCAO']);
            Session::destroy();self::$user=null;return null;
        }
        $pdo=Database::connection();$stmt=$pdo->prepare('SELECT u.*,m.slug municipio_slug,m.nome municipio_nome,m.uf municipio_uf,m.inteligencia_territorial_ativa municipio_territorial_ativa,s.nome secretaria_nome,s.sigla secretaria_sigla,d.nome departamento_nome,d.sigla departamento_sigla FROM usuarios u LEFT JOIN municipios m ON m.id=u.municipio_id LEFT JOIN secretarias s ON s.id=u.secretaria_id AND s.municipio_id=u.municipio_id LEFT JOIN departamentos d ON d.id=u.departamento_id AND d.municipio_id=u.municipio_id WHERE u.id=? AND u.ativo=1 LIMIT 1');$stmt->execute([$id]);$user=$stmt->fetch(PDO::FETCH_ASSOC)?:null;
        if(!$user){Session::destroy();return null;}
        if((int)Session::get('auth_version',1)!==(int)($user['auth_version']??1)){
            Audit::logActor($id,'SESSAO_INVALIDADA','Sessão invalidada após alteração de credencial/permissão',(int)($user['municipio_id']??0)?:null,['categoria'=>'SEGURANCA','severidade'=>'ATENCAO']);Session::destroy();return null;
        }
        Session::put('auth_last_activity_at',$now);self::$user=$user;return self::$user;
    }

    public static function check(): bool{return self::user()!==null;}
    public static function id(): ?int{return self::user()?(int)self::user()['id']:null;}
    public static function isPlatformAdmin(): bool{return (bool)(self::user()['administrador_plataforma']??false);}
    public static function role(): ?string{return self::user()['grupo']??null;}
    public static function syncAuthVersion(int $version): void{Session::put('auth_version',$version);self::$user=null;}

    public static function logout(): void
    {
        $u=self::user();if($u)Audit::logActor((int)$u['id'],'LOGOUT','Sessão encerrada',(int)($u['municipio_id']??0)?:null,['categoria'=>'SEGURANCA']);Session::destroy();self::$user=null;
    }

    private static function isLoginBlocked(string $email,string $ip): bool
    {
        try{$pdo=Database::connection();$st=$pdo->prepare('SELECT COUNT(*) FROM tentativas_login WHERE sucesso=0 AND criado_em>=DATE_SUB(NOW(),INTERVAL 15 MINUTE) AND (email_normalizado=? OR ip=?)');$st->execute([$email,$ip]);return (int)$st->fetchColumn()>=5;}catch(\Throwable){return false;}
    }
    private static function recordAttempt(string $email,string $ip,?int $uid,bool $success,string $reason): void
    {
        try{$pdo=Database::connection();$pdo->prepare('INSERT INTO tentativas_login(usuario_id,email_normalizado,ip,user_agent,sucesso,motivo,criado_em) VALUES(?,?,?,?,?,?,NOW())')->execute([$uid,$email,$ip,substr($_SERVER['HTTP_USER_AGENT']??'',0,500),$success?1:0,$reason]);}catch(\Throwable){}
    }
}
