<?php
use App\Core\Format;use App\Core\Csrf;use App\Core\PhaseNumber;
$previewMode=$previewMode??'workflow';$slug=$tenant['slug'];
$previewFormalmenteIniciado=(new \App\Services\FormalStartService())->get((int)$tenant['id'])!==null;
// v4.17.1: utiliza sempre as fases situacionais, pois elas carregam o estado documental calculado
// (ready/run/correction/done). A lista bruta de fases fazia o Workflow exibir PENDENTE mesmo com 100% aprovado.
$phaseList=$fasesSituacionais;
// V19: filhos operacionais dos atos, limitados ao município e aos atos visíveis.
// Prazo individual calculado: nao herdar atraso da fase para atos/atividades sem vencimento.
$workflowPrazoItens=[];
try {
    $dbPrazo=\App\Core\Database::connection();
    $qPrazo=$dbPrazo->prepare('SELECT nivel,item_id,fim_previsto FROM pge_prazo_resultados WHERE municipio_id=? AND situacao="CALCULADO" AND fim_previsto IS NOT NULL AND nivel IN ("ATO","ATIVIDADE")');
    $qPrazo->execute([(int)$tenant['id']]);
    foreach($qPrazo->fetchAll(\PDO::FETCH_ASSOC) as $pr){
        $workflowPrazoItens[$pr['nivel'].':'.(int)$pr['item_id']]=(string)$pr['fim_previsto'];
    }
} catch (\Throwable $ex) { error_log('[PGE prazos individuais] '.$ex->getMessage()); }
$workflowHoje=date('Y-m-d');
$workflowFilhas=[];
try {
    $workflowAtoIds=array_values(array_unique(array_map('intval',array_column(($atividadesVisiveis??[]),'id'))));
    if($workflowAtoIds){
        $workflowDb=\App\Core\Database::connection();
        $workflowIn=implode(',',array_fill(0,count($workflowAtoIds),'?'));
        $workflowStmt=$workflowDb->prepare('SELECT id,ato_legado_id,ordem,codigo,titulo,status_execucao FROM pge_atividades_ato WHERE municipio_id=? AND ativo=1 AND ato_legado_id IN ('.$workflowIn.') ORDER BY ato_legado_id,ordem,id');
        $workflowStmt->execute(array_merge([(int)$tenant['id']],$workflowAtoIds));
        foreach($workflowStmt->fetchAll(\PDO::FETCH_ASSOC) as $workflowChild){
            $workflowFilhas[(int)$workflowChild['ato_legado_id']][]=$workflowChild;
        }
    }
} catch (\Throwable $e) {
    // Apenas a visualização: o Workflow deve continuar acessível se os filhos falharem.
    error_log('[PGE Workflow V19] Consulta de atividades filhas: '.$e->getMessage());
}
// Os números abaixo são de exibição. IDs/ordem globais continuam sendo usados só para os vínculos.
$workflowNumeroFase=static function(array $fase, int $posicao):int{
    $titulo=trim((string)($fase['titulo']??''));
    // Fases antigas podem trazer o número de negócio no próprio título.
    if(preg_match('/^([1-9][0-9]*)\s*[.\-–)]\s*/u',$titulo,$m))return (int)$m[1];
    // Nesta visão as fases pertencem à modalidade selecionada; ordem global não é numeração.
    return $posicao+1;
};
$workflowNumeroAto=static function(array $ato,int $faseNumero):string{
    $titulo=trim((string)($ato['titulo']??''));
    // Contratação emergencial: atos explicitamente numerados 1, 2, ... 13.
    if(preg_match('/^([1-9][0-9]*)\s*[.\-–)]\s+/u',$titulo,$m))return $m[1];
    foreach([(string)($ato['codigo']??''),$titulo] as $origem){
        if(preg_match('/^\s*([0-9]+\.[0-9]+)(?![0-9.])/u',$origem,$m))return $m[1];
    }
    return $faseNumero.'.'.(int)($ato['ordem']??0);
};
$workflowNumeroAtividade=static function(array $filha,string $codigoAto):string{
    // Primeiro o número cadastrado, sempre no INÍCIO do código ou do título.
    // Nunca capturar datas como 02.11.2026 que aparecem no meio da descrição.
    foreach([(string)($filha['codigo']??''),(string)($filha['titulo']??'')] as $origem){
        if(preg_match('/^\s*([0-9]+\.[0-9]+\.[0-9]+)(?![0-9.])/u',$origem,$m))return $m[1];
        if(preg_match('/^\s*([0-9]+\.[0-9]+)(?![0-9.])/u',$origem,$m))return $m[1];
    }
    return $codigoAto.'.'.(int)($filha['ordem']??0);
};
$workflowSemPrefixo=static function(string $titulo,string $codigo):string{
    $codigo=preg_quote($codigo,'/');
    $limpo=preg_replace('/^\s*'.$codigo.'\s*[.\-–:]?\s*/u','',$titulo);
    return trim((string)$limpo)?:$titulo;
};
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
        <span class="process-phase-nav-position" aria-live="polite">Fase <?=Format::h($workflowNumeroFase($phaseList[0],0))?></span>
        <button type="button" class="process-nav-btn next" aria-label="Ver próximas fases" title="Avançar uma fase">›</button>
      </div>
    </div><?php endif;?>
  </div>
