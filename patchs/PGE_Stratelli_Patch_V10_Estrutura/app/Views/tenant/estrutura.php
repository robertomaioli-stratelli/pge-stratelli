<?php
use App\Core\Format;
$h=static fn($s)=>Format::h((string)($s??''));
$dest='/'.rawurlencode((string)$slug).'/configuracoes/estrutura/salvar';
$allP=[];$allE=[];$allF=[];$allA=[];$allT=[];
foreach(($arvoreOperacional??[]) as $p){$allP[]=$p;foreach($p['etapas'] as $e){$e['_processo']=$p['titulo'];$allE[]=$e;foreach($e['fases'] as $f){$f['_etapa']=$e['nome'];$f['_etapa_id']=$e['id'];$allF[]=$f;foreach($f['atos'] as $a){$a['_fase']=$f['titulo'];$a['_fase_id']=$f['id'];$allA[]=$a;foreach($a['atividades'] as $v){$v['_ato']=$a['titulo'];$v['_ato_id']=$a['id'];$allT[]=$v;}}}}}
$types=['etapa'=>['Etapas',$allE],'fase'=>['Fases',$allF],'ato'=>['Atos',$allA],'atividade'=>['Atividades',$allT]];
$selected=(string)($_GET['nivel']??'etapa');if(!isset($types[$selected]))$selected='etapa';
$editId=(int)($_GET['id']??0);$editing=null;
foreach($types[$selected][1] as $item){if((int)$item['id']===$editId){$editing=$item;break;}}
?>
<section class="qs-head">
 <div><span class="qs-eyebrow">CONFIGURAÇÕES / ESTRUTURA DO PROCESSO</span><h2>Etapas, fases, atos e atividades</h2><p>Cadastre e edite cada nível. Todas as alterações ficam restritas à instância deste município.</p></div>
 <a class="btn secondary" href="/<?=$h($slug)?>/hierarquia">Ver execução</a>
