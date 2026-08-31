<?php
use App\Core\Format;
$browser=new App\Services\SecurityAuditService();
$queryBase=array_filter([
    'busca'=>$search?:null,
    'categoria'=>$category?:null,
    'severidade'=>$severity?:null,
    'municipio'=>$mid?:null,
    'ordem'=>$sort?:null,
    'direcao'=>$direction?:null,
],static fn($v)=>$v!==null&&$v!=='');

$sortUrl=static function(string $field) use($queryBase,$sort,$direction): string {
    $next=($sort===$field&&$direction==='asc')?'desc':'asc';
    return '/admin/auditoria?'.http_build_query(array_merge($queryBase,[
        'ordem'=>$field,'direcao'=>$next,'pagina'=>1,
    ]));
};
$sortIcon=static function(string $field) use($sort,$direction): string {
    if($sort!==$field) return '↕';
    return $direction==='asc'?'▲':'▼';
};
$rangeStart=$total?((($page-1)*$per)+1):0;
$rangeEnd=$total?min($page*$per,$total):0;
?>
<div class="module-page security-audit-page">
    <div class="module-hero">
        <div><h1>Auditoria e Segurança</h1><p>Eventos técnicos, autenticações, alterações administrativas e acessos sensíveis registrados de forma permanente.</p></div>
        <a class="btn neutral" href="/admin/auditoria/exportar?<?=http_build_query($queryBase)?>">↓ Exportar CSV</a>
    </div>

    <div class="module-kpis-grid">
        <div class="module-kpi"><small>EVENTOS</small><b><?=$summary['total']?></b><span>No filtro atual</span></div>
        <div class="module-kpi warning"><small>FALHAS</small><b><?=$summary['falhas']?></b><span>Operações sem sucesso</span></div>
        <div class="module-kpi alert"><small>ALERTAS</small><b><?=$summary['alertas']?></b><span>Eventos críticos</span></div>
        <div class="module-kpi"><small>LOGINS</small><b><?=$summary['logins']?></b><span>Tentativas e acessos</span></div>
        <div class="module-kpi neutral"><small>DOWNLOADS</small><b><?=$summary['downloads']?></b><span>Acessos a arquivos</span></div>
    </div>

    <section class="card security-audit-card">
        <form class="history-filter security-audit-filter" method="get" action="/admin/auditoria">
            <label class="security-audit-search">Buscar
                <input type="search" name="busca" value="<?=Format::h($search)?>" placeholder="Evento, usuário, e-mail, IP ou rota...">
            </label>
            <label>Categoria
                <select name="categoria"><option value="">Todas</option><?php foreach(['SEGURANCA','ACESSO_DADOS','ADMINISTRACAO','OPERACIONAL'] as$c):?><option <?=$category===$c?'selected':''?>><?=$c?></option><?php endforeach;?></select>
            </label>
            <label>Severidade
                <select name="severidade"><option value="">Todas</option><?php foreach(['INFO','ATENCAO','ALERTA'] as$s):?><option <?=$severity===$s?'selected':''?>><?=$s?></option><?php endforeach;?></select>
            </label>
            <label>Município
                <select name="municipio"><option value="0">Todos</option><?php foreach($municipios as$m):?><option value="<?=$m['id']?>" <?=$mid===(int)$m['id']?'selected':''?>><?=Format::h($m['nome'].' - '.$m['uf'])?></option><?php endforeach;?></select>
            </label>
            <input type="hidden" name="ordem" value="<?=Format::h($sort)?>">
            <input type="hidden" name="direcao" value="<?=Format::h($direction)?>">
            <button class="btn primary">Filtrar</button>
            <a class="btn neutral" href="/admin/auditoria">Limpar</a>
        </form>

        <div class="security-audit-grid-meta">
            <span>Exibindo <b><?=$rangeStart?></b> a <b><?=$rangeEnd?></b> de <b><?=$total?></b> evento(s)</span>
            <span>10 registros por página · Clique no cabeçalho para ordenar</span>
        </div>

        <div class="table-scroll security-audit-table-wrap">
            <table class="production-table security-audit-table sortable-security-audit-table">
                <thead><tr>
                    <?php foreach([
                        'data'=>'Data / Hora','evento'=>'Evento','usuario'=>'Usuário','municipio'=>'Município',
                        'origem'=>'Origem','rota'=>'Rota','classificacao'=>'Classificação','detalhes'=>'Detalhes'
                    ] as$field=>$label):?>
                    <th><a class="security-audit-sort <?=$sort===$field?'active':''?>" href="<?=Format::h($sortUrl($field))?>"><span><?=$label?></span><i><?=$sortIcon($field)?></i></a></th>
                    <?php endforeach;?>
                </tr></thead>
                <tbody>
                <?php if($items):foreach($items as$a):?><tr>
                    <td><?=Format::h(Format::dateTime($a['criado_em']))?></td>
                    <td><b><?=Format::h($a['evento'])?></b><br><small><?=$a['sucesso']?'✓ Sucesso':'× Falha'?></small></td>
                    <td><b><?=Format::h($a['usuario_nome']?:'Não autenticado')?></b><br><small><?=Format::h($a['usuario_email']?:'—')?></small></td>
                    <td><?=Format::h($a['municipio_nome']?($a['municipio_nome'].' - '.$a['municipio_uf']):'Plataforma')?></td>
                    <td><b><?=Format::h($a['ip']?:'—')?></b><br><small><?=Format::h($browser->browser((string)$a['user_agent']))?></small></td>
                    <td><code><?=Format::h(($a['metodo']?:'GET').' '.($a['rota']?:'—'))?></code></td>
                    <td><span class="security-badge cat-<?=strtolower($a['categoria'])?>"><?=Format::h($a['categoria'])?></span><br><span class="security-severity sev-<?=strtolower($a['severidade'])?>"><?=Format::h($a['severidade'])?></span></td>
                    <td><?=Format::h($a['detalhes']?:'—')?><?php if($a['contexto_json']):?><details><summary>Contexto técnico</summary><code><?=Format::h($a['contexto_json'])?></code></details><?php endif;?></td>
                </tr><?php endforeach;else:?><tr><td colspan="8"><div class="empty-state">Nenhum evento encontrado.</div></td></tr><?php endif;?>
                </tbody>
            </table>
        </div>

        <div class="history-pagination security-audit-pagination">
            <div class="history-pagination-info"><?=$total?'Exibindo '.$rangeStart.' a '.$rangeEnd.' de '.$total.' evento(s) · Página '.$page.' de '.$pages:'Nenhum evento'?></div>
            <nav class="history-pagination-nav">
                <?php if($page>1):?><a class="history-page-link" href="/admin/auditoria?<?=http_build_query(array_merge($queryBase,['pagina'=>$page-1]))?>">‹ Anterior</a><?php endif;?>
                <?php
                $from=max(1,$page-2);$to=min($pages,$page+2);
                if($from>1):?><a class="history-page-link" href="/admin/auditoria?<?=http_build_query(array_merge($queryBase,['pagina'=>1]))?>">1</a><?php if($from>2):?><span class="history-page-ellipsis">…</span><?php endif;?><?php endif;?>
                <?php for($p=$from;$p<=$to;$p++):?>
                    <?php if($p===$page):?><span class="history-page-current"><?=$p?></span><?php else:?><a class="history-page-link" href="/admin/auditoria?<?=http_build_query(array_merge($queryBase,['pagina'=>$p]))?>"><?=$p?></a><?php endif;?>
                <?php endfor;?>
                <?php if($to<$pages):?><?php if($to<$pages-1):?><span class="history-page-ellipsis">…</span><?php endif;?><a class="history-page-link" href="/admin/auditoria?<?=http_build_query(array_merge($queryBase,['pagina'=>$pages]))?>"><?=$pages?></a><?php endif;?>
                <?php if($page<$pages):?><a class="history-page-link" href="/admin/auditoria?<?=http_build_query(array_merge($queryBase,['pagina'=>$page+1]))?>">Próxima ›</a><?php endif;?>
            </nav>
        </div>
    </section>
</div>
