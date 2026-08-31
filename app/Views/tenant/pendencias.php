<?php
use App\Core\Format;
$desk=$pendingDesk??['items'=>[],'stats'=>[],'actionCount'=>0,'trackingCount'=>0];
$items=$desk['items']??[];$stats=$desk['stats']??[];$isStratelli=($scope??'')==='stratelli';
$titleDesk=$isStratelli?'Central de Pendências':'Minha Mesa';
$subtitle=$isStratelli?'Ações que exigem atuação da Stratelli nesta instância e itens que merecem acompanhamento.':'Tudo o que exige sua atuação, organizado por prioridade e respeitando seu escopo de acesso.';
?>
<div class="module-page workdesk-page">
    <div class="module-hero workdesk-hero">
        <div><h1><?=$titleDesk?></h1><p><?=Format::h($subtitle)?></p></div>
        <div class="workdesk-action-total"><small>AÇÕES QUE EXIGEM VOCÊ</small><b><?=$desk['actionCount']?></b><span><?=$desk['actionCount']===1?'ação pendente':'ações pendentes'?></span></div>
    </div>

    <div class="workdesk-kpis">
        <article class="workdesk-kpi primary"><small>AÇÕES IMEDIATAS</small><b><?=$desk['actionCount']?></b><span>Exigem atuação do seu perfil</span></article>
        <article class="workdesk-kpi pending"><small>PARA ENVIAR</small><b><?=$stats['send']??0?></b><span>Documentos ainda não enviados</span></article>
        <article class="workdesk-kpi correction"><small>CORREÇÕES</small><b><?=$stats['correction']??0?></b><span>Precisam de nova versão</span></article>
        <article class="workdesk-kpi waiting"><small>AGUARDANDO VALIDAÇÃO</small><b><?=$isStratelli?($stats['validation']??0):($stats['waiting']??0)?></b><span><?=$isStratelli?'Recebidos para análise':'Envios em análise pela Stratelli'?></span></article>
        <article class="workdesk-kpi deadline"><small>FASES / PRAZOS</small><b><?=($stats['closure']??0)+($stats['deadline']??0)+($stats['blocked']??0)?></b><span>Encerramentos, prazos ou dependências</span></article>
    </div>

    <section class="card workdesk-list-card">
        <div class="workdesk-list-head">
            <div><h2>Fila de trabalho</h2><p>Prioridades mais altas aparecem primeiro. Itens de acompanhamento não entram no total de ações imediatas.</p></div>
            <span class="workdesk-result-count" id="workdeskResultCount"><?=count($items)?> item(ns)</span>
        </div>
        <div class="workdesk-toolbar">
            <label class="workdesk-search"><span>⌕</span><input id="workdeskSearch" type="search" placeholder="Buscar documento, fase ou unidade"></label>
            <select id="workdeskFilter"><option value="all">Todos os tipos</option><option value="send">Para enviar</option><option value="correction">Correções</option><option value="validation">Validação</option><option value="waiting">Aguardando</option><option value="closure">Encerramento formal</option><option value="deadline">Prazo</option><option value="monitoring">Acompanhamento</option><option value="blocked">Fases bloqueadas</option></select>
            <select id="workdeskActionFilter"><option value="all">Ações + acompanhamento</option><option value="action">Somente minhas ações</option><option value="tracking">Somente acompanhamento</option></select>
        </div>

        <div class="workdesk-items" id="workdeskItems">
        <?php if($items):foreach($items as $i=>$item):$search=strtolower(trim(($item['title']??'').' '.($item['document']??'').' '.($item['phase']??'').' '.($item['unit']??'').' '.($item['text']??'')));?>
            <article class="workdesk-item <?=Format::h($item['tone'])?>" data-index="<?=$i?>" data-type="<?=Format::h($item['type'])?>" data-action="<?=!empty($item['action'])?'1':'0'?>" data-search="<?=Format::h($search)?>">
                <div class="workdesk-item-icon"><?=Format::h($item['icon'])?></div>
                <div class="workdesk-item-body">
                    <div class="workdesk-item-top"><div><span class="workdesk-type-chip <?=Format::h($item['tone'])?>"><?=!empty($item['action'])?'AÇÃO':'ACOMPANHAMENTO'?></span><h3><?=Format::h($item['title'])?></h3></div><?php if(!empty($item['date'])):?><time><?=Format::h(Format::dateTime($item['date']))?></time><?php endif;?></div>
                    <strong class="workdesk-document"><?=Format::h($item['document'])?></strong>
                    <div class="workdesk-meta"><span><?=Format::h($item['phase'])?></span><?php if(!empty($item['unit'])):?><span><?=Format::h($item['unit'])?></span><?php endif;?></div>
                    <?php if(!empty($item['text'])):?><p><?=Format::h($item['text'])?></p><?php endif;?>
                </div>
                <div class="workdesk-item-action"><a class="mini-link <?=!empty($item['action'])?'primary':''?>" href="<?=Format::h($item['url'])?>"><?=Format::h($item['action_label'])?></a></div>
            </article>
        <?php endforeach;else:?><div class="workdesk-empty"><span>✓</span><b>Nenhuma pendência encontrada</b><p>Não há ações ou acompanhamentos relevantes para o seu perfil neste momento.</p></div><?php endif;?>
        </div>
        <div class="workdesk-pagination-bar"><span id="workdeskRange">Exibindo 0 de 0</span><nav id="workdeskPagination" class="workdesk-pagination"></nav></div>
    </section>
