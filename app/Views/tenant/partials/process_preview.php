<?php
use App\Core\Format;use App\Core\Csrf;
$previewMode=$previewMode??'workflow';$slug=$tenant['slug'];
// v4.17.1: utiliza sempre as fases situacionais, pois elas carregam o estado documental calculado
// (ready/run/correction/done). A lista bruta de fases fazia o Workflow exibir PENDENTE mesmo com 100% aprovado.
$phaseList=$fasesSituacionais;
$icons=['▣','▤','✎','⌕','☷','⚖','◎','◇','✓','§'];
$obsEncerramentoObrigatoria=!empty(($parametrosInstancia??[])['observacao_encerramento_obrigatoria']);
$viewedPhaseId=0;
if(!empty($atividade['id']))$viewedPhaseId=(int)$atividade['id'];
elseif(!empty($phase['id']))$viewedPhaseId=(int)$phase['id'];
elseif(!empty($faseSituacional['id']))$viewedPhaseId=(int)$faseSituacional['id'];
?>
<section class="contract-preview <?=$scope==='stratelli'?'stratelli':''?> <?=$previewMode==='dashboard'?'dashboard-process-preview':''?>" aria-label="<?=$previewMode==='dashboard'?'Situação de todas as fases do processo':'Prévia do cronograma das fases'?>">
<div class="contract-preview-head">
  <div class="contract-preview-intro"><h2><?=$previewMode==='dashboard'?'Situação de todo o processo':'Prévia do cronograma por fase'?></h2><p><?=$previewMode==='dashboard'?'Visão consolidada das fases e seus atos/atividades. O progresso da fase é calculado pela conclusão dos atos visíveis neste processo.':'Cronograma das fases com seus atos/atividades. As datas previstas são calculadas pelos prazos cadastrados e pelo encerramento formal da fase anterior.'?></p></div>
  <div class="contract-preview-head-tools">
    <span class="contract-preview-law"><?=$previewMode==='dashboard'?$dashboardFasesConcluidas.' de '.$dashboardQtdFases.' fases encerradas':'Base normativa geral: Lei nº 14.133/2021'?></span>
    <?php if($phaseList):?><div class="process-phase-nav" aria-label="Navegação entre as fases">
      <span class="process-phase-nav-label">Navegar pelas fases</span>
      <div class="process-phase-nav-actions">
        <button type="button" class="process-nav-btn prev" aria-label="Ver fases anteriores" title="Voltar uma fase">‹</button>
        <span class="process-phase-nav-position" aria-live="polite">Fase <?=Format::h($phaseList[0]['ordem']??0)?></span>
        <button type="button" class="process-nav-btn next" aria-label="Ver próximas fases" title="Avançar uma fase">›</button>
      </div>
    </div><?php endif;?>
  </div>
