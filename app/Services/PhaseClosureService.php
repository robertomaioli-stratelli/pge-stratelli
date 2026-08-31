<?php
namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Tenant;
use PDO;
use RuntimeException;

final class PhaseClosureService
{
    private PDO $pdo;
    private int $mid;

    public function __construct()
    {
        $this->pdo=Database::connection();
        $this->mid=(int)Tenant::id();
    }

    public function isClosed(int $phaseId): bool
    {
        $st=$this->pdo->prepare('SELECT status FROM cronograma_fases WHERE municipio_id=? AND fase_id=? LIMIT 1');
        $st->execute([$this->mid,$phaseId]);
        return strtoupper((string)$st->fetchColumn())==='ENCERRADA';
    }

    public function assertOpen(int $phaseId): void
    {
        if($phaseId>0 && $this->isClosed($phaseId)){
            throw new RuntimeException('Esta fase está formalmente encerrada. Reabra a fase antes de realizar qualquer alteração.');
        }
    }

    public function eligibility(int $phaseId): array
    {
        $phase=$this->phase($phaseId);
        $docs=$this->phaseDocuments($phaseId,true);
        $total=count($docs);$approved=0;$waiting=0;$correction=0;$missing=0;
        foreach($docs as $d){
            $status=(string)($d['documento_status']??'');
            if($status==='APROVADO')$approved++;
            elseif($status==='AGUARDANDO')$waiting++;
            elseif($status==='CORRECAO')$correction++;
            else$missing++;
        }
        $eligible=$total===0 || $approved===$total;
        return compact('phase','total','approved','waiting','correction','missing','eligible');
    }