</div>
<?php if($phaseList):?>
<div class="contract-preview-scroll"><div class="contract-preview-flow" style="--phase-count:<?=count($phaseList)?>">
<?php foreach($phaseList as $idx=>$f):$deadline=$cronogramaPorFase[(int)$f['id']]??null;$globalStatus=$f['status_situacional']??(!empty($deadline['conclusao_real'])?'done':'pending');$isCurrent=(int)($faseSituacional['id']??0)===(int)$f['id'];$isViewed=$viewedPhaseId===(int)$f['id'];$class=$globalStatus==='done'?'completed':($isCurrent?'current':'upcoming');if($class!=='completed'&&$isCurrent&&($deadline['status']??'')==='overdue')$class='overdue';if (!$previewFormalmenteIniciado) $class='upcoming';$stageClass=trim($class.($isViewed?' is-viewed':''));$dateLabel=$previewFormalmenteIniciado ? (Format::date($deadline['inicio']??null).' — '.Format::date($deadline['fim']??null)) : 'AGUARDANDO INÍCIO FORMAL';$reqs=array_values(array_filter($requisitosVisiveis,fn($r)=>(int)$r['fase_id']===(int)$f['id']));$a=$w=$c=$p=0;foreach($reqs as$r){$d=$ultimosDocs[(int)$r['id']]??null;if(!$d)$p++;elseif($d['status']==='APROVADO')$a++;elseif($d['status']==='CORRECAO')$c++;else$w++;}$tot=count($reqs);$phaseActs=array_values(array_filter(($atividadesVisiveis??[]),fn($x)=>(int)$x['fase_id']===(int)$f['id']));$phaseActMetric=$faseMetricasAtividades[(int)$f['id']]??['total'=>0,'concluidas'=>0,'percentual'=>0];$pct=$phaseActMetric['total']?(int)$phaseActMetric['percentual']:($tot?round($a/$tot*100):0);$access=$faseAcesso[(int)$f['id']]??['pode_acessar'=>true,'motivo'=>''];$canOpen=(bool)($access['pode_acessar']??false);$tag=$globalStatus==='done'?'ENCERRADA':($globalStatus==='ready'?'PRONTA PARA ENCERRAMENTO':($globalStatus==='correction'?'CORREÇÃO SOLICITADA':($globalStatus==='run'?'EM ANDAMENTO':'PENDENTE')));$faseNumeroVisual=$workflowNumeroFase($f,$idx);$url='/'.rawurlencode($slug).'/workflow/fase/'.(int)$f['id'];?>
<div class="contract-preview-stage <?=Format::h($stageClass)?>" data-phase-order="<?=Format::h($faseNumeroVisual)?>" <?=$isCurrent?'aria-current="step"':''?> <?=$isViewed?'data-viewing="true"':''?>>
<div class="contract-preview-number"><?=Format::h($faseNumeroVisual)?></div>
<?php if($canOpen):?><a class="contract-preview-card production-stage-link" href="<?=Format::h($url)?>"><?php else:?><div class="contract-preview-card production-stage-locked" title="<?=Format::h($access['motivo']??'Fase bloqueada')?>"><?php endif;?>
<div class="contract-preview-date"><?=Format::h($dateLabel)?></div><div class="contract-preview-body"><div class="contract-preview-icon"><?=Format::h($icons[$idx%count($icons)])?></div><strong><?=Format::h($f['titulo'])?></strong><small><?=$tag?> · <?=Format::h($deadline['rotulo']??'AGUARDANDO')?></small></div><div class="contract-preview-duration"><?php if(!$canOpen&&$scope!=='stratelli'):?>🔒 BLOQUEADA<?php else:?><?=$phaseActMetric['total']?$pct.'% DOS ATOS':($tot?$pct.'% APROVADO':'SEM ATOS')?><?php endif;?></div>
<?php if($canOpen):?></a><?php else:?></div><?php endif;?>
<?php if(true):?><div class="contract-preview-milestone dashboard-activity-stack">
<div class="dashboard-activity-head"><b><?=$phaseActMetric['concluidas']?>/<?=$phaseActMetric['total']?> atos concluídos</b><span><?=$pct?>%</span></div>
<?php if($phaseActs):?><div class="dashboard-activity-list">
<?php foreach($phaseActs as $act):
  $am=$atividadeMetricas[(int)$act['id']]??['status'=>'PENDENTE','percentual'=>0,'concluida'=>false,'total_documentos'=>0,'aprovados'=>0];
  $numeroAto=$workflowNumeroAto($act,$faseNumeroVisual);
  $atoConcluido=($act['status_execucao']??'')==='CONCLUIDA';
  $fimAto=(string)($workflowPrazoItens['ATO:'.(int)$act['id']]??($act['fim_previsto']??''));
  $prazoExplicitoAto=$fimAto!=='' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$fimAto);
  $atoAtrasado=$previewFormalmenteIniciado && !$atoConcluido && $prazoExplicitoAto && $fimAto<$workflowHoje;
  $atoClasse=$atoConcluido?'is-complete':($atoAtrasado?'is-overdue':(in_array($class,['current','overdue'],true)?'is-current':'is-upcoming'));
  $filhas=$workflowFilhas[(int)$act['id']]??[];
  $qtdFilhas=count($filhas);$concluidasFilhas=count(array_filter($filhas,static fn($a)=>($a['status_execucao']??'')==='CONCLUIDA'));
