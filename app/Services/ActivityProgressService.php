<?php
namespace App\Services;
use App\Core\Auth;use App\Core\Database;use App\Core\Tenant;use PDO;use RuntimeException;
final class ActivityProgressService
{
 private PDO $pdo;private int $mid;
 public function __construct(){ $this->pdo=Database::connection();$this->mid=(int)Tenant::id(); }
 public function metrics(array $activities,array $requirements,array $latest): array
 {
  $by=[];foreach($requirements as$r){$aid=(int)($r['atividade_id']??0);if($aid>0&&(int)$r['ativo']===1)$by[$aid][]=$r;}
  $out=[];foreach($activities as$a){$aid=(int)$a['id'];$docs=$by[$aid]??[];$required=array_values(array_filter($docs,fn($r)=>(int)$r['obrigatorio']===1));$approved=0;$waiting=0;$correction=0;$missing=0;
   foreach($required as$r){$d=$latest[(int)$r['id']]??null;if(!$d)$missing++;elseif($d['status']==='APROVADO')$approved++;elseif($d['status']==='CORRECAO')$correction++;else$waiting++;}
   $manual=strtoupper((string)($a['status_execucao']??'PENDENTE'))==='CONCLUIDA';$total=count($required);$complete=$total>0?($approved===$total):$manual;
   $pct=$total>0?(int)round($approved/$total*100):($complete?100:0);
   $status=$complete?'CONCLUIDA':($correction?'CORRECAO':(($approved+$waiting)>0?'EM_ANDAMENTO':'PENDENTE'));
   $out[$aid]=['id'=>$aid,'fase_id'=>(int)$a['fase_id'],'total_documentos'=>$total,'aprovados'=>$approved,'aguardando'=>$waiting,'correcao'=>$correction,'pendentes'=>$missing,'percentual'=>$pct,'concluida'=>$complete,'status'=>$status,'manual'=>$manual];
  }return$out;
 }
 public function phaseMetrics(array $activities,array $activityMetrics): array
 { $out=[];foreach($activities as$a){if(!(int)$a['ativo'])continue;$fid=(int)$a['fase_id'];if(!isset($out[$fid]))$out[$fid]=['total'=>0,'concluidas'=>0,'percentual'=>0];$out[$fid]['total']++;if(!empty($activityMetrics[(int)$a['id']]['concluida']))$out[$fid]['concluidas']++;}foreach($out as&$m)$m['percentual']=$m['total']?(int)round($m['concluidas']/$m['total']*100):0;unset($m);return$out; }
 public function setCompleted(int $activityId,bool $completed): void
 { if(!Auth::isPlatformAdmin())throw new RuntimeException('Apenas a Stratelli pode concluir ou reabrir um ato/atividade.');$q=$this->pdo->prepare('SELECT fase_id FROM atividades_fase WHERE id=? AND municipio_id=? AND ativo=1');$q->execute([$activityId,$this->mid]);$fid=(int)$q->fetchColumn();if(!$fid)throw new RuntimeException('Ato/atividade não localizado.');(new PhaseClosureService())->assertOpen($fid);
  if($completed){$q=$this->pdo->prepare('SELECT COUNT(*) total,SUM(CASE WHEN d.status="APROVADO" THEN 1 ELSE 0 END) approved FROM requisitos_documentais r LEFT JOIN documentos_enviados d ON d.id=(SELECT MAX(d2.id) FROM documentos_enviados d2 WHERE d2.municipio_id=r.municipio_id AND d2.requisito_id=r.id) WHERE r.municipio_id=? AND r.atividade_id=? AND r.ativo=1 AND r.obrigatorio=1');$q->execute([$this->mid,$activityId]);$m=$q->fetch(PDO::FETCH_ASSOC)?:[];$total=(int)($m['total']??0);$approved=(int)($m['approved']??0);if($total>0&&$approved<$total)throw new RuntimeException('Este ato ainda possui documentos obrigatórios não aprovados.');}
  $this->pdo->prepare('UPDATE atividades_fase SET status_execucao=?,concluida_em=?,concluida_por_usuario_id=?,atualizado_em=NOW() WHERE id=? AND municipio_id=?')->execute([$completed?'CONCLUIDA':'PENDENTE',$completed?date('Y-m-d H:i:s'):null,$completed?Auth::id():null,$activityId,$this->mid]);
 }
}