    public function current(int $phaseId): ?array
    {
        $st=$this->pdo->prepare('SELECT c.*,uc.nome concluido_por_nome,uc.email concluido_por_email,ur.nome reaberto_por_nome,ur.email reaberto_por_email
            FROM cronograma_fases c
            LEFT JOIN usuarios uc ON uc.id=c.concluido_por_usuario_id
            LEFT JOIN usuarios ur ON ur.id=c.reaberto_por_usuario_id
            WHERE c.municipio_id=? AND c.fase_id=? LIMIT 1');
        $st->execute([$this->mid,$phaseId]);$row=$st->fetch(PDO::FETCH_ASSOC);
        return $row?:null;
    }

    public function history(int $phaseId): array
    {
        $st=$this->pdo->prepare('SELECT h.*,u.nome usuario_nome,u.email usuario_email
            FROM historico_fases h LEFT JOIN usuarios u ON u.id=h.usuario_id
            WHERE h.municipio_id=? AND h.fase_id=? ORDER BY h.id DESC');
        $st->execute([$this->mid,$phaseId]);return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function close(int $phaseId,string $date,string $observation): int
    {
        if(!Auth::isPlatformAdmin())throw new RuntimeException('Apenas a Stratelli pode encerrar formalmente uma fase.');
        (new EtapaArchiveService())->assertOpen();
        if(!$this->validDate($date))throw new RuntimeException('Informe uma data de encerramento válida.');
        if($date>date('Y-m-d'))throw new RuntimeException('A data de encerramento não pode estar no futuro.');
        $observation=trim($observation);if((new InstanceParameterService())->observationRequired($this->mid,'encerramento')&&$observation==='')throw new RuntimeException('Informe uma observação para o encerramento formal da fase.');
        $phase=$this->phase($phaseId);
        $existing=$this->current($phaseId);if($existing&&strtoupper((string)$existing['status'])==='ENCERRADA')throw new RuntimeException('Esta fase já está formalmente encerrada.');
        $this->assertPreviousPhasesClosed($phase);
        $this->assertClosureDate($phase,$date);
        $eligibility=$this->eligibility($phaseId);
        if(!$eligibility['eligible']){
            throw new RuntimeException('A fase ainda não pode ser encerrada: '.$eligibility['approved'].' de '.$eligibility['total'].' documento(s) obrigatório(s) estão aprovados.');
        }
        $lastApproval=$this->lastMandatoryApprovalDate($phaseId);if($lastApproval&&$date<$lastApproval)throw new RuntimeException('A data de encerramento não pode ser anterior à última aprovação documental obrigatória ('.$lastApproval.').');
        [$snapshot,$hash]=$this->snapshot($phase,$eligibility);
        $this->pdo->beginTransaction();
        try{
            $sql='INSERT INTO cronograma_fases(municipio_id,fase_id,data_conclusao_real,concluido_por_usuario_id,observacao,status,snapshot_documental,snapshot_sha256,encerrado_em,reaberto_por_usuario_id,reaberto_em,motivo_reabertura,criado_em,atualizado_em)
                VALUES(?,?,?,?,?,"ENCERRADA",?,?,NOW(),NULL,NULL,NULL,NOW(),NOW())
                ON DUPLICATE KEY UPDATE data_conclusao_real=VALUES(data_conclusao_real),concluido_por_usuario_id=VALUES(concluido_por_usuario_id),observacao=VALUES(observacao),status="ENCERRADA",snapshot_documental=VALUES(snapshot_documental),snapshot_sha256=VALUES(snapshot_sha256),encerrado_em=NOW(),reaberto_por_usuario_id=NULL,reaberto_em=NULL,motivo_reabertura=NULL,atualizado_em=NOW()';
            $this->pdo->prepare($sql)->execute([$this->mid,$phaseId,$date,Auth::id(),$observation,$snapshot,$hash]);
            // Consulta explicitamente o registro após o UPSERT. Isso evita depender do estado de LAST_INSERT_ID()
            // quando a fase já existia e está sendo encerrada novamente após uma reabertura auditada.
            $q=$this->pdo->prepare('SELECT id FROM cronograma_fases WHERE municipio_id=? AND fase_id=?');
            $q->execute([$this->mid,$phaseId]);$cronogramaId=(int)$q->fetchColumn();
            if(!$cronogramaId)throw new RuntimeException('Não foi possível identificar o registro formal de encerramento da fase.');
            $this->pdo->prepare('INSERT INTO historico_fases(municipio_id,fase_id,cronograma_fase_id,evento,usuario_id,data_referencia,observacao,snapshot_documental,snapshot_sha256,criado_em) VALUES(?,?,?,"ENCERRAMENTO",?,?,?,?,?,NOW())')
                ->execute([$this->mid,$phaseId,$cronogramaId,Auth::id(),$date,$observation,$snapshot,$hash]);
            $eventId=(int)$this->pdo->lastInsertId();
            $this->pdo->commit();
        }catch(\Throwable $e){$this->pdo->rollBack();throw $e;}
        (new HistoryService())->log(['fase_id'=>$phaseId,'evento'=>'Fase encerrada formalmente','tipo_arquivo'=>'fase','checksum_sha256'=>$hash,'motivo'=>$observation,'status'=>'ENCERRADA']);
        Audit::log('FASE_ENCERRADA','Fase #'.$phaseId.' · snapshot SHA-256 '.$hash.' · data '.$date,$this->mid);
        try{(new NotificationService())->phaseCompleted($this->mid,$phaseId,true,$eventId);}catch(\Throwable){}
        return $eventId;
    }

    public function reopen(int $phaseId,string $reason): int
    {
        if(!Auth::isPlatformAdmin())throw new RuntimeException('Apenas a Stratelli pode reabrir uma fase.');
        (new EtapaArchiveService())->assertOpen();
        $reason=trim($reason);if((new InstanceParameterService())->observationRequired($this->mid,'reabertura')&&$reason==='')throw new RuntimeException('Informe o motivo da reabertura da fase.');
        $phase=$this->phase($phaseId);$current=$this->current($phaseId);
        if(!$current||strtoupper((string)$current['status'])!=='ENCERRADA')throw new RuntimeException('Esta fase não está formalmente encerrada.');
        $later=$this->pdo->prepare('SELECT f.ordem,f.aba FROM fases f JOIN cronograma_fases c ON c.fase_id=f.id AND c.municipio_id=f.municipio_id AND c.status="ENCERRADA" WHERE f.municipio_id=? AND f.ativo=1 AND f.ordem>? ORDER BY f.ordem LIMIT 1');
        $later->execute([$this->mid,(int)$phase['ordem']]);$next=$later->fetch(PDO::FETCH_ASSOC);
        if($next)throw new RuntimeException('Não é possível reabrir esta fase enquanto a Fase '.$next['ordem'].' — '.$next['aba'].' estiver encerrada. Reabra as fases posteriores primeiro.');
        $this->pdo->beginTransaction();
        try{
            $this->pdo->prepare('UPDATE cronograma_fases SET status="REABERTA",reaberto_por_usuario_id=?,reaberto_em=NOW(),motivo_reabertura=?,atualizado_em=NOW() WHERE municipio_id=? AND fase_id=?')
                ->execute([Auth::id(),$reason,$this->mid,$phaseId]);
            $this->pdo->prepare('INSERT INTO historico_fases(municipio_id,fase_id,cronograma_fase_id,evento,usuario_id,data_referencia,observacao,snapshot_documental,snapshot_sha256,criado_em) VALUES(?,?,?,"REABERTURA",?,CURDATE(),?,?,?,NOW())')
                ->execute([$this->mid,$phaseId,(int)$current['id'],Auth::id(),$reason,$current['snapshot_documental']??null,$current['snapshot_sha256']??null]);
            $eventId=(int)$this->pdo->lastInsertId();$this->pdo->commit();
        }catch(\Throwable $e){$this->pdo->rollBack();throw $e;}
        (new HistoryService())->log(['fase_id'=>$phaseId,'evento'=>'Fase reaberta','tipo_arquivo'=>'fase','checksum_sha256'=>$current['snapshot_sha256']??'','motivo'=>$reason,'status'=>'REABERTA']);
        Audit::log('FASE_REABERTA','Fase #'.$phaseId.' · motivo: '.$reason,$this->mid);
        try{(new NotificationService())->phaseReopened($this->mid,$phaseId,$reason,$eventId);}catch(\Throwable){}
        return $eventId;
    }

    private function snapshot(array $phase,array $eligibility): array
    {
        $tenant=Tenant::current()??[];$docs=$this->phaseDocuments((int)$phase['id'],false);$items=[];$files=new FileStorage();
        foreach($docs as $d){
            if(!empty($d['documento_id'])){
                $expected=trim((string)($d['checksum_sha256']??''));if($expected==='')throw new RuntimeException('O documento "'.(string)$d['requisito_nome'].'" não possui checksum SHA-256 auditado. Regularize a integridade antes de encerrar a fase.');
                $integrity=$files->inspect((string)($d['documento_arquivo_salvo']??''),$expected);if(empty($integrity['exists']))throw new RuntimeException('O arquivo físico do documento "'.(string)$d['requisito_nome'].'" não está disponível. O encerramento foi bloqueado para preservar a cadeia de custódia.');if(empty($integrity['valid']))throw new RuntimeException('Falha de integridade no documento "'.(string)$d['requisito_nome'].'". O SHA-256 atual não corresponde ao registro auditado.');
            }
            if(!empty($d['modelo_id'])&&!empty($d['modelo_checksum_sha256'])){
                $modelIntegrity=$files->inspect((string)($d['modelo_arquivo_salvo']??''),(string)$d['modelo_checksum_sha256']);if(empty($modelIntegrity['exists'])||empty($modelIntegrity['valid']))throw new RuntimeException('O modelo documental de "'.(string)$d['requisito_nome'].'" apresenta falha de integridade ou não está disponível.');
            }
            $items[]=[
                'requisito_id'=>(int)$d['requisito_id'],'documento'=>(string)$d['requisito_nome'],'descricao'=>(string)($d['requisito_descricao']??''),'ordem'=>(int)($d['requisito_ordem']??0),'obrigatorio'=>(bool)$d['obrigatorio'],
                'secretaria'=>(string)$d['secretaria_nome'],'departamento'=>(string)($d['departamento_nome']??''),'tipo'=>(string)$d['tipo_nome'],'perfil_envio'=>(string)$d['perfil_envio'],
                'modelo'=>['id'=>$d['modelo_id']?(int)$d['modelo_id']:null,'versao'=>$d['modelo_versao']?(int)$d['modelo_versao']:null,'arquivo'=>(string)($d['modelo_arquivo_original']??''),'checksum_sha256'=>(string)($d['modelo_checksum_sha256']??''),'mime_type'=>(string)($d['modelo_mime_type']??''),'tamanho'=>(int)($d['modelo_tamanho']??0),'criado_em'=>$d['modelo_criado_em']??null],
                'documento_id'=>$d['documento_id']?(int)$d['documento_id']:null,'versao'=>$d['documento_versao']?(int)$d['documento_versao']:null,
                'arquivo'=>(string)($d['arquivo_original']??''),'status'=>(string)($d['documento_status']??'PENDENTE'),'checksum_sha256'=>(string)($d['checksum_sha256']??''),
                'mime_type'=>(string)($d['mime_type']??''),'tamanho'=>(int)($d['tamanho']??0),'enviado_em'=>$d['enviado_em']??null,'validado_em'=>$d['validado_em']??null,
                'enviado_por'=>(string)($d['enviado_por_nome']??''),'validado_por'=>(string)($d['validado_por_nome']??'')
            ];
        }
        $data=[
            'schema'=>'INPACTA_PHASE_SNAPSHOT_V1','gerado_em'=>date('c'),
            'municipio'=>['id'=>$this->mid,'nome'=>(string)($tenant['nome']??''),'uf'=>(string)($tenant['uf']??''),'slug'=>(string)($tenant['slug']??'')],
            'fase'=>['id'=>(int)$phase['id'],'ordem'=>(int)$phase['ordem'],'aba'=>(string)$phase['aba'],'titulo'=>(string)$phase['titulo']],
            'documentos_obrigatorios'=>['total'=>(int)$eligibility['total'],'aprovados'=>(int)$eligibility['approved']],
            'documentos'=>$items,
        ];
        $json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION);
        if($json===false)throw new RuntimeException('Não foi possível gerar o snapshot documental da fase.');
        return[$json,hash('sha256',$json)];
    }

    private function phaseDocuments(int $phaseId,bool $mandatoryOnly): array
    {
        $sql='SELECT r.id requisito_id,r.nome requisito_nome,r.descricao requisito_descricao,r.ordem requisito_ordem,r.obrigatorio,r.perfil_envio,s.nome secretaria_nome,dpt.nome departamento_nome,t.nome tipo_nome,
            mdl.id modelo_id,mdl.versao modelo_versao,mdl.arquivo_original modelo_arquivo_original,mdl.arquivo_salvo modelo_arquivo_salvo,mdl.checksum_sha256 modelo_checksum_sha256,mdl.mime_type modelo_mime_type,mdl.tamanho modelo_tamanho,mdl.criado_em modelo_criado_em,
            doc.id documento_id,doc.versao documento_versao,doc.arquivo_original,doc.arquivo_salvo documento_arquivo_salvo,doc.status documento_status,doc.checksum_sha256,doc.mime_type,doc.tamanho,doc.enviado_em,doc.validado_em,ue.nome enviado_por_nome,uv.nome validado_por_nome
            FROM requisitos_documentais r
            JOIN secretarias s ON s.id=r.secretaria_id AND s.municipio_id=r.municipio_id
            LEFT JOIN departamentos dpt ON dpt.id=r.departamento_id AND dpt.municipio_id=r.municipio_id
            JOIN tipos_documento t ON t.id=r.tipo_documento_id AND t.municipio_id=r.municipio_id
            LEFT JOIN modelos_documentos mdl ON mdl.id=(SELECT MAX(m2.id) FROM modelos_documentos m2 WHERE m2.municipio_id=r.municipio_id AND m2.requisito_id=r.id AND m2.ativo=1)
            LEFT JOIN documentos_enviados doc ON doc.id=(SELECT MAX(d2.id) FROM documentos_enviados d2 WHERE d2.municipio_id=r.municipio_id AND d2.requisito_id=r.id)
            LEFT JOIN usuarios ue ON ue.id=doc.enviado_por_usuario_id LEFT JOIN usuarios uv ON uv.id=doc.validado_por_usuario_id
            WHERE r.municipio_id=? AND r.fase_id=? AND r.ativo=1'.($mandatoryOnly?' AND r.obrigatorio=1':'').' ORDER BY r.ordem,r.id';
        $st=$this->pdo->prepare($sql);$st->execute([$this->mid,$phaseId]);return$st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function assertPreviousPhasesClosed(array $phase): void
    {
        $st=$this->pdo->prepare('SELECT f.ordem,f.aba FROM fases f LEFT JOIN cronograma_fases c ON c.fase_id=f.id AND c.municipio_id=f.municipio_id AND c.status="ENCERRADA" WHERE f.municipio_id=? AND f.ativo=1 AND f.ordem<? AND c.id IS NULL ORDER BY f.ordem LIMIT 1');
        $st->execute([$this->mid,(int)$phase['ordem']]);$missing=$st->fetch(PDO::FETCH_ASSOC);
        if($missing)throw new RuntimeException('A Fase '.$missing['ordem'].' — '.$missing['aba'].' precisa ser encerrada formalmente antes desta fase.');
    }

    private function assertClosureDate(array $phase,string $date): void
    {
        $st=$this->pdo->prepare('SELECT f.ordem,c.data_conclusao_real FROM fases f JOIN cronograma_fases c ON c.fase_id=f.id AND c.municipio_id=f.municipio_id AND c.status="ENCERRADA" WHERE f.municipio_id=? AND f.ativo=1 AND f.ordem<? ORDER BY f.ordem DESC LIMIT 1');
        $st->execute([$this->mid,(int)$phase['ordem']]);$prev=$st->fetch(PDO::FETCH_ASSOC);
        if($prev){$min=(new \DateTimeImmutable((string)$prev['data_conclusao_real']))->modify('+1 day')->format('Y-m-d');}
        else{$q=$this->pdo->prepare('SELECT data_inicio FROM cronograma_processos WHERE municipio_id=?');$q->execute([$this->mid]);$min=(string)($q->fetchColumn()?:date('Y-m-d'));}
        if($date<$min)throw new RuntimeException('A data de encerramento não pode ser anterior ao início operacional da fase ('.$min.').');
    }

    private function lastMandatoryApprovalDate(int $phaseId): ?string
    {
        $st=$this->pdo->prepare('SELECT MAX(DATE(d.validado_em)) FROM requisitos_documentais r JOIN documentos_enviados d ON d.id=(SELECT MAX(d2.id) FROM documentos_enviados d2 WHERE d2.municipio_id=r.municipio_id AND d2.requisito_id=r.id) WHERE r.municipio_id=? AND r.fase_id=? AND r.ativo=1 AND r.obrigatorio=1 AND d.status="APROVADO"');
        $st->execute([$this->mid,$phaseId]);$v=$st->fetchColumn();return $v?((string)$v):null;
    }

    private function phase(int $phaseId): array
    {
        $st=$this->pdo->prepare('SELECT * FROM fases WHERE id=? AND municipio_id=? AND ativo=1 LIMIT 1');$st->execute([$phaseId,$this->mid]);$p=$st->fetch(PDO::FETCH_ASSOC);
        if(!$p)throw new RuntimeException('Fase não encontrada ou inativa.');return$p;
    }

    private function validDate(string $d): bool
    {
        $dt=\DateTimeImmutable::createFromFormat('!Y-m-d',$d);return$dt!==false&&$dt->format('Y-m-d')===$d;
    }
}