?>
<div class="dashboard-activity-item pge-wf-ato <?=$atoClasse?>">
  <div class="dashboard-activity-row"><span class="dashboard-activity-check"><?=$atoConcluido?'✓':'○'?></span>
    <div><b><?=Format::h($numeroAto.'. '.$workflowSemPrefixo((string)$act['titulo'],$numeroAto))?></b>
      <small><?=$atoConcluido?'CONCLUÍDO':($atoClasse==='is-overdue'?'EM ATRASO':($atoClasse==='is-current'?'EM ANDAMENTO':'AGUARDANDO ABERTURA'))?><?php if($qtdFilhas):?> · <?=$concluidasFilhas?>/<?=$qtdFilhas?> atividades<?php endif;?></small></div>
    <strong><?=$qtdFilhas?(int)round($concluidasFilhas/$qtdFilhas*100):(int)($am['percentual']??0)?>%</strong>
  </div>
  <?php if($filhas):?><div class="pge-wf-filhas" aria-label="Atividades do ato <?=Format::h($numeroAto)?>">
    <?php foreach($filhas as $filha):$numeroFilha=$workflowNumeroAtividade($filha,$numeroAto);$filhaConcluida=($filha['status_execucao']??'')==='CONCLUIDA';
      $fimFilha=(string)($workflowPrazoItens['ATIVIDADE:'.(int)$filha['id']]??'');
      $filhaAtrasada=$previewFormalmenteIniciado&&!$filhaConcluida&&preg_match('/^\d{4}-\d{2}-\d{2}$/',$fimFilha)&&$fimFilha<$workflowHoje;
      $filhaClasse=$filhaConcluida?'is-complete':($filhaAtrasada?'is-overdue':$atoClasse);
      if(!$filhaConcluida&&!$filhaAtrasada&&$filhaClasse==='is-overdue')$filhaClasse='is-current'; ?>
    <div class="pge-wf-filha <?=$filhaClasse?>"><span class="pge-wf-filha-check"><?=$filhaConcluida?'✓':'○'?></span>
      <div><b><?=Format::h($numeroFilha.'. '.$workflowSemPrefixo((string)$filha['titulo'],$numeroFilha))?></b><small><?=$filhaConcluida?'CONCLUÍDA':($filhaClasse==='is-overdue'?'EM ATRASO':($filhaClasse==='is-current'?'PENDENTE':'AGUARDANDO ABERTURA'))?></small></div>
    </div>
    <?php endforeach;?></div><?php endif;?>
  <?php if($scope==='stratelli'&&$previewMode==='dashboard'&&$previewFormalmenteIniciado):
    $atoDocumentoLinhas=[];$atoPodeConcluir=true;$qtdDocsObrigatorios=0;$qtdDocsAprovados=0;
    $idsFilhasDoAto=array_map('intval',array_column($filhas,'id'));
    foreach(($requisitosVisiveis??[]) as $requisitoAto){
      if((int)($requisitoAto['ativo']??0)!==1)continue;
      $idFilhaRequisito=(int)($requisitoAto['atividade_ato_id']??0);
      if((int)($requisitoAto['atividade_id']??0)!==(int)$act['id']&&!in_array($idFilhaRequisito,$idsFilhasDoAto,true))continue;
      $docUltimo=$ultimosDocs[(int)$requisitoAto['id']]??null;
      $situacaoDoc=$docUltimo?(string)($docUltimo['status']??'EM_ANALISE'):'NAO_ENTREGUE';
      $obrigatorioDoc=(int)($requisitoAto['obrigatorio']??0)===1;
      if($obrigatorioDoc){$qtdDocsObrigatorios++;if($situacaoDoc==='APROVADO')$qtdDocsAprovados++;else $atoPodeConcluir=false;}
      $atoDocumentoLinhas[]=['nome'=>(string)$requisitoAto['nome'],'status'=>$situacaoDoc,'obrigatorio'=>$obrigatorioDoc,'filha_id'=>$idFilhaRequisito];
    }
    // Uma atividade filha sem documento obrigatório precisa estar previamente concluída.
    foreach($filhas as $filhaAto){
      $filhaId=(int)$filhaAto['id'];$temDocObrigatorio=false;
      foreach($atoDocumentoLinhas as $linhaDoc){if($linhaDoc['filha_id']===$filhaId&&$linhaDoc['obrigatorio']){$temDocObrigatorio=true;break;}}
      if(!$temDocObrigatorio&&($filhaAto['status_execucao']??'')!=='CONCLUIDA')$atoPodeConcluir=false;
    }
    if(!$qtdDocsObrigatorios&&!$filhas)$atoPodeConcluir=false;
    $conferenciaId='pge-conferencia-ato-'.(int)$act['id'];
  ?>
    <button class="dashboard-activity-action" type="button" onclick="document.getElementById('<?=Format::h($conferenciaId)?>').showModal()"><?=$atoConcluido?'Reabrir ato':'Conferir / Concluir ato'?></button>
    <dialog id="<?=Format::h($conferenciaId)?>" class="pge-ato-conferencia" aria-label="Conferência documental do ato <?=Format::h($numeroAto)?>">
      <div class="pge-ato-conferencia-top"><div><small>CONFERÊNCIA DOCUMENTAL</small><h3><?=Format::h($numeroAto.'. '.$workflowSemPrefixo((string)$act['titulo'],$numeroAto))?></h3><p>Confira os documentos obrigatórios. A conclusão também é registrada automaticamente após a aprovação de todos.</p></div><button type="button" class="pge-ato-conferencia-close" onclick="this.closest('dialog').close()" aria-label="Fechar">×</button></div>
      <div class="pge-ato-conferencia-resumo"><b><?=$qtdDocsAprovados?> de <?=$qtdDocsObrigatorios?> documentos obrigatórios aprovados</b><span><?=$atoConcluido?'Ato já concluído':($atoPodeConcluir?'Documentação pronta para conclusão':'Existem pendências que impedem a conclusão')?></span></div>
      <div class="pge-ato-conferencia-lista">
      <?php if($atoDocumentoLinhas):foreach($atoDocumentoLinhas as $linhaDoc):
        $rotuloDoc=['APROVADO'=>'Aprovado','CORRECAO'=>'Correção solicitada','NAO_ENTREGUE'=>'Não entregue','EM_ANALISE'=>'Em análise','ENVIADO'=>'Aguardando validação'][$linhaDoc['status']]??'Aguardando validação';
        $classeDoc=$linhaDoc['status']==='APROVADO'?'ok':($linhaDoc['status']==='NAO_ENTREGUE'?'missing':'pending');
      ?><div class="pge-ato-conferencia-doc"><div><b><?=Format::h($linhaDoc['nome'])?></b><small><?=$linhaDoc['obrigatorio']?'Obrigatório':'Opcional'?></small></div><span class="pge-ato-conferencia-status <?=$classeDoc?>"><?=Format::h($rotuloDoc)?></span></div><?php endforeach;else:?>
        <p>Nenhum documento está vinculado a este ato. A conclusão deve ser verificada conforme as atividades cadastradas.</p>
      <?php endif;?>
      </div>
      <?php if(!$atoConcluido&&$filhas):?><p class="pge-ato-conferencia-info">As atividades com documentos obrigatórios aprovados acompanham a conclusão automática.</p><?php endif;?>
      <div class="pge-ato-conferencia-acoes"><button type="button" class="pge-ato-conferencia-cancelar" onclick="this.closest('dialog').close()">Cancelar</button>
        <form method="post" action="/<?=Format::h($slug)?>/atividades/<?=(int)$act['id']?>/<?=$atoConcluido?'reabrir':'concluir'?>"><input type="hidden" name="_token" value="<?=Format::h(Csrf::token())?>"><button type="submit" class="pge-ato-conferencia-confirmar" <?=(!$atoConcluido&&!$atoPodeConcluir)?'disabled title="Existem documentos ou atividades pendentes"':''?>><?=$atoConcluido?'Confirmar reabertura':'✓ Confirmar conclusão do ato'?></button></form>
      </div>
    </dialog>
  <?php endif;?>
