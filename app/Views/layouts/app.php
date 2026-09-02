<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Format;
use App\Core\Tenant;
use App\Services\InstanceParameterService;
$user=Auth::user();$tenant=Tenant::current();$platform=Auth::isPlatformAdmin();$title=$title??'INPACTA by Stratelli';$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
if(!function_exists('activeLink')){function activeLink(string $needle,string $path): string{return str_contains($path,$needle)?'active':'';}}
$scope=$scope??($platform?'stratelli':(($user['grupo']??'')==='USUARIO'?'secretaria':'municipio'));
$notifications=$notificacoes??[];$notificationCount=$notificacaoContagemAtiva??0;
$instanceParams=[];if($tenant){try{$instanceParams=(new InstanceParameterService())->forMunicipio((int)$tenant['id']);}catch(\Throwable){$instanceParams=[];}}
$instancePrimary=$instanceParams['cor_primaria']??'#082A55';$instanceSecondary=$instanceParams['cor_secundaria']??'#176FDD';$instanceHeaderDecoration=in_array((string)($instanceParams['estilo_decoracao_cabecalho']??'semicirculo'),['semicirculo','nenhuma'],true)?(string)($instanceParams['estilo_decoracao_cabecalho']??'semicirculo'):'semicirculo';
$instanceHeaderText='#FFFFFF';if(preg_match('/^#([0-9A-Fa-f]{6})$/',$instancePrimary,$m)){ $hex=$m[1];$r=hexdec(substr($hex,0,2));$g=hexdec(substr($hex,2,2));$b=hexdec(substr($hex,4,2));$lum=(0.299*$r+0.587*$g+0.114*$b)/255;$instanceHeaderText=$lum>.64?'#082A55':'#FFFFFF';}
$instanceNotificationsEnabled=$platform||!$tenant||!empty($instanceParams['notificacoes_ativas']);
?>
<!doctype html><html lang="pt-br"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>INPACTA by Stratelli | <?=Format::h($title)?></title><meta name="robots" content="noindex,nofollow"><link rel="stylesheet" href="/assets/mvp-base.css?v=4206"><link rel="stylesheet" href="/assets/production.css?v=4206"><link rel="stylesheet" href="/assets/global-search.css?v=4206"><link rel="stylesheet" href="/assets/tenant-theme.css?v=4209"><link rel="stylesheet" href="/assets/municipality-profile.css?v=42010"></head>
<body class="<?=$tenant?'tenant-theme-active tenant-decoration-'.Format::h($instanceHeaderDecoration):''?>" style="--navy:<?=Format::h($instancePrimary)?>;--navy2:<?=Format::h($instanceSecondary)?>;--preview-accent:<?=Format::h($instanceSecondary)?>;--tenant-primary:<?=Format::h($instancePrimary)?>;--tenant-secondary:<?=Format::h($instanceSecondary)?>;--tenant-header-text:<?=Format::h($instanceHeaderText)?>"><div class="app production-app" id="appShell">
<aside class="side" id="menuLateral">
    <div class="brand"><div class="mark">IN</div><div>PGE<br><small>Plataforma de<br>Governança Executiva</small></div></div>
    <div class="user"><div class="avatar"><?=Format::h(strtoupper(substr($user['nome'],0,1)))?></div><div><b><?=Format::h($user['nome'])?></b><br><span><?=Format::h($user['email'])?></span><br><span class="role <?=$platform?'stratelli':($scope==='secretaria'?'secretaria':'')?>"><?=Format::h($platform?'ADMINISTRADOR STRATELLI':($scope==='secretaria'?'SECRETARIA':$user['grupo']))?></span></div></div>
    <nav class="menu">
    <?php if($platform&&!$tenant):?>
        <a class="mi <?=$path==='/admin'?'active':''?>" href="/admin">▦ Visão Macro</a>
        <a class="mi <?=activeLink('/admin/pendencias',$path)?>" href="/admin/pendencias">◆ Central de Pendências</a>
        <a class="mi <?=activeLink('/admin/indicadores-historicos',$path)?>" href="/admin/indicadores-historicos">▥ Indicadores Históricos</a>
        <a class="mi <?=$path==='/notificacoes'?'active':''?>" href="/notificacoes">🔔 Notificações</a>
        <a class="mi <?=activeLink('/admin/municipios',$path)?>" href="/admin/municipios">▥ Municípios</a>
        <a class="mi <?=activeLink('/admin/usuarios',$path)?>" href="/admin/usuarios">◉ Usuários</a>
        <a class="mi <?=activeLink('/admin/auditoria',$path)?>" href="/admin/auditoria">◷ Auditoria e Segurança</a>
        <a class="mi <?=activeLink('/admin/configuracoes',$path)?>" href="/admin/configuracoes">⚙ Configurações</a>
    <?php else:$slug=$tenant['slug']??$user['municipio_slug'];?>
        <?php if($platform):?><a class="mi" href="/admin">← Visão Macro</a><a class="mi" href="/admin/municipios">▥ Municípios</a><?php endif;?>
        <a class="mi <?=activeLink('/dashboard',$path)?>" href="/<?=Format::h($slug)?>/dashboard">▦ Dashboard Situacional</a>
        <a class="mi <?=activeLink('/pendencias',$path)?>" href="/<?=Format::h($slug)?>/pendencias">◆ <?=$platform?'Central de Pendências':'Minha Mesa'?></a>
        <?php if($instanceNotificationsEnabled):?><a class="mi <?=$path==='/notificacoes'?'active':''?>" href="/notificacoes">🔔 Notificações<?php if($notificationCount>0):?> <span class="menu-notification-badge"><?=$notificationCount?></span><?php endif;?></a><?php endif;?>
        <a class="mi <?=activeLink('/workflow',$path)?>" href="/<?=Format::h($slug)?>/workflow">▤ <?=$scope==='secretaria'?'Minhas Entregas':'Workflow de Contratação'?></a>
        <?php if($scope!=='secretaria'):?><a class="mi <?=activeLink('/documentos',$path)?>" href="/<?=Format::h($slug)?>/documentos">▤ Documentos</a><?php endif;?>
        <?php $territorialMenuEnabled=$platform||((int)($tenant['inteligencia_territorial_ativa']??$user['municipio_territorial_ativa']??0)===1);?>
        <?php if($territorialMenuEnabled):?><a class="mi <?=activeLink('/territorio',$path)?>" href="/<?=Format::h($slug)?>/territorio">⌖ Inteligência Territorial</a><?php endif;?>
        <a class="mi <?=activeLink('/relatorios',$path)?>" href="/<?=Format::h($slug)?>/relatorios">▥ <?=$scope==='secretaria'?'Meus Relatórios':'Relatórios'?></a>
        <a class="mi <?=activeLink('/historico',$path)?>" href="/<?=Format::h($slug)?>/historico">◷ <?=$scope==='secretaria'?'Meu Histórico':'Histórico'?></a>
        <?php if($platform):?><a class="mi <?=activeLink('/configuracoes',$path)?>" href="/<?=Format::h($slug)?>/configuracoes">⚙ Configurações</a><?php endif;?>
    <?php endif;?>
        <a class="mi <?=activeLink('/conta/senha',$path)?>" href="/conta/senha">🔒 Alterar senha</a>
    <form method="post" action="/logout" class="logout-form"><input type="hidden" name="_token" value="<?=Format::h(Csrf::token())?>"><button class="mi logout-button" type="submit">↪ Sair</button></form>
    </nav>
    <div class="profilebox <?=$platform?'stratelli':($scope==='secretaria'?'secretaria':'')?>"><small>Perfil ativo</small><b><?=Format::h($platform?'STRATELLI':($scope==='secretaria'?'SECRETARIA':$user['grupo']))?></b><?php if($tenant):?><small><?=Format::h($tenant['nome'].' - '.$tenant['uf'])?></small><?php endif;?><?php if($scope==='secretaria'):?><small><?=Format::h(($user['secretaria_sigla']?:'').' '.($user['secretaria_nome']??''))?></small><?php if(!empty($user['departamento_nome'])):?><small><?=Format::h(($user['departamento_sigla']?:'').' '.($user['departamento_nome']??''))?></small><?php endif;?><?php endif;?></div>