</div>
<?php if($phaseList):?>
<div class="contract-preview-scroll"><div class="contract-preview-flow" style="--phase-count:<?=count($phaseList)?>">
<?php foreach($phaseList as $idx=>$f):$deadline=$cronogramaPorFase[(int)$f['id']]??null;$globalStatus=$f['status_situacional']??(!empty($deadline['conclusao_real'])?'done':'pending');$isCurrent=(int)($faseSituacional['id']??0)===(int)$f['id'];$isViewed=$viewedPhaseId===(int)$f['id'];$class=($globalStatus==='done'?'completed':($isCurrent?'current':'upcoming'));if(($deadline['status']??'')==='overdue')$class='overdue';elseif(($deadline['status']??'')==='attention'&&$isCurrent)$class='attention';$stageClass=trim($class.($isViewed?' is-viewed':''));$dateLabel=Format::date($deadline['inicio']??null).' — '.Format::date($deadline['fim']??null);$reqs=array_values(array_filter($requisitosVisiveis,fn($r)=>(int)$r['fase_id']===(int)$f['id']));$a=$w=$c=$p=0;foreach($reqs as$r){$d=$ultimosDocs[(int)$r['id']]??null;if(!$d)$p++;elseif($d['status']==='APROVADO')$a++;elseif($d['status']==='CORRECAO')$c++;else$w++;}$tot=count($reqs);$phaseActs=array_values(array_filter(($atividadesVisiveis??[]),fn($x)=>(int)$x['fase_id']===(int)$f['id']));$phaseActMetric=$faseMetricasAtividades[(int)$f['id']]??['total'=>0,'concluidas'=>0,'percentual'=>0];$pct=$phaseActMetric['total']?(int)$phaseActMetric['percentual']:($tot?round($a/$tot*100):0);$access=$faseAcesso[(int)$f['id']]??['pode_acessar'=>true,'motivo'=>''];$canOpen=(bool)($access['pode_acessar']??false);$tag=$globalStatus==='done'?'ENCERRADA':($globalStatus==='ready'?'PRONTA PARA ENCERRAMENTO':($globalStatus==='correction'?'CORREÇÃO SOLICITADA':($globalStatus==='run'?'EM ANDAMENTO':'PENDENTE')));$url='/'.rawurlencode($slug).'/workflow/fase/'.(int)$f['id'];?>
<div class="contract-preview-stage <?=Format::h($stageClass)?>" data-phase-order="<?=Format::h($f['ordem'])?>" <?=$isCurrent?'aria-current="step"':''?> <?=$isViewed?'data-viewing="true"':''?>>
<div class="contract-preview-number"><?=Format::h($f['ordem'])?></div>
<?php if($canOpen):?><a class="contract-preview-card production-stage-link" href="<?=Format::h($url)?>"><?php else:?><div class="contract-preview-card production-stage-locked" title="<?=Format::h($access['motivo']??'Fase bloqueada')?>"><?php endif;?>
<div class="contract-preview-date"><?=Format::h($dateLabel)?></div><div class="contract-preview-body"><div class="contract-preview-icon"><?=Format::h($icons[$idx%count($icons)])?></div><strong><?=Format::h($f['titulo'])?></strong><small><?=$tag?> · <?=Format::h($deadline['rotulo']??'AGUARDANDO')?></small></div><div class="contract-preview-duration"><?php if(!$canOpen&&$scope!=='stratelli'):?>🔒 BLOQUEADA<?php else:?><?=$phaseActMetric['total']?$pct.'% DOS ATOS':($tot?$pct.'% APROVADO':'SEM ATOS')?><?php endif;?></div>
<?php if($canOpen):?></a><?php else:?></div><?php endif;?>
<?php if(true):?><div class="contract-preview-milestone dashboard-activity-stack"><div class="dashboard-activity-head"><b><?=$phaseActMetric['concluidas']?>/<?=$phaseActMetric['total']?> atos concluídos</b><span><?=$pct?>%</span></div><?php if($phaseActs):?><div class="dashboard-activity-list"><?php foreach($phaseActs as$act):$am=$atividadeMetricas[(int)$act['id']]??['status'=>'PENDENTE','percentual'=>0,'concluida'=>false,'total_documentos'=>0,'aprovados'=>0];?><div class="dashboard-activity-item <?=!empty($am['concluida'])?'is-done':''?>"><div class="dashboard-activity-row"><span class="dashboard-activity-check"><?=!empty($am['concluida'])?'✓':'○'?></span><div><b><?=Format::h($act['ordem'].'. '.$act['titulo'])?></b><small><?=Format::h(str_replace('_',' ',(string)$am['status']))?><?php if($am['total_documentos']):?> · <?=$am['aprovados']?>/<?=$am['total_documentos']?> docs<?php endif;?></small></div><strong><?=$am['percentual']?>%</strong></div><?php if($scope==='stratelli'&&$previewMode==='dashboard'):?><form method="post" action="/<?=Format::h($slug)?>/atividades/<?=(int)$act['id']?>/<?=!empty($am['concluida'])?'reabrir':'concluir'?>"><input type="hidden" name="_token" value="<?=Format::h(Csrf::token())?>"><button class="dashboard-activity-action" type="submit"><?=!empty($am['concluida'])?'Reabrir ato':'Concluir ato'?></button></form><?php endif;?></div><?php endforeach;?></div><?php else:?><small class="dashboard-activity-empty">Nenhum ato/atividade visível para este perfil.</small><?php endif;?><div class="dashboard-doc-mini">Docs: <?=$a?> aprovados · <?=$w?> em análise · <?=$c?> correção · <?=$p?> pendentes</div></div><?php else:?><div class="contract-preview-milestone"><small class="contract-preview-milestone-label">ENTREGÁVEL</small><?=Format::h($f['entregavel']?:'Entregável a definir')?></div><?php if($scope==='stratelli'&&$globalStatus==='ready'&&$canOpen):?><?php $previewCloseId='phase-close-dialog-'.(int)$f['id'];$previewCloseMin=(string)($deadline['inicio']??$dataInicioProcesso);$previewCloseToday=date('Y-m-d');$previewCloseAvailable=$previewCloseMin<=$previewCloseToday;?><?php if($previewCloseAvailable):?><button type="button" class="contract-preview-close-action" onclick="document.getElementById('<?=Format::h($previewCloseId)?>').showModal()">✓ Encerrar fase</button><dialog id="<?=Format::h($previewCloseId)?>" class="phase-close-dialog"><form class="phase-close-dialog-form" method="post" action="/<?=Format::h($slug)?>/cronograma/fases/<?=(int)$f['id']?>/encerrar"><input type="hidden" name="_token" value="<?=Format::h(Csrf::token())?>"><div class="phase-close-dialog-head"><div class="phase-close-dialog-headcopy"><small>ENCERRAMENTO FORMAL</small><h3>Encerrar Fase <?=Format::h($f['ordem'])?> — <?=Format::h($f['aba'])?></h3><p>Ao confirmar, o sistema registrará o encerramento formal, preservará o snapshot documental e liberará a próxima fase.</p></div><button type="button" class="phase-close-dialog-x" aria-label="Fechar" onclick="this.closest('dialog').close()">×</button></div><div class="phase-close-dialog-summary"><div class="phase-close-dialog-summary-item"><small>Fase</small><b><?=Format::h($f['titulo'])?></b><span><?=Format::h($f['entregavel']?:'Entregável cadastrado')?></span></div><div class="phase-close-dialog-summary-item"><small>Status atual</small><b>Pronta para encerramento</b><span>Documentação obrigatória aprovada</span></div></div><div class="phase-close-dialog-grid"><label class="phase-close-field"><span>Data do encerramento</span><input type="date" name="data_conclusao" value="<?=Format::h($previewCloseToday)?>" min="<?=Format::h($previewCloseMin)?>" max="<?=Format::h($previewCloseToday)?>" required></label><label class="phase-close-field"><span>Responsável</span><input type="text" value="<?=Format::h($user['nome']??'Stratelli')?>" readonly></label><label class="phase-close-field phase-close-dialog-observation"><span>Observação do encerramento <em><?=$obsEncerramentoObrigatoria?'obrigatória':'opcional'?></em></span><textarea name="observacao" rows="5" maxlength="1000" <?=$obsEncerramentoObrigatoria?'required':''?> placeholder="Registre a decisão, conferências realizadas, pendências saneadas e o contexto do encerramento."></textarea><small class="phase-close-field-help">Este texto ficará registrado no histórico formal da fase.</small></label></div><div class="phase-close-dialog-warning"><div class="phase-close-dialog-warning-icon">!</div><div><b>Confirmação necessária</b><span>O botão verde da prévia apenas abre este formulário. A fase só será encerrada após clicar em <strong>Confirmar encerramento</strong>.</span></div></div><div class="phase-close-dialog-actions"><button type="button" class="btn neutral" onclick="this.closest('dialog').close()">Cancelar</button><button class="btn approve" type="submit">✓ Confirmar encerramento</button></div></form></dialog><?php else:?><button type="button" class="contract-preview-close-action is-disabled" disabled title="O encerramento ficará disponível em <?=Format::h(Format::date($previewCloseMin))?>">◷ Encerramento em <?=Format::h(Format::date($previewCloseMin))?></button><?php endif;?><?php endif;?><?php endif;?>
</div>
<?php endforeach;?></div></div><?php endif;?>
<?php if($previewMode==='dashboard'):?><div class="contract-preview-footer dashboard-process-footer"><div class="contract-preview-legal dashboard-process-summary"><div class="contract-preview-legal-icon">◎</div><div class="dashboard-process-summary-content"><b><?=Format::h(strtoupper($dashboardResumoCard['titulo']))?></b><div class="dashboard-process-summary-metrics"><div class="dashboard-process-summary-metric"><b><?=Format::h($dashboardResumoCard['progresso'])?></b><small>Progresso</small></div><div class="dashboard-process-summary-metric"><b><?=Format::h($dashboardResumoCard['pendencias'])?></b><small>Pendências</small></div><div class="dashboard-process-summary-metric"><b><?=Format::h($dashboardResumoCard['analise'])?></b><small>Em análise</small></div><div class="dashboard-process-summary-metric"><b><?=Format::h($dashboardResumoCard['fases'])?></b><small>Fases encerradas</small></div></div></div></div><div class="contract-preview-note"><b>LEITURA DA SITUAÇÃO</b><span>Cada fase apresenta seus atos/atividades. Documentos pertencem aos atos; atos concluídos compõem o percentual da fase.</span></div></div><?php else:?><div class="contract-preview-footer"><div class="contract-preview-legal"><div class="contract-preview-legal-icon">⚖</div><div><b>BASE LEGAL</b><span>Lei nº 14.133/2021 e demais normas aplicáveis às licitações e contratações públicas.</span></div></div><div class="contract-preview-note"><b>LEITURA DA PRÉVIA</b><span>As datas e durações são parâmetros operacionais cadastrados no INPACTA by Stratelli e são recalculadas após o encerramento formal da etapa anterior.</span></div></div><?php endif;?>
</section>