</div>
<script>
(function(){
 const rows=[...document.querySelectorAll('.workdesk-item')],search=document.getElementById('workdeskSearch'),type=document.getElementById('workdeskFilter'),action=document.getElementById('workdeskActionFilter'),count=document.getElementById('workdeskResultCount'),range=document.getElementById('workdeskRange'),pager=document.getElementById('workdeskPagination');
 const perPage=10;let page=1,filtered=[];
 function normalize(v){return (v||'').toLocaleLowerCase('pt-BR').normalize('NFD').replace(/[\u0300-\u036f]/g,'')}
 function filterRows(){const q=normalize(search?.value),tv=type?.value||'all',av=action?.value||'all';filtered=rows.filter(r=>{const sr=normalize(r.dataset.search);const matchQ=!q||sr.includes(q),matchT=tv==='all'||r.dataset.type===tv,matchA=av==='all'||(av==='action'?r.dataset.action==='1':r.dataset.action==='0');return matchQ&&matchT&&matchA});page=1;render()}
 function render(){rows.forEach(r=>r.hidden=true);const pages=Math.max(1,Math.ceil(filtered.length/perPage));page=Math.min(page,pages);const start=(page-1)*perPage,end=Math.min(start+perPage,filtered.length);filtered.slice(start,end).forEach(r=>r.hidden=false);if(count)count.textContent=filtered.length+' item(ns)';if(range)range.textContent=filtered.length?'Exibindo '+(start+1)+' a '+end+' de '+filtered.length:'Exibindo 0 de 0';if(!pager)return;pager.innerHTML='';if(pages<=1)return;const button=(label,p,disabled=false,active=false)=>{const b=document.createElement('button');b.type='button';b.textContent=label;b.disabled=disabled;b.classList.toggle('active',active);b.onclick=()=>{page=p;render();};pager.appendChild(b)};button('‹',Math.max(1,page-1),page===1);let set=pages<=7?[...Array(pages)].map((_,i)=>i+1):[1,page-2,page-1,page,page+1,page+2,pages].filter((v,i,a)=>v>=1&&v<=pages&&a.indexOf(v)===i).sort((a,b)=>a-b);let prev=0;set.forEach(p=>{if(prev&&p-prev>1){const s=document.createElement('span');s.textContent='…';pager.appendChild(s)}button(String(p),p,false,p===page);prev=p});button('›',Math.min(pages,page+1),page===pages)}
 search?.addEventListener('input',filterRows);type?.addEventListener('change',filterRows);action?.addEventListener('change',filterRows);filterRows();
})();
</script>
