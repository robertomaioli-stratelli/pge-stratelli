<?php
use App\Core\Format;
$macroHasTerritory=(bool)array_filter($clientes,fn($c)=>!empty($c['geojson_delimitacao']));
$healthMeta=[
    'normal'=>['label'=>'Normal','icon'=>'●'],
    'attention'=>['label'=>'Atenção','icon'=>'●'],
    'critical'=>['label'=>'Crítico','icon'=>'●'],
    'implementation'=>['label'=>'Implantação','icon'=>'●'],
    'completed'=>['label'=>'Concluído','icon'=>'●'],
];
?>
<?php if($macroHasTerritory):?><link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""><?php endif;?>
<div class="module-page macro-dashboard">
    <div class="module-hero macro-hero">
        <div>
            <h1>Visão Macro Stratelli</h1>
            <p>Panorama executivo da carteira de municípios, com prazos, documentos, pendências e ações prioritárias da equipe.</p>
        </div>
        <div class="macro-live-badge">● Dados consolidados em tempo real</div>
    </div>
    <?php $desk=$pendingDesk??['actionCount'=>0,'stats'=>[]];?>
    <section class="workdesk-dashboard-strip macro <?=($desk['actionCount']??0)>0?'has-actions':'is-clear'?>"><div class="workdesk-dashboard-icon"><?=($desk['actionCount']??0)>0?'◆':'✓'?></div><div><small>CENTRAL DE PENDÊNCIAS STRATELLI</small><b><?php if(($desk['actionCount']??0)>0):?><?=$desk['actionCount']?> ação(ões) exigem atuação da equipe<?php else:?>Nenhuma ação imediata na carteira<?php endif;?></b><span><?=($desk['stats']['validation']??0)?> validação(ões) · <?=($desk['stats']['closure']??0)?> encerramento(s) · <?=($desk['stats']['deadline']??0)?> prazo(s) em atenção · <?=($desk['stats']['monitoring']??0)?> acompanhamento(s)</span></div><a class="btn primary" href="/admin/pendencias">Abrir Central</a></section>

    <div class="macro-kpis">
        <article class="module-kpi"><small>CLIENTES CADASTRADOS</small><b><?=$summary['clientes']?></b><span><?=$summary['ativos']?> instância(s) ativa(s)</span></article>
        <article class="module-kpi success"><small>PROCESSOS EM ANDAMENTO</small><b><?=$summary['em_andamento']?></b><span><?=$summary['no_prazo']?> em situação normal</span></article>
        <article class="module-kpi warning"><small>ATENÇÃO / CRÍTICOS</small><b><?=$summary['atencao']?></b><span>Clientes que exigem acompanhamento</span></article>
        <article class="module-kpi neutral"><small>EM IMPLANTAÇÃO</small><b><?=$summary['implantacao']?></b><span>Instâncias ainda em configuração</span></article>
        <article class="module-kpi"><small>AGUARDANDO VALIDAÇÃO</small><b><?=$summary['aguardando_validacao']?></b><span>Documentos recebidos pela Stratelli</span></article>
        <article class="module-kpi warning"><small>CORREÇÕES PENDENTES</small><b><?=$summary['correcoes']?></b><span>Documentos devolvidos para ajuste</span></article>
        <article class="module-kpi danger"><small>SECRETARIAS PENDENTES</small><b><?=$summary['secretarias_pendentes']?></b><span>Unidades com envio ou correção pendente</span></article>
        <article class="module-kpi success"><small>FASES ENCERRADAS</small><b><?=$summary['fases_concluidas']?>/<?=$summary['fases_total']?></b><span>Consolidado de toda a carteira</span></article>
        <article class="module-kpi"><small>OBJETOS TERRITORIAIS</small><b><?=$summary['objetos_territoriais']?></b><span>Equipamentos, áreas, linhas e pontos</span></article>
        <article class="module-kpi neutral"><small>CAMADAS TERRITORIAIS</small><b><?=$summary['camadas_territoriais']?></b><span>Camadas ativas na carteira</span></article>
    </div>

    <?php if($attention):?>
    <section class="macro-section macro-attention-section">
        <div class="macro-section-head">
            <div><h2>Clientes que precisam de atenção</h2><p>Municípios com prazo em atenção, atraso ou situação operacional crítica.</p></div>
            <span class="macro-section-count"><?=count($attention)?> cliente(s)</span>
        </div>
        <div class="macro-attention-grid">
            <?php foreach(array_slice($attention,0,4) as $c):?>
            <a class="macro-attention-card <?=Format::h($c['saude'])?>" href="/<?=Format::h($c['slug'])?>/dashboard">
                <div><span class="macro-health-dot"></span><strong><?=Format::h($c['nome'].' - '.$c['uf'])?></strong></div>
                <b><?=Format::h($c['prazo_rotulo'])?></b>
                <p><?=Format::h($c['proxima_acao'])?></p>
            </a>
            <?php endforeach;?>
        </div>
    </section>
    <?php endif;?>

    <section class="macro-section">
        <div class="macro-section-head macro-list-head">
            <div><h2>Todos os municípios</h2><p>Indicadores básicos de cada cliente e acesso rápido às áreas operacionais.</p></div>
            <div class="macro-toolbar">
                <div class="macro-filter-buttons" id="macroFilters">
                    <button type="button" class="active" data-filter="all">Todos</button>
                    <button type="button" data-filter="andamento">Em andamento</button>
                    <button type="button" data-filter="atencao">Atenção</button>
                    <button type="button" data-filter="implantacao">Implantação</button>
                    <button type="button" data-filter="concluido">Concluídos</button>
                </div>
                <label class="macro-search"><span>⌕</span><input type="search" id="macroClientSearch" placeholder="Buscar município"></label>
            </div>
        </div>

        <div class="macro-client-grid" id="macroClientGrid">
        <?php foreach($clientes as $c):$phase=$c['fase_atual'];$health=$healthMeta[$c['saude']]??$healthMeta['normal'];?>
            <article class="macro-client-card <?=Format::h($c['saude'])?>" data-category="<?=Format::h($c['categoria'])?>" data-name="<?=Format::h(strtolower($c['nome'].' '.$c['uf']))?>">
                <header class="macro-client-head">
                    <div class="macro-client-identity">
                        <?php if(!empty($c['brasao_path'])):?><img class="macro-client-coat" src="/media/municipios/<?=$c['id']?>/brasao?v=<?=substr(sha1((string)$c['brasao_path']),0,12)?>" loading="eager" decoding="async" alt="Brasão de <?=Format::h($c['nome'])?>"><?php else:?><span class="macro-client-coat placeholder">▥</span><?php endif;?>
                        <div><span class="macro-client-location">MUNICÍPIO CLIENTE</span><h3><?=Format::h($c['nome'])?> <small>— <?=Format::h($c['uf'])?></small></h3></div>
                    </div>
                    <span class="macro-health <?=Format::h($c['saude'])?>"><i></i><?=Format::h($health['label'])?></span>
                </header>

                <?php if($c['saude']==='implementation'):?>
                    <div class="macro-implementation">
                        <div class="macro-implementation-icon">◌</div>
                        <div><b>Aguardando implantação</b><p>A estrutura operacional deste município ainda não foi iniciada.</p></div>
                    </div>
                    <div class="macro-client-mini-grid">
                        <div><b><?=$c['usuarios_ativos']?></b><small>Usuários ativos</small></div>
                        <div><b><?=$c['gestores_ativos']?></b><small>Gestores</small></div>
                        <div><b><?=$c['secretarias_ativas']?></b><small>Secretarias</small></div>
                        <div><b><?=$c['fases_total']?></b><small>Fases cadastradas</small></div>
                    </div>
                <?php else:?>
                    <div class="macro-current-phase">
                        <div>
                            <small>FASE ATUAL</small>
                            <?php if($phase):?><b>Fase <?=Format::h($phase['ordem'])?> — <?=Format::h($phase['aba'])?></b><span><?=Format::h($phase['titulo'])?></span><?php else:?><b>Processo concluído</b><span>Todas as fases foram encerradas formalmente</span><?php endif;?>
                        </div>
                        <div class="macro-deadline-box <?=Format::h($c['prazo_atual']['status']??'completed')?>"><small>SITUAÇÃO DO PRAZO</small><b><?=Format::h($c['prazo_rotulo'])?></b><span><?=Format::h($c['prazo_texto'])?></span></div>
                    </div>
                    <div class="macro-progress-block">
                        <div><span>Progresso documental</span><strong><?=$c['progresso']?>%</strong></div>
                        <div class="macro-progress-track"><span style="width:<?=$c['progresso']?>%"></span></div>
                    </div>
                    <div class="macro-doc-grid">
                        <div class="approved"><b><?=$c['aprovados']?></b><small>Aprovados</small></div>
                        <div class="waiting"><b><?=$c['aguardando']?></b><small>Em validação</small></div>
                        <div class="correction"><b><?=$c['correcoes']?></b><small>Correções</small></div>
                        <div class="pending"><b><?=$c['pendentes']?></b><small>Pendentes</small></div>
                    </div>
                    <div class="macro-client-secondary">
                        <span><b><?=$c['secretarias_pendentes']?></b> secretaria(s) pendente(s)</span>
                        <span><b><?=$c['fases_concluidas']?>/<?=$c['fases_total']?></b> fases encerradas</span>
                        <span><b><?=$c['gestores_ativos']?></b> gestor(es)</span>
                    </div>
                    <div class="macro-territorial-metrics"><span>⌖ <b><?=$c['objetos_territoriais_ativos']?></b> objetos</span><span>▱ <b><?=$c['camadas_territoriais_ativas']?></b> camadas</span><span>↔ <b><?=$c['fases_territorializadas']?></b> fases territorializadas</span></div>
                <?php endif;?>

                <?php if(!empty($c['geojson_delimitacao'])):?><div class="macro-territory-preview"><div class="macro-territory-preview-map" id="macroTerritoryMap<?=$c['id']?>" data-mid="<?=$c['id']?>"></div><a href="/<?=Format::h($c['slug'])?>/territorio">⌖ Ver território</a></div><?php endif;?>
                <div class="macro-next-action <?=Format::h($c['saude'])?>">
                    <small>PRÓXIMA AÇÃO DA STRATELLI</small>
                    <b><?=Format::h($c['proxima_acao'])?></b>
                </div>
                <div class="macro-last-movement"><span>Última movimentação</span><b><?=Format::h($c['ultima_movimentacao']?Format::dateTime($c['ultima_movimentacao']):'Sem movimentação')?></b><small><?=Format::h($c['ultima_movimentacao_evento'])?></small></div>

                <footer class="macro-client-actions">
                    <a class="mini-link primary" href="/<?=Format::h($c['slug'])?>/dashboard">Dashboard</a>
                    <a class="mini-link" href="/admin/municipios/<?=$c['id']?>">Cadastro</a>
                    <?php if(!empty($c['geojson_delimitacao'])):?><a class="mini-link" href="/<?=Format::h($c['slug'])?>/territorio">Território</a><?php endif;?>
                    <a class="mini-link" href="/admin/configuracoes?municipio=<?=$c['id']?>">Configurações</a>
                    <?php if($c['fases_total']>0):?>
                    <a class="mini-link" href="/<?=Format::h($c['slug'])?>/workflow">Workflow</a>
                    <a class="mini-link" href="/<?=Format::h($c['slug'])?>/documentos">Documentos</a>
                    <a class="mini-link" href="/<?=Format::h($c['slug'])?>/historico">Histórico</a>
                    <?php else:?><a class="mini-link" href="/admin/municipios">Configurar cliente</a><?php endif;?>
                </footer>
            </article>
        <?php endforeach;?>
        </div>
        <div class="macro-empty-filter" id="macroEmptyFilter" hidden>Nenhum município corresponde ao filtro selecionado.</div>
    </section>

    <section class="macro-section macro-recent-section">
        <div class="macro-section-head"><div><h2>Atividades recentes da plataforma</h2><p>Últimas movimentações registradas entre todos os clientes.</p></div><span class="macro-section-count"><?=count($recent)?> registro(s)</span></div>
        <div class="macro-recent-list">
            <?php if($recent):foreach($recent as $r):?>
            <a href="/<?=Format::h($r['slug'])?>/historico" class="macro-recent-item">
                <span class="macro-recent-icon">◷</span>
                <div><b><?=Format::h($r['evento'])?></b><p><?=Format::h($r['municipio_nome'].' - '.$r['uf'])?><?=!empty($r['usuario_nome'])?' · '.Format::h($r['usuario_nome']):''?></p></div>
                <div class="macro-recent-time"><b><?=Format::h(Format::dateTime($r['criado_em']))?></b><small><?=Format::h($r['status']?:'Movimentação')?></small></div>
            </a>
            <?php endforeach;else:?><div class="empty-state">Ainda não existem movimentações registradas.</div><?php endif;?>
        </div>
    </section>
