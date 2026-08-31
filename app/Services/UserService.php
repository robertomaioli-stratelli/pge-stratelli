<?php
namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use RuntimeException;

final class UserService
{
    public function create(array $data, ?int $municipioId, bool $platformAdmin = false): int
    {
        $nome = trim((string)($data['nome'] ?? ''));
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $senha = (string)($data['senha'] ?? '');
        $grupo = strtoupper((string)($data['grupo'] ?? 'USUARIO'));
        if ($platformAdmin) $grupo='ADMINISTRADOR';
        if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Nome e e-mail válidos são obrigatórios.');
        if (strlen($senha) < 10) throw new RuntimeException('A senha deve ter no mínimo 10 caracteres.');
        if (!in_array($grupo, ['ADMINISTRADOR','GESTOR','USUARIO'], true)) throw new RuntimeException('Grupo inválido.');
        if (!$platformAdmin && !$municipioId) throw new RuntimeException('Usuário municipal deve estar vinculado a um município.');
        $secretariaId = $platformAdmin ? null : (int)($data['secretaria_id'] ?? 0);
        $departamentoId = $platformAdmin ? null : (int)($data['departamento_id'] ?? 0);
        if ($grupo === 'USUARIO' && !$secretariaId) throw new RuntimeException('Usuário comum deve estar vinculado a uma secretaria.');
        if ($grupo !== 'USUARIO') { $secretariaId = null; $departamentoId = null; }
        if (!$departamentoId) $departamentoId = null;

        $pdo = Database::connection();
        if ($secretariaId) {
            $st=$pdo->prepare('SELECT COUNT(*) FROM secretarias WHERE id=? AND municipio_id=? AND ativo=1');
            $st->execute([$secretariaId,$municipioId]);
            if (!(int)$st->fetchColumn()) throw new RuntimeException('A secretaria selecionada não pertence ao município informado.');
        }
        if ($departamentoId) {
            $st=$pdo->prepare('SELECT COUNT(*) FROM departamentos WHERE id=? AND municipio_id=? AND secretaria_id=? AND ativo=1');
            $st->execute([$departamentoId,$municipioId,$secretariaId]);
            if (!(int)$st->fetchColumn()) throw new RuntimeException('O departamento selecionado não pertence à secretaria informada.');
        }
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        $stmt = $pdo->prepare('INSERT INTO usuarios (municipio_id,secretaria_id,departamento_id,nome,email,senha_hash,grupo,administrador_plataforma,ativo,criado_em,atualizado_em)
            VALUES (?,?,?,?,?,?,?,?,1,NOW(),NOW())');
        $stmt->execute([$municipioId,$secretariaId,$departamentoId,$nome,$email,password_hash($senha,$algo),$grupo,$platformAdmin?1:0]);
        $id = (int)$pdo->lastInsertId();
        Audit::log('USUARIO_CRIADO','Usuário '.$email.' criado',$municipioId,['categoria'=>'ADMINISTRACAO','severidade'=>'ATENCAO','usuario_alvo'=>$id,'grupo'=>$grupo,'administrador_plataforma'=>$platformAdmin?1:0,'secretaria_id'=>$secretariaId,'departamento_id'=>$departamentoId]);
        try{(new NotificationService())->userCreated($id);}catch(\Throwable){}
        return $id;
    }


    public function changeOwnPassword(int $userId, array $data): void
    {
        if ($userId <= 0) throw new RuntimeException('Usuário inválido.');
        $senhaAtual = (string)($data['senha_atual'] ?? '');
        $novaSenha = (string)($data['nova_senha'] ?? '');
        $confirmacao = (string)($data['confirmacao_senha'] ?? '');
        if ($senhaAtual === '' || $novaSenha === '' || $confirmacao === '') {
            throw new RuntimeException('Preencha a senha atual, a nova senha e a confirmação.');
        }
        if (strlen($novaSenha) < 10) throw new RuntimeException('A nova senha deve ter no mínimo 10 caracteres.');
        if ($novaSenha !== $confirmacao) throw new RuntimeException('A confirmação da nova senha não confere.');

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, senha_hash, municipio_id, email FROM usuarios WHERE id=? AND ativo=1 LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) throw new RuntimeException('Usuário não encontrado.');
        if (!password_verify($senhaAtual, (string)$user['senha_hash'])) throw new RuntimeException('A senha atual informada está incorreta.');
        if (password_verify($novaSenha, (string)$user['senha_hash'])) throw new RuntimeException('A nova senha deve ser diferente da senha atual.');

        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        $stmt = $pdo->prepare('UPDATE usuarios SET senha_hash=?, auth_version=auth_version+1, senha_alterada_em=NOW(), atualizado_em=NOW() WHERE id=?');
        $stmt->execute([password_hash($novaSenha, $algo), $userId]);
        $st=$pdo->prepare('SELECT auth_version FROM usuarios WHERE id=?');$st->execute([$userId]);\App\Core\Auth::syncAuthVersion((int)$st->fetchColumn());
        Audit::log('senha_alterada', 'Senha atualizada pelo próprio usuário: '.$user['email'], (int)($user['municipio_id'] ?? 0) ?: null);
    }


