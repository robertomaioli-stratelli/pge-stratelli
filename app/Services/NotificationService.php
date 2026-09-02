<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Tenant;
use PDO;

final class NotificationService
{
    private PDO $pdo;

    public function __construct(){ $this->pdo=Database::connection(); }

    public function recentForCurrentUser(int $limit=8): array
    {
        $uid=(int)(Auth::id()??0); if(!$uid||!$this->tableReady())return[];
        $limit=max(1,min(50,$limit));
        $st=$this->pdo->prepare('SELECT n.*,m.nome municipio_nome,m.uf municipio_uf FROM notificacoes n LEFT JOIN municipios m ON m.id=n.municipio_id WHERE n.usuario_id=? ORDER BY (n.lida_em IS NULL) DESC,n.prioridade DESC,n.id DESC LIMIT '.$limit);
        $st->execute([$uid]); return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function unreadCount(): int
    {
        $uid=(int)(Auth::id()??0); if(!$uid||!$this->tableReady())return 0;
        $st=$this->pdo->prepare('SELECT COUNT(*) FROM notificacoes WHERE usuario_id=? AND lida_em IS NULL');$st->execute([$uid]);return(int)$st->fetchColumn();
    }

    public function listing(array $filters=[]): array
    {
        $uid=(int)(Auth::id()??0); if(!$uid||!$this->tableReady())return['rows'=>[],'total'=>0,'page'=>1,'pages'=>1,'perPage'=>10,'unread'=>0,'types'=>[]];
        $page=max(1,(int)($filters['page']??1));$perPage=10;
        $status=(string)($filters['status']??'');$type=trim((string)($filters['tipo']??''));$q=trim((string)($filters['q']??''));
        $where=['n.usuario_id=?'];$params=[$uid];
        if($status==='nao_lidas')$where[]='n.lida_em IS NULL'; elseif($status==='lidas')$where[]='n.lida_em IS NOT NULL';
        if($type!==''){$where[]='n.tipo=?';$params[]=$type;}
        if($q!==''){$where[]='(n.titulo LIKE ? OR n.mensagem LIKE ? OR COALESCE(m.nome,"") LIKE ?)';$like='%'.$q.'%';array_push($params,$like,$like,$like);}
        $sqlWhere=' WHERE '.implode(' AND ',$where);
        $st=$this->pdo->prepare('SELECT COUNT(*) FROM notificacoes n LEFT JOIN municipios m ON m.id=n.municipio_id'.$sqlWhere);$st->execute($params);$total=(int)$st->fetchColumn();
        $pages=max(1,(int)ceil($total/$perPage));$page=min($page,$pages);$offset=($page-1)*$perPage;
        $st=$this->pdo->prepare('SELECT n.*,m.nome municipio_nome,m.uf municipio_uf FROM notificacoes n LEFT JOIN municipios m ON m.id=n.municipio_id'.$sqlWhere.' ORDER BY (n.lida_em IS NULL) DESC,n.prioridade DESC,n.id DESC LIMIT '.$perPage.' OFFSET '.$offset);$st->execute($params);$rows=$st->fetchAll(PDO::FETCH_ASSOC);
        $types=$this->pdo->prepare('SELECT DISTINCT tipo FROM notificacoes WHERE usuario_id=? ORDER BY tipo');$types->execute([$uid]);
        return['rows'=>$rows,'total'=>$total,'page'=>$page,'pages'=>$pages,'perPage'=>$perPage,'unread'=>$this->unreadCount(),'types'=>$types->fetchAll(PDO::FETCH_COLUMN)];
    }

    public function markRead(int $id): void
    {
        $uid=(int)(Auth::id()??0);if(!$uid||!$this->tableReady())return;$this->pdo->prepare('UPDATE notificacoes SET lida_em=COALESCE(lida_em,NOW()) WHERE id=? AND usuario_id=?')->execute([$id,$uid]);
    }

    public function markAllRead(): void
    {
        $uid=(int)(Auth::id()??0);if(!$uid||!$this->tableReady())return;$this->pdo->prepare('UPDATE notificacoes SET lida_em=NOW() WHERE usuario_id=? AND lida_em IS NULL')->execute([$uid]);
    }

    public function open(int $id): string
    {
        $uid=(int)(Auth::id()??0);if(!$uid||!$this->tableReady())return'/';
        $st=$this->pdo->prepare('SELECT link FROM notificacoes WHERE id=? AND usuario_id=? LIMIT 1');$st->execute([$id,$uid]);$link=(string)($st->fetchColumn()?:'/');
        $this->markRead($id); return str_starts_with($link,'/')&&!str_starts_with($link,'//')?$link:'/';
    }

    public function documentUploaded(int $documentId): void
    {
        if(!$this->tableReady())return;
        $st=$this->pdo->prepare('SELECT d.id,d.status,d.enviado_em,r.id requisito_id,r.nome documento_nome,r.fase_id,r.perfil_envio,s.nome secretaria_nome,f.ordem fase_ordem,f.aba fase_aba,m.id municipio_id,m.nome municipio_nome,m.uf,m.slug FROM documentos_enviados d JOIN requisitos_documentais r ON r.id=d.requisito_id AND r.municipio_id=d.municipio_id JOIN secretarias s ON s.id=r.secretaria_id AND s.municipio_id=r.municipio_id JOIN fases f ON f.id=r.fase_id AND f.municipio_id=r.municipio_id JOIN municipios m ON m.id=d.municipio_id WHERE d.id=? LIMIT 1');$st->execute([$documentId]);$x=$st->fetch(PDO::FETCH_ASSOC);if(!$x)return;
        if($x['status']==='AGUARDANDO'){
            $this->notifyUsers($this->platformAdminIds(),'DOCUMENTO_ENVIADO','📄','Novo documento enviado',$x['municipio_nome'].' — '.$x['documento_nome'].' / '.$x['secretaria_nome'].' · Fase '.$x['fase_ordem'].' — '.$x['fase_aba'],'/'.$x['slug'].'/workflow/fase/'.$x['fase_id'],(int)$x['municipio_id'],430,'DOC_ENVIADO:'.$documentId);
        }elseif($x['status']==='APROVADO'){
            $this->phaseReadyForClosure((int)$x['municipio_id'],(int)$x['fase_id']);
        }
    }

    public function documentValidated(int $documentId,string $status,string $observation=''): void
    {
        if(!$this->tableReady())return;
        $st=$this->pdo->prepare('SELECT d.id,d.requisito_id,r.nome documento_nome,r.fase_id,r.secretaria_id,r.departamento_id,s.nome secretaria_nome,f.ordem fase_ordem,f.aba fase_aba,m.id municipio_id,m.nome municipio_nome,m.slug FROM documentos_enviados d JOIN requisitos_documentais r ON r.id=d.requisito_id AND r.municipio_id=d.municipio_id JOIN secretarias s ON s.id=r.secretaria_id AND s.municipio_id=r.municipio_id JOIN fases f ON f.id=r.fase_id AND f.municipio_id=r.municipio_id JOIN municipios m ON m.id=d.municipio_id WHERE d.id=? LIMIT 1');$st->execute([$documentId]);$x=$st->fetch(PDO::FETCH_ASSOC);if(!$x)return;
        $users=$this->requirementAudience((int)$x['municipio_id'],(int)$x['requisito_id']);$link='/'.$x['slug'].'/workflow/fase/'.$x['fase_id'];
        if($status==='APROVADO'){
            $this->notifyUsers($users,'APROVACAO','✓','Documento aprovado',$x['documento_nome'].' foi aprovado pela Stratelli. · Fase '.$x['fase_ordem'].' — '.$x['fase_aba'],$link,(int)$x['municipio_id'],360,'DOC_APROVADO:'.$documentId);
            $this->phaseReadyForClosure((int)$x['municipio_id'],(int)$x['fase_id']);
        }else{
            $msg=$x['documento_nome'].' precisa de correção'.($observation!==''?': '.$observation:'.');
            $this->notifyUsers($users,'CORRECAO','!','Correção solicitada',$msg,$link,(int)$x['municipio_id'],480,'DOC_CORRECAO:'.$documentId);
        }
    }

    public function userCreated(int $userId): void
    {
        if(!$this->tableReady())return;
        $st=$this->pdo->prepare('SELECT u.id,u.nome,u.email,u.municipio_id,u.administrador_plataforma,m.nome municipio_nome,m.slug FROM usuarios u LEFT JOIN municipios m ON m.id=u.municipio_id WHERE u.id=? LIMIT 1');$st->execute([$userId]);$u=$st->fetch(PDO::FETCH_ASSOC);if(!$u)return;
        $link=(int)$u['administrador_plataforma']===1?'/admin':'/'.($u['slug']?:'').'/dashboard';
        $this->notifyUsers([$userId],'USUARIO_CRIADO','👤','Seu acesso foi criado','Seu usuário INPACTA by Stratelli está ativo'.($u['municipio_nome']?' para '.$u['municipio_nome']:'').'.',$link,$u['municipio_id']?(int)$u['municipio_id']:null,250,'USUARIO_CRIADO:'.$userId);
        $admins=array_values(array_filter($this->platformAdminIds(),fn($id)=>$id!==(int)(Auth::id()??0)&&$id!==$userId));
        $this->notifyUsers($admins,'USUARIO_CRIADO','👤','Novo usuário criado',$u['nome'].' · '.$u['email'].($u['municipio_nome']?' · '.$u['municipio_nome']:''),'/admin/usuarios',$u['municipio_id']?(int)$u['municipio_id']:null,180,'USUARIO_CRIADO_ADMIN:'.$userId);
    }

    public function territorialActivated(int $municipioId): void
    {
        if(!$this->tableReady())return;$m=$this->municipio($municipioId);if(!$m)return;
        $this->notifyUsers($this->municipalUserIds($municipioId),'TERRITORIO_ATIVADO','⌖','Inteligência Territorial liberada','A Inteligência Territorial foi ativada para '.$m['nome'].'.','/'.$m['slug'].'/territorio',$municipioId,300,'TERRITORIO_ATIVADO:'.$municipioId.':'.date('YmdHis'));
        $admins=array_values(array_filter($this->platformAdminIds(),fn($id)=>$id!==(int)(Auth::id()??0)));$this->notifyUsers($admins,'TERRITORIO_ATIVADO','⌖','Inteligência Territorial ativada — '.$m['nome'],'O módulo foi liberado para os usuários municipais.','/admin/municipios/'.$municipioId,$municipioId,170,'TERRITORIO_ATIVADO_ADMIN:'.$municipioId.':'.date('YmdHis'));
    }

    public function phaseCompleted(int $municipioId,int $phaseId,bool $force=false,int $eventId=0): void
    {
        if(!$this->tableReady())return;if(!$force&&!$this->isPhaseFormallyClosed($municipioId,$phaseId))return;$m=$this->municipio($municipioId);if(!$m)return;
        $st=$this->pdo->prepare('SELECT ordem,aba,titulo FROM fases WHERE id=? AND municipio_id=? LIMIT 1');$st->execute([$phaseId,$municipioId]);$f=$st->fetch(PDO::FETCH_ASSOC);if(!$f)return;
        $msg='Fase '.$f['ordem'].' — '.$f['aba'].' encerrada formalmente'.(!empty($f['titulo'])?' · '.$f['titulo']:'').'.';
        $suffix=$eventId>0?':'.$eventId:':'.date('YmdHis');
        $users=$this->municipalUserIds($municipioId);$this->notifyUsers($users,'FASE_CONCLUIDA','✓','Fase encerrada',$msg,'/'.$m['slug'].'/dashboard',$municipioId,390,'FASE_CONCLUIDA:'.$municipioId.':'.$phaseId.$suffix);
        $admins=array_values(array_filter($this->platformAdminIds(),fn($id)=>$id!==(int)(Auth::id()??0)));$this->notifyUsers($admins,'FASE_CONCLUIDA','✓','Fase encerrada — '.$m['nome'],$msg,'/'.$m['slug'].'/workflow/fase/'.$phaseId,$municipioId,260,'FASE_CONCLUIDA_ADMIN:'.$municipioId.':'.$phaseId.$suffix);
    }

    public function phaseReadyForClosure(int $municipioId,int $phaseId): void
    {
        if(!$this->tableReady()||!$this->isPhaseComplete($municipioId,$phaseId)||$this->isPhaseFormallyClosed($municipioId,$phaseId))return;
        $m=$this->municipio($municipioId);if(!$m)return;$st=$this->pdo->prepare('SELECT ordem,aba FROM fases WHERE id=? AND municipio_id=? LIMIT 1');$st->execute([$phaseId,$municipioId]);$f=$st->fetch(PDO::FETCH_ASSOC);if(!$f)return;
        $latest=$this->pdo->prepare('SELECT MAX(COALESCE(d.validado_em,d.enviado_em)) FROM documentos_enviados d JOIN requisitos_documentais r ON r.id=d.requisito_id AND r.municipio_id=d.municipio_id WHERE d.municipio_id=? AND r.fase_id=? AND r.ativo=1 AND r.obrigatorio=1');$latest->execute([$municipioId,$phaseId]);$stamp=preg_replace('/[^0-9]/','',(string)($latest->fetchColumn()?:date('Y-m-d H:i:s')));
        $msg='Todos os documentos obrigatórios da Fase '.$f['ordem'].' — '.$f['aba'].' estão aprovados. Falta o encerramento formal.';
        $this->notifyUsers($this->platformAdminIds(),'FASE_PRONTA','◎','Fase pronta para encerramento',$m['nome'].' · '.$msg,'/'.$m['slug'].'/workflow/fase/'.$phaseId,$municipioId,500,'FASE_PRONTA:'.$municipioId.':'.$phaseId.':'.$stamp);
        $this->notifyUsers($this->managerIds($municipioId),'FASE_PRONTA','◎','Documentação da fase concluída',$msg.' Aguardando ato formal da Stratelli.','/'.$m['slug'].'/workflow/fase/'.$phaseId,$municipioId,220,'FASE_PRONTA_GESTOR:'.$municipioId.':'.$phaseId.':'.$stamp);
    }

    public function phaseReopened(int $municipioId,int $phaseId,string $reason,int $eventId=0): void
    {
        if(!$this->tableReady())return;$m=$this->municipio($municipioId);if(!$m)return;$st=$this->pdo->prepare('SELECT ordem,aba FROM fases WHERE id=? AND municipio_id=? LIMIT 1');$st->execute([$phaseId,$municipioId]);$f=$st->fetch(PDO::FETCH_ASSOC);if(!$f)return;
        $msg='Fase '.$f['ordem'].' — '.$f['aba'].' reaberta pela Stratelli. Motivo: '.$reason;$suffix=$eventId>0?':'.$eventId:':'.date('YmdHis');
        $this->notifyUsers($this->municipalUserIds($municipioId),'FASE_REABERTA','↺','Fase reaberta',$msg,'/'.$m['slug'].'/workflow/fase/'.$phaseId,$municipioId,470,'FASE_REABERTA:'.$municipioId.':'.$phaseId.$suffix);
        $admins=array_values(array_filter($this->platformAdminIds(),fn($id)=>$id!==(int)(Auth::id()??0)));$this->notifyUsers($admins,'FASE_REABERTA','↺','Fase reaberta — '.$m['nome'],$msg,'/'.$m['slug'].'/workflow/fase/'.$phaseId,$municipioId,280,'FASE_REABERTA_ADMIN:'.$municipioId.':'.$phaseId.$suffix);
    }

    public function syncDeadlineState(?array $phase,?array $deadline): void
    {
        if(!$this->tableReady()||!$phase||!$deadline)return;$status=(string)($deadline['status']??'');if(!in_array($status,['attention','overdue'],true))return;
        $mid=(int)(Tenant::id()??0);if(!$mid)return;$m=$this->municipio($mid);if(!$m)return;$fid=(int)$phase['id'];$dateKey=(string)($deadline['fim']??'sem-data');
        $users=array_values(array_unique(array_merge($this->platformAdminIds(),$this->phaseAudience($mid,$fid))));
        if($status==='attention'){
            $text='Fase '.$phase['ordem'].' — '.$phase['aba'].' · '.($deadline['texto']??'prazo próximo do vencimento');
            $this->notifyUsers($users,'PRAZO_PROXIMO','⌛','Prazo próximo do vencimento',$text,'/'.$m['slug'].'/workflow/fase/'.$fid,$mid,440,'PRAZO_PROXIMO:'.$mid.':'.$fid.':'.$dateKey);
        }else{
            $text='Fase '.$phase['ordem'].' — '.$phase['aba'].' · '.($deadline['texto']??'prazo vencido');
            $this->notifyUsers($users,'PRAZO_VENCIDO','⏰','Prazo vencido',$text,'/'.$m['slug'].'/workflow/fase/'.$fid,$mid,520,'PRAZO_VENCIDO:'.$mid.':'.$fid.':'.$dateKey);
        }
    }

    public function notifyUsers(array $userIds,string $type,string $icon,string $title,string $message,string $link,?int $municipioId,int $priority,string $eventKey): void
    {
        if(!$this->tableReady())return;if($municipioId!==null&&!(new InstanceParameterService())->notificationsEnabled($municipioId))return;$ids=array_values(array_unique(array_filter(array_map('intval',$userIds))));if(!$ids)return;
        $st=$this->pdo->prepare('INSERT IGNORE INTO notificacoes(usuario_id,municipio_id,tipo,icone,titulo,mensagem,link,prioridade,chave_evento,lida_em,criado_em) VALUES(?,?,?,?,?,?,?,?,?,NULL,NOW())');
        foreach($ids as $uid){$st->execute([$uid,$municipioId,$type,$icon,$title,$message,$link,$priority,$eventKey]);}
    }

    private function isPhaseComplete(int $mid,int $phaseId): bool
    {
        $st=$this->pdo->prepare('SELECT id FROM requisitos_documentais WHERE municipio_id=? AND fase_id=? AND ativo=1 AND obrigatorio=1');$st->execute([$mid,$phaseId]);$ids=array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN));if(!$ids)return false;
        foreach($ids as $rid){$d=$this->pdo->prepare('SELECT status FROM documentos_enviados WHERE municipio_id=? AND requisito_id=? ORDER BY id DESC LIMIT 1');$d->execute([$mid,$rid]);if((string)$d->fetchColumn()!=='APROVADO')return false;}return true;
    }

    private function isPhaseFormallyClosed(int $mid,int $phaseId): bool
    {
        $st=$this->pdo->prepare('SELECT status FROM cronograma_fases WHERE municipio_id=? AND fase_id=? LIMIT 1');$st->execute([$mid,$phaseId]);return strtoupper((string)$st->fetchColumn())==='ENCERRADA';
    }

    private function requirementAudience(int $mid,int $requirementId): array
    {
        $st=$this->pdo->prepare('SELECT secretaria_id,departamento_id FROM requisitos_documentais WHERE id=? AND municipio_id=? LIMIT 1');$st->execute([$requirementId,$mid]);$r=$st->fetch(PDO::FETCH_ASSOC);if(!$r)return$this->managerIds($mid);
        $ids=$this->managerIds($mid);$sql='SELECT id FROM usuarios WHERE municipio_id=? AND grupo="USUARIO" AND ativo=1 AND secretaria_id=?';$p=[$mid,(int)$r['secretaria_id']];
        $dep=(int)($r['departamento_id']??0);if($dep){$sql.=' AND (departamento_id IS NULL OR departamento_id=?)';$p[]=$dep;}
        $q=$this->pdo->prepare($sql);$q->execute($p);return array_values(array_unique(array_merge($ids,array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN)))));
    }

    private function phaseAudience(int $mid,int $phaseId): array
    {
        $ids=$this->managerIds($mid);
        $st=$this->pdo->prepare('SELECT DISTINCT u.id FROM usuarios u JOIN requisitos_documentais r ON r.municipio_id=u.municipio_id AND r.secretaria_id=u.secretaria_id AND r.fase_id=? AND r.ativo=1 AND r.perfil_envio="MUNICIPIO" WHERE u.municipio_id=? AND u.grupo="USUARIO" AND u.ativo=1 AND (u.departamento_id IS NULL OR r.departamento_id IS NULL OR r.departamento_id=u.departamento_id)');$st->execute([$phaseId,$mid]);
        return array_values(array_unique(array_merge($ids,array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN)))));
    }
    private function managerIds(int $mid): array{$st=$this->pdo->prepare('SELECT id FROM usuarios WHERE municipio_id=? AND grupo="GESTOR" AND ativo=1');$st->execute([$mid]);return array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN));}
    private function municipalUserIds(int $mid): array{$st=$this->pdo->prepare('SELECT id FROM usuarios WHERE municipio_id=? AND ativo=1');$st->execute([$mid]);return array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN));}
    private function platformAdminIds(): array{$st=$this->pdo->query('SELECT id FROM usuarios WHERE administrador_plataforma=1 AND ativo=1');return array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN));}
    private function municipio(int $mid): ?array{$st=$this->pdo->prepare('SELECT id,nome,uf,slug FROM municipios WHERE id=? LIMIT 1');$st->execute([$mid]);$r=$st->fetch(PDO::FETCH_ASSOC);return$r?:null;}

    private function tableReady(): bool
    {
        static $ready=null;if($ready!==null)return$ready;
        try{$this->pdo->query('SELECT 1 FROM notificacoes LIMIT 1');return$ready=true;}catch(\Throwable){return$ready=false;}
    }
}
