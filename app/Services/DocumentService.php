<?php
namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Tenant;
use PDO;
use RuntimeException;

final class DocumentService
{
    private PDO $pdo;
    private int $mid;
    private array $user;
    private FileStorage $files;
    private HistoryService $history;
    private InstanceParameterService $params;

    public function __construct()
    {
        $this->pdo=Database::connection();$this->mid=(int)Tenant::id();$this->user=Auth::user()??[];$this->files=new FileStorage();$this->history=new HistoryService();$this->params=new InstanceParameterService();
    }

    public function uploadDocument(int $requirementId,string $sendObservation=''): int
    {
        $req=$this->requirement($requirementId);$this->assertCanSend($req);(new PhaseClosureService())->assertOpen((int)$req['fase_id']);
        $specific=array_filter(array_map('trim',explode(',',(string)$req['extensoes'])));$allowed=$this->params->allowedExtensions($this->mid,$specific);if(!$allowed)throw new RuntimeException('Nenhuma extensão deste tipo documental está liberada nos Parâmetros da Instância.');
        $sendObservation=trim($sendObservation);if($this->params->observationRequired($this->mid,'envio')&&$sendObservation==='')throw new RuntimeException('Informe uma observação para o envio deste documento.');
        $up=$this->files->storeUpload('arquivo','documentos/'.$this->mid,'documento-'.$requirementId,$allowed,$this->params->maxUploadBytes($this->mid));
        $this->pdo->beginTransaction();
        try{
            $st=$this->pdo->prepare('SELECT * FROM documentos_enviados WHERE municipio_id=? AND requisito_id=? ORDER BY versao DESC,id DESC LIMIT 1 FOR UPDATE');$st->execute([$this->mid,$requirementId]);$previous=$st->fetch(PDO::FETCH_ASSOC)?:null;
            $version=(int)($previous['versao']??0)+1;
            $status=Auth::isPlatformAdmin()&&$req['perfil_envio']==='STRATELLI'?'APROVADO':'AGUARDANDO';
            $validatedBy=$status==='APROVADO'?Auth::id():null;$validatedAt=$status==='APROVADO'?date('Y-m-d H:i:s'):null;
            $sql='INSERT INTO documentos_enviados(municipio_id,requisito_id,documento_anterior_id,versao,arquivo_original,arquivo_salvo,tamanho,mime_type,checksum_sha256,observacao_envio,status,observacao_validacao,enviado_por_usuario_id,enviado_em,validado_por_usuario_id,validado_em)
                VALUES(?,?,?,?,?,?,?,?,?,?,?,NULL,?,NOW(),?,?)';
            $this->pdo->prepare($sql)->execute([$this->mid,$requirementId,$previous['id']??null,$version,$up['original'],$up['path'],$up['size'],$up['mime'],$up['checksum'],$sendObservation?:null,$status,Auth::id(),$validatedBy,$validatedAt]);
            $docId=(int)$this->pdo->lastInsertId();
            $this->history->log(['fase_id'=>$req['fase_id'],'requisito_id'=>$requirementId,'documento_id'=>$docId,'evento'=>$previous?'Nova versão enviada':'Documento enviado','tipo_arquivo'=>'documento','arquivo_original'=>$up['original'],'arquivo_salvo'=>$up['path'],'arquivo_anterior_original'=>$previous['arquivo_original']??'','tamanho'=>$up['size'],'mime_type'=>$up['mime'],'checksum_sha256'=>$up['checksum'],'versao'=>$version,'motivo'=>$sendObservation?:($previous?'Versão '.$version.' registrada em substituição à versão '.(int)$previous['versao'].'.':'Primeiro envio do documento.'),'status'=>$status]);
            $this->pdo->commit();
        }catch(\Throwable $e){$this->pdo->rollBack();$this->files->discard($up['path']);throw $e;}
        Audit::log('DOCUMENTO_ENVIADO','Documento #'.$docId.' · requisito #'.$requirementId.' · versão '.$version.' · SHA-256 '.$up['checksum'],$this->mid);
        try{(new NotificationService())->documentUploaded($docId);}catch(\Throwable){}
        return $docId;
    }