</div>
<?php endforeach;?></div><?php else:?><small class="dashboard-activity-empty">Nenhum ato visível para este perfil.</small><?php endif;?>
<div class="dashboard-doc-mini">Docs: <?=$a?> aprovados · <?=$w?> em análise · <?=$c?> correção · <?=$p?> pendentes</div></div><?php else:?><div class="contract-preview-milestone"><small class="contract-preview-milestone-label">ENTREGÁVEL</small><?=Format::h($f['entregavel']?:'Entregável a definir')?></div><?php if($scope==='stratelli'&&$globalStatus==='ready'&&$canOpen&&$previewFormalmenteIniciado):?><?php $previewCloseId='phase-close-dialog-'.(int)$f['id'];$previewCloseMin=(string)($deadline['inicio']??$dataInicioProcesso);$previewCloseToday=date('Y-m-d');$previewCloseAvailable=$previewCloseMin<=$previewCloseToday;?><?php if($previewCloseAvailable):?><button type="button" class="contract-preview-close-action" onclick="document.getElementById('<?=Format::h($previewCloseId)?>').showModal()">✓ Encerrar fase</button><dialog id="<?=Format::h($previewCloseId)?>" class="phase-close-dialog"><form class="phase-close-dialog-form" method="post" action="/<?=Format::h($slug)?>/cronograma/fases/<?=(int)$f['id']?>/encerrar"><input type="hidden" name="_token" value="<?=Format::h(Csrf::token())?>"><div class="phase-close-dialog-head"><div class="phase-close-dialog-headcopy"><small>ENCERRAMENTO FORMAL</small><h3>Encerrar Fase <?=Format::h($faseNumeroVisual)?> — <?=Format::h($f['aba'])?></h3><p>Ao confirmar, o sistema registrará o encerramento formal, preservará o snapshot documental e liberará a próxima fase.</p></div><button type="button" class="phase-close-dialog-x" aria-label="Fechar" onclick="this.closest('dialog').close()">×</button></div><div class="phase-close-dialog-summary"><div class="phase-close-dialog-summary-item"><small>Fase</small><b><?=Format::h($f['titulo'])?></b><span><?=Format::h($f['entregavel']?:'Entregável cadastrado')?></span></div><div class="phase-close-dialog-summary-item"><small>Status atual</small><b>Pronta para encerramento</b><span>Documentação obrigatória aprovada</span></div></div><div class="phase-close-dialog-grid"><label class="phase-close-field"><span>Data do encerramento</span><input type="date" name="data_conclusao" value="<?=Format::h($previewCloseToday)?>" min="<?=Format::h($previewCloseMin)?>" max="<?=Format::h($previewCloseToday)?>" required></label><label class="phase-close-field"><span>Responsável</span><input type="text" value="<?=Format::h($user['nome']??'Stratelli')?>" readonly></label><label class="phase-close-field phase-close-dialog-observation"><span>Observação do encerramento <em><?=$obsEncerramentoObrigatoria?'obrigatória':'opcional'?></em></span><textarea name="observacao" rows="5" maxlength="1000" <?=$obsEncerramentoObrigatoria?'required':''?> placeholder="Registre a decisão, conferências realizadas, pendências saneadas e o contexto do encerramento."></textarea><small class="phase-close-field-help">Este texto ficará registrado no histórico formal da fase.</small></label></div><div class="phase-close-dialog-warning"><div class="phase-close-dialog-warning-icon">!</div><div><b>Confirmação necessária</b><span>O botão verde da prévia apenas abre este formulário. A fase só será encerrada após clicar em <strong>Confirmar encerramento</strong>.</span></div></div><div class="phase-close-dialog-actions"><button type="button" class="btn neutral" onclick="this.closest('dialog').close()">Cancelar</button><button class="btn approve" type="submit">✓ Confirmar encerramento</button></div></form></dialog><?php else:?><button type="button" class="contract-preview-close-action is-disabled" disabled title="O encerramento ficará disponível em <?=Format::h(Format::date($previewCloseMin))?>">◷ Encerramento em <?=Format::h(Format::date($previewCloseMin))?></button><?php endif;?><?php endif;?><?php endif;?>
</div>
<?php endforeach;?></div></div><?php endif;?>
<?php if($previewMode==='dashboard'&&$previewFormalmenteIniciado):?><div class="contract-preview-footer dashboard-process-footer"><div class="contract-preview-legal dashboard-process-summary"><div class="contract-preview-legal-icon">◎</div><div class="dashboard-process-summary-content"><b><?=Format::h(strtoupper($dashboardResumoCard['titulo']))?></b><div class="dashboard-process-summary-metrics"><div class="dashboard-process-summary-metric"><b><?=Format::h($dashboardResumoCard['progresso'])?></b><small>Progresso</small></div><div class="dashboard-process-summary-metric"><b><?=Format::h($dashboardResumoCard['pendencias'])?></b><small>Pendências</small></div><div class="dashboard-process-summary-metric"><b><?=Format::h($dashboardResumoCard['analise'])?></b><small>Em análise</small></div><div class="dashboard-process-summary-metric"><b><?=Format::h($dashboardResumoCard['fases'])?></b><small>Fases encerradas</small></div></div></div></div><div class="contract-preview-note"><b>LEITURA DA SITUAÇÃO</b><span>Cada fase apresenta seus atos/atividades. Documentos pertencem aos atos; atos concluídos compõem o percentual da fase.</span></div></div><?php else:?><div class="contract-preview-footer"><div class="contract-preview-legal"><div class="contract-preview-legal-icon">⚖</div><div><b>BASE LEGAL</b><span>Lei nº 14.133/2021 e demais normas aplicáveis às licitações e contratações públicas.</span></div></div><div class="contract-preview-note"><b>LEITURA DA PRÉVIA</b><span>As datas e durações são parâmetros operacionais cadastrados no INPACTA by Stratelli e são recalculadas após o encerramento formal da etapa anterior.</span></div></div><?php endif;?>
</section>


