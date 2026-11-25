<?php
namespace App\Services;
use App\Core\Auth;use App\Core\Audit;use App\Core\Database;use App\Core\Tenant;use PDO;use RuntimeException;
final class ActivityProgressService
{
 private PDO $pdo;private int $mid;
 public function __construct(){ $this->pdo=Database::connection();$this->mid=(int)Tenant::id(); }
 public function metrics(array $activities,array $requirements,array $latest): array
 {
  $by=[];foreach($requirements as$r){$aid=(int)($r['atividade_id']??0);if($aid>0&&(int)$r['ativo']===1 && empty($r['atividade_ato_id']))$by[$aid][]=$r;}
  $q=$this->pdo->prepare('SELECT a.id,a.ato_legado_id,a.status_execucao FROM pge_atividades_ato a WHERE a.municipio_id=? AND a.ativo=1');$q->execute([$this->mid]);$children=[];
  foreach($q->fetchAll(PDO::FETCH_ASSOC) as $child)$children[(int)$child['ato_legado_id']][]=$child;
  $docByChild=[];foreach($requirements as $r)if((int)($r['atividade_ato_id']??0)>0 && (int)$r['ativo']===1)$docByChild[(int)$r['atividade_ato_id']][]=$r;
  $out=[];foreach($activities as$a){$aid=(int)$a['id'];$docs=$by[$aid]??[];$required=array_values(array_filter($docs,fn($r)=>(int)$r['obrigatorio']===1));$approved=0;$waiting=0;$correction=0;$missing=0;
   foreach($required as$r){$d=$latest[(int)$r['id']]??null;if(!$d)$missing++;elseif($d['status']==='APROVADO')$approved++;elseif($d['status']==='CORRECAO')$correction++;else$waiting++;}
   $manual=strtoupper((string)($a['status_execucao']??'PENDENTE'))==='CONCLUIDA';$total=count($required);$complete=$total>0?($approved===$total):$manual;
   $pct=$total>0?(int)round($approved/$total*100):($complete?100:0);
   if(!empty($children[$aid])){$done=0;$points=0;foreach($children[$aid] as $child){$childDocs=array_values(array_filter($docByChild[(int)$child['id']]??[],fn($r)=>(int)$r['obrigatorio']===1));$childApproved=0;foreach($childDocs as $r)if(($latest[(int)$r['id']]['status']??'')==='APROVADO')$childApproved++;$childComplete=$childDocs?($childApproved===count($childDocs)):($child['status_execucao']==='CONCLUIDA');$points+=$childDocs?(int)round(100*$childApproved/count($childDocs)):($childComplete?100:0);if($childComplete)$done++;}$complete=$done===count($children[$aid])&&($total===0||$approved===$total);$pct=(int)round(($points/count($children[$aid]) + ($total>0?100*$approved/$total:0))/($total>0?2:1));}
   $complete=$manual; // A conclusão do ato exige confirmação administrativa.
   $status=$complete?'CONCLUIDA':($correction?'CORRECAO':(($approved+$waiting)>0?'EM_ANDAMENTO':'PENDENTE'));
   $out[$aid]=['id'=>$aid,'fase_id'=>(int)$a['fase_id'],'total_documentos'=>$total,'aprovados'=>$approved,'aguardando'=>$waiting,'correcao'=>$correction,'pendentes'=>$missing,'percentual'=>$pct,'concluida'=>$complete,'status'=>$status,'manual'=>$manual];
  }return$out;
 }
 public function phaseMetrics(array $activities,array $activityMetrics): array
 { $out=[];foreach($activities as$a){if(!(int)$a['ativo'])continue;$fid=(int)$a['fase_id'];if(!isset($out[$fid]))$out[$fid]=['total'=>0,'concluidas'=>0,'percentual'=>0];$out[$fid]['total']++;if(!empty($activityMetrics[(int)$a['id']]['concluida']))$out[$fid]['concluidas']++;}foreach($out as&$m)$m['percentual']=$m['total']?(int)round($m['concluidas']/$m['total']*100):0;unset($m);return$out; }
 /**
  * Uma unica fonte de verdade para o ato e suas atividades.
  * Restringe requisitos a fase do ato e seus filhos ativos, sem trazer requisitos
  * de outras modalidades ou fases. Status vem da versao mais recente enviada.
  */
 private function snapshot(int $atoId): array
 {
  $q=$this->pdo->prepare('SELECT a.id,a.fase_id,a.status_execucao,f.etapa_execucao_id FROM atividades_fase a JOIN fases f ON f.id=a.fase_id AND f.municipio_id=a.municipio_id AND f.ativo=1 JOIN pge_etapas_execucao e ON e.id=f.etapa_execucao_id AND e.municipio_id=a.municipio_id AND e.habilitada=1 AND e.status<>"ARQUIVADO" WHERE a.id=? AND a.municipio_id=? AND a.ativo=1');
  $q->execute([$atoId,$this->mid]);$ato=$q->fetch(PDO::FETCH_ASSOC);
  if(!$ato)throw new RuntimeException('Ato nao localizado na etapa ativa.');
  $q=$this->pdo->prepare('SELECT id,status_execucao FROM pge_atividades_ato WHERE municipio_id=? AND ato_legado_id=? AND ativo=1 ORDER BY ordem,id');
  $q->execute([$this->mid,$atoId]);$filhas=$q->fetchAll(PDO::FETCH_ASSOC);
  $q=$this->pdo->prepare('SELECT r.id,r.atividade_ato_id,r.obrigatorio, (SELECT d.status FROM documentos_enviados d WHERE d.municipio_id=r.municipio_id AND d.requisito_id=r.id ORDER BY d.versao DESC,d.id DESC LIMIT 1) ultimo_status FROM requisitos_documentais r WHERE r.municipio_id=? AND r.fase_id=? AND r.ativo=1 AND (r.atividade_id=? OR r.atividade_ato_id IN (SELECT c.id FROM pge_atividades_ato c WHERE c.municipio_id=? AND c.ato_legado_id=? AND c.ativo=1))');
  $q->execute([$this->mid,(int)$ato['fase_id'],$atoId,$this->mid,$atoId]);$docs=$q->fetchAll(PDO::FETCH_ASSOC);
  // Ha documentos vinculados a atividades e documentos gerais do ato.
  // Duplicatas por OR nao existem em SELECT simples; cada requisito e contado uma vez.
  $required=array_values(array_filter($docs,static fn($r)=>(int)$r['obrigatorio']===1));
  $ready=count($required)>0;
  foreach($required as $doc)if(($doc['ultimo_status']??null)!=='APROVADO')$ready=false;
  return ['ato'=>$ato,'filhas'=>$filhas,'requisitos'=>$required,'pronto'=>$ready];
 }