    public function uploadModel(int $requirementId,array $groupIds): void
    {
        if(!Auth::isPlatformAdmin())throw new RuntimeException('Apenas a Stratelli pode disponibilizar modelos.');
        $ids=array_values(array_unique(array_filter(array_map('intval',$groupIds))));if(!in_array($requirementId,$ids,true))$ids[]=$requirementId;if(!$ids)throw new RuntimeException('Documento configurado não encontrado.');
        $marks=implode(',',array_fill(0,count($ids),'?'));$params=array_merge([$this->mid],$ids);
        $st=$this->pdo->prepare('SELECT r.*,t.extensoes FROM requisitos_documentais r JOIN tipos_documento t ON t.id=r.tipo_documento_id AND t.municipio_id=r.municipio_id WHERE r.municipio_id=? AND r.id IN ('.$marks.') ORDER BY r.id');$st->execute($params);$reqs=$st->fetchAll(PDO::FETCH_ASSOC);if(!$reqs)throw new RuntimeException('Documento configurado não encontrado.');
        $ref=$reqs[0];(new PhaseClosureService())->assertOpen((int)$ref['fase_id']);foreach($reqs as $r){if((int)$r['fase_id']!==(int)$ref['fase_id']||(int)$r['tipo_documento_id']!==(int)$ref['tipo_documento_id']||trim($r['nome'])!==trim($ref['nome'])||$r['perfil_envio']!==$ref['perfil_envio']||(int)$r['obrigatorio']!==(int)$ref['obrigatorio'])throw new RuntimeException('Os documentos selecionados não podem compartilhar o mesmo modelo.');}
        $specific=array_filter(array_map('trim',explode(',',$ref['extensoes'])));$allowed=$this->params->allowedExtensions($this->mid,$specific);if(!$allowed)throw new RuntimeException('Nenhuma extensão deste tipo documental está liberada nos Parâmetros da Instância.');$up=$this->files->storeUpload('arquivo_modelo','modelos/'.$this->mid,'modelo-grupo-'.$requirementId,$allowed,$this->params->maxUploadBytes($this->mid));
        $this->pdo->beginTransaction();
        try{
            foreach($reqs as $r){
                $st=$this->pdo->prepare('SELECT * FROM modelos_documentos WHERE municipio_id=? AND requisito_id=? ORDER BY versao DESC,id DESC LIMIT 1 FOR UPDATE');$st->execute([$this->mid,$r['id']]);$previous=$st->fetch(PDO::FETCH_ASSOC)?:null;$version=(int)($previous['versao']??0)+1;
                $this->pdo->prepare('UPDATE modelos_documentos SET ativo=0 WHERE municipio_id=? AND requisito_id=?')->execute([$this->mid,$r['id']]);
                $this->pdo->prepare('INSERT INTO modelos_documentos(municipio_id,requisito_id,modelo_anterior_id,versao,arquivo_original,arquivo_salvo,tamanho,mime_type,checksum_sha256,usuario_id,ativo,criado_em) VALUES(?,?,?,?,?,?,?,?,?,?,1,NOW())')->execute([$this->mid,$r['id'],$previous['id']??null,$version,$up['original'],$up['path'],$up['size'],$up['mime'],$up['checksum'],Auth::id()]);
                $this->history->log(['fase_id'=>$r['fase_id'],'requisito_id'=>$r['id'],'evento'=>$previous?'Modelo substituído':'Modelo disponibilizado','tipo_arquivo'=>'modelo','arquivo_original'=>$up['original'],'arquivo_salvo'=>$up['path'],'arquivo_anterior_original'=>$previous['arquivo_original']??'','tamanho'=>$up['size'],'mime_type'=>$up['mime'],'checksum_sha256'=>$up['checksum'],'versao'=>$version,'motivo'=>count($reqs)>1?'Modelo único aplicado a todas as secretarias vinculadas a este documento.':'Modelo disponibilizado pela Stratelli.','status'=>'MODELO']);
            }
            $this->pdo->commit();
            Audit::log('MODELO_DOCUMENTAL_ENVIADO','Modelo SHA-256 '.$up['checksum'].' aplicado a '.count($reqs).' requisito(s).',$this->mid);
        }catch(\Throwable $e){$this->pdo->rollBack();$this->files->discard($up['path']);throw$e;}
    }