</aside>
<main class="main">
<header class="top app-header production-header">
    <div class="production-header-title"><strong><?=Format::h($title)?></strong><?php if($tenant):?><span class="tenant-pill">▦ <?=Format::h($tenant['nome'].' - '.$tenant['uf'])?></span><?php endif;?></div>
    <div class="production-header-actions">
        <div class="inp-global-search" id="globalSearchHeader">
            <button class="header-action-button inp-global-trigger" type="button" id="globalSearchToggle" aria-expanded="false" aria-controls="globalSearchPanel" title="Busca Global (Ctrl+K)">
                <span class="inp-global-trigger-icon">⌕</span>
                <span class="inp-global-trigger-text"><b>Busca Global</b><small>Pesquisar na plataforma</small></span>
                <kbd>Ctrl K</kbd>
            </button>
            <div class="inp-global-panel" id="globalSearchPanel" hidden>
                <header class="inp-global-panel-head">
                    <div class="inp-global-panel-brand"><span>⌕</span><div><small>PESQUISA INTEGRADA</small><b>Busca Global</b><p>Encontre registros em múltiplas áreas da plataforma.</p></div></div>
                    <a href="/busca">Busca completa <i>→</i></a>
                </header>
                <form class="inp-global-panel-form" action="/busca" method="get" id="globalSearchForm">
                    <span>⌕</span>
                    <input type="search" name="q" id="globalSearchInput" placeholder="Digite ETP, documento, fase, usuário, município..." autocomplete="off" aria-label="Busca Global">
                    <button type="submit">Pesquisar</button>
                </form>
                <div class="inp-global-panel-body" id="globalSearchSuggestions">
                    <div class="inp-global-hint"><div>⌕</div><b>Comece sua pesquisa</b><span>Digite pelo menos 2 caracteres. A busca consulta várias áreas ao mesmo tempo.</span><section><em>ETP</em><em>Obras</em><em>Contrato</em><em>Maringá</em></section></div>
                </div>
                <footer class="inp-global-panel-footer"><span>✓ Escopo de acesso aplicado automaticamente</span><a href="/busca">Abrir página de busca</a></footer>
            </div>
        </div>
        <button class="header-action-button" type="button" id="collapseMenuButton" onclick="toggleMenu()">▣ Recolher menu</button>
        <button class="header-action-button" type="button" onclick="window.print()">▣ Imprimir</button>
        <?php if($tenant):?><?php $municipalityHeaderHref=$platform?'/admin/municipios/'.(int)$tenant['id']:'/'.rawurlencode((string)$tenant['slug']).'/municipio';$municipalityHeaderLabel=$platform?'Editar cadastro do município':'Consultar dados do município';?><a class="header-info-chip municipality-header-chip municipality-header-link" href="<?=Format::h($municipalityHeaderHref)?>" title="<?=Format::h($municipalityHeaderLabel)?>"><?php if(!empty($tenant['brasao_path'])):?><img class="header-municipality-coat" src="/media/municipios/<?=$tenant['id']?>/brasao?v=<?=substr(sha1((string)$tenant['brasao_path']),0,12)?>" loading="eager" decoding="async" alt="Brasão de <?=Format::h($tenant['nome'])?>"><?php else:?><span class="header-municipality-coat placeholder">▥</span><?php endif;?><span class="municipality-header-copy"><small>Município</small><b><?=Format::h($tenant['nome'].' - '.$tenant['uf'])?></b></span><span class="municipality-header-access"><?=$platform?'✎':'›'?></span></a><?php endif;?>
        <?php if($scope==='secretaria'):?><span class="header-info-chip">▤ Secretaria <b><?=Format::h($user['secretaria_sigla']?:$user['secretaria_nome'])?></b></span><?php endif;?>
        <?php if($instanceNotificationsEnabled):?><div class="notification-center"><button class="notification-button" id="notificationToggle" type="button" onclick="toggleNotifications(event)" aria-expanded="false" aria-label="Notificações">🔔<?php if($notificationCount>0):?><span class="notification-count"><?=$notificationCount?></span><?php endif;?></button>
            <div class="notification-panel" id="notificationPanel" hidden>
                <div class="notification-panel-head"><div><h3>Notificações</h3><p><?=$notificationCount?> não lida(s)</p></div><button type="button" onclick="closeNotifications()">×</button></div>
                <div class="notification-list"><?php if($notifications):foreach(array_slice($notifications,0,8) as $n):?><a class="notification-item notification-type-<?=Format::h(strtolower((string)$n['tipo']))?> <?=empty($n['lida_em'])?'is-unread':'is-read'?>" href="/notificacoes/<?=intval($n['id'])?>/abrir"><span class="notification-item-icon"><?=Format::h($n['icone']?:'•')?></span><div><b><?=Format::h($n['titulo'])?></b><p><?=Format::h($n['mensagem'])?></p><small><?php if(!empty($n['municipio_nome'])):?><?=Format::h($n['municipio_nome'].' - '.$n['municipio_uf'])?> · <?php endif;?><?=Format::h(Format::dateTime($n['criado_em']))?></small></div><?php if(empty($n['lida_em'])):?><i class="notification-unread-dot"></i><?php endif;?></a><?php endforeach;else:?><div class="notification-empty">Nenhuma notificação registrada.</div><?php endif;?></div>
                <div class="notification-panel-actions"><a class="mini-link primary" href="/notificacoes">Ver todas</a><?php if($notificationCount>0):?><form method="post" action="/notificacoes/ler-todas"><input type="hidden" name="_token" value="<?=Format::h(Csrf::token())?>"><input type="hidden" name="voltar" value="<?=$path?>"><button class="mini-link" type="submit">Marcar todas como lidas</button></form><?php endif;?></div>
            </div>
        </div><?php endif;?>
        <form method="post" action="/logout" class="header-logout-form"><input type="hidden" name="_token" value="<?=Format::h(Csrf::token())?>"><button class="header-action-button header-logout-button" type="submit">↪ Sair</button></form>
    </div>