<style>
.pge-ato-conferencia{width:min(760px,calc(100vw - 32px));max-height:86vh;padding:0;border:1px solid #cbd5e1;border-radius:16px;background:white;color:#102a4b;box-shadow:0 24px 80px #0b1d3d55;overflow:auto}
.pge-ato-conferencia::backdrop{background:#081a35a8}
.pge-ato-conferencia-top{display:flex;justify-content:space-between;gap:12px;padding:22px 24px 15px;border-bottom:1px solid #dbe4ef}
.pge-ato-conferencia-top small{font-weight:800;color:#586d86;letter-spacing:.06em}
.pge-ato-conferencia-top h3{margin:6px 0 6px;font-size:20px;line-height:1.4}
.pge-ato-conferencia-top p{margin:0;color:#536780;font-size:13px}
.pge-ato-conferencia-close{border:0;background:#edf2f8;border-radius:8px;padding:4px 12px;font-size:23px;align-self:start;cursor:pointer}
.pge-ato-conferencia-resumo{margin:18px 24px;padding:14px 16px;background:#f1f5fb;border:1px solid #d8e3ef;border-radius:10px;display:flex;flex-direction:column;gap:4px;font-size:13px}
.pge-ato-conferencia-resumo span{color:#54677f}
.pge-ato-conferencia-lista{margin:0 24px;display:flex;flex-direction:column;gap:8px}
.pge-ato-conferencia-doc{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:12px;border:1px solid #dee6f0;border-radius:9px;font-size:13px}
.pge-ato-conferencia-doc b{display:block}.pge-ato-conferencia-doc small{display:block;margin-top:5px;color:#718096}
.pge-ato-conferencia-status{white-space:nowrap;padding:5px 8px;border-radius:8px;font-weight:700;background:#fff0c2;color:#855600}
.pge-ato-conferencia-status.ok{background:#dff7e9;color:#146534}.pge-ato-conferencia-status.missing{background:#fee6e6;color:#a32929}
.pge-ato-conferencia-info{font-size:12px;color:#526680;margin:16px 24px 0}
.pge-ato-conferencia-acoes{display:flex;justify-content:flex-end;gap:10px;padding:20px 24px;position:sticky;bottom:0;background:white;border-top:1px solid #e1e8f0;margin-top:18px}
.pge-ato-conferencia-acoes button{border:0;border-radius:9px;padding:12px 16px;font-weight:700;cursor:pointer}
.pge-ato-conferencia-cancelar{background:#edf2f9;color:#193452}.pge-ato-conferencia-confirmar{background:#137e47;color:white}
.pge-ato-conferencia-confirmar:disabled{background:#abb7c6;cursor:not-allowed}
@media(max-width:620px){.pge-ato-conferencia-doc{align-items:flex-start;flex-direction:column}.pge-ato-conferencia-status{white-space:normal}.pge-ato-conferencia-acoes{flex-wrap:wrap}}
</style>

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

<style>
/* PGE Workflow V19: estados e hierarquia por Fase > Ato > Atividade. */
.contract-preview .contract-preview-stage.completed .contract-preview-number,
.contract-preview .contract-preview-stage.completed .contract-preview-date,
.contract-preview .contract-preview-stage.completed .contract-preview-milestone{background:#16804a!important;color:#fff!important}
.contract-preview .contract-preview-stage.current .contract-preview-number,
.contract-preview .contract-preview-stage.current .contract-preview-date,
.contract-preview .contract-preview-stage.current .contract-preview-milestone{background:#12518e!important;color:#fff!important}
.contract-preview .contract-preview-stage.upcoming .contract-preview-number,
.contract-preview .contract-preview-stage.upcoming .contract-preview-date,
.contract-preview .contract-preview-stage.upcoming .contract-preview-milestone{background:#687689!important;color:#fff!important}
.contract-preview .contract-preview-stage.overdue .contract-preview-number,
.contract-preview .contract-preview-stage.overdue .contract-preview-date,
.contract-preview .contract-preview-stage.overdue .contract-preview-milestone{background:#bb302e!important;color:#fff!important}
.contract-preview .contract-preview-stage.completed .contract-preview-card{border-color:#16804a!important}
.contract-preview .contract-preview-stage.current .contract-preview-card{border-color:#12518e!important}
.contract-preview .contract-preview-stage.upcoming .contract-preview-card{border-color:#8995a4!important}
.contract-preview .contract-preview-stage.overdue .contract-preview-card{border-color:#bb302e!important}
.contract-preview .pge-wf-ato{border-radius:9px;padding:9px;margin:0!important}
/* V37: mesma semântica de status; atos em tom forte e atividades em tom mais claro. */
.contract-preview .pge-wf-ato.is-complete{background:#16804a!important;color:#fff!important}
.contract-preview .pge-wf-ato.is-current{background:#195b9c!important;color:#fff!important}
.contract-preview .pge-wf-ato.is-upcoming{background:#7a8697!important;color:#fff!important}
.contract-preview .pge-wf-ato.is-overdue{background:#c43f3b!important;color:#fff!important}
.contract-preview .pge-wf-filha.is-complete{background:#27945d!important;color:#fff!important}
.contract-preview .pge-wf-filha.is-current{background:#367bb5!important;color:#fff!important}
.contract-preview .pge-wf-filha.is-upcoming{background:#96a1ae!important;color:#fff!important}
.contract-preview .pge-wf-filha.is-overdue{background:#d55f59!important;color:#fff!important}
.contract-preview .pge-wf-filhas{display:grid;gap:5px;margin:8px 0 0 10px;padding-left:9px;border-left:2px solid rgba(255,255,255,.46)}
.contract-preview .pge-wf-filha{display:grid;grid-template-columns:14px minmax(0,1fr);gap:6px;padding:7px;border-radius:7px;border:1px solid rgba(255,255,255,.24)}
.contract-preview .pge-wf-filha b{font-size:10px;line-height:1.4;display:block;overflow-wrap:anywhere}
.contract-preview .pge-wf-filha small{font-size:8px;display:block;opacity:.9;margin-top:3px}
.contract-preview .pge-wf-filha-check{font-size:12px;font-weight:900}
.contract-preview .dashboard-activity-row b{overflow-wrap:anywhere!important}
/* V36: tipografia ampliada nos atos e atividades; usado no Workflow e Dashboard. */
.contract-preview .dashboard-activity-row > div b{font-size:12.5px!important;line-height:1.52!important;letter-spacing:0!important;overflow-wrap:anywhere}
.contract-preview .dashboard-activity-row > div small{font-size:9.5px!important;line-height:1.45!important}
.contract-preview .pge-wf-filha b{font-size:11.5px!important;line-height:1.5!important;overflow-wrap:anywhere}
.contract-preview .pge-wf-filha small{font-size:9px!important;line-height:1.45!important}
.contract-preview .pge-wf-filha{padding:9px 8px!important;gap:7px!important}
.contract-preview .pge-wf-ato{padding:11px!important}

</style>