    public function validate(int $documentId,string $action,string $observation=''): void
    {
        if(!Auth::isPlatformAdmin())throw new RuntimeException('Apenas a Stratelli pode validar documentos.');
        $st=$this->pdo->prepare('SELECT d.*,r.fase_id,r.nome FROM documentos_enviados d JOIN requisitos_documentais r ON r.id=d.requisito_id AND r.municipio_id=d.municipio_id WHERE d.id=? AND d.municipio_id=?');$st->execute([$documentId,$this->mid]);$doc=$st->fetch(PDO::FETCH_ASSOC);if(!$doc)throw new RuntimeException('Documento enviado não encontrado.');(new PhaseClosureService())->assertOpen((int)$doc['fase_id']);if($doc['status']==='APROVADO')throw new RuntimeException('Este documento já está aprovado. Envie uma nova versão para realizar uma nova validação.');if($doc['status']==='CORRECAO')throw new RuntimeException('Aguarde o reenvio de uma nova versão antes de realizar outra validação.');
        $status=$action==='aprovar'?'APROVADO':'CORRECAO';$observation=trim($observation);if($status==='CORRECAO'&&$observation==='')throw new RuntimeException('Informe o motivo da correção.');if($status==='APROVADO'&&$this->params->observationRequired($this->mid,'aprovacao')&&$observation==='')throw new RuntimeException('Informe uma observação para a aprovação documental.');
        $this->pdo->prepare('UPDATE documentos_enviados SET status=?,observacao_validacao=?,validado_por_usuario_id=?,validado_em=NOW() WHERE id=? AND municipio_id=?')->execute([$status,$observation?:null,Auth::id(),$documentId,$this->mid]);
        $this->history->log(['fase_id'=>$doc['fase_id'],'requisito_id'=>$doc['requisito_id'],'documento_id'=>$documentId,'evento'=>$status==='APROVADO'?'Documento aprovado':'Correção solicitada','tipo_arquivo'=>'validacao','arquivo_original'=>$doc['arquivo_original'],'arquivo_salvo'=>$doc['arquivo_salvo'],'tamanho'=>$doc['tamanho'],'mime_type'=>$doc['mime_type']??'','checksum_sha256'=>$doc['checksum_sha256']??'','versao'=>$doc['versao']??null,'motivo'=>$observation?:'Documento conferido e aprovado pela Stratelli.','status'=>$status]);
        Audit::log($status==='APROVADO'?'DOCUMENTO_APROVADO':'DOCUMENTO_CORRECAO_SOLICITADA','Documento #'.$documentId.' · versão '.(int)$doc['versao'].' · SHA-256 '.($doc['checksum_sha256']??''),$this->mid);
        try{(new NotificationService())->documentValidated($documentId,$status,$observation);}catch(\Throwable){}
    }