</header>
<div class="content production-content"><?php if(!empty($erro)):?><div class="flash error"><?=Format::h($erro)?></div><?php endif;?><?php if(!empty($ok)):?><div class="flash ok"><?=Format::h($ok)?></div><?php endif;?><?=$content?></div></main>
</div>
<script>
function toggleMenu(){
    const app=document.getElementById('appShell');
    if(!app)return;
    app.classList.toggle('menu-collapsed');
    try{localStorage.setItem('inpactaMenuRecolhido',app.classList.contains('menu-collapsed')?'1':'0')}catch(e){}
}
function toggleNotifications(e){
    if(e)e.stopPropagation();
    const p=document.getElementById('notificationPanel');
    const b=document.getElementById('notificationToggle');
    if(!p||!b)return;
    const open=p.hidden;
    p.hidden=!open;
    b.setAttribute('aria-expanded',open?'true':'false');
}
function closeNotifications(){
    const p=document.getElementById('notificationPanel');
    if(p)p.hidden=true;
}
function openGlobalSearch(){
    const panel=document.getElementById('globalSearchPanel');
    const button=document.getElementById('globalSearchToggle');
    const input=document.getElementById('globalSearchInput');
    if(!panel||!button)return;
    panel.hidden=false;
    button.setAttribute('aria-expanded','true');
    setTimeout(()=>input?.focus(),20);
}
function closeGlobalSearch(){
    const panel=document.getElementById('globalSearchPanel');
    const button=document.getElementById('globalSearchToggle');
    if(panel)panel.hidden=true;
    if(button)button.setAttribute('aria-expanded','false');
}
function toggleGlobalSearch(e){
    if(e)e.stopPropagation();
    const panel=document.getElementById('globalSearchPanel');
    if(!panel)return;
    panel.hidden?openGlobalSearch():closeGlobalSearch();
}
function renderGlobalSearchSuggestions(data){
    const box=document.getElementById('globalSearchSuggestions');
    if(!box)return;
    box.innerHTML='';
    const query=String(data?.query||'').trim();
    if(query.length<2){
        const hint=document.createElement('div');
        hint.className='inp-global-hint';
        hint.innerHTML='<div>⌕</div><b>Comece sua pesquisa</b><span>Digite pelo menos 2 caracteres. A busca consulta várias áreas ao mesmo tempo.</span><section><em>ETP</em><em>Obras</em><em>Contrato</em><em>Maringá</em></section>';
        box.appendChild(hint);
        return;
    }
    if(!Array.isArray(data.groups)||!data.groups.length){
        const empty=document.createElement('div');
        empty.className='inp-global-empty';
        const icon=document.createElement('div');icon.textContent='∅';
        const title=document.createElement('b');title.textContent='Nenhum resultado encontrado';
        const msg=document.createElement('span');msg.textContent='Tente uma sigla, parte do nome, documento, fase ou município.';
        const q=document.createElement('small');q.textContent='Pesquisa: “'+query+'”';
        empty.append(icon,title,msg,q);box.appendChild(empty);
        return;
    }
    data.groups.forEach(group=>{
        const section=document.createElement('section');
        section.className='inp-global-group';
        const head=document.createElement('div');
        head.className='inp-global-group-head';
        const left=document.createElement('span');
        const icon=document.createElement('i');icon.textContent=group.icon||'•';
        const label=document.createTextNode(String(group.label||'Resultados'));
        left.append(icon,label);
        const count=document.createElement('b');count.textContent=group.count;
        head.append(left,count);section.appendChild(head);
        group.items.forEach(item=>{
            const a=document.createElement('a');a.className='inp-global-item';a.href=item.url;
            const main=document.createElement('div');main.className='inp-global-item-main';
            const top=document.createElement('div');top.className='inp-global-item-top';
            const title=document.createElement('strong');title.textContent=item.title;
            const badge=document.createElement('em');badge.textContent=item.badge||group.label;
            top.append(title,badge);
            const sub=document.createElement('span');sub.textContent=item.subtitle;
            const meta=document.createElement('small');meta.textContent=item.meta||'';
            main.append(top,sub,meta);
            const ctx=document.createElement('small');ctx.className='inp-global-item-context';ctx.textContent=item.municipio;
            a.append(main,ctx);section.appendChild(a);
        });
        box.appendChild(section);
    });
    const all=document.createElement('a');all.className='inp-global-view-all';all.href='/busca?q='+encodeURIComponent(query);
    const prefix=document.createTextNode('Ver todos os resultados para ');
    const bold=document.createElement('b');bold.textContent='“'+query+'”';
    const suffix=document.createElement('span');suffix.textContent='abrir página completa →';
    all.append(prefix,bold,suffix);box.appendChild(all);
}
let globalSearchTimer=null;
let globalSearchAbort=null;

