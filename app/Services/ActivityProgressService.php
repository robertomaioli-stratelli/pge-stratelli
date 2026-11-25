<?php
namespace App\Services;
use App\Core\Auth;use App\Core\Database;use App\Core\Tenant;use PDO;use RuntimeException;
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
 public function setCompleted(int $activityId, bool $completed): void
 {
  if (!Auth::isPlatformAdmin()) throw new RuntimeException('Apenas a Stratelli pode concluir ou reabrir um ato.');
  $q=$this->pdo->prepare('SELECT a.fase_id FROM atividades_fase a JOIN fases f ON f.id=a.fase_id AND f.municipio_id=a.municipio_id AND f.ativo=1 WHERE a.id=? AND a.municipio_id=? AND a.ativo=1');
  $q->execute([$activityId,$this->mid]);$fid=(int)$q->fetchColumn();
  if(!$fid)throw new RuntimeException('Ato não localizado.');
  (new PhaseClosureService())->assertOpen($fid);
  if($completed && !(new FormalStartService())->get($this->mid))throw new RuntimeException('Registre o início formal antes de concluir atos.');
  $this->pdo->beginTransaction();
  try {
   $q=$this->pdo->prepare('SELECT id,status_execucao FROM pge_atividades_ato WHERE municipio_id=? AND ato_legado_id=? AND ativo=1 FOR UPDATE');
   $q->execute([$this->mid,$activityId]);$children=$q->fetchAll(PDO::FETCH_ASSOC);
   if($completed){
    // Inclui os documentos obrigatórios do próprio ato e de suas atividades subordinadas.
    $q=$this->pdo->prepare('SELECT r.id,r.atividade_ato_id,(SELECT d.status FROM documentos_enviados d WHERE d.municipio_id=r.municipio_id AND d.requisito_id=r.id ORDER BY d.id DESC LIMIT 1) AS ultimo_status FROM requisitos_documentais r WHERE r.municipio_id=? AND r.ativo=1 AND r.obrigatorio=1 AND (r.atividade_id=? OR r.atividade_ato_id IN (SELECT a.id FROM pge_atividades_ato a WHERE a.municipio_id=? AND a.ato_legado_id=? AND a.ativo=1))');
    $q->execute([$this->mid,$activityId,$this->mid,$activityId]);$docs=$q->fetchAll(PDO::FETCH_ASSOC);
    $docsPorFilha=[];$temDocsObrigatorios=false;
    foreach($docs as $doc){$temDocsObrigatorios=true;if($doc['ultimo_status']!=='APROVADO')throw new RuntimeException('O ato contém documentos obrigatórios não entregues ou ainda não aprovados.');$childId=(int)($doc['atividade_ato_id']??0);if($childId>0)$docsPorFilha[$childId]=true;}
    if(!$temDocsObrigatorios&&!$children)throw new RuntimeException('Este ato não possui documentos obrigatórios para conferência.');
    foreach($children as $child){$childId=(int)$child['id'];
     if(empty($docsPorFilha[$childId])&&$child['status_execucao']!=='CONCLUIDA')throw new RuntimeException('Há atividade sem documentos obrigatórios que ainda não foi concluída.');
    }
    // Filhas com documentos exigidos e aprovados acompanham a conclusão do ato.
    if($docsPorFilha){$ids=array_keys($docsPorFilha);$params=array_merge([Auth::id(),$this->mid,$activityId],$ids);$holders=implode(',',array_fill(0,count($ids),'?'));
     $this->pdo->prepare('UPDATE pge_atividades_ato SET status_execucao="CONCLUIDA",concluida_em=NOW(),concluida_por_usuario_id=? WHERE municipio_id=? AND ato_legado_id=? AND ativo=1 AND id IN ('.$holders.')')->execute($params);
    }
   }else{
    // Ao reabrir, mantém o histórico de aprovação documental intacto.
    // A reabertura de cada atividade subordinada deve ser tratada separadamente.
   }
   $q=$this->pdo->prepare('UPDATE atividades_fase SET status_execucao=?,concluida_em=?,concluida_por_usuario_id=?,atualizado_em=NOW() WHERE id=? AND municipio_id=?');
   $q->execute([$completed?'CONCLUIDA':'PENDENTE',$completed?date('Y-m-d H:i:s'):null,$completed?Auth::id():null,$activityId,$this->mid]);
   $this->pdo->commit();
  }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
 }
}