    public function auditData(int $requirementId): array
    {
        $req=$this->requirementAny($requirementId);$this->assertCanViewRequirement($req);
        $sql='SELECT d.*,ue.nome enviado_por_nome,ue.email enviado_por_email,uv.nome validado_por_nome,uv.email validado_por_email,
            p.versao versao_anterior,p.arquivo_original arquivo_anterior
            FROM documentos_enviados d
            LEFT JOIN usuarios ue ON ue.id=d.enviado_por_usuario_id
            LEFT JOIN usuarios uv ON uv.id=d.validado_por_usuario_id
            LEFT JOIN documentos_enviados p ON p.id=d.documento_anterior_id
            WHERE d.municipio_id=? AND d.requisito_id=? ORDER BY d.versao DESC,d.id DESC';
        $st=$this->pdo->prepare($sql);$st->execute([$this->mid,$requirementId]);$versions=$st->fetchAll(PDO::FETCH_ASSOC);
        foreach($versions as &$v){$v['integridade']=$this->files->inspect((string)$v['arquivo_salvo'],(string)($v['checksum_sha256']??''));}unset($v);
        $hs=$this->pdo->prepare('SELECT h.*,u.nome usuario_nome,u.email usuario_email FROM historico_documentos h LEFT JOIN usuarios u ON u.id=h.usuario_id WHERE h.municipio_id=? AND h.requisito_id=? ORDER BY h.id DESC');$hs->execute([$this->mid,$requirementId]);$events=$hs->fetchAll(PDO::FETCH_ASSOC);
        $model=$this->pdo->prepare('SELECT m.*,u.nome usuario_nome FROM modelos_documentos m LEFT JOIN usuarios u ON u.id=m.usuario_id WHERE m.municipio_id=? AND m.requisito_id=? ORDER BY m.versao DESC,m.id DESC');$model->execute([$this->mid,$requirementId]);$models=$model->fetchAll(PDO::FETCH_ASSOC);
        foreach($models as &$m){$m['integridade']=$this->files->inspect((string)$m['arquivo_salvo'],(string)($m['checksum_sha256']??''));}unset($m);
        $latest=$versions[0]??null;
        return ['requisito'=>$req,'versoes'=>$versions,'eventos'=>$events,'modelos'=>$models,'ultimaVersao'=>$latest,'tenant'=>Tenant::current(),'scope'=>Auth::isPlatformAdmin()?'stratelli':(($this->user['grupo']??'')==='USUARIO'?'secretaria':'municipio')];
    }

    public function downloadModel(int $requirementId): never
    {
        $req=$this->requirement($requirementId);$this->assertCanViewRequirement($req);$st=$this->pdo->prepare('SELECT * FROM modelos_documentos WHERE municipio_id=? AND requisito_id=? AND ativo=1 ORDER BY id DESC LIMIT 1');$st->execute([$this->mid,$requirementId]);$m=$st->fetch(PDO::FETCH_ASSOC);if(!$m)throw new RuntimeException('O modelo ainda não foi disponibilizado.');Audit::log('MODELO_DOWNLOAD','Modelo #'.$m['id'].' · requisito #'.$requirementId.' · SHA-256 '.($m['checksum_sha256']??''),$this->mid);$this->files->send($m['arquivo_salvo'],$m['arquivo_original'],$m['checksum_sha256']??null,$m['mime_type']??null);
    }

    public function downloadDocument(int $documentId): never
    {
        $st=$this->pdo->prepare('SELECT d.*,r.secretaria_id,r.departamento_id,r.perfil_envio,r.fase_id FROM documentos_enviados d JOIN requisitos_documentais r ON r.id=d.requisito_id AND r.municipio_id=d.municipio_id WHERE d.id=? AND d.municipio_id=?');$st->execute([$documentId,$this->mid]);$d=$st->fetch(PDO::FETCH_ASSOC);if(!$d)throw new RuntimeException('Documento não encontrado.');$this->assertCanViewRequirement($d);Audit::log('DOCUMENTO_DOWNLOAD','Documento #'.$documentId.' · versão '.(int)$d['versao'].' · SHA-256 '.($d['checksum_sha256']??''),$this->mid);$this->files->send($d['arquivo_salvo'],$d['arquivo_original'],$d['checksum_sha256']??null,$d['mime_type']??null);
    }

    public function downloadHistory(int $historyId): never
    {
        $st=$this->pdo->prepare('SELECT h.*,r.secretaria_id,r.departamento_id,r.perfil_envio FROM historico_documentos h LEFT JOIN requisitos_documentais r ON r.id=h.requisito_id AND r.municipio_id=h.municipio_id WHERE h.id=? AND h.municipio_id=?');$st->execute([$historyId,$this->mid]);$h=$st->fetch(PDO::FETCH_ASSOC);if(!$h||!$h['arquivo_salvo'])throw new RuntimeException('Arquivo do histórico não encontrado.');$this->assertCanViewRequirement($h);Audit::log('HISTORICO_DOCUMENTO_DOWNLOAD','Histórico #'.$historyId.' · SHA-256 '.($h['checksum_sha256']??''),$this->mid);$this->files->send($h['arquivo_salvo'],$h['arquivo_original']?:'documento',$h['checksum_sha256']??null,$h['mime_type']??null);
    }