<script>
(function(){
  document.querySelectorAll('.contract-preview').forEach(function(root){
    var viewport=root.querySelector('.contract-preview-scroll');
    var flow=root.querySelector('.contract-preview-flow');
    var prev=root.querySelector('.process-nav-btn.prev');
    var next=root.querySelector('.process-nav-btn.next');
    var pos=root.querySelector('.process-phase-nav-position');
    if(!viewport||!flow||!prev||!next)return;

    function stages(){ return Array.prototype.slice.call(flow.querySelectorAll('.contract-preview-stage')); }
    function nearestIndex(){
      var list=stages(), viewLeft=viewport.getBoundingClientRect().left, best=0, dist=Infinity;
      list.forEach(function(el,i){
        var d=Math.abs(el.getBoundingClientRect().left-viewLeft);
        if(d<dist){dist=d;best=i;}
      });
      return best;
    }
    function go(index){
      var list=stages();
      if(!list.length)return;
      index=Math.max(0,Math.min(list.length-1,index));
      var delta=list[index].getBoundingClientRect().left-viewport.getBoundingClientRect().left;
      viewport.scrollBy({left:delta,behavior:'smooth'});
      window.setTimeout(update,320);
    }
    function update(){
      var list=stages(), i=nearestIndex(), stage=list[i];
      prev.disabled=i<=0;
      next.disabled=i>=list.length-1;
      if(pos&&stage){
        var ordem=stage.getAttribute('data-phase-order');
        pos.textContent='Fase '+ordem+' • '+(i+1)+'/'+list.length;
      }
    }
    function move(dir){ go(nearestIndex()+dir); }

    prev.addEventListener('click',function(){move(-1);});
    next.addEventListener('click',function(){move(1);});

    var delay=null, repeat=null;
    function stopHover(){
      if(delay){clearTimeout(delay);delay=null;}
      if(repeat){clearInterval(repeat);repeat=null;}
    }
    function startHover(dir,button){
      if(button.disabled)return;
      stopHover();
      delay=setTimeout(function(){
        move(dir);
        repeat=setInterval(function(){
          if(button.disabled){stopHover();return;}
          move(dir);
        },850);
      },450);
    }
    prev.addEventListener('mouseenter',function(){startHover(-1,prev);});
    next.addEventListener('mouseenter',function(){startHover(1,next);});
    prev.addEventListener('mouseleave',stopHover);
    next.addEventListener('mouseleave',stopHover);
    prev.addEventListener('focusout',stopHover);
    next.addEventListener('focusout',stopHover);

    viewport.addEventListener('scroll',function(){
      window.clearTimeout(viewport._phaseNavTimer);
      viewport._phaseNavTimer=window.setTimeout(update,80);
    },{passive:true});
    window.addEventListener('resize',update);
    update();
  });
})();
</script>