    public function findForAdmin(int $userId): array
    {
        if (!Auth::isPlatformAdmin()) throw new RuntimeException('Acesso restrito à Stratelli.');
        $pdo=Database::connection();$st=$pdo->prepare('SELECT * FROM usuarios WHERE id=? LIMIT 1');$st->execute([$userId]);$u=$st->fetch();if(!$u)throw new RuntimeException('Usuário não encontrado.');return $u;
    }

    public function updateByAdmin(int $userId,array $data): void
    {
        if (!Auth::isPlatformAdmin()) throw new RuntimeException('Acesso restrito à Stratelli.');
        $pdo=Database::connection();$old=$this->findForAdmin($userId);$nome=trim((string)($data['nome']??''));$grupo=strtoupper((string)($data['grupo']??$old['grupo']));$platform=isset($data['administrador_plataforma']);if($nome===''||!in_array($grupo,['ADMINISTRADOR','GESTOR','USUARIO'],true))throw new RuntimeException('Dados do usuário inválidos.');
        $mid=$platform?null:(int)($data['municipio_id']??0);if($platform)$grupo='ADMINISTRADOR';if(!$platform&&!$mid)throw new RuntimeException('Usuário municipal deve ter município.');$sid=$platform?null:(int)($data['secretaria_id']??0);$did=$platform?null:(int)($data['departamento_id']??0);if($grupo==='USUARIO'&&!$sid)throw new RuntimeException('Usuário comum deve ter secretaria.');if($grupo!=='USUARIO'){$sid=null;$did=null;}if(!$did)$did=null;if($sid){$st=$pdo->prepare('SELECT COUNT(*) FROM secretarias WHERE id=? AND municipio_id=? AND ativo=1');$st->execute([$sid,$mid]);if(!(int)$st->fetchColumn())throw new RuntimeException('A secretaria selecionada não pertence ao município.');}if($did){$st=$pdo->prepare('SELECT COUNT(*) FROM departamentos WHERE id=? AND municipio_id=? AND secretaria_id=? AND ativo=1');$st->execute([$did,$mid,$sid]);if(!(int)$st->fetchColumn())throw new RuntimeException('O departamento selecionado não pertence à secretaria.');}
        if((int)$old['ativo']===1&&$old['grupo']==='GESTOR'&&((int)($old['municipio_id']??0)!==$mid||$grupo!=='GESTOR'||$platform))$this->assertCanRemoveManager($userId);
        if((int)$old['ativo']===1&&(int)$old['administrador_plataforma']===1&&!$platform)$this->assertCanRemovePlatformAdmin($userId);
        $before=['grupo'=>$old['grupo'],'administrador_plataforma'=>(int)$old['administrador_plataforma'],'municipio_id'=>$old['municipio_id'],'secretaria_id'=>$old['secretaria_id'],'departamento_id'=>$old['departamento_id']];$after=['grupo'=>$grupo,'administrador_plataforma'=>$platform?1:0,'municipio_id'=>$mid,'secretaria_id'=>$sid,'departamento_id'=>$did];
        $pdo->prepare('UPDATE usuarios SET nome=?,grupo=?,administrador_plataforma=?,municipio_id=?,secretaria_id=?,departamento_id=?,auth_version=auth_version+1,atualizado_em=NOW() WHERE id=?')->execute([$nome,$grupo,$platform?1:0,$mid,$sid,$did,$userId]);
        Audit::log('PERMISSAO_USUARIO_ALTERADA','Permissões/escopo atualizados para '.$old['email'],$mid,['categoria'=>'SEGURANCA','severidade'=>'ATENCAO','usuario_alvo'=>$userId,'antes'=>$before,'depois'=>$after]);
    }