document.addEventListener('DOMContentLoaded',()=>{
    const app=document.getElementById('appShell');
    try{if(localStorage.getItem('inpactaMenuRecolhido')==='1')app?.classList.add('menu-collapsed')}catch(e){}

    document.getElementById('notificationPanel')?.addEventListener('click',e=>e.stopPropagation());
    document.getElementById('globalSearchPanel')?.addEventListener('click',e=>e.stopPropagation());
    document.getElementById('globalSearchToggle')?.addEventListener('click',toggleGlobalSearch);

    document.getElementById('globalSearchInput')?.addEventListener('input',e=>{
        const q=String(e.target.value||'').trim();
        clearTimeout(globalSearchTimer);
        if(globalSearchAbort)globalSearchAbort.abort();
        if(q.length<2){
            renderGlobalSearchSuggestions({query:q,groups:[]});
            return;
        }
        globalSearchTimer=setTimeout(async()=>{
            globalSearchAbort=new AbortController();
            try{
                const res=await fetch('/busca/sugestoes?q='+encodeURIComponent(q),{
                    headers:{'Accept':'application/json'},
                    credentials:'same-origin',
                    signal:globalSearchAbort.signal
                });
                if(!res.ok)throw new Error();
                renderGlobalSearchSuggestions(await res.json());
            }catch(err){
                if(err?.name!=='AbortError'){
                    const box=document.getElementById('globalSearchSuggestions');
                    if(box)box.innerHTML='<div class="inp-global-empty"><div>!</div><b>Busca temporariamente indisponível</b><span>Tente novamente em instantes.</span></div>';
                }
            }
        },250);
    });

    document.addEventListener('click',()=>{
        closeNotifications();
        closeGlobalSearch();
    });
    document.addEventListener('keydown',e=>{
        if((e.ctrlKey||e.metaKey)&&String(e.key).toLowerCase()==='k'){
            e.preventDefault();
            openGlobalSearch();
            return;
        }
        if(e.key==='Escape'){
            closeNotifications();
            closeGlobalSearch();
        }
    });
    document.querySelectorAll('img[src*="/media/municipios/"][src*="/brasao"]').forEach(img=>{
        img.addEventListener('error',()=>{
            img.classList.add('media-load-error');
            img.removeAttribute('src');
            img.alt='Brasão indisponível';
        },{once:true});
    });
});
</script>
</body></html>