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
$ganttInicioFormalData = null;
$ganttTemPrazosSemMarco = false;
$ganttPossuiAtividades = false;
$ganttRegras = [];
$ganttResultados = [];
$ganttHoje = (new \DateTimeImmutable('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');

try {
    $municipioGantt = (int)($tenant['id'] ?? 0);
    if ($municipioGantt > 0) {
        $ganttRegistroFormal = (new \App\Services\FormalStartService())->get($municipioGantt);
        $ganttFormalmenteIniciado = $ganttRegistroFormal !== null;
        if ($ganttFormalmenteIniciado && !empty($ganttRegistroFormal['data_inicio'])) {
            $ganttInicioFormalData = substr((string)$ganttRegistroFormal['data_inicio'], 0, 10);
        } else {
            $ganttFormalmenteIniciado = false;
        }
        if (!$ganttFormalmenteIniciado) $ganttAviso = 'Aguardando documento formal de início. Nenhuma data representa um prazo em curso.';
        $pdoGantt = Database::connection();
        $ganttRegras=[];
        $ganttResultados=[];
        try {
            $ganttMotor = new \App\Services\DeadlineAutomationService();
            $ganttMotor->recalculate($municipioGantt);
            $ganttRegras=$ganttMotor->rules($municipioGantt);
            $ganttResultados=$ganttMotor->results($municipioGantt);
        } catch (\Throwable $ex) {
            error_log('[PGE Gantt V32] Motor de prazo indisponivel: '.$ex->getMessage());
            $ganttAviso='Execute a migração V32 para ativar o motor automático de prazos.';
        }
        $consulta = $pdoGantt->prepare("SELECT f.id, f.titulo, f.aba, f.ordem, f.dia_inicio, f.dia_fim, e.nome AS etapa_nome
            FROM fases f
            LEFT JOIN pge_etapas_execucao e ON e.id=f.etapa_execucao_id AND e.municipio_id=f.municipio_id
            WHERE f.municipio_id=? AND f.ativo=1 AND (e.id IS NULL OR e.status<>'ARQUIVADO')
            ORDER BY COALESCE(e.numero,0),f.ordem,f.id");
        $consulta->execute([$municipioGantt]);
        foreach ($consulta->fetchAll(\PDO::FETCH_ASSOC) as $fase) {
            $fase['numero_exibicao'] = PhaseNumber::from($fase);
            $fase['atos'] = [];
            $fase['_inicio_previsto'] = null;
            $fase['_fim_previsto'] = null;
            // Os intervalos de fase usam dias relativos ao marco formal, quando definidos.
            // Valores 1/1 não identificam um período de planejamento.
            if ($ganttFormalmenteIniciado && (int)$fase['dia_inicio'] > 0 && (int)$fase['dia_fim'] > (int)$fase['dia_inicio']) {
                $fase['_inicio_previsto'] = (new \DateTimeImmutable($ganttInicioFormalData))->modify('+'.((int)$fase['dia_inicio']-1).' days')->format('Y-m-d');
                $fase['_fim_previsto'] = (new \DateTimeImmutable($ganttInicioFormalData))->modify('+'.((int)$fase['dia_fim']-1).' days')->format('Y-m-d');
            }
            $ganttFases[(int)$fase['id']] = $fase;
        }
        if ($ganttFases) {
            $ids = array_keys($ganttFases);
            $marcadores = implode(',',array_fill(0,count($ids),'?'));
            // Colunas de datas são criadas pela migração V12.
            try {
                $sql = "SELECT id,fase_id,codigo,titulo,prazo_referencia,fundamento_normativo,
                        inicio_previsto,fim_previsto,prazo_quantidade,prazo_tipo,prazo_direcao,prazo_marco_data,status_execucao
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
                        prazo_quantidade,prazo_tipo,status_execucao FROM atividades_fase
                        WHERE municipio_id=? AND ativo=1 AND fase_id IN ($marcadores)
                        ORDER BY fase_id,ordem,id";
                $st = $pdoGantt->prepare($sql);
                $st->execute(array_merge([$municipioGantt],$ids));
                $atos = $st->fetchAll(\PDO::FETCH_ASSOC);
                $ganttAviso = 'As datas previstas ainda não estão disponíveis. Confira a migração V12.';
            }
            // Atividades subordinadas são exibidas, mas prazos textuais não
            // são convertidos em datas sem um evento ou calendário definido.
            $activitiesByAto = [];
            try {
                $idsAtos = array_map('intval', array_column($atos,'id'));
                if ($idsAtos) {
                    $markersAtos=implode(',',array_fill(0,count($idsAtos),'?'));
                    $stAtv=$pdoGantt->prepare("SELECT id,ato_legado_id,codigo,titulo,prazo_texto,status_execucao FROM pge_atividades_ato WHERE municipio_id=? AND ativo=1 AND ato_legado_id IN ($markersAtos) ORDER BY ato_legado_id,ordem,id");
                    $stAtv->execute(array_merge([$municipioGantt],$idsAtos));
                    foreach ($stAtv->fetchAll(\PDO::FETCH_ASSOC) as $atv) {
                        $regAtv=$ganttResultados['ATIVIDADE:'.$atv['id']]??null;
                        $atvCalculada=is_array($regAtv)
                            && ($regAtv['situacao']??null)==='CALCULADO'
                            && !empty($regAtv['inicio_previsto'])
                            && !empty($regAtv['fim_previsto']);
                        $atv['_inicio_gantt']=$atvCalculada ? $regAtv['inicio_previsto'] : null;
                        $atv['_fim_gantt']=$atvCalculada ? $regAtv['fim_previsto'] : null;
                        if ($atv['_fim_gantt']) {
                            $ganttTemDatas=true;
                            if ($ganttInicio===null || $atv['_inicio_gantt']<$ganttInicio) $ganttInicio=$atv['_inicio_gantt'];
                            if ($ganttFim===null || $atv['_fim_gantt']>$ganttFim) $ganttFim=$atv['_fim_gantt'];
                        }
                        $activitiesByAto[(int)$atv['ato_legado_id']][] = $atv;
                        $ganttPossuiAtividades = true;
                    }
                }
            } catch (\Throwable $ex) {
                error_log('[PGE Gantt V31] Atividades indisponiveis: '.$ex->getMessage());
            }
            foreach ($atos as $ato) {
                $ato['atividades'] = $activitiesByAto[(int)$ato['id']] ?? [];
                $regAto=$ganttResultados['ATO:'.$ato['id']]??null;
                $inicio = is_array($regAto) ? ($regAto['inicio_previsto']??null) : ($ato['inicio_previsto']??null);
                $fim = is_array($regAto) ? ($regAto['fim_previsto']??null) : ($ato['fim_previsto']??null);
                // Calcula prazo quando o tipo e a data do marco são explícitos.
                // NÃO associa automaticamente todo Ato à data inicial do processo.
                if ($ganttFormalmenteIniciado && (!$inicio || !$fim)
                    && !empty($ato['prazo_marco_data']) && !empty($ato['prazo_quantidade'])
                    && in_array(($ato['prazo_tipo']??''), ['DIAS_CORRIDOS','DIAS_UTEIS'],true)
                    && in_array(($ato['prazo_direcao']??''), ['APOS_MARCO','ANTES_MARCO'],true)) {
                    $marco=new \DateTimeImmutable($ato['prazo_marco_data']);
                    $quantidade=min(3650,max(0,(int)$ato['prazo_quantidade']));
                    $direcao=($ato['prazo_direcao']==='ANTES_MARCO')?-1:1;
                    $destino=$marco;
                    $contados=0;
                    while ($contados<$quantidade) {
                        $destino=$destino->modify(($direcao>0?'+1':'-1').' day');
                        $diaSemana=(int)$destino->format('N');
                        if ($ato['prazo_tipo']==='DIAS_CORRIDOS' || $diaSemana<=5) $contados++;
                    }
                    $inicio=($direcao>0?$marco:$destino)->format('Y-m-d');
                    $fim=($direcao>0?$destino:$marco)->format('Y-m-d');
                    $ato['_calculado_marco']=true;
                }
                if (!$inicio || !$fim) {
                    if (!empty($ato['prazo_referencia']) || !empty($ato['prazo_quantidade'])) $ganttTemPrazosSemMarco=true;
                }
                $ato['_inicio_gantt']=$inicio;
                $ato['_fim_gantt']=$fim;
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
            foreach ($ganttFases as &$phaseRow) {
                $regFase=$ganttResultados['FASE:'.$phaseRow['id']]??null;
                if (is_array($regFase) && ($regFase['situacao']??null)==='CALCULADO' && !empty($regFase['inicio_previsto']) && !empty($regFase['fim_previsto'])) {
                    $phaseRow['_inicio_previsto']=$regFase['inicio_previsto'];
                    $phaseRow['_fim_previsto']=$regFase['fim_previsto'];
                }
                if (!empty($phaseRow['_inicio_previsto']) && !empty($phaseRow['_fim_previsto'])) {
                    $ganttTemDatas=true;
                    if ($ganttInicio===null || $phaseRow['_inicio_previsto']<$ganttInicio) $ganttInicio=$phaseRow['_inicio_previsto'];
                    if ($ganttFim===null || $phaseRow['_fim_previsto']>$ganttFim) $ganttFim=$phaseRow['_fim_previsto'];
                }
            }
            unset($phaseRow);
        }
    }
} catch (\Throwable $ex) {
    error_log('[PGE Gantt V14] '.$ex->getMessage());
    $ganttAviso = 'Não foi possível carregar o cronograma. Consulte o log da aplicação.';
}

// A escala SEMPRE começa no início formal; antes da formalização, apenas hoje é referência visual.
$ganttInicioEscala = new \DateTimeImmutable($ganttInicioFormalData ?: $ganttHoje);
$ganttHojeDt = new \DateTimeImmutable($ganttHoje);
$ganttFimEscala = $ganttInicioEscala->modify('+30 days');
$ganttFimMinimo = $ganttHojeDt->modify('+14 days');
if ($ganttFimMinimo > $ganttFimEscala) $ganttFimEscala = $ganttFimMinimo;
if ($ganttTemDatas && $ganttFim) {
    $ganttFimPlanejado = (new \DateTimeImmutable($ganttFim))->modify('+14 days');
    if ($ganttFimPlanejado > $ganttFimEscala) $ganttFimEscala = $ganttFimPlanejado;
}
// Evita uma grade excessiva caso um prazo tenha sido lançado vários anos à frente.
if ($ganttFimEscala > $ganttInicioEscala->modify('+730 days')) $ganttFimEscala=$ganttInicioEscala->modify('+730 days');
$ganttDias = (int)$ganttInicioEscala->diff($ganttFimEscala)->days + 1;
$ganttLarguraDia = 44;
$ganttLarguraGrade = $ganttDias * $ganttLarguraDia;
$ganttIndiceHoje = ($ganttHojeDt >= $ganttInicioEscala) ? (int)$ganttInicioEscala->diff($ganttHojeDt)->days : null;
$ganttDatas = [];
for ($ganttI = 0; $ganttI < $ganttDias; $ganttI++) {
    $ganttData = $ganttInicioEscala->modify('+' . $ganttI . ' days');
    $ganttDatas[] = [
        'data' => $ganttData->format('Y-m-d'),
        'dia' => $ganttData->format('d'),
        'mes' => $ganttData->format('m/Y'),
        'semana' => ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'][(int)$ganttData->format('w')],
        'fim_semana' => in_array((int)$ganttData->format('w'), [0,6], true),
    ];
}
// Evita repetir a numeração nos títulos dos Atos importados.
$ganttTituloAto = static function (array $ato): string {
    $titulo = trim((string)($ato['titulo'] ?? ''));
    if (preg_match('/^\d+(?:\.\d+){1,3}\.?\s+/', $titulo)) return $titulo;
    $codigo = (string)($ato['codigo'] ?? '');
    if (preg_match('/(\d+\.\d+(?:\.\d+)?)/', $codigo, $m)) return $m[1] . ' — ' . $titulo;
    return $titulo;
};
?>
<section class="card pge-gantt-v30" aria-label="Gráfico Gantt com datas reais">
  <div class="pge-gantt-head">
    <div>
      <h2>Gráfico Gantt — Cronograma do Processo</h2>
      <p>Escala real desde o início formal, ampliada pelos prazos cadastrados. Use as setas para navegar por semanas.</p>
    </div>
    <div class="pge-gantt-head-actions">
      <span class="pge-gantt-chip"><?=count($ganttFases)?> fase(s)</span>
      <span class="pge-gantt-chip"><?=$ganttFormalmenteIniciado?'Início: '.$ganttEsc($ganttInicioEscala->format('d/m/Y')):'Aguardando início formal'?></span>
      <div class="pge-gantt-nav" aria-label="Navegar pelo calendário"><button type="button" data-gantt-move="-1" aria-label="Voltar 7 dias">‹</button><button type="button" data-gantt-today>Hoje</button><button type="button" data-gantt-move="1" aria-label="Avançar 7 dias">›</button></div>
    </div>
  </div>
  <?php if($ganttAviso): ?><div class="pge-gantt-alert"><?=$ganttEsc($ganttAviso)?></div><?php endif; ?>
  <div class="pge-gantt-scroll" data-gantt-scroll>
    <div class="pge-gantt-grid" style="--gantt-width:<?=$ganttLarguraGrade?>px;--gantt-day-width:<?=$ganttLarguraDia?>px">
      <div class="pge-gantt-axis">
        <div class="pge-gantt-label pge-gantt-sticky"><b>ETAPA / FASE / ATO / ATIVIDADE</b></div>
        <div class="pge-gantt-timeline pge-gantt-day-head">
          <?php foreach($ganttDatas as $col): ?>
            <div class="pge-gantt-day <?= $col['fim_semana']?'weekend':'' ?> <?= $col['data']===$ganttHoje?'today':'' ?>" title="<?=$ganttEsc($col['data'])?>">
              <small><?=$ganttEsc($col['semana'])?></small>
              <b><?=$ganttEsc($col['dia'])?></b>
              <small><?=$ganttEsc($col['mes'])?></small>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php if(!$ganttFases): ?>
        <div class="pge-gantt-row">
          <div class="pge-gantt-label pge-gantt-sticky"><b>Nenhuma Fase cadastrada</b><small>Cadastre Etapas e Fases em Configurações.</small></div>
          <div class="pge-gantt-timeline pge-gantt-track"><span class="pge-gantt-empty">Aguardando cadastros</span></div>
        </div>
      <?php else: ?>
        <?php $etapaAnterior=null; foreach($ganttFases as $fase):
          $nomeEtapa=trim((string)($fase['etapa_nome']??''));
          if($nomeEtapa!==$etapaAnterior): $etapaAnterior=$nomeEtapa; ?>
            <div class="pge-gantt-stage"><?=$ganttEsc($nomeEtapa!==''?$nomeEtapa:'Etapa não informada')?></div>
          <?php endif; ?>
          <div class="pge-gantt-row is-phase">
            <div class="pge-gantt-label pge-gantt-sticky"><b><?=$ganttEsc($fase['titulo'])?></b><small><?=count($fase['atos'])?> ato(s)</small></div>
            <div class="pge-gantt-timeline pge-gantt-track">
              <?php if($ganttIndiceHoje!==null): ?><span class="pge-gantt-now" style="left:<?=($ganttIndiceHoje+.5)*$ganttLarguraDia?>px" aria-hidden="true"></span><?php endif; ?>
              <?php if(!empty($fase['_inicio_previsto']) && !empty($fase['_fim_previsto'])):
                $iniF=new \DateTimeImmutable($fase['_inicio_previsto']);$fimF=new \DateTimeImmutable($fase['_fim_previsto']);
                $iniFC=$iniF<$ganttInicioEscala?$ganttInicioEscala:$iniF;$fimFC=$fimF>$ganttFimEscala?$ganttFimEscala:$fimF;
                if($iniFC<=$fimFC):$offF=(int)$ganttInicioEscala->diff($iniFC)->days;$lenF=(int)$iniFC->diff($fimFC)->days+1; ?>
                <span class="pge-gantt-bar phase" style="left:<?=$offF*$ganttLarguraDia+3?>px;width:<?=max(6,$lenF*$ganttLarguraDia-6)?>px" title="Intervalo planejado da Fase: <?=$ganttEsc($iniF->format('d/m/Y').' a '.$fimF->format('d/m/Y'))?>"></span>
                <?php endif; else: ?><span class="pge-gantt-empty">Fase sem intervalo previsto</span><?php endif; ?>
            </div>
          </div>
          <?php foreach($fase['atos'] as $ato):
            $temPeriodo=(bool)$ato['_datas_validas'];
            $inicioAto=$temPeriodo?new \DateTimeImmutable($ato['_inicio_gantt']):null;
            $fimAto=$temPeriodo?new \DateTimeImmutable($ato['_fim_gantt']):null;
            $inicioBarra=$inicioAto && $inicioAto<$ganttInicioEscala ? $ganttInicioEscala : $inicioAto;
            $fimBarra=$fimAto && $fimAto>$ganttFimEscala ? $ganttFimEscala : $fimAto;
            $barraVisivel=$temPeriodo && $inicioBarra <= $fimBarra;
            $deslocamento=$barraVisivel?(int)$ganttInicioEscala->diff($inicioBarra)->days:0;
            $duracao=$barraVisivel?(int)$inicioBarra->diff($fimBarra)->days+1:0;
            $atrasado=$ganttFormalmenteIniciado && $temPeriodo && $ato['status_execucao']!=='CONCLUIDA' && $ato['_fim_gantt']<$ganttHoje;
          ?>
            <div class="pge-gantt-row">
              <div class="pge-gantt-label pge-gantt-sticky"><b><?=$ganttEsc($ganttTituloAto($ato))?></b><small><?=$temPeriodo?$ganttEsc($inicioAto->format('d/m/Y').' a '.$fimAto->format('d/m/Y')):'Sem datas previstas cadastradas'?></small></div>
              <div class="pge-gantt-timeline pge-gantt-track">
                <?php if($ganttIndiceHoje!==null): ?><span class="pge-gantt-now" style="left:<?=($ganttIndiceHoje+.5)*$ganttLarguraDia?>px" aria-hidden="true"></span><?php endif; ?>
                <?php if($barraVisivel): ?>
                  <span class="pge-gantt-bar <?=$atrasado?'late':($ato['status_execucao']==='CONCLUIDA'?'done':'')?>" style="left:<?=$deslocamento*$ganttLarguraDia+3?>px;width:<?=max(6,$duracao*$ganttLarguraDia-6)?>px" title="<?=$ganttEsc($inicioAto->format('d/m/Y').' até '.$fimAto->format('d/m/Y').' | '.($ato['prazo_referencia']??'').' | '.($ato['fundamento_normativo']??''))?>"></span>
                <?php else: ?><span class="pge-gantt-empty">Aguardando datas do Ato</span><?php endif; ?>
              </div>
            </div>
            <?php foreach(($ato['atividades']??[]) as $sub): ?>
            <div class="pge-gantt-row is-activity">
              <?php
                  $subStart=!empty($sub['_inicio_gantt'])?new \DateTimeImmutable($sub['_inicio_gantt']):null;
                  $subEnd=!empty($sub['_fim_gantt'])?new \DateTimeImmutable($sub['_fim_gantt']):null;
                  $subVisible=$subStart && $subEnd && $subEnd>=$ganttInicioEscala && $subStart<=$ganttFimEscala;
                  $subFrom=$subVisible && $subStart<$ganttInicioEscala?$ganttInicioEscala:$subStart;
                  $subTo=$subVisible && $subEnd>$ganttFimEscala?$ganttFimEscala:$subEnd;
                  $subOffset=$subVisible?(int)$ganttInicioEscala->diff($subFrom)->days:0;
                  $subLength=$subVisible?(int)$subFrom->diff($subTo)->days+1:0;
                  $subLate=$ganttFormalmenteIniciado && $subEnd && ($sub['status_execucao']??'')!=='CONCLUIDA' && $sub['_fim_gantt']<$ganttHoje;
              ?>
              <div class="pge-gantt-label pge-gantt-sticky"><b>↳ <?=$ganttEsc(trim((string)$sub['titulo']))?></b><small><?=($subStart && $subEnd)?$ganttEsc($subStart->format('d/m/Y').' a '.$subEnd->format('d/m/Y')):$ganttEsc($sub['prazo_texto'] ?: 'Aguardando configuracao do marco')?></small></div>
              <div class="pge-gantt-timeline pge-gantt-track">
                <?php if($ganttIndiceHoje!==null): ?><span class="pge-gantt-now" style="left:<?=($ganttIndiceHoje+.5)*$ganttLarguraDia?>px" aria-hidden="true"></span><?php endif; ?>
                <?php if($subVisible): ?><span class="pge-gantt-bar <?=$subLate?'late':(($sub['status_execucao']??'')==='CONCLUIDA'?'done':'')?>" style="left:<?=$subOffset*$ganttLarguraDia+3?>px;width:<?=max(6,$subLength*$ganttLarguraDia-6)?>px"></span>
                <?php else: ?><span class="pge-gantt-empty">Aguardando marco / programacao</span><?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endforeach; ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
  <?php require __DIR__.'/deadline_rules_panel.php'; ?>
  <div class="pge-gantt-legend">
    <span><i style="background:#1469bb"></i> Previsto</span>
    <span><i style="background:#228b5f"></i> Concluído</span>
    <span><i style="background:#c83d41"></i> Atrasado</span>
    <span><i style="background:#d52d46"></i> Hoje</span>
    <span><i style="background:#cad5e3"></i> Sem datas previstas</span>
  </div>
  <?php if(!$ganttTemDatas): ?><p class="pge-gantt-note">A escala utiliza datas de calendário reais. Nenhum Ato sem período previsto recebe uma data fictícia; seus prazos deverão ser definidos a partir dos marcos aplicáveis.</p><?php endif; ?>
<?php if($ganttTemPrazosSemMarco): ?><p class="pge-gantt-note"><strong>Prazos cadastrados aguardando marco:</strong> durações sem data de referência não são convertidas em vencimentos automaticamente. Dias úteis aqui consideram segunda a sexta-feira; feriados ainda precisam ser configurados.</p><?php endif; ?>
</section>
<style>
.pge-gantt-nav{display:inline-flex;gap:4px;align-items:center;padding:4px;border:1px solid #c7d9eb;border-radius:12px;background:#f4f8fd}.pge-gantt-nav button{border:0;border-radius:9px;min-width:38px;height:34px;background:#fff;color:#164c81;font-weight:900;font-size:17px;cursor:pointer}.pge-gantt-nav button:hover{background:#dfedfc}.pge-gantt-bar.phase{background:#7d8fa6;height:13px;top:19px;opacity:.85}.pge-gantt-row.is-activity .pge-gantt-label{padding-left:28px;background:#fbfdff}.pge-gantt-row.is-activity .pge-gantt-label b{font-weight:600;color:#506a88}.pge-gantt-v30{padding:20px;margin:20px 0}.pge-gantt-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:14px;flex-wrap:wrap}.pge-gantt-head h2{margin:0 0 5px;color:#143a63;font-size:1.18rem}.pge-gantt-head p,.pge-gantt-note{color:#63758c;font-size:.83rem;margin:0}.pge-gantt-head-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.pge-gantt-chip{font-size:.75rem;font-weight:800;color:#1d568a;background:#e8f2ff;border-radius:99px;padding:7px 10px}.pge-gantt-today-button{border:1px solid #bfd4ec;border-radius:9px;background:#fff;color:#125991;font-weight:800;padding:7px 10px;cursor:pointer}.pge-gantt-scroll{overflow-x:auto;border:1px solid #dce6f0;border-radius:12px;scrollbar-width:thin}.pge-gantt-grid{width:calc(320px + var(--gantt-width));min-width:100%}.pge-gantt-axis,.pge-gantt-row{display:grid;grid-template-columns:320px var(--gantt-width)}.pge-gantt-sticky{position:sticky;left:0;z-index:5}.pge-gantt-axis .pge-gantt-sticky{z-index:8;background:#e7eff9}.pge-gantt-label{padding:10px 12px;border-right:1px solid #d5e0ed;background:#fff;overflow-wrap:anywhere}.pge-gantt-label b{display:block;font-size:.78rem;color:#153b66}.pge-gantt-label small{display:block;margin-top:4px;font-size:.72rem;color:#718297}.pge-gantt-axis .pge-gantt-label{display:flex;align-items:center}.pge-gantt-day-head{display:flex;background:#e7eff9}.pge-gantt-day{flex:none;width:var(--gantt-day-width);height:62px;border-right:1px solid #cfdced;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;color:#345778}.pge-gantt-day b{font-size:13px}.pge-gantt-day small{font-size:9px}.pge-gantt-day.weekend{background:#dce7f3}.pge-gantt-day.today{background:#ffe6e8;color:#b31d35;box-shadow:inset 0 -3px 0 #d52d46}.pge-gantt-stage{padding:11px 13px;background:#153f6d;color:#fff;font-size:.83rem;font-weight:900;position:sticky;left:0;z-index:6;width:calc(320px + var(--gantt-width))}.pge-gantt-row{min-height:51px;border-top:1px solid #e7edf4}.pge-gantt-row.is-phase .pge-gantt-label{background:#f1f6fc}.pge-gantt-row.is-phase .pge-gantt-track{background-color:#f1f6fc}.pge-gantt-track{position:relative;min-height:51px;display:flex;align-items:center;justify-content:center;background-color:#fafcff;background-image:repeating-linear-gradient(to right,transparent 0,transparent calc(var(--gantt-day-width) - 1px),#e3eaf3 calc(var(--gantt-day-width) - 1px),#e3eaf3 var(--gantt-day-width))}.pge-gantt-now{position:absolute;top:0;bottom:0;width:2px;background:#d52d46;z-index:2;pointer-events:none}.pge-gantt-bar{position:absolute;top:15px;height:22px;border-radius:5px;background:#1469bb;z-index:3;box-shadow:0 1px 4px #0002}.pge-gantt-bar.done{background:#228b5f}.pge-gantt-bar.late{background:#c83d41}.pge-gantt-empty{color:#9caabd;font-size:.7rem;font-style:italic;white-space:nowrap}.pge-gantt-legend{display:flex;gap:15px;flex-wrap:wrap;margin-top:13px;color:#63778d;font-size:.76rem}.pge-gantt-legend span{display:flex;align-items:center;gap:7px}.pge-gantt-legend i{display:block;width:10px;height:10px;border-radius:3px}.pge-gantt-note{margin-top:10px}.pge-gantt-alert{padding:10px 12px;background:#fff7ed;border:1px solid #ffdfba;color:#925312;margin-bottom:12px;border-radius:9px}
</style>
<script>
(function(){
  const section=document.querySelector('.pge-gantt-v30');
  if(!section)return;
  const scroll=section.querySelector('[data-gantt-scroll]');
  const marker=section.querySelector('.pge-gantt-day.today');
  section.querySelectorAll('[data-gantt-move]').forEach(function(button){
    button.addEventListener('click',function(){
      scroll.scrollBy({left:Number(button.dataset.ganttMove)*7*44,behavior:'smooth'});
    });
  });
  section.querySelector('[data-gantt-today]')?.addEventListener('click',function(){
    if(!marker||!scroll)return;
    const left=marker.offsetLeft-(scroll.clientWidth-320)/2;
    scroll.scrollTo({left:Math.max(0,left),behavior:'smooth'});
  });
})();
</script>