</div>

<?php if($macroHasTerritory):?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
(function(){if(typeof L==='undefined')return;document.querySelectorAll('.macro-territory-preview-map').forEach(el=>{const mid=el.dataset.mid;if(!mid)return;const map=L.map(el,{zoomControl:false,attributionControl:false,scrollWheelZoom:false,dragging:false,doubleClickZoom:false,boxZoom:false,keyboard:false,touchZoom:false});map.getContainer().style.background='#eef4f8';fetch('/media/municipios/'+mid+'/territorio',{credentials:'same-origin'}).then(r=>r.json()).then(geo=>{const boundary=L.geoJSON(geo,{style:{color:'#174c98',weight:2,fillColor:'#176fdd',fillOpacity:.14}}).addTo(map);const b=boundary.getBounds();if(b.isValid())map.fitBounds(b,{padding:[8,8]});}).catch(()=>{});setTimeout(()=>map.invalidateSize(),80);});})();
</script>
<?php endif;?>

<script>
(function(){
 const buttons=[...document.querySelectorAll('#macroFilters button')],search=document.getElementById('macroClientSearch'),cards=[...document.querySelectorAll('#macroClientGrid .macro-client-card')],empty=document.getElementById('macroEmptyFilter');
 let filter='all';
 function apply(){const q=(search?.value||'').trim().toLowerCase();let visible=0;cards.forEach(card=>{const category=card.dataset.category||'';const name=card.dataset.name||'';const show=(filter==='all'||category===filter)&&(!q||name.includes(q));card.hidden=!show;if(show)visible++;});if(empty)empty.hidden=visible!==0;}
 buttons.forEach(btn=>btn.addEventListener('click',()=>{buttons.forEach(b=>b.classList.remove('active'));btn.classList.add('active');filter=btn.dataset.filter||'all';apply();}));
 search?.addEventListener('input',apply);
})();
</script>
