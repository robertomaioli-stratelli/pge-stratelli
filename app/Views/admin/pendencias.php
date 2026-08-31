<?php
use App\Core\Format;
$items=$items??[];$stats=$stats??[];
?>
<div class="module-page workdesk-page macro-workdesk">
    <div class="module-hero workdesk-hero">
        <div><h1>Central de Pendências</h1><p>Fila operacional da Stratelli consolidada entre todos os municípios clientes, com validações, prazos e acompanhamentos.</p></div>
        <div class="workdesk-action-total"><small>AÇÕES DA STRATELLI</small><b><?=$actionCount??0?></b><span>em toda a carteira</span></div>
    </div>
    <div class="workdesk-kpis">
        <article class="workdesk-kpi primary"><small>AÇÕES IMEDIATAS</small><b><?=$actionCount??0?></b><span>Exigem atuação da Stratelli</span></article>
        <article class="workdesk-kpi waiting"><small>VALIDAÇÕES</small><b><?=$stats['validation']??0?></b><span>Documentos aguardando análise</span></article>
        <article class="workdesk-kpi pending"><small>ENVIOS STRATELLI</small><b><?=$stats['send']??0?></b><span>Documentos sob nossa responsabilidade</span></article>
        <article class="workdesk-kpi deadline"><small>ENCERRAMENTOS / PRAZOS</small><b><?=($stats['closure']??0)+($stats['deadline']??0)?></b><span>Fases prontas, atenção ou atraso</span></article>
        <article class="workdesk-kpi neutral"><small>ACOMPANHAMENTOS</small><b><?=$stats['monitoring']??0?></b><span>Dependem de ação municipal</span></article>
    </div>
    <section class="card workdesk-list-card">
        <div class="workdesk-list-head"><div><h2>Fila consolidada da carteira</h2><p>Use a busca e os filtros para localizar rapidamente um cliente ou tipo de ação.</p></div><span class="workdesk-result-count" id="workdeskResultCount"><?=count($items)?> item(ns)</span></div>
        <div class="workdesk-toolbar"><label class="workdesk-search"><span>⌕</span><input id="workdeskSearch" type="search" placeholder="Buscar município, documento, fase ou secretaria"></label><select id="workdeskFilter"><option value="all">Todos os tipos</option><option value="validation">Validação</option><option value="send">Envio Stratelli</option><option value="closure">Encerramento formal</option><option value="deadline">Prazo</option><option value="monitoring">Acompanhamento municipal</option></select><select id="workdeskActionFilter"><option value="all">Ações + acompanhamento</option><option value="action">Somente ações da Stratelli</option><option value="tracking">Somente acompanhamento</option></select></div>
        <div class="workdesk-items" id="workdeskItems">
        <?php if($items):foreach($items as $i=>$item):$search=strtolower(trim(($item['client_name']??'').' '.($item['client_uf']??'').' '.($item['title']??'').' '.($item['document']??'').' '.($item['phase']??'').' '.($item['unit']??'').' '.($item['text']??'')));?>
            <article class="workdesk-item <?=Format::h($item['tone'])?>" data-index="<?=$i?>" data-type="<?=Format::h($item['type'])?>" data-action="<?=!empty($item['action'])?'1':'0'?>" data-search="<?=Format::h($search)?>">
                <div class="workdesk-item-icon"><?=Format::h($item['icon'])?></div>
                <div class="workdesk-item-body"><div class="workdesk-item-top"><div><span class="workdesk-type-chip <?=Format::h($item['tone'])?>"><?=!empty($item['action'])?'AÇÃO STRATELLI':'ACOMPANHAMENTO'?></span><h3><?=Format::h($item['title'])?></h3></div><?php if(!empty($item['date'])):?><time><?=Format::h(Format::dateTime($item['date']))?></time><?php endif;?></div><strong class="workdesk-document"><?=Format::h($item['document'])?></strong><div class="workdesk-meta"><span><?=Format::h($item['phase'])?></span><?php if(!empty($item['unit'])):?><span><?=Format::h($item['unit'])?></span><?php endif;?></div><?php if(!empty($item['text'])):?><p><?=Format::h($item['text'])?></p><?php endif;?></div>
                <div class="workdesk-item-action"><a class="mini-link <?=!empty($item['action'])?'primary':''?>" href="<?=Format::h($item['url'])?>"><?=Format::h($item['action_label'])?></a></div>
            </article>
        <?php endforeach;else:?><div class="workdesk-empty"><span>✓</span><b>Carteira sem pendências operacionais</b><p>Nenhuma ação ou acompanhamento relevante foi identificado neste momento.</p></div><?php endif;?>
        </div>
        <div class="workdesk-pagination-bar"><span id="workdeskRange">Exibindo 0 de 0</span><nav id="workdeskPagination" class="workdesk-pagination"></nav></div>
    </section>
</div>
<script>
(function(){const rows=[...document.querySelectorAll('.workdesk-item')],search=document.getElementById('workdeskSearch'),type=document.getElementById('workdeskFilter'),action=document.getElementById('workdeskActionFilter'),count=document.getElementById('workdeskResultCount'),range=document.getElementById('workdeskRange'),pager=document.getElementById('workdeskPagination');const perPage=10;let page=1,filtered=[];function n(v){return(v||'').toLocaleLowerCase('pt-BR').normalize('NFD').replace(/[\u0300-\u036f]/g,'')}function f(){const q=n(search?.value),tv=type?.value||'all',av=action?.value||'all';filtered=rows.filter(r=>(!q||n(r.dataset.search).includes(q))&&(tv==='all'||r.dataset.type===tv)&&(av==='all'||(av==='action'?r.dataset.action==='1':r.dataset.action==='0')));page=1;render()}function render(){rows.forEach(r=>r.hidden=true);const pages=Math.max(1,Math.ceil(filtered.length/perPage));page=Math.min(page,pages);const s=(page-1)*perPage,e=Math.min(s+perPage,filtered.length);filtered.slice(s,e).forEach(r=>r.hidden=false);if(count)count.textContent=filtered.length+' item(ns)';if(range)range.textContent=filtered.length?'Exibindo '+(s+1)+' a '+e+' de '+filtered.length:'Exibindo 0 de 0';if(!pager)return;pager.innerHTML='';if(pages<=1)return;const b=(l,p,d=false,a=false)=>{const x=document.createElement('button');x.type='button';x.textContent=l;x.disabled=d;x.classList.toggle('active',a);x.onclick=()=>{page=p;render()};pager.appendChild(x)};b('‹',Math.max(1,page-1),page===1);let set=pages<=7?[...Array(pages)].map((_,i)=>i+1):[1,page-2,page-1,page,page+1,page+2,pages].filter((v,i,a)=>v>=1&&v<=pages&&a.indexOf(v)===i).sort((a,b)=>a-b),prev=0;set.forEach(p=>{if(prev&&p-prev>1){const sp=document.createElement('span');sp.textContent='…';pager.appendChild(sp)}b(String(p),p,false,p===page);prev=p});b('›',Math.min(pages,page+1),page===pages)}search?.addEventListener('input',f);type?.addEventListener('change',f);action?.addEventListener('change',f);f()})();
</script>
