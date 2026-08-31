<?php
namespace App\Services;
use App\Core\Audit;use App\Core\Auth;use App\Core\Database;use RuntimeException;
final class PasswordResetService
{
    public function request(string $email): ?string
    {
        $email=strtolower(trim($email));if(!filter_var($email,FILTER_VALIDATE_EMAIL))return null;$pdo=Database::connection();
        if($this->rateLimitedByIp($pdo)){Audit::logActor(null,'RECUPERACAO_SENHA_LIMITADA','Limite de solicitações de recuperação atingido para o IP',null,['categoria'=>'SEGURANCA','severidade'=>'ALERTA','sucesso'=>false]);return null;}
        $st=$pdo->prepare('SELECT id,municipio_id,nome,email FROM usuarios WHERE LOWER(email)=LOWER(?) AND ativo=1 LIMIT 1');$st->execute([$email]);$u=$st->fetch();
        if(!$u){Audit::logActor(null,'RECUPERACAO_SENHA_SOLICITADA','Solicitação para conta não localizada',null,['categoria'=>'SEGURANCA','email'=>$email]);return null;}
        if($this->rateLimitedByUser($pdo,(int)$u['id'])){Audit::logActor((int)$u['id'],'RECUPERACAO_SENHA_LIMITADA','Limite de solicitações de recuperação atingido para a conta',(int)($u['municipio_id']??0)?:null,['categoria'=>'SEGURANCA','severidade'=>'ALERTA','sucesso'=>false]);return null;}
        $token=$this->issue((int)$u['id']);$url=$this->url($token);$minutes=$this->minutes();
        try{(new MailService())->sendPasswordReset((string)$u['email'],(string)($u['nome']??''),$url,$minutes);Audit::logActor((int)$u['id'],'EMAIL_RECUPERACAO_ENVIADO','E-mail de recuperação de senha enviado',(int)($u['municipio_id']??0)?:null,['categoria'=>'SEGURANCA']);}
        catch(\Throwable $e){$this->invalidateToken($token);Audit::logActor((int)$u['id'],'EMAIL_RECUPERACAO_FALHOU','Falha no envio do e-mail de recuperação: '.$e->getMessage(),(int)($u['municipio_id']??0)?:null,['categoria'=>'SEGURANCA','severidade'=>'ALERTA','sucesso'=>false]);throw $e;}
        Audit::logActor((int)$u['id'],'RECUPERACAO_SENHA_SOLICITADA','Token temporário de recuperação emitido e enviado por e-mail',(int)($u['municipio_id']??0)?:null,['categoria'=>'SEGURANCA']);
        $show=filter_var(getenv('PASSWORD_RESET_SHOW_LINK_LOCAL')?:'false',FILTER_VALIDATE_BOOLEAN);return $show?$url:null;
    }
    public function adminSend(int $userId): void
    {
        if(!Auth::isPlatformAdmin())throw new RuntimeException('Ação restrita à Stratelli.');$pdo=Database::connection();$st=$pdo->prepare('SELECT id,municipio_id,nome,email FROM usuarios WHERE id=? AND ativo=1');$st->execute([$userId]);$u=$st->fetch();if(!$u)throw new RuntimeException('Usuário não encontrado ou inativo.');$token=$this->issue($userId);$url=$this->url($token);
        try{(new MailService())->sendPasswordReset((string)$u['email'],(string)($u['nome']??''),$url,$this->minutes());Audit::log('EMAIL_RECUPERACAO_ADMIN_ENVIADO','E-mail de recuperação enviado para '.$u['email'],(int)($u['municipio_id']??0)?:null,['categoria'=>'SEGURANCA','severidade'=>'ATENCAO','usuario_alvo'=>$userId]);}
        catch(\Throwable $e){$this->invalidateToken($token);throw new RuntimeException('Não foi possível enviar o e-mail de recuperação. Verifique a configuração SMTP.');}
    }
    public function reset(string $token,string $password,string $confirmation): void
    {
        if(strlen($password)<10)throw new RuntimeException('A nova senha deve ter no mínimo 10 caracteres.');if($password!==$confirmation)throw new RuntimeException('A confirmação da senha não confere.');$hash=hash('sha256',$token);$pdo=Database::connection();$st=$pdo->prepare('SELECT pr.*,u.municipio_id,u.email FROM password_reset_tokens pr JOIN usuarios u ON u.id=pr.usuario_id WHERE pr.token_hash=? AND pr.usado_em IS NULL AND pr.expira_em>=NOW() AND u.ativo=1 LIMIT 1');$st->execute([$hash]);$r=$st->fetch();if(!$r)throw new RuntimeException('Link de recuperação inválido ou expirado.');$algo=defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_DEFAULT;$pdo->beginTransaction();try{$pdo->prepare('UPDATE usuarios SET senha_hash=?,auth_version=auth_version+1,senha_alterada_em=NOW(),atualizado_em=NOW() WHERE id=?')->execute([password_hash($password,$algo),$r['usuario_id']]);$pdo->prepare('UPDATE password_reset_tokens SET usado_em=NOW() WHERE usuario_id=? AND usado_em IS NULL')->execute([$r['usuario_id']]);$pdo->commit();}catch(\Throwable $e){$pdo->rollBack();throw $e;}Audit::logActor((int)$r['usuario_id'],'SENHA_REDEFINIDA','Senha redefinida por token de recuperação',(int)($r['municipio_id']??0)?:null,['categoria'=>'SEGURANCA','severidade'=>'ATENCAO']);
    }
    public function validate(string $token): bool{if($token==='')return false;$pdo=Database::connection();$st=$pdo->prepare('SELECT COUNT(*) FROM password_reset_tokens WHERE token_hash=? AND usado_em IS NULL AND expira_em>=NOW()');$st->execute([hash('sha256',$token)]);return (int)$st->fetchColumn()>0;}
    private function issue(int $userId): string{$token=bin2hex(random_bytes(32));$minutes=$this->minutes();$pdo=Database::connection();$pdo->prepare('UPDATE password_reset_tokens SET usado_em=NOW() WHERE usuario_id=? AND usado_em IS NULL')->execute([$userId]);$pdo->prepare("INSERT INTO password_reset_tokens(usuario_id,token_hash,expira_em,requested_ip,requested_user_agent,criado_em) VALUES(?,?,DATE_ADD(NOW(),INTERVAL {$minutes} MINUTE),?,?,NOW())")->execute([$userId,hash('sha256',$token),Audit::ip(),substr($_SERVER['HTTP_USER_AGENT']??'',0,500)]);return $token;}
    private function invalidateToken(string $token): void{try{Database::connection()->prepare('UPDATE password_reset_tokens SET usado_em=NOW() WHERE token_hash=? AND usado_em IS NULL')->execute([hash('sha256',$token)]);}catch(\Throwable){}}
    private function url(string $token): string{$base=rtrim(getenv('APP_URL')?:'http://stratelli.local','/');return $base.'/redefinir-senha/'.$token;}
    private function minutes(): int{return min(120,max(10,(int)(getenv('PASSWORD_RESET_MINUTES')?:30)));}
    private function rateLimitedByIp($pdo): bool{$ip=Audit::ip();if($ip==='')return false;$st=$pdo->prepare('SELECT COUNT(*) FROM password_reset_tokens WHERE requested_ip=? AND criado_em>=DATE_SUB(NOW(),INTERVAL 15 MINUTE)');$st->execute([$ip]);return (int)$st->fetchColumn()>=5;}
    private function rateLimitedByUser($pdo,int $userId): bool{$st=$pdo->prepare('SELECT COUNT(*) FROM password_reset_tokens WHERE usuario_id=? AND criado_em>=DATE_SUB(NOW(),INTERVAL 15 MINUTE)');$st->execute([$userId]);return (int)$st->fetchColumn()>=3;}
}
