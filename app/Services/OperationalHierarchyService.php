<?php
namespace App\Services;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Tenant;
use PDO;
use RuntimeException;
final class OperationalHierarchyService {
 private PDO $db; private int $mid;
 public function __construct(){ $this->db=Database::connection();$this->mid=(int)Tenant::id(); }
 public function tree(): array {
  $q=$this->db->prepare('SELECT * FROM pge_processos_execucao WHERE municipio_id=? AND status<>\'ARQUIVADO\' ORDER BY id');$q->execute([$this->mid]);$processos=$q->fetchAll(PDO::FETCH_ASSOC);
  $q=$this->db->prepare("SELECT * FROM pge_etapas_execucao WHERE municipio_id=? AND status<>'ARQUIVADO' ORDER BY numero,id");$q->execute([$this->mid]);$etapas=$q->fetchAll(PDO::FETCH_ASSOC);
  $q=$this->db->prepare('SELECT * FROM fases WHERE municipio_id=? AND ativo=1 ORDER BY ordem,id');$q->execute([$this->mid]);$fases=$q->fetchAll(PDO::FETCH_ASSOC);
  $q=$this->db->prepare('SELECT * FROM atividades_fase WHERE municipio_id=? AND ativo=1 ORDER BY ordem,id');$q->execute([$this->mid]);$atos=$q->fetchAll(PDO::FETCH_ASSOC);
  $q=$this->db->prepare('SELECT * FROM pge_atividades_ato WHERE municipio_id=? AND ativo=1 ORDER BY ordem,id');$q->execute([$this->mid]);$atividades=$q->fetchAll(PDO::FETCH_ASSOC);
  $q=$this->db->prepare('SELECT r.id,r.nome,r.atividade_id,r.atividade_ato_id,r.fase_id,r.obrigatorio,r.ativo,(SELECT de.status FROM documentos_enviados de WHERE de.municipio_id=r.municipio_id AND de.requisito_id=r.id ORDER BY de.id DESC LIMIT 1) documento_status FROM requisitos_documentais r WHERE r.municipio_id=? AND r.ativo=1 ORDER BY r.ordem,r.id');$q->execute([$this->mid]);$documentos=$q->fetchAll(PDO::FETCH_ASSOC);
  $docsAtividade=[];$docsAto=[];$docsFase=[];
  foreach($documentos as $d){if((int)$d['atividade_ato_id']>0)$docsAtividade[(int)$d['atividade_ato_id']][]=$d;elseif((int)$d['atividade_id']>0)$docsAto[(int)$d['atividade_id']][]=$d;else $docsFase[(int)$d['fase_id']][]=$d;}
  $children=[];foreach($atividades as $a){$a['documentos']=$docsAtividade[(int)$a['id']]??[];$children[(int)$a['ato_legado_id']][]=$a;}
  $byFase=[];foreach($atos as $a){$a['atividades']=$children[(int)$a['id']]??[];$a['documentos']=$docsAto[(int)$a['id']]??[];$a['progresso']=$this->progress($a['atividades'],$a['documentos'],$a['status_execucao']);$byFase[(int)$a['fase_id']][]=$a;}
  $byEtapa=[];foreach($fases as $f){$f['atos']=$byFase[(int)$f['id']]??[];$f['documentos']=$docsFase[(int)$f['id']]??[];$f['progresso']=$this->average($f['atos']);$byEtapa[(int)$f['etapa_execucao_id']][]=$f;}
  $byProcesso=[];foreach($etapas as $e){$e['fases']=$byEtapa[(int)$e['id']]??[];$e['progresso']=$this->average($e['fases']);$byProcesso[(int)$e['processo_id']][]=$e;}
  foreach($processos as &$p){$p['etapas']=$byProcesso[(int)$p['id']]??[];$p['progresso']=$this->average($p['etapas']);}unset($p);
  return $processos;
 }
 private function average(array $items): int {if(!$items)return 0;return (int)round(array_sum(array_map(fn($i)=>(int)($i['progresso']??0),$items))/count($items));}
 private function progress(array $children,array $docs,string $status):int { $mandatory=array_values(array_filter($docs,fn($d)=>(int)$d['obrigatorio']===1));$docPct=$mandatory?(int)round(count(array_filter($mandatory,fn($d)=>$d['documento_status']==='APROVADO'))/count($mandatory)*100):null;if($children){$childPct=(int)round(array_sum(array_map(fn($a)=>$this->progress([], $a['documentos'],(string)$a['status_execucao']),$children))/count($children));return $docPct===null?$childPct:(int)round(($docPct+$childPct)/2);}if($docPct!==null)return $docPct;return $status==='CONCLUIDA'?100:0; }
 public function modelos():array {$q=$this->db->query('SELECT id,nome,versao FROM pge_modelos ORDER BY nome,id');return $q->fetchAll(PDO::FETCH_ASSOC);}
 public function instantiateModel(int $modelId,string $title):void {
  $this->admin();$title=trim($title);if(!$title)throw new RuntimeException('Informe o nome do processo.');
  $q=$this->db->prepare('SELECT * FROM pge_modelos WHERE id=?');$q->execute([$modelId]);$model=$q->fetch(PDO::FETCH_ASSOC);if(!$model)throw new RuntimeException('Modelo inválido.');
  $this->db->beginTransaction();try{
   $this->db->prepare('INSERT INTO pge_processos_execucao(municipio_id,modelo_id,titulo) VALUES(?,?,?)')->execute([$this->mid,$modelId,$title]);$pid=(int)$this->db->lastInsertId();
   $q=$this->db->prepare('SELECT * FROM pge_modelo_etapas WHERE modelo_id=? ORDER BY ordem,id');$q->execute([$modelId]);$stages=$q->fetchAll(PDO::FETCH_ASSOC);
   $max=(int)$this->db->query('SELECT COALESCE(MAX(ordem),0) FROM fases WHERE municipio_id='.(int)$this->mid)->fetchColumn();
   foreach($stages as $stage){$this->db->prepare('INSERT INTO pge_etapas_execucao(processo_id,municipio_id,modelo_etapa_id,numero,nome) VALUES(?,?,?,?,?)')->execute([$pid,$this->mid,$stage['id'],$stage['ordem'],$stage['nome']]);$stageId=(int)$this->db->lastInsertId();
    $q=$this->db->prepare('SELECT * FROM pge_modelo_fases WHERE etapa_id=? ORDER BY ordem,id');$q->execute([$stage['id']]);foreach($q->fetchAll(PDO::FETCH_ASSOC) as $phase){$max++;$code='P'.$pid.'-E'.$stage['ordem'].'-F'.$phase['ordem'];$name=mb_substr((string)$phase['nome'],0,180);$this->db->prepare('INSERT INTO fases(municipio_id,etapa_execucao_id,ordem,codigo,aba,titulo,descricao,responsavel,dia_inicio,dia_fim,entregavel,criterio,ativo,criado_em,atualizado_em) VALUES(?,?,?,?,?,?,?,?,1,1,?,?,1,NOW(),NOW())')->execute([$this->mid,$stageId,$max,$code,mb_substr($name,0,120),$name,'Modelo: '.$model['nome'],'','','']);$faseId=(int)$this->db->lastInsertId();
     $q=$this->db->prepare('SELECT * FROM pge_modelo_atos WHERE fase_id=? ORDER BY ordem,id');$q->execute([$phase['id']]);foreach($q->fetchAll(PDO::FETCH_ASSOC) as $act){$atoCode=$code.'-A'.$act['ordem'];$nome=mb_substr((string)$act['nome'],0,220);$this->db->prepare('INSERT INTO atividades_fase(municipio_id,fase_id,ordem,codigo,titulo,descricao,responsavel,prazo_tipo,prazo_referencia,fundamento_normativo,entregavel,obrigatoria,ativo,criado_em,atualizado_em) VALUES(?,?,?,?,?,?,?,"SEM_PRAZO",?,?,?,1,1,NOW(),NOW())')->execute([$this->mid,$faseId,$act['ordem'],$atoCode,$nome,(string)$act['nome'],'',mb_substr((string)$act['prazo_texto'],0,255),mb_substr((string)$act['fundamento_normativo'],0,255),'']);$atoId=(int)$this->db->lastInsertId();
      $q=$this->db->prepare('SELECT * FROM pge_modelo_atividades WHERE ato_id=? ORDER BY ordem,id');$q->execute([$act['id']]);foreach($q->fetchAll(PDO::FETCH_ASSOC) as $child){$this->db->prepare('INSERT INTO pge_atividades_ato(municipio_id,ato_legado_id,modelo_atividade_id,ordem,codigo,titulo,descricao) VALUES(?,?,?,?,?,?,?)')->execute([$this->mid,$atoId,$child['id'],$child['ordem'],$child['codigo'],mb_substr((string)$child['nome'],0,255),(string)$child['texto_origem']]);}
     }
    }
   }
   $this->db->commit();
  }catch(\Throwable $e){$this->db->rollBack();throw $e;}
 }
 private function admin():void {if(!Auth::check() || $this->mid<1)throw new RuntimeException('Usuário não autenticado na instância.');}
 public function createProcess(string $title): void {$this->admin();$title=trim($title);if(!$title)throw new RuntimeException('Informe o processo.');$this->db->prepare('INSERT INTO pge_processos_execucao(municipio_id,titulo) VALUES(?,?)')->execute([$this->mid,$title]);}
 /** Cria uma etapa diretamente: o processo_id é apenas um agrupador técnico interno. */
 public function createEtapa(int $processId,string $title):void {
  $this->admin();
  $title=trim($title);
  if($title==='')throw new RuntimeException('Informe o nome da etapa.');
  $this->db->beginTransaction();
  try {
   // A trava municipal evita criar dois agrupadores internos em cadastros simultâneos.
   $lock=$this->db->prepare('SELECT id FROM municipios WHERE id=? FOR UPDATE');
   $lock->execute([$this->mid]);
   if(!$lock->fetchColumn())throw new RuntimeException('Município inválido.');
   $find=$this->db->prepare("SELECT id FROM pge_processos_execucao WHERE municipio_id=? AND titulo='[INTERNO] Estrutura de Etapas' AND status<>'ARQUIVADO' ORDER BY id LIMIT 1");
   $find->execute([$this->mid]);
   $pid=(int)$find->fetchColumn();
   if(!$pid){
    $this->db->prepare("INSERT INTO pge_processos_execucao(municipio_id,titulo,status) VALUES(?,'[INTERNO] Estrutura de Etapas','EM_ANDAMENTO')")->execute([$this->mid]);
    $pid=(int)$this->db->lastInsertId();
   }
   $next=$this->db->prepare('SELECT COALESCE(MAX(numero),0)+1 FROM pge_etapas_execucao WHERE processo_id=?');
   $next->execute([$pid]);
   $number=(int)$next->fetchColumn();
   $this->db->prepare('INSERT INTO pge_etapas_execucao(processo_id,municipio_id,numero,nome) VALUES(?,?,?,?)')->execute([$pid,$this->mid,$number,$title]);
   $this->db->commit();
  }catch(\Throwable $e){$this->db->rollBack();throw $e;}
 }
 public function assignFase(int $phaseId,int $stageId):void {$this->admin();$q=$this->db->prepare('SELECT id FROM pge_etapas_execucao WHERE id=? AND municipio_id=?');$q->execute([$stageId,$this->mid]);if(!$q->fetchColumn())throw new RuntimeException('Etapa inválida.');$this->db->prepare('UPDATE fases SET etapa_execucao_id=? WHERE id=? AND municipio_id=?')->execute([$stageId,$phaseId,$this->mid]);}
 public function saveProcess(array $d):void {
  $this->admin();$id=(int)($d['id']??0);$title=trim((string)($d['titulo']??''));if($title==='')throw new RuntimeException('Informe o nome do processo.');
  if($id){$q=$this->db->prepare('UPDATE pge_processos_execucao SET titulo=? WHERE id=? AND municipio_id=?');$q->execute([$title,$id,$this->mid]);if(!$q->rowCount())$this->assertOwned('pge_processos_execucao',$id);}
  else $this->createProcess($title);
 }
 public function saveEtapa(array $d):void {
  $this->admin();$id=(int)($d['id']??0);$title=trim((string)($d['nome']??$d['titulo']??''));
  if($title==='')throw new RuntimeException('Informe o nome da etapa.');
  if($id){
   $this->assertOwned('pge_etapas_execucao',$id);
   $this->db->prepare("UPDATE pge_etapas_execucao SET nome=?,atualizado_em=NOW() WHERE id=? AND municipio_id=? AND status<>'ARQUIVADO'")->execute([$title,$id,$this->mid]);
  }else $this->createEtapa(0,$title);
 }
 public function saveFase(array $d):void {
  $this->admin();$stage=(int)($d['etapa_id']??0);$this->assertOwned('pge_etapas_execucao',$stage);
  $id=(int)($d['id']??0);$title=trim((string)($d['titulo']??''));if(!$title)throw new RuntimeException('Informe o nome da fase.');
  $order=max(1,(int)($d['ordem']??1));$code=trim((string)($d['codigo']??''))?:'F-'.$stage.'-'.$order;
  if($id){$q=$this->db->prepare('SELECT etapa_execucao_id FROM fases WHERE id=? AND municipio_id=? AND ativo=1');$q->execute([$id,$this->mid]);if((int)$q->fetchColumn()!==$stage)throw new RuntimeException('A fase não pertence à etapa selecionada.');(new PhaseClosureService())->assertOpen($id);$this->db->prepare('UPDATE fases SET ordem=?,codigo=?,aba=?,titulo=?,descricao=?,responsavel=?,atualizado_em=NOW() WHERE id=? AND municipio_id=?')->execute([$order,$code,mb_substr($title,0,120),$title,trim((string)($d['descricao']??'')),trim((string)($d['responsavel']??'')),$id,$this->mid]);}
  else{$q=$this->db->prepare('SELECT COALESCE(MAX(ordem),0)+1 FROM fases WHERE municipio_id=?');$q->execute([$this->mid]);$order=(int)$q->fetchColumn();$this->db->prepare('INSERT INTO fases(municipio_id,etapa_execucao_id,ordem,codigo,aba,titulo,descricao,responsavel,dia_inicio,dia_fim,entregavel,criterio,ativo,criado_em,atualizado_em) VALUES(?,?,?,?,?,?,?,?,1,1,"","",1,NOW(),NOW())')->execute([$this->mid,$stage,$order,$code,mb_substr($title,0,120),$title,trim((string)($d['descricao']??'')),trim((string)($d['responsavel']??''))]);}
 }
 public function saveAto(array $d):void {
  $this->admin();
  $fid=(int)($d['fase_id']??0);$id=(int)($d['id']??0);
  $name=trim((string)($d['titulo']??''));
  if($name==='')throw new RuntimeException('Informe o nome do ato.');
  $q=$this->db->prepare('SELECT id FROM fases WHERE id=? AND municipio_id=? AND ativo=1 AND etapa_execucao_id IS NOT NULL');
  $q->execute([$fid,$this->mid]);if(!$q->fetchColumn())throw new RuntimeException('Selecione uma fase ativa vinculada a uma etapa.');
  (new PhaseClosureService())->assertOpen($fid);
  $order=max(1,(int)($d['ordem']??1));
  $code=trim((string)($d['codigo']??''))?:'A-'.$fid.'-'.$order;
  $type=(string)($d['prazo_tipo']??'SEM_PRAZO');
  $validTypes=['DIAS_CORRIDOS','DIAS_UTEIS','DATA_FIXADA','DURANTE_FASE','IMEDIATO','CONFORME_CRONOGRAMA','SEM_PRAZO'];
  if(!in_array($type,$validTypes,true))throw new RuntimeException('Tipo de prazo inválido.');
  $direction=(string)($d['prazo_direcao']??'NAO_APLICAVEL');
  if(!in_array($direction,['APOS_MARCO','ANTES_MARCO','NAO_APLICAVEL'],true))throw new RuntimeException('Direção do prazo inválida.');
  $days=trim((string)($d['prazo_quantidade']??''));
  $days=$days===''?null:(int)$days;
  if($days!==null&&($days<0||$days>3650))throw new RuntimeException('Quantidade de dias inválida.');
  $marco=$this->validDate($d['prazo_marco_data']??null);
  $inicio=$this->validDate($d['inicio_previsto']??null);
  $fim=$this->validDate($d['fim_previsto']??null);
  $inicioReal=$this->validDate($d['inicio_real']??null);
  $fimReal=$this->validDate($d['fim_real']??null);
  if($inicio!==null&&$fim!==null&&$fim<$inicio)throw new RuntimeException('O fim previsto não pode ser anterior ao início.');
  if($inicioReal!==null&&$fimReal!==null&&$fimReal<$inicioReal)throw new RuntimeException('O fim real não pode ser anterior ao início real.');
  // Quando marco e prazo simples estao definidos, calcula fim previsto.
  // DIAS_UTEIS aqui significa segunda a sexta; feriados requerem calendario municipal.
  if($marco!==null&&$days!==null&&$days>0&&in_array($type,['DIAS_UTEIS','DIAS_CORRIDOS'],true)&&$direction!=='NAO_APLICAVEL'){
    $calculated=$this->shiftDays($marco,$days,$type==='DIAS_UTEIS',$direction==='ANTES_MARCO');
    if($direction==='ANTES_MARCO'){$fim=$calculated;if($inicio===null)$inicio=$calculated;}
    else{$fim=$calculated;if($inicio===null)$inicio=$marco;}
  }
  $fields=[$order,$code,$name,trim((string)($d['descricao']??'')),trim((string)($d['responsavel']??'')),
    trim((string)($d['prazo_texto']??'')),trim((string)($d['fundamento_normativo']??'')),
    $type,$days,$direction,$marco,$inicio,$fim,$inicioReal,$fimReal];
  if($id){
    $q=$this->db->prepare('SELECT fase_id FROM atividades_fase WHERE id=? AND municipio_id=? AND ativo=1');
    $q->execute([$id,$this->mid]);if((int)$q->fetchColumn()!==$fid)throw new RuntimeException('O ato não pertence à fase selecionada.');
    $this->db->prepare('UPDATE atividades_fase SET ordem=?,codigo=?,titulo=?,descricao=?,responsavel=?,prazo_referencia=?,fundamento_normativo=?,prazo_tipo=?,prazo_quantidade=?,prazo_direcao=?,prazo_marco_data=?,inicio_previsto=?,fim_previsto=?,inicio_real=?,fim_real=?,atualizado_em=NOW() WHERE id=? AND municipio_id=?')->execute([...$fields,$id,$this->mid]);
  }else{
    $this->db->prepare('INSERT INTO atividades_fase(municipio_id,fase_id,ordem,codigo,titulo,descricao,responsavel,prazo_referencia,fundamento_normativo,prazo_tipo,prazo_quantidade,prazo_direcao,prazo_marco_data,inicio_previsto,fim_previsto,inicio_real,fim_real,entregavel,obrigatoria,ativo,criado_em,atualizado_em) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,"",1,1,NOW(),NOW())')->execute([$this->mid,$fid,...$fields]);
  }
 }
 private function validDate($raw):?string {
  $v=trim((string)($raw??''));if($v==='')return null;
  $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$v);
  if(!$date||$date->format('Y-m-d')!==$v)throw new RuntimeException('Data inválida. Use AAAA-MM-DD.');
  return $v;
 }
 private function shiftDays(string $anchor,int $count,bool $business,bool $backward):string {
  $date=new \DateTimeImmutable($anchor);$sign=$backward?-1:1;$steps=0;
  while($steps<$count){$date=$date->modify(($sign===1?'+1':'-1').' day');
    if(!$business||(int)$date->format('N')<=5)$steps++;
  }
  return $date->format('Y-m-d');
 }
 public function toggleNode(string $type,int $id):void {
  $this->admin();$types=['fase'=>['fases','fase_id','atividades_fase'],'ato'=>['atividades_fase','ato_legado_id','pge_atividades_ato'],'atividade'=>['pge_atividades_ato',null,null]];
  if(!isset($types[$type]))throw new RuntimeException('Tipo inválido.');[$table,$fk,$childTable]=$types[$type];$this->assertOwned($table,$id);
  if($type==='fase')$phaseId=$id;elseif($type==='ato'){$q=$this->db->prepare('SELECT fase_id FROM atividades_fase WHERE municipio_id=? AND id=?');$q->execute([$this->mid,$id]);$phaseId=(int)$q->fetchColumn();}else{$q=$this->db->prepare('SELECT b.fase_id FROM pge_atividades_ato a JOIN atividades_fase b ON b.id=a.ato_legado_id WHERE a.municipio_id=? AND a.id=?');$q->execute([$this->mid,$id]);$phaseId=(int)$q->fetchColumn();}
  (new PhaseClosureService())->assertOpen($phaseId);
  $q=$this->db->prepare("SELECT ativo FROM {$table} WHERE id=? AND municipio_id=?");$q->execute([$id,$this->mid]);$on=(int)$q->fetchColumn();
  if($on && $childTable){$q=$this->db->prepare("SELECT COUNT(*) FROM {$childTable} WHERE municipio_id=? AND {$fk}=? AND ativo=1");$q->execute([$this->mid,$id]);if((int)$q->fetchColumn()>0)throw new RuntimeException('Desative os itens subordinados antes de desativar este registro.');}
  if($on && $type==='ato'){$q=$this->db->prepare('SELECT COUNT(*) FROM requisitos_documentais WHERE municipio_id=? AND atividade_id=? AND ativo=1');$q->execute([$this->mid,$id]);if((int)$q->fetchColumn()>0)throw new RuntimeException('Existem documentos ativos vinculados ao ato.');}
  if($on && $type==='atividade'){$q=$this->db->prepare('SELECT COUNT(*) FROM requisitos_documentais WHERE municipio_id=? AND atividade_ato_id=? AND ativo=1');$q->execute([$this->mid,$id]);if((int)$q->fetchColumn()>0)throw new RuntimeException('Existem documentos ativos vinculados à atividade.');}
  $this->db->prepare("UPDATE {$table} SET ativo=?,atualizado_em=NOW() WHERE id=? AND municipio_id=?")->execute([$on?0:1,$id,$this->mid]);
 }
 private function assertOwned(string $table,int $id):void {
  if($id<1)throw new RuntimeException('Selecione um registro válido.');$q=$this->db->prepare("SELECT id FROM {$table} WHERE id=? AND municipio_id=?");$q->execute([$id,$this->mid]);if(!$q->fetchColumn())throw new RuntimeException('Registro não encontrado neste município.');
 }
 public function saveAtividade(array $d):void {$this->admin();$id=(int)($d['id']??0);$ato=(int)($d['ato_id']??0);$name=trim((string)($d['titulo']??''));if(!$ato||!$name)throw new RuntimeException('Informe o ato e a atividade.');$q=$this->db->prepare('SELECT fase_id FROM atividades_fase WHERE id=? AND municipio_id=? AND ativo=1');$q->execute([$ato,$this->mid]);$fase=(int)$q->fetchColumn();if(!$fase)throw new RuntimeException('Ato inválido.');(new PhaseClosureService())->assertOpen($fase);
 $order=max(1,(int)($d['ordem']??1));$args=[$order,trim((string)($d['codigo']??'')),$name,trim((string)($d['descricao']??'')),trim((string)($d['responsavel']??'')),trim((string)($d['prazo_texto']??''))];
 if($id){$q=$this->db->prepare('SELECT ato_legado_id FROM pge_atividades_ato WHERE id=? AND municipio_id=?');$q->execute([$id,$this->mid]);if((int)$q->fetchColumn()!==$ato)throw new RuntimeException('Atividade não pertence ao ato.');$this->db->prepare('UPDATE pge_atividades_ato SET ordem=?,codigo=?,titulo=?,descricao=?,responsavel=?,prazo_texto=?,atualizado_em=NOW() WHERE id=? AND municipio_id=?')->execute([...$args,$id,$this->mid]);}
 else $this->db->prepare('INSERT INTO pge_atividades_ato(municipio_id,ato_legado_id,ordem,codigo,titulo,descricao,responsavel,prazo_texto) VALUES(?,?,?,?,?,?,?,?)')->execute([$this->mid,$ato,...$args]);
 }
 public function setAtividadeStatus(int $id,bool $done):void {$this->admin();$q=$this->db->prepare('SELECT a.id,a.ato_legado_id,b.fase_id FROM pge_atividades_ato a JOIN atividades_fase b ON b.id=a.ato_legado_id AND b.municipio_id=a.municipio_id WHERE a.id=? AND a.municipio_id=? AND a.ativo=1');$q->execute([$id,$this->mid]);$a=$q->fetch(PDO::FETCH_ASSOC);if(!$a)throw new RuntimeException('Atividade inválida.');(new PhaseClosureService())->assertOpen((int)$a['fase_id']);if($done && !(new FormalStartService())->get($this->mid)) throw new RuntimeException('Registre o início formal antes de concluir atividades.');if($done){$q=$this->db->prepare('SELECT COUNT(*) total, SUM(CASE WHEN (SELECT d.status FROM documentos_enviados d WHERE d.municipio_id=r.municipio_id AND d.requisito_id=r.id ORDER BY d.id DESC LIMIT 1)="APROVADO" THEN 1 ELSE 0 END) aprovado FROM requisitos_documentais r WHERE r.municipio_id=? AND r.atividade_ato_id=? AND r.ativo=1 AND r.obrigatorio=1');$q->execute([$this->mid,$id]);$m=$q->fetch(PDO::FETCH_ASSOC);if((int)$m['total']>(int)$m['aprovado'])throw new RuntimeException('Há documentos obrigatórios pendentes de aprovação.');} $this->db->prepare('UPDATE pge_atividades_ato SET status_execucao=?,concluida_em=?,concluida_por_usuario_id=?,atualizado_em=NOW() WHERE id=? AND municipio_id=?')->execute([$done?'CONCLUIDA':'PENDENTE',$done?date('Y-m-d H:i:s'):null,$done?Auth::id():null,$id,$this->mid]); }
 public function assignRequirement(int $reqId,?int $activityId):void {$this->admin();$q=$this->db->prepare('SELECT r.id,r.fase_id,r.atividade_id FROM requisitos_documentais r WHERE r.id=? AND r.municipio_id=?');$q->execute([$reqId,$this->mid]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new RuntimeException('Requisito inválido.');(new PhaseClosureService())->assertOpen((int)$r['fase_id']);if($activityId){$q=$this->db->prepare('SELECT a.ato_legado_id,b.fase_id FROM pge_atividades_ato a JOIN atividades_fase b ON b.id=a.ato_legado_id WHERE a.id=? AND a.municipio_id=? AND a.ativo=1');$q->execute([$activityId,$this->mid]);$a=$q->fetch(PDO::FETCH_ASSOC);if(!$a||(int)$a['fase_id']!==(int)$r['fase_id']||((int)$r['atividade_id']>0&&(int)$r['atividade_id']!==(int)$a['ato_legado_id']))throw new RuntimeException('Atividade incompatível com o requisito/ato existente.');$this->db->prepare('UPDATE requisitos_documentais SET atividade_id=?,atividade_ato_id=?,atualizado_em=NOW() WHERE id=? AND municipio_id=?')->execute([$a['ato_legado_id'],$activityId,$reqId,$this->mid]);}else{$this->db->prepare('UPDATE requisitos_documentais SET atividade_ato_id=NULL,atualizado_em=NOW() WHERE id=? AND municipio_id=?')->execute([$reqId,$this->mid]);}}
}