</section>
<?php if(!$allP):?><div class="qs-warning">Nenhum processo operacional encontrado. Execute a migração V10 em homologação para criar o processo inicial.</div><?php endif;?>
<div class="qs-metrics"><div><b><?=count($allE)?></b><span>Etapas</span></div><div><b><?=count($allF)?></b><span>Fases</span></div><div><b><?=count($allA)?></b><span>Atos</span></div><div><b><?=count($allT)?></b><span>Atividades</span></div></div>
<nav class="qs-tabs" aria-label="Níveis de cadastro">
<?php foreach($types as $key=>$info):?><a href="?secao=estrutura&amp;nivel=<?=$key?>" class="<?=$selected===$key?'qs-active':''?>"><?=$h($info[0])?> <small><?=count($info[1])?></small></a><?php endforeach;?>
</nav>
<div class="qs-grid"><section class="qs-panel"><div class="qs-panel-title"><h3><?=$editing?'Editar':'Cadastrar'?> <?=$h(rtrim($types[$selected][0],'s'))?></h3><?php if($editing):?><a href="?secao=estrutura&amp;nivel=<?=$selected?>">+ Novo</a><?php endif;?></div>
<form method="post" action="<?=$h($dest)?>" class="qs-form">
<input type="hidden" name="_token" value="<?=$h($token)?>"><input type="hidden" name="tipo" value="<?=$selected?>"><input type="hidden" name="id" value="<?=$h($editing['id']??0)?>">
<?php if($selected==='etapa'):?>
<label>Processo superior<select name="processo_id" required><option value="">Selecione o processo</option><?php foreach($allP as $p):?><option value="<?=$h($p['id'])?>" <?=((int)($editing['processo_id']??0)===(int)$p['id'])?'selected':''?>><?=$h($p['titulo'])?></option><?php endforeach;?></select></label>
<label>Nome da etapa<input name="nome" value="<?=$h($editing['nome']??'')?>" required maxlength="255"></label>
<?php elseif($selected==='fase'):?>
<label>Etapa superior<select name="etapa_id" required><option value="">Selecione a etapa</option><?php foreach($allE as $e):?><option value="<?=$h($e['id'])?>" <?=((int)($editing['etapa_execucao_id']??0)===(int)$e['id'])?'selected':''?>><?=$h($e['_processo'].' / '.$e['nome'])?></option><?php endforeach;?></select></label>
<label>Nome da fase<input name="titulo" value="<?=$h($editing['titulo']??'')?>" required maxlength="180"></label>
<div class="qs-inline"><label>Ordem<input name="ordem" type="number" min="1" value="<?=$h($editing['ordem']??count($allF)+1)?>" required></label><label>Código<input name="codigo" maxlength="80" value="<?=$h($editing['codigo']??'')?>" placeholder="1"></label></div>
<label>Responsável<input name="responsavel" maxlength="180" value="<?=$h($editing['responsavel']??'')?>"></label>
<?php elseif($selected==='ato'):?>
<label>Fase superior<select name="fase_id" required><option value="">Selecione a fase</option><?php foreach($allF as $f):?><option value="<?=$h($f['id'])?>" <?=((int)($editing['fase_id']??0)===(int)$f['id'])?'selected':''?>><?=$h($f['_etapa'].' / '.$f['titulo'])?></option><?php endforeach;?></select></label>
<label>Título do ato<input name="titulo" value="<?=$h($editing['titulo']??'')?>" required maxlength="220"></label>
<div class="qs-inline"><label>Ordem<input name="ordem" type="number" min="1" value="<?=$h($editing['ordem']??count($allA)+1)?>" required></label><label>Código<input name="codigo" maxlength="80" value="<?=$h($editing['codigo']??'')?>" placeholder="1.1"></label></div>
<label>Prazo / marco de referência<textarea name="prazo_texto" rows="2" maxlength="255"><?=$h($editing['prazo_referencia']??'')?></textarea></label>
<label>Fundamento normativo<textarea name="fundamento_normativo" rows="2" maxlength="255"><?=$h($editing['fundamento_normativo']??'')?></textarea></label>
<label>Responsável<input name="responsavel" maxlength="180" value="<?=$h($editing['responsavel']??'')?>"></label>
<?php else:?>
<label>Ato superior<select name="ato_id" required><option value="">Selecione o ato</option><?php foreach($allA as $a):?><option value="<?=$h($a['id'])?>" <?=((int)($editing['ato_legado_id']??0)===(int)$a['id'])?'selected':''?>><?=$h($a['_fase'].' / '.$a['codigo'].' — '.$a['titulo'])?></option><?php endforeach;?></select></label>
<label>Nome da atividade<input name="titulo" value="<?=$h($editing['titulo']??'')?>" required maxlength="255"></label>
<div class="qs-inline"><label>Ordem<input name="ordem" type="number" min="1" value="<?=$h($editing['ordem']??count($allT)+1)?>" required></label><label>Código<input name="codigo" maxlength="80" value="<?=$h($editing['codigo']??'')?>" placeholder="1.1.1"></label></div>
<label>Responsável<input name="responsavel" maxlength="180" value="<?=$h($editing['responsavel']??'')?>"></label>
<label>Prazo / observação<input name="prazo_texto" maxlength="255" value="<?=$h($editing['prazo_texto']??'')?>"></label>
<?php endif;?>
<button class="btn primary" type="submit"><?=$editing?'Salvar alterações':'Cadastrar '.$h(rtrim($types[$selected][0],'s'))?></button>
</form></section>
<section class="qs-panel qs-list-panel"><div class="qs-panel-title"><h3>Itens cadastrados</h3><span><?=count($types[$selected][1])?> registros</span></div>
<div class="qs-filter"><input id="qs-search" type="search" placeholder="Buscar por código ou descrição..." aria-label="Buscar registros"></div>
<div class="qs-items" id="qs-items">
<?php foreach($types[$selected][1] as $node):?>
<div class="qs-item" data-search="<?=$h(mb_strtolower(($node['codigo']??'').' '.($node['titulo']??$node['nome']??''),'UTF-8'))?>"><div class="qs-item-main"><span class="qs-code"><?=$h($node['codigo']??($node['numero']??''))?></span><div><strong><?=$h($node['titulo']??$node['nome']??'')?></strong><small><?=$h($node['_processo']??$node['_etapa']??$node['_fase']??$node['_ato']??'')?></small></div></div><a class="qs-edit" href="?secao=estrutura&amp;nivel=<?=$selected?>&amp;id=<?=$h($node['id'])?>">Editar</a></div>
<?php endforeach;?>
<?php if(!$types[$selected][1]):?><div class="qs-empty">Nenhum registro cadastrado neste nível.</div><?php endif;?>
</div></section></div>
<style>
.qs-head{display:flex;justify-content:space-between;align-items:center;gap:18px;padding:22px 4px 16px}.qs-head h2{margin:4px 0 6px;font-size:1.45rem}.qs-head p{margin:0;color:#607084;font-size:.93rem}.qs-eyebrow{font-size:.71rem;letter-spacing:.11em;color:#58759a;font-weight:800}.qs-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:10px 0 20px}.qs-metrics>div{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px;display:flex;gap:10px;align-items:baseline}.qs-metrics b{font-size:1.7rem;color:#12375f}.qs-metrics span{color:#65758a;font-size:.86rem}.qs-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}.qs-tabs a{padding:10px 18px;background:#f1f5f9;border-radius:9px;text-decoration:none;color:#304760;font-weight:650}.qs-tabs a.qs-active{color:white;background:#173b63}.qs-tabs small{opacity:.75;margin-left:5px}.qs-grid{display:grid;grid-template-columns:minmax(300px,0.85fr) minmax(340px,1.15fr);gap:16px;align-items:start}.qs-panel{border:1px solid #dce5ef;border-radius:13px;background:#fff;padding:20px;min-width:0}.qs-panel-title{display:flex;align-items:center;justify-content:space-between;gap:10px;border-bottom:1px solid #e5ebf1;padding-bottom:13px;margin-bottom:15px}.qs-panel-title h3{margin:0;font-size:1.06rem}.qs-panel-title span{font-size:.79rem;color:#64748b}.qs-form{display:flex;flex-direction:column;gap:12px}.qs-form label{display:flex;flex-direction:column;gap:6px;font-size:.85rem;font-weight:600;color:#405268}.qs-form input,.qs-form select,.qs-form textarea,.qs-filter input{width:100%;box-sizing:border-box;border:1px solid #c9d5e3;border-radius:8px;padding:10px 11px;font:inherit;background:white;color:#172c42}.qs-form textarea{resize:vertical}.qs-form button{align-self:flex-start;margin-top:5px}.qs-inline{display:grid;grid-template-columns:1fr 2fr;gap:12px}.qs-filter{margin-bottom:11px}.qs-item{display:flex;justify-content:space-between;gap:12px;align-items:center;border-bottom:1px solid #ebeff4;padding:13px 2px}.qs-item-main{display:flex;gap:12px;min-width:0}.qs-code{min-width:50px;font-size:.78rem;font-weight:800;color:#3970a4}.qs-item strong{display:block;font-size:.89rem;overflow-wrap:anywhere}.qs-item small{display:block;color:#7c8797;margin-top:3px;font-size:.78rem}.qs-edit{white-space:nowrap;font-size:.83rem;text-decoration:none;font-weight:700;color:#1761a6}.qs-empty{padding:26px;color:#64748b;text-align:center}.qs-warning{padding:13px;border:1px solid #ead09b;background:#fff8eb;border-radius:9px;color:#87520d}@media(max-width:930px){.qs-grid{grid-template-columns:1fr}.qs-metrics{grid-template-columns:repeat(2,1fr)}.qs-head{align-items:flex-start;flex-direction:column}}
</style>
<script>(function(){const f=document.getElementById('qs-search');if(!f)return;f.addEventListener('input',()=>{const term=f.value.toLocaleLowerCase('pt-BR');document.querySelectorAll('#qs-items .qs-item').forEach(el=>{el.hidden=!el.dataset.search.includes(term);});});})();</script>
