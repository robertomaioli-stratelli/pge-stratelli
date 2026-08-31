<?php use App\Core\Format;$sla=$slaOperacional??['secretarias'=>[],'resumo'=>[]];$rows=$sla['secretarias']??[];$summary=$sla['resumo']??[];$current=$sla['fase_atual']??null; ?>
<link rel="stylesheet" href="/assets/sla.css?v=4208">
<div class="module-page sla-page">
    <section class="sla-hero">
        <div class="sla-hero-copy">
            <span class="sla-eyebrow">GESTÃO OPERACIONAL</span>
            <h1><?=$scope==='secretaria'?'Meu SLA Operacional':'SLA por Secretaria'?></h1>
            <p><?=$scope==='secretaria'?'Acompanhe pontualidade das suas entregas, tempo médio de envio, correções e aprovação na primeira versão.':'Compare a capacidade de entrega das Secretarias com base nos prazos operacionais e na trilha documental já registrada no INPACTA.'?></p>
            <?php if($current):?><span class="sla-current-phase">Fase atual · <?=Format::h($current['ordem'])?> — <?=Format::h($current['aba'])?></span><?php endif;?>
        </div>
        <div class="sla-hero-actions">
            <a class="btn neutral" href="/<?=Format::h($tenant['slug'])?>/relatorios">← Relatórios</a>
            <a class="btn primary" href="/<?=Format::h($tenant['slug'])?>/relatorios/sla/exportar">↓ Exportar CSV</a>
        </div>
    </section>

    <section class="sla-kpis">
        <article class="sla-kpi sla-tone-<?=Format::h($summary['sla_class']??'neutral')?>">
            <div class="sla-kpi-icon">⌛</div><div><small>SLA GERAL</small><strong><?=($summary['sla_percentual']??null)===null?'—':Format::h(number_format((float)$summary['sla_percentual'],1,',','.')).'%'?></strong><span><?=Format::h($summary['sla_label']??'SEM AMOSTRA')?> · <?=intval($summary['no_prazo']??0)?> de <?=intval($summary['avaliadas']??0)?> entregas no prazo</span></div>
        </article>
        <article class="sla-kpi"><div class="sla-kpi-icon">◷</div><div><small>TEMPO MÉDIO PARA ENVIAR</small><strong><?=Format::h($summary['tempo_medio_envio']??'Sem amostra')?></strong><span>Do início da fase até a primeira entrega</span></div></article>
        <article class="sla-kpi"><div class="sla-kpi-icon">!</div><div><small>CORREÇÕES REGISTRADAS</small><strong><?=intval($summary['correcoes']??0)?></strong><span>Solicitações formais de correção documental</span></div></article>
        <article class="sla-kpi"><div class="sla-kpi-icon">✓</div><div><small>APROVAÇÃO NA 1ª VERSÃO</small><strong><?=($summary['aprovacao_primeira_percentual']??null)===null?'—':Format::h(number_format((float)$summary['aprovacao_primeira_percentual'],1,',','.')).'%'?></strong><span><?=intval($summary['primeira_versao_aprovada']??0)?> de <?=intval($summary['primeira_versao_decidida']??0)?> primeiras versões decididas</span></div></article>
    </section>

    <?php if(!$rows):?>
        <section class="card sla-empty"><div>⌛</div><h2>Ainda não há dados suficientes para calcular o SLA.</h2><p>O indicador será formado automaticamente conforme documentos obrigatórios forem enviados e os prazos das fases forem consolidados.</p></section>
    <?php else:?>
        <?php if($scope!=='secretaria'):?>
        <section class="sla-highlight-grid">
            <article class="card sla-highlight best"><small>MELHOR DESEMPENHO</small><?php if(!empty($summary['melhor'])):?><strong><?=Format::h($summary['melhor']['display_name'])?></strong><b><?=Format::h(number_format((float)$summary['melhor']['sla_percentual'],1,',','.'))?>% dentro do prazo</b><span><?=Format::h($summary['melhor']['tempo_medio_envio'])?> para primeiro envio · <?=intval($summary['melhor']['correcoes'])?> correção(ões)</span><?php else:?><strong>Sem amostra consolidada</strong><?php endif;?></article>
            <article class="card sla-highlight attention"><small>MAIOR PONTO DE ATENÇÃO</small><?php if(!empty($summary['atencao'])):?><strong><?=Format::h($summary['atencao']['display_name'])?></strong><b><?=Format::h(number_format((float)$summary['atencao']['sla_percentual'],1,',','.'))?>% dentro do prazo</b><span><?=intval($summary['atencao']['fora_prazo'])?> entrega(s) fora do prazo · <?=intval($summary['atencao']['pendentes_vencidas'])?> pendência(s) vencida(s)</span><?php else:?><strong>Sem amostra consolidada</strong><?php endif;?></article>
        </section>
        <?php endif;?>

        <section class="card sla-ranking-card">
            <div class="sla-section-head"><div><span>DESEMPENHO OPERACIONAL</span><h2><?=$scope==='secretaria'?'Indicadores da sua unidade':'Ranking por Secretaria'?></h2><p>O SLA mede a pontualidade da primeira entrega obrigatória. Correções e aprovação na primeira versão aparecem separadamente para preservar a leitura gerencial.</p></div><span class="sla-count"><?=count($rows)?> secretaria(s)</span></div>
            <div class="sla-ranking-grid">
                <?php foreach($rows as $i=>$s):?>
                    <article class="sla-rank-card sla-tone-<?=Format::h($s['sla_class'])?>">
                        <div class="sla-rank-top"><span class="sla-position"><?=($i+1)?>º</span><div><small><?=Format::h($s['sigla']?:'SECRETARIA')?></small><strong><?=Format::h($s['nome'])?></strong></div><span class="sla-status"><?=Format::h($s['sla_label'])?></span></div>
                        <div class="sla-score"><b><?=$s['sla_percentual']===null?'—':Format::h(number_format((float)$s['sla_percentual'],1,',','.')).'%'?></b><span>dentro do prazo</span></div>
                        <div class="sla-progress"><span style="width:<?=max(0,min(100,(float)($s['sla_percentual']??0)))?>%"></span></div>
                        <div class="sla-card-metrics">
                            <div><small>Tempo médio</small><b><?=Format::h($s['tempo_medio_envio'])?></b></div>
                            <div><small>Correções</small><b><?=intval($s['correcoes'])?></b></div>
                            <div><small>1ª versão</small><b><?=$s['aprovacao_primeira_percentual']===null?'—':Format::h(number_format((float)$s['aprovacao_primeira_percentual'],1,',','.')).'%'?></b></div>
                        </div>
                        <div class="sla-rank-foot"><span>✓ <?=intval($s['no_prazo'])?> no prazo</span><span>× <?=intval($s['fora_prazo'])?> fora</span><span>⌛ <?=intval($s['pendentes_vencidas'])?> vencida(s)</span></div>
                    </article>
                <?php endforeach;?>
            </div>
        </section>

        <section class="card sla-table-card">
            <div class="sla-section-head compact"><div><span>DETALHAMENTO</span><h2>Leitura técnica do SLA</h2><p>Dados usados para comparação, acompanhamento e priorização das Secretarias.</p></div></div>
            <div class="table-scroll"><table class="production-table sla-table"><thead><tr><th>Secretaria</th><th>SLA</th><th>Avaliadas</th><th>No prazo</th><th>Fora do prazo</th><th>Tempo médio</th><th>Correções</th><th>1ª versão</th><th>Pendências vencidas</th></tr></thead><tbody>
            <?php foreach($rows as $s):?><tr><td><b><?=Format::h($s['display_name'])?></b></td><td><span class="sla-table-pill sla-tone-<?=Format::h($s['sla_class'])?>"><?=$s['sla_percentual']===null?'SEM AMOSTRA':Format::h(number_format((float)$s['sla_percentual'],1,',','.')).'%'?></span></td><td><?=intval($s['avaliadas'])?></td><td><?=intval($s['no_prazo'])?></td><td><?=intval($s['fora_prazo'])?></td><td><?=Format::h($s['tempo_medio_envio'])?></td><td><?=intval($s['correcoes'])?></td><td><?=$s['aprovacao_primeira_percentual']===null?'—':Format::h(number_format((float)$s['aprovacao_primeira_percentual'],1,',','.')).'%'?></td><td><?=intval($s['pendentes_vencidas'])?></td></tr><?php endforeach;?>
            </tbody></table></div>
        </section>
    <?php endif;?>

    <details class="card sla-methodology"><summary><span>ⓘ</span><div><b>Como o SLA é calculado?</b><small>Abra para consultar os critérios do indicador.</small></div><strong>+</strong></summary><div class="sla-methodology-body"><?php foreach($sla['metodologia']??[] as $m):?><p>• <?=Format::h($m)?></p><?php endforeach;?></div></details>
</div>