    public function setPasswordByAdmin(int $userId, array $data): void
    {
        if (!Auth::isPlatformAdmin()) throw new RuntimeException('Acesso restrito à Stratelli.');
        if ($userId <= 0) throw new RuntimeException('Usuário inválido.');
        $novaSenha = (string)($data['nova_senha'] ?? '');
        $confirmacao = (string)($data['confirmacao_senha'] ?? '');
        if ($novaSenha === '' || $confirmacao === '') throw new RuntimeException('Preencha a nova senha e a confirmação.');
        if (strlen($novaSenha) < 10) throw new RuntimeException('A nova senha deve ter no mínimo 10 caracteres.');
        if ($novaSenha !== $confirmacao) throw new RuntimeException('A confirmação da nova senha não confere.');

        $pdo = Database::connection();
        $old = $this->findForAdmin($userId);
        if ((int)$old['ativo'] !== 1) throw new RuntimeException('Não é possível definir senha para um usuário inativo.');
        if (password_verify($novaSenha, (string)$old['senha_hash'])) throw new RuntimeException('A nova senha deve ser diferente da senha atual.');

        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        $stmt = $pdo->prepare('UPDATE usuarios SET senha_hash=?, auth_version=auth_version+1, senha_alterada_em=NOW(), atualizado_em=NOW() WHERE id=?');
        $stmt->execute([password_hash($novaSenha, $algo), $userId]);
        Audit::log('SENHA_ALTERADA_ADMIN', 'Senha redefinida pelo administrador da plataforma para '.$old['email'], (int)($old['municipio_id'] ?? 0) ?: null, ['categoria'=>'SEGURANCA','severidade'=>'ATENCAO','usuario_alvo'=>$userId]);
    }

    public function setActive(int $userId,bool $active): void
    {
        if(!Auth::isPlatformAdmin())throw new RuntimeException('Acesso restrito à Stratelli.');if($userId===(int)Auth::id()&&!$active)throw new RuntimeException('Você não pode desativar sua própria conta.');$pdo=Database::connection();$u=$this->findForAdmin($userId);if(!$active){$this->assertCanRemoveManager($userId);if((int)$u['administrador_plataforma']===1)$this->assertCanRemovePlatformAdmin($userId);}$pdo->prepare('UPDATE usuarios SET ativo=?,auth_version=auth_version+1,atualizado_em=NOW() WHERE id=?')->execute([$active?1:0,$userId]);Audit::log($active?'USUARIO_ATIVADO':'USUARIO_DESATIVADO','Status alterado para '.$u['email'],(int)($u['municipio_id']??0)?:null,['categoria'=>'SEGURANCA','severidade'=>'ATENCAO','usuario_alvo'=>$userId]);
    }

    private function assertCanRemovePlatformAdmin(int $userId): void
    {
        $pdo=Database::connection();$st=$pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE administrador_plataforma=1 AND ativo=1 AND id<>?');$st->execute([$userId]);if((int)$st->fetchColumn()<1)throw new RuntimeException('A plataforma deve permanecer com ao menos um Administrador Stratelli ativo.');
    }

    public function assertCanRemoveManager(int $userId): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT municipio_id,grupo,ativo FROM usuarios WHERE id=?');
        $stmt->execute([$userId]);
        $u = $stmt->fetch();
        if (!$u || !$u['municipio_id'] || $u['grupo'] !== 'GESTOR' || !(int)$u['ativo']) return;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE municipio_id=? AND grupo='GESTOR' AND ativo=1 AND id<>?");
        $stmt->execute([$u['municipio_id'],$userId]);
        if ((int)$stmt->fetchColumn() < 1) throw new RuntimeException('O município deve permanecer com ao menos um gestor ativo.');
    }
}