 /** Chamado apos aprovar documento ou upload ja aprovado pela Stratelli. */
 public function synchronizeFromRequirement(int $requirementId): void
 {
  $q=$this->pdo->prepare('SELECT r.atividade_id,r.atividade_ato_id,c.ato_legado_id FROM requisitos_documentais r LEFT JOIN pge_atividades_ato c ON c.id=r.atividade_ato_id AND c.municipio_id=r.municipio_id WHERE r.id=? AND r.municipio_id=? AND r.ativo=1');
  $q->execute([$requirementId,$this->mid]);$r=$q->fetch(PDO::FETCH_ASSOC);
  if(!$r)return;
  $atoId=(int)($r['ato_legado_id']??0) ?: (int)($r['atividade_id']??0);
  if($atoId>0)$this->synchronizeAct($atoId);
 }

 /** Conclui automaticamente somente quando ha requisito obrigatorio e todos estao aprovados. */
 public function synchronizeAct(int $atoId): bool
 {
  if(!(new FormalStartService())->get($this->mid))return false;
  // A transacao garante a atualizacao atomica das atividades e do ato.
  $this->pdo->beginTransaction();$changed=false;
  try {
   $snapshot=$this->snapshot($atoId);
   if(!$snapshot['pronto']){$this->pdo->commit();return false;}
   $q=$this->pdo->prepare('SELECT status_execucao FROM atividades_fase WHERE id=? AND municipio_id=? FOR UPDATE');$q->execute([$atoId,$this->mid]);$wasCompleted=$q->fetchColumn()==='CONCLUIDA';
   $childDocs=[];
   foreach($snapshot['requisitos'] as $doc)if((int)$doc['atividade_ato_id']>0)$childDocs[(int)$doc['atividade_ato_id']][]=$doc;
   foreach($snapshot['filhas'] as $child){
    $cid=(int)$child['id'];$required=$childDocs[$cid]??[];
    // Atividades sem requisito obrigatorio nao bloqueiam a conclusao documental do ato.
    if(!$required)continue;
    $ok=true;foreach($required as $doc)if($doc['ultimo_status']!=='APROVADO')$ok=false;
    if($ok)$this->pdo->prepare('UPDATE pge_atividades_ato SET status_execucao="CONCLUIDA",concluida_em=COALESCE(concluida_em,NOW()),concluida_por_usuario_id=COALESCE(concluida_por_usuario_id,?) WHERE id=? AND municipio_id=? AND ativo=1 AND status_execucao<>"CONCLUIDA"')->execute([Auth::id(),$cid,$this->mid]);
   }
   if(!$wasCompleted){$this->pdo->prepare('UPDATE atividades_fase SET status_execucao="CONCLUIDA",concluida_em=NOW(),concluida_por_usuario_id=?,atualizado_em=NOW() WHERE id=? AND municipio_id=?')->execute([Auth::id(),$atoId,$this->mid]);$changed=true;}
   $this->pdo->commit();
  }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
  if($changed)Audit::log('ATO_CONCLUIDO_AUTOMATICAMENTE','Ato #'.$atoId.' concluido por aprovacao de todos os documentos obrigatorios.',$this->mid,['categoria'=>'OPERACIONAL']);
  return $changed;
 }

 public function setCompleted(int $activityId, bool $completed): void
 {
  if(!Auth::isPlatformAdmin())throw new RuntimeException('Apenas a Stratelli pode concluir ou reabrir um ato.');
  $snap=$this->snapshot($activityId);
  $fid=(int)$snap['ato']['fase_id'];
  (new PhaseClosureService())->assertOpen($fid);
  if($completed){
   if(!(new FormalStartService())->get($this->mid))throw new RuntimeException('Registre o inicio formal antes de concluir atos.');
   if(!$snap['pronto'])throw new RuntimeException('O ato contem documentos obrigatorios nao entregues ou ainda nao aprovados.');
   // Mesmo criterio da conclusao automatica; evita falso bloqueio por outra fase.
   $this->synchronizeAct($activityId);
   return;
  }
  $this->pdo->prepare('UPDATE atividades_fase SET status_execucao="PENDENTE",concluida_em=NULL,concluida_por_usuario_id=NULL,atualizado_em=NOW() WHERE id=? AND municipio_id=?')->execute([$activityId,$this->mid]);
 }
}
