<?php
/** PGE V14 — Gantt sempre visível, mesmo sem datas ou cadastros. */
use App\Core\Database;
use App\Core\Format;
use App\Core\PhaseNumber;

$ganttEsc = static fn($v): string => Format::h((string)($v ?? ''));
$ganttFases = [];
$ganttAviso = null;
$ganttTemDatas = false;
$ganttFormalmenteIniciado = false;
$ganttInicio = null;
$ganttFim = null;

try {
    $municipioGantt = (int)($tenant['id'] ?? 0);
    if ($municipioGantt > 0) {
        $ganttFormalmenteIniciado = (new \App\Services\FormalStartService())->get($municipioGantt) !== null;
        if (!$ganttFormalmenteIniciado) $ganttAviso = 'Aguardando documento formal de início. Nenhuma data representa um prazo em curso.';
        $pdoGantt = Database::connection();
        $consulta = $pdoGantt->prepare("SELECT f.id, f.titulo, f.aba, f.ordem, e.nome AS etapa_nome
            FROM fases f
            LEFT JOIN pge_etapas_execucao e ON e.id=f.etapa_execucao_id AND e.municipio_id=f.municipio_id
            WHERE f.municipio_id=? AND f.ativo=1 AND (e.id IS NULL OR e.status<>'ARQUIVADO')
            ORDER BY COALESCE(e.numero,0),f.ordem,f.id");
        $consulta->execute([$municipioGantt]);
        foreach ($consulta->fetchAll(\PDO::FETCH_ASSOC) as $fase) {
            $fase['numero_exibicao'] = PhaseNumber::from($fase);
            $fase['atos'] = [];
            $ganttFases[(int)$fase['id']] = $fase;
        }
        if ($ganttFases) {
            $ids = array_keys($ganttFases);
            $marcadores = implode(',',array_fill(0,count($ids),'?'));
            // Colunas de datas são criadas pela migração V12.
            try {
                $sql = "SELECT id,fase_id,codigo,titulo,prazo_referencia,fundamento_normativo,
                        inicio_previsto,fim_previsto,status_execucao
                        FROM atividades_fase
                        WHERE municipio_id=? AND ativo=1 AND fase_id IN ($marcadores)
                        ORDER BY fase_id,ordem,id";
                $st = $pdoGantt->prepare($sql);
                $st->execute(array_merge([$municipioGantt],$ids));
                $atos = $st->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\PDOException $ex) {
                // Antes da V12, ainda apresentamos o Gantt vazio, sem produzir erro 500.
                error_log('[PGE Gantt V14] Datas V12 indisponiveis: '.$ex->getMessage());
                $sql = "SELECT id,fase_id,codigo,titulo,prazo_referencia,fundamento_normativo,
                        status_execucao FROM atividades_fase
                        WHERE municipio_id=? AND ativo=1 AND fase_id IN ($marcadores)
                        ORDER BY fase_id,ordem,id";
                $st = $pdoGantt->prepare($sql);
                $st->execute(array_merge([$municipioGantt],$ids));
                $atos = $st->fetchAll(\PDO::FETCH_ASSOC);
                $ganttAviso = 'As datas previstas ainda não estão disponíveis. Confira a migração V12.';
            }
            foreach ($atos as $ato) {
                $inicio = $ato['inicio_previsto'] ?? null;
                $fim = $ato['fim_previsto'] ?? null;
                $validas = false;
                if ($ganttFormalmenteIniciado && $inicio && $fim) {
                    try {
                        $dtInicio = new \DateTimeImmutable($inicio);
                        $dtFim = new \DateTimeImmutable($fim);
                        $validas = $dtFim >= $dtInicio;
                    } catch (\Throwable $ex) {
                        $validas = false;
                    }
                }
                $ato['_datas_validas'] = $validas;
                if ($validas) {
                    $ganttTemDatas = true;
                    if ($ganttInicio === null || $inicio < $ganttInicio) $ganttInicio = $inicio;
                    if ($ganttFim === null || $fim > $ganttFim) $ganttFim = $fim;
                }
                $fid = (int)$ato['fase_id'];
                if (isset($ganttFases[$fid])) $ganttFases[$fid]['atos'][] = $ato;
            }
        }
    }
} catch (\Throwable $ex) {
    error_log('[PGE Gantt V14] '.$ex->getMessage());
    $ganttAviso = 'Não foi possível carregar o cronograma. Consulte o log da aplicação.';
}

$ganttMin = $ganttTemDatas ? new \DateTimeImmutable($ganttInicio) : null;
$ganttMax = $ganttTemDatas ? new \DateTimeImmutable($ganttFim) : null;
$ganttDias = $ganttTemDatas ? max(1,(int)$ganttMin->diff($ganttMax)->days+1) : 0;
?>
<section class="card pge-gantt-v14" aria-label="Gráfico Gantt de Etapas, Fases e Atos">
  <div class="pge-gantt-v14-head">
    <div><h2>Gráfico Gantt — Cronograma do Processo</h2><p>Etapas, Fases e Atos; as barras serão exibidas quando os períodos previstos forem cadastrados.</p></div>
    <span class="pge-gantt-v14-chip"><?=count($ganttFases)?> fase(s)</span>
  </div>
  <?php if($ganttAviso): ?><div class="pge-gantt-v14-alert"><?=$ganttEsc($ganttAviso)?></div><?php endif; ?>
  <div class="pge-gantt-v14-scroll">
    <div class="pge-gantt-v14-axis">
      <div class="pge-gantt-v14-left">ETAPA / FASE / ATO</div>
      <div class="pge-gantt-v14-times">
        <?php for($i=0;$i<8;$i++):
          if($ganttTemDatas){
              $offset=(int)round(($ganttDias-1)*$i/7);
              $label=$ganttMin->modify('+'.$offset.' days')->format('d/m/Y');
          } else {
              $label='P'.$i;
          }
        ?><span><?=$ganttEsc($label)?></span><?php endfor; ?>
      </div>
    </div>
    <?php if(!$ganttFases): ?>
      <div class="pge-gantt-v14-row"><div class="pge-gantt-v14-label"><b>Nenhuma Fase cadastrada</b><small>Cadastre uma Etapa e suas Fases em Configurações.</small></div><div class="pge-gantt-v14-track"><span class="pge-gantt-v14-empty">Aguardando cadastros</span></div></div>
    <?php else: ?>
      <?php $etapaAnterior=null; foreach($ganttFases as $fase):
        $nomeEtapa=trim((string)($fase['etapa_nome']??''));
        if($nomeEtapa!==$etapaAnterior): $etapaAnterior=$nomeEtapa; ?>
          <div class="pge-gantt-v14-stage"><?=$ganttEsc($nomeEtapa!==''?$nomeEtapa:'Etapa não informada')?></div>
        <?php endif; ?>
        <div class="pge-gantt-v14-row is-phase"><div class="pge-gantt-v14-label"><b>Fase <?=$ganttEsc($fase['numero_exibicao'])?> — <?=$ganttEsc($fase['titulo'])?></b><small><?=count($fase['atos'])?> ato(s)</small></div><div class="pge-gantt-v14-track"><span class="pge-gantt-v14-empty">Resumo da Fase</span></div></div>
        <?php foreach($fase['atos'] as $ato):
            $temPeriodo=(bool)$ato['_datas_validas'];
            $left=0.0;$width=0.0;
            if($temPeriodo && $ganttMin){
                $iniAto=new \DateTimeImmutable($ato['inicio_previsto']);
                $fimAto=new \DateTimeImmutable($ato['fim_previsto']);
                $deslocamento=(int)$ganttMin->diff($iniAto)->days;
                $duracao=max(1,(int)$iniAto->diff($fimAto)->days+1);
                $left=max(0,min(100,$deslocamento*100/$ganttDias));
                $width=max(.5,min(100-$left,$duracao*100/$ganttDias));
            }
            $atrasado=$temPeriodo && $ato['status_execucao']!=='CONCLUIDA' && $ato['fim_previsto']<date('Y-m-d');
        ?>
          <div class="pge-gantt-v14-row"><div class="pge-gantt-v14-label"><b><?=$ganttEsc($ato['codigo'])?> — <?=$ganttEsc($ato['titulo'])?></b><small><?=$temPeriodo?$ganttEsc(date('d/m/Y',strtotime($ato['inicio_previsto'])).' a '.date('d/m/Y',strtotime($ato['fim_previsto']))):'Sem datas previstas cadastradas'?></small></div><div class="pge-gantt-v14-track">
          <?php if($temPeriodo): ?><span class="pge-gantt-v14-bar <?=$atrasado?'late':($ato['status_execucao']==='CONCLUIDA'?'done':'')?>" style="left:<?=number_format($left,3,'.','')?>%;width:<?=number_format($width,3,'.','')?>%" title="<?=$ganttEsc(($ato['prazo_referencia']??'').' | '.($ato['fundamento_normativo']??''))?>"></span>
          <?php else: ?><span class="pge-gantt-v14-empty">Aguardando cronograma</span><?php endif; ?></div></div>
        <?php endforeach; ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <div class="pge-gantt-v14-foot"><span><i class="pge-gantt-v14-legend" style="background:#3478b6"></i> Previsto</span><span><i class="pge-gantt-v14-legend" style="background:#29906a"></i> Concluído</span><span><i class="pge-gantt-v14-legend" style="background:#c74b4b"></i> Atrasado</span><span><i class="pge-gantt-v14-legend" style="background:#cbd5e1"></i> Sem datas</span></div>
  <?php if(!$ganttTemDatas): ?><p class="pge-gantt-v14-note">Os períodos ainda não foram informados. A escala P0–P7 é apenas uma grade visual provisória, sem datas nem durações calculadas.</p><?php endif; ?>
</section>
<style>
.pge-gantt-v14{padding:20px;margin:20px 0}.pge-gantt-v14-head{display:flex;gap:14px;align-items:start;justify-content:space-between;margin-bottom:14px}.pge-gantt-v14-head h2{margin:0 0 5px;font-size:1.2rem;color:#183b60}.pge-gantt-v14-head p,.pge-gantt-v14-note{margin:0;color:#617387;font-size:.84rem}.pge-gantt-v14-chip{background:#e6f0fa;color:#1d4f82;padding:6px 11px;border-radius:99px;font-weight:700;font-size:.78rem;white-space:nowrap}.pge-gantt-v14-scroll{overflow-x:auto;border:1px solid #dce5f0;border-radius:10px}.pge-gantt-v14-axis,.pge-gantt-v14-row{display:grid;grid-template-columns:minmax(250px,35%) minmax(450px,65%);min-width:770px}.pge-gantt-v14-axis{background:#eaf1f9;color:#425b74;font-size:.73rem;font-weight:700}.pge-gantt-v14-left{padding:12px}.pge-gantt-v14-times{display:flex;justify-content:space-between;align-items:center;padding:10px 12px;background:repeating-linear-gradient(to right,transparent 0,transparent calc(12.5% - 1px),#d8e4f0 calc(12.5% - 1px),#d8e4f0 12.5%)}.pge-gantt-v14-stage{min-width:770px;background:#153f6d;color:#fff;padding:10px 13px;font-weight:700;font-size:.82rem}.pge-gantt-v14-row{border-top:1px solid #e8eef5;min-height:47px}.pge-gantt-v14-row.is-phase{background:#f1f6fc}.pge-gantt-v14-label{padding:8px 12px;overflow-wrap:anywhere}.pge-gantt-v14-label b{display:block;font-size:.79rem;color:#23476b}.pge-gantt-v14-label small{display:block;color:#728095;font-size:.73rem;margin-top:4px}.pge-gantt-v14-track{position:relative;background:repeating-linear-gradient(to right,#f9fbfe 0,#f9fbfe calc(12.5% - 1px),#e5ebf4 calc(12.5% - 1px),#e5ebf4 12.5%);display:flex;align-items:center;justify-content:center;min-height:47px}.pge-gantt-v14-bar{position:absolute;top:13px;height:20px;border-radius:5px;min-width:3px;background:#3478b6;z-index:1}.pge-gantt-v14-bar.done{background:#29906a}.pge-gantt-v14-bar.late{background:#c74b4b}.pge-gantt-v14-empty{font-size:.7rem;color:#9baaba;font-style:italic}.pge-gantt-v14-foot{display:flex;gap:17px;flex-wrap:wrap;margin-top:12px;color:#5f7288;font-size:.76rem}.pge-gantt-v14-foot span{display:inline-flex;align-items:center;gap:6px}.pge-gantt-v14-legend{display:inline-block;width:10px;height:10px;border-radius:3px}.pge-gantt-v14-note{margin-top:10px}.pge-gantt-v14-alert{margin:10px 0;background:#fff7ed;color:#9a4d16;border:1px solid #ffd7ad;padding:10px;border-radius:6px}
</style>
