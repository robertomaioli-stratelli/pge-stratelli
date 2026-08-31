<?php
use App\Core\Csrf;
use App\Core\Format;

$rows=$rows??[];$total=(int)($total??0);$page=(int)($page??1);$pages=(int)($pages??1);$perPage=(int)($perPage??10);$unread=(int)($unread??0);$types=$types??[];
$status=(string)($_GET['status']??'');$selectedType=(string)($_GET['tipo']??'');$q=(string)($_GET['q']??'');
$typeLabels=['DOCUMENTO_ENVIADO'=>'Documento enviado','CORRECAO'=>'Correção','APROVACAO'=>'Aprovação','PRAZO_PROXIMO'=>'Prazo próximo','PRAZO_VENCIDO'=>'Prazo vencido','FASE_CONCLUIDA'=>'Fase encerrada','FASE_PRONTA'=>'Fase pronta para encerramento','FASE_REABERTA'=>'Fase reaberta','USUARIO_CRIADO'=>'Usuário criado','TERRITORIO_ATIVADO'=>'Inteligência Territorial'];
$start=$total?($page-1)*$perPage+1:0;$end=min($total,$page*$perPage);
$qs=function(array $replace=[]):string{$base=$_GET;foreach($replace as $k=>$v){if($v===null||$v==='')unset($base[$k]);else$base[$k]=$v;}return http_build_query($base);};
?>
<div class="notification-page module-page">
    <div class="module-hero notification-page-hero">
        <div><h1>Notificações</h1><p>Histórico persistente dos acontecimentos que exigem atenção ou acompanhamento.</p></div>
        <div class="notification-page-actions"><span class="notification-unread-summary"><b><?=$unread?></b> não lida(s)</span><?php if($unread>0):?><form method="post" action="/notificacoes/ler-todas"><input type="hidden" name="_token" value="<?=Format::h(Csrf::token())?>"><input type="hidden" name="voltar" value="/notificacoes"><button class="btn" type="submit">✓ Marcar todas como lidas</button></form><?php endif;?></div>
    </div>

    <section class="card notification-filter-card">
        <form method="get" action="/notificacoes" class="notification-filter-form">
            <label class="notification-search-field"><span>Buscar</span><input type="search" name="q" value="<?=Format::h($q)?>" placeholder="Título, mensagem ou município"></label>
            <label><span>Situação</span><select name="status"><option value="">Todas</option><option value="nao_lidas" <?=$status==='nao_lidas'?'selected':''?>>Não lidas</option><option value="lidas" <?=$status==='lidas'?'selected':''?>>Lidas</option></select></label>
            <label><span>Tipo</span><select name="tipo"><option value="">Todos os tipos</option><?php foreach($types as $t):?><option value="<?=Format::h($t)?>" <?=$selectedType===$t?'selected':''?>><?=Format::h($typeLabels[$t]??ucwords(strtolower(str_replace('_',' ',$t))))?></option><?php endforeach;?></select></label>
            <div class="notification-filter-buttons"><button class="btn primary" type="submit">Filtrar</button><a class="btn" href="/notificacoes">Limpar</a></div>
        </form>
    </section>

    <section class="card notification-history-card">
        <div class="notification-history-head"><div><h2>Central de notificações</h2><p>Exibindo <?=$start?> a <?=$end?> de <?=$total?> registro(s).</p></div></div>
        <?php if($rows):?><div class="notification-history-list">
            <?php foreach($rows as $n):$isUnread=empty($n['lida_em']);$label=$typeLabels[$n['tipo']]??ucwords(strtolower(str_replace('_',' ',$n['tipo'])));?>
            <article class="notification-history-item notification-type-<?=Format::h(strtolower((string)$n['tipo']))?> <?=$isUnread?'is-unread':'is-read'?>">
                <a class="notification-history-main" href="/notificacoes/<?=intval($n['id'])?>/abrir">
                    <span class="notification-history-icon"><?=Format::h($n['icone']?:'•')?></span>
                    <div class="notification-history-body"><div class="notification-history-title"><b><?=Format::h($n['titulo'])?></b><span class="notification-type-label"><?=Format::h($label)?></span><?php if($isUnread):?><span class="notification-new-label">NOVA</span><?php endif;?></div><p><?=Format::h($n['mensagem'])?></p><small><?php if(!empty($n['municipio_nome'])):?><?=Format::h($n['municipio_nome'].' - '.$n['municipio_uf'])?> · <?php endif;?><?=Format::h(Format::dateTime($n['criado_em']))?></small></div>
                </a>
                <?php if($isUnread):?><form method="post" action="/notificacoes/<?=intval($n['id'])?>/ler" class="notification-mark-form"><input type="hidden" name="_token" value="<?=Format::h(Csrf::token())?>"><input type="hidden" name="voltar" value="/notificacoes<?=($_SERVER['QUERY_STRING']??'')?'?'.Format::h($_SERVER['QUERY_STRING']):''?>"><button class="mini-link" type="submit">Marcar como lida</button></form><?php else:?><span class="notification-read-label">✓ Lida <?=Format::h(Format::dateTime($n['lida_em']))?></span><?php endif;?>
            </article>
            <?php endforeach;?>
        </div><?php else:?><div class="notification-empty-state"><span>🔔</span><b>Nenhuma notificação encontrada.</b><p>Quando houver novos eventos, eles aparecerão aqui.</p></div><?php endif;?>

        <?php if($pages>1):?><div class="notification-pagination"><a class="mini-link <?=$page<=1?'disabled':''?>" href="/notificacoes?<?=$qs(['pagina'=>max(1,$page-1)])?>">← Anterior</a><span>Página <b><?=$page?></b> de <b><?=$pages?></b></span><a class="mini-link <?=$page>=$pages?'disabled':''?>" href="/notificacoes?<?=$qs(['pagina'=>min($pages,$page+1)])?>">Próxima →</a></div><?php endif;?>
    </section>
</div>