    private function requirement(int $id): array
    {
        $st=$this->pdo->prepare('SELECT r.*,f.ordem fase_ordem,f.aba fase_aba,f.titulo fase_titulo,s.nome secretaria_nome,s.sigla secretaria_sigla,d.nome departamento_nome,t.nome tipo_nome,t.extensoes FROM requisitos_documentais r JOIN fases f ON f.id=r.fase_id AND f.municipio_id=r.municipio_id JOIN secretarias s ON s.id=r.secretaria_id AND s.municipio_id=r.municipio_id LEFT JOIN departamentos d ON d.id=r.departamento_id AND d.municipio_id=r.municipio_id JOIN tipos_documento t ON t.id=r.tipo_documento_id AND t.municipio_id=r.municipio_id WHERE r.id=? AND r.municipio_id=? AND r.ativo=1');$st->execute([$id,$this->mid]);$r=$st->fetch(PDO::FETCH_ASSOC);if(!$r)throw new RuntimeException('Documento configurado não encontrado.');return$r;
    }

    private function requirementAny(int $id): array
    {
        $st=$this->pdo->prepare('SELECT r.*,f.ordem fase_ordem,f.aba fase_aba,f.titulo fase_titulo,s.nome secretaria_nome,s.sigla secretaria_sigla,d.nome departamento_nome,t.nome tipo_nome,t.extensoes FROM requisitos_documentais r JOIN fases f ON f.id=r.fase_id AND f.municipio_id=r.municipio_id JOIN secretarias s ON s.id=r.secretaria_id AND s.municipio_id=r.municipio_id LEFT JOIN departamentos d ON d.id=r.departamento_id AND d.municipio_id=r.municipio_id JOIN tipos_documento t ON t.id=r.tipo_documento_id AND t.municipio_id=r.municipio_id WHERE r.id=? AND r.municipio_id=?');$st->execute([$id,$this->mid]);$r=$st->fetch(PDO::FETCH_ASSOC);if(!$r)throw new RuntimeException('Documento configurado não encontrado.');return$r;
    }

    private function assertCanSend(array $r): void
    {
        if(Auth::isPlatformAdmin()){if($r['perfil_envio']!=='STRATELLI')throw new RuntimeException('O envio deste documento é de responsabilidade municipal.');return;}
        if($r['perfil_envio']!=='MUNICIPIO')throw new RuntimeException('Seu perfil não é o responsável pelo envio deste documento.');
        if(($this->user['grupo']??'')==='USUARIO'&&!$this->commonOwnsRequirement($r))throw new RuntimeException('Este documento não pertence à sua secretaria ou departamento.');
        $this->assertPhaseAccessible((int)($r['fase_id']??0));
    }

    private function assertCanViewRequirement(array $r): void
    {
        if(Auth::isPlatformAdmin())return;
        if(($r['perfil_envio']??'')!=='MUNICIPIO')throw new RuntimeException('Arquivo indisponível para seu perfil.');
        if(($this->user['grupo']??'')==='USUARIO'&&!$this->commonOwnsRequirement($r))throw new RuntimeException('Este arquivo não pertence à sua secretaria ou departamento.');
        if(!empty($r['fase_id']))$this->assertPhaseAccessible((int)$r['fase_id']);
    }

    private function assertPhaseAccessible(int $phaseId): void
    {
        if($phaseId<=0||Auth::isPlatformAdmin())return;
        $state=(new WorkflowService())->load($phaseId);
        if(!($state['faseAcesso'][$phaseId]['pode_acessar']??false))throw new RuntimeException('Esta fase ainda está bloqueada. A fase anterior precisa estar formalmente encerrada pela Stratelli.');
    }

    private function commonOwnsRequirement(array $r): bool
    {
        $secretariaId=(int)($this->user['secretaria_id']??0);$departamentoId=(int)($this->user['departamento_id']??0);
        if(!$secretariaId||(int)($r['secretaria_id']??0)!==$secretariaId)return false;
        if(!$departamentoId)return true;
        $reqDepartment=(int)($r['departamento_id']??0);
        return $reqDepartment===0||$reqDepartment===$departamentoId;
    }
}
