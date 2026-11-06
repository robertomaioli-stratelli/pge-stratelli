<?php
use App\Core\Format;
$h=static fn($s)=>Format::h((string)($s??''));
$dest='/'.rawurlencode((string)$slug).'/configuracoes/estrutura/salvar';
$allP=[];$allE=[];$allF=[];$allA=[];$allT=[];
foreach(($arvoreOperacional??[]) as $p){$allP[]=$p;foreach($p['etapas'] as $e){$e['_processo']=$p['titulo'];$allE[]=$e;foreach($e['fases'] as $f){$f['_etapa']=$e['nome'];$f['_etapa_id']=$e['id'];$allF[]=$f;foreach($f['atos'] as $a){$a['_fase']=$f['titulo'];$a['_fase_id']=$f['id'];$allA[]=$a;foreach($a['atividades'] as $v){$v['_ato']=$a['titulo'];$v['_ato_id']=$a['id'];$allT[]=$v;}}}}}
$types=['etapa'=>['Etapas',$allE],'fase'=>['Fases',$allF],'ato'=>['Atos',$allA],'atividade'=>['Atividades',$allT]];
$selected=(string)($_GET['nivel']??'etapa');if(!isset($types[$selected])&&$selected!=='arvore')$selected='etapa';
$editId=(int)($_GET['id']??0);$editing=null;
if($selected!=='arvore'){foreach($types[$selected][1] as $item){if((int)$item['id']===$editId){$editing=$item;break;}}}
?>
<section class="qs-head">
 <div><span class="qs-eyebrow">CONFIGURAÇÕES / ESTRUTURA DO PROCESSO</span><h2>Etapas, fases, atos e atividades</h2><p>Cadastre e edite cada nível. Todas as alterações ficam restritas à instância deste município.</p></div>
 <a class="btn secondary" href="/<?=$h($slug)?>/hierarquia">Ver execução</a>
</section>

<div class="qs-metrics"><div><b><?=count($allE)?></b><span>Etapas</span></div><div><b><?=count($allF)?></b><span>Fases</span></div><div><b><?=count($allA)?></b><span>Atos</span></div><div><b><?=count($allT)?></b><span>Atividades</span></div></div>


<nav class="qs-tabs" aria-label="Níveis de cadastro e visualização">
<?php foreach($types as $key=>$info):?><a href="?secao=estrutura&amp;nivel=<?=$key?>" class="<?=$selected===$key?'qs-active':''?>" <?=$selected===$key?'aria-current="page"':''?>><?=$h($info[0])?> <small><?=count($info[1])?></small></a><?php endforeach;?>
<a href="?secao=estrutura&amp;nivel=arvore" class="<?=$selected==='arvore'?'qs-active':''?>" <?=$selected==='arvore'?'aria-current="page"':''?>>Árvore Hierárquica <small><?=count($allE)?></small></a>
</nav>
<?php if($selected==='arvore'):?>
<section class="qs-panel qs-tree-panel" id="arvore-etapas" aria-labelledby="qs-tree-heading">
 <div class="qs-panel-title qs-tree-title"><div><h3 id="qs-tree-heading">Árvore hierárquica das Etapas</h3><p>Consulte todas as Etapas, Fases, Atos e Atividades. Expanda os níveis e use Editar para abrir o cadastro correspondente.</p></div><div class="qs-tree-actions"><button type="button" id="qs-tree-expand">Expandir tudo</button><button type="button" id="qs-tree-collapse">Recolher tudo</button></div></div>
 <div class="qs-tree-search"><label for="qs-tree-filter">Localizar na estrutura</label><input type="search" id="qs-tree-filter" placeholder="Pesquise etapa, fase, ato ou atividade..."></div>
 <div class="qs-tree-content" id="qs-tree-content">
 <?php if(!$allE):?><div class="qs-empty">Nenhuma Etapa cadastrada. Utilize a aba Etapas para iniciar a estrutura.</div><?php endif;?>
 <?php foreach(($arvoreOperacional??[]) as $qsTreeProcess):foreach(($qsTreeProcess['etapas']??[]) as $qsTreeEtapa):?>
 <details class="qs-tree-node qs-tree-etapa" open data-tree-label="<?=$h(mb_strtolower((string)($qsTreeEtapa['nome']??''),'UTF-8'))?>">
 <summary><span class="qs-tree-chevron" aria-hidden="true">▸</span><span class="qs-tree-badge">ETAPA</span><strong><?=$h($qsTreeEtapa['nome']??'Etapa sem nome')?></strong><span class="qs-modalidade-status <?=!empty($qsTreeEtapa['habilitada'])?'is-on':'is-off'?>"><?=!empty($qsTreeEtapa['habilitada'])?'ATIVA':'INATIVA'?></span><span class="qs-tree-count"><?=count($qsTreeEtapa['fases']??[])?> fases</span><a class="qs-tree-edit" href="?secao=estrutura&amp;nivel=etapa&amp;id=<?=(int)$qsTreeEtapa['id']?>">Editar</a></summary>
 <div class="qs-tree-children">
 <?php foreach(($qsTreeEtapa['fases']??[]) as $qsTreeFase):?>
 <details class="qs-tree-node qs-tree-fase" data-tree-label="<?=$h(mb_strtolower((string)($qsTreeFase['titulo']??''),'UTF-8'))?>">
 <summary><span class="qs-tree-chevron" aria-hidden="true">▸</span><span class="qs-tree-badge">FASE</span><strong><?=$h($qsTreeFase['titulo']??'Fase sem título')?></strong><span class="qs-tree-count"><?=count($qsTreeFase['atos']??[])?> atos</span><a class="qs-tree-edit" href="?secao=estrutura&amp;nivel=fase&amp;id=<?=(int)$qsTreeFase['id']?>">Editar</a></summary>
 <div class="qs-tree-children">
 <?php foreach(($qsTreeFase['atos']??[]) as $qsTreeAto):?>
 <details class="qs-tree-node qs-tree-ato" data-tree-label="<?=$h(mb_strtolower((string)(($qsTreeAto['codigo']??'').' '.($qsTreeAto['titulo']??'')),'UTF-8'))?>">
 <summary><span class="qs-tree-chevron" aria-hidden="true">▸</span><span class="qs-tree-badge">ATO</span><strong><?=$h($qsTreeAto['titulo']??'Ato sem título')?></strong><span class="qs-tree-count"><?=count($qsTreeAto['atividades']??[])?> atividades</span><a class="qs-tree-edit" href="?secao=estrutura&amp;nivel=ato&amp;id=<?=(int)$qsTreeAto['id']?>">Editar</a></summary>
 <div class="qs-tree-children">
 <?php foreach(($qsTreeAto['atividades']??[]) as $qsTreeAtividade):?>
 <div class="qs-tree-leaf" data-tree-label="<?=$h(mb_strtolower((string)(($qsTreeAtividade['codigo']??'').' '.($qsTreeAtividade['titulo']??'')),'UTF-8'))?>"><span class="qs-tree-badge">ATIVIDADE</span><span><?=$h($qsTreeAtividade['titulo']??'Atividade sem título')?></span><a class="qs-tree-edit" href="?secao=estrutura&amp;nivel=atividade&amp;id=<?=(int)$qsTreeAtividade['id']?>">Editar</a></div>
 <?php endforeach;?>
 <?php if(empty($qsTreeAto['atividades'])):?><p class="qs-tree-empty">Nenhuma atividade cadastrada neste ato.</p><?php endif;?>
 </div></details>
 <?php endforeach;?>
 <?php if(empty($qsTreeFase['atos'])):?><p class="qs-tree-empty">Nenhum ato cadastrado nesta fase.</p><?php endif;?>
 </div></details>
 <?php endforeach;?>
 <?php if(empty($qsTreeEtapa['fases'])):?><p class="qs-tree-empty">Nenhuma fase cadastrada nesta etapa.</p><?php endif;?>
 </div></details>
 <?php endforeach;endforeach;?>
 </div>
</section>
<?php else:?>
<div class="qs-grid"><section class="qs-panel"><div class="qs-panel-title"><h3><?=$editing?'Editar':'Cadastrar'?> <?=$h(rtrim($types[$selected][0],'s'))?></h3><?php if($editing):?><a href="?secao=estrutura&amp;nivel=<?=$selected?>">+ Novo</a><?php endif;?></div>
<form method="post" action="<?=$h($dest)?>" class="qs-form">
<input type="hidden" name="_token" value="<?=$h($token)?>"><input type="hidden" name="tipo" value="<?=$selected?>"><input type="hidden" name="id" value="<?=$h($editing['id']??0)?>">
<?php if($selected==='etapa'):?>
<p class="qs-note">A Etapa é o primeiro nível de cadastro. Não é necessário cadastrar um Processo.</p>
<label>Nome da etapa / modalidade<input name="nome" value="<?=$h($editing['nome']??'')?>" required maxlength="255" placeholder="ETAPA 5 - CONTRATAÇÃO - PREGÃO"></label>
<?php if($editing):?><label class="qs-modalidade-check"><input type="checkbox" name="habilitada" value="1" <?=!empty($editing['habilitada'])?'checked':''?>> <span><b>Habilitada para operação deste município</b><small>Desmarcar desativa esta modalidade; marcar ativa esta e desativa as demais. Toda a hierarquia fica preservada.</small></span></label><?php else:?><p class="qs-note">A modalidade será criada inicialmente inativa. Após o cadastro, abra Editar e habilite-a.</p><?php endif;?>
<?php elseif($selected==='fase'):?>
<label>Etapa superior<select name="etapa_id" required><option value="">Selecione a etapa</option><?php foreach($allE as $e):?><option value="<?=$h($e['id'])?>" <?=((int)($editing['etapa_execucao_id']??0)===(int)$e['id'])?'selected':''?>><?=$h($e['nome'])?></option><?php endforeach;?></select></label>
<label>Nome da fase<input name="titulo" value="<?=$h($editing['titulo']??'')?>" required maxlength="180"></label>
<div class="qs-inline"><label>Ordem<input name="ordem" type="number" min="1" value="<?=$h($editing['ordem']??count($allF)+1)?>" required></label><label>Código<input name="codigo" maxlength="80" value="<?=$h($editing['codigo']??'')?>" placeholder="1"></label></div>
<label>Responsável<input name="responsavel" maxlength="180" value="<?=$h($editing['responsavel']??'')?>"></label>
<?php elseif($selected==='ato'):?>
<label>Fase superior<select name="fase_id" required><option value="">Selecione a fase</option><?php foreach($allF as $f):?><option value="<?=$h($f['id'])?>" <?=((int)($editing['fase_id']??0)===(int)$f['id'])?'selected':''?>><?=$h($f['_etapa'].' / '.$f['titulo'])?></option><?php endforeach;?></select></label>
<label>Título do ato<input name="titulo" value="<?=$h($editing['titulo']??'')?>" required maxlength="220"></label>
<div class="qs-inline"><label>Ordem<input name="ordem" type="number" min="1" value="<?=$h($editing['ordem']??count($allA)+1)?>" required></label><label>Código<input name="codigo" maxlength="80" value="<?=$h($editing['codigo']??'')?>" placeholder="1.1"></label></div>
<label>Prazo — texto original da planilha<textarea name="prazo_texto" rows="2" maxlength="255" placeholder="Ex.: até 5 dias úteis após a abertura"><?=$h($editing['prazo_referencia']??'')?></textarea></label>
<label>Fundamento normativo / item do edital<textarea name="fundamento_normativo" rows="2" maxlength="255" placeholder="Ex.: Edital; art. 32"><?=$h($editing['fundamento_normativo']??'')?></textarea></label>
<div class="qs-inline"><label>Tipo de prazo<select name="prazo_tipo"><?php foreach(['SEM_PRAZO'=>'Não calculável / não informado','DIAS_UTEIS'=>'Dias úteis','DIAS_CORRIDOS'=>'Dias corridos','DATA_FIXADA'=>'Data fixada','DURANTE_FASE'=>'Durante a fase','IMEDIATO'=>'Imediato','CONFORME_CRONOGRAMA'=>'Conforme cronograma'] as $k=>$v):?><option value="<?=$k?>" <?=($editing['prazo_tipo']??'SEM_PRAZO')===$k?'selected':''?>><?=$h($v)?></option><?php endforeach;?></select></label><label>Quantidade de dias<input type="number" min="0" max="3650" name="prazo_quantidade" value="<?=$h($editing['prazo_quantidade']??'')?>" placeholder="Ex.: 5"></label></div>
<div class="qs-inline"><label>Contagem<select name="prazo_direcao"><?php foreach(['NAO_APLICAVEL'=>'Sem cálculo automático','APOS_MARCO'=>'Após o marco','ANTES_MARCO'=>'Antes do marco'] as $k=>$v):?><option value="<?=$k?>" <?=($editing['prazo_direcao']??'NAO_APLICAVEL')===$k?'selected':''?>><?=$h($v)?></option><?php endforeach;?></select></label><label>Data do marco (evento)<input type="date" name="prazo_marco_data" value="<?=$h($editing['prazo_marco_data']??'')?>"></label></div>
<div class="qs-inline"><label>Início previsto<input type="date" name="inicio_previsto" value="<?=$h($editing['inicio_previsto']??'')?>"></label><label>Término previsto<input type="date" name="fim_previsto" value="<?=$h($editing['fim_previsto']??'')?>"></label></div>
<div class="qs-inline"><label>Início real<input type="date" name="inicio_real" value="<?=$h($editing['inicio_real']??'')?>"></label><label>Término real<input type="date" name="fim_real" value="<?=$h($editing['fim_real']??'')?>"></label></div>
<p class="qs-note">Prazos em dias úteis consideram segunda a sexta, sem descontar feriados. Para prazos compostos ou marcos desconhecidos, use as datas manualmente após validação. Datas previstas permitem exibição no Gantt.</p>
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
<div class="qs-item" data-search="<?=$h(mb_strtolower(($node['codigo']??'').' '.($node['titulo']??$node['nome']??''),'UTF-8'))?>"><div class="qs-item-main"><span class="qs-code"><?=$h($node['codigo']??($node['numero']??''))?></span><div><strong><?=$h($node['titulo']??$node['nome']??'')?></strong><?php if($selected==='etapa'):?><small class="qs-modalidade-status <?=!empty($node['habilitada'])?'is-on':'is-off'?>"><?=!empty($node['habilitada'])?'● ATIVA':'○ INATIVA'?></small><?php endif;?><small><?=$h($node['_etapa']??$node['_fase']??$node['_ato']??'')?></small><?php if($selected==='ato'):?><small><b>Prazo:</b> <?=$h($node['prazo_referencia']??'Não informado')?><?php if(!empty($node['fim_previsto'])):?> · Vencimento: <?=$h($node['fim_previsto'])?><?php endif;?></small><small><b>Fundamento:</b> <?=$h($node['fundamento_normativo']??'Não informado')?></small><?php endif;?></div></div><a class="qs-edit" href="?secao=estrutura&amp;nivel=<?=$selected?>&amp;id=<?=$h($node['id'])?>">Editar</a></div>
<?php endforeach;?>
<?php if(!$types[$selected][1]):?><div class="qs-empty">Nenhum registro cadastrado neste nível.</div><?php endif;?>
</div></section></div>
<?php endif;?>
<style>
.qs-modalidade-check{display:flex!important;flex-direction:row!important;gap:12px;align-items:flex-start;border:1px solid #cfe0f1;background:#f4f9ff;padding:13px;border-radius:12px}.qs-modalidade-check input{width:19px!important;flex:none;margin-top:3px}.qs-modalidade-check span{display:grid;gap:4px}.qs-modalidade-check small{font-weight:400;color:#66788e}.qs-modalidade-status{display:inline-flex!important;width:fit-content;padding:3px 8px;border-radius:99px;font-size:10px!important;font-weight:850;white-space:nowrap}.qs-modalidade-status.is-on{background:#e3f6ea;color:#13783d}.qs-modalidade-status.is-off{background:#edf0f5;color:#627487}


.qs-tree-jump{margin:0 0 14px}.qs-tree-jump a{color:#175184;font-weight:700;text-decoration:none}.qs-tree-panel{margin-bottom:18px}.qs-tree-content{min-height:240px}.qs-tree-title{align-items:flex-start;flex-wrap:wrap}.qs-tree-title p{font-size:.83rem;color:#63758a;margin:5px 0 0;max-width:620px;line-height:1.5}.qs-tree-actions{display:flex;gap:7px;flex-wrap:wrap}.qs-tree-actions button{background:#f1f5f9;border:1px solid #d8e1eb;border-radius:7px;padding:8px 11px;color:#244764;font-weight:650;cursor:pointer}.qs-tree-search{display:flex;align-items:center;gap:15px;margin:0 0 15px}.qs-tree-search label{font-size:.85rem;font-weight:650;color:#435a70}.qs-tree-search input{flex:1;min-width:0;border:1px solid #c9d5e3;border-radius:8px;padding:10px 12px;font:inherit}.qs-tree-content{max-height:680px;overflow:auto;border:1px solid #e5eaf1;border-radius:10px;padding:10px;background:#fafcfe}.qs-tree-node{margin:3px 0}.qs-tree-node>summary{display:flex;align-items:center;gap:10px;list-style:none;padding:10px 12px;border-radius:8px;background:#fff;border:1px solid #e3eaf1;cursor:pointer;min-width:0}.qs-tree-node>summary::-webkit-details-marker{display:none}.qs-tree-node>summary strong{font-size:.87rem;overflow-wrap:anywhere;flex:1}.qs-tree-node[open]>summary{border-color:#b7cee4;background:#f0f6fc}.qs-tree-chevron{font-size:1.1rem;color:#587995;display:inline-block;transition:transform .15s}.qs-tree-node[open]>summary>.qs-tree-chevron{transform:rotate(90deg)}.qs-tree-badge{font-size:.66rem;font-weight:800;letter-spacing:.04em;background:#dcebf9;color:#154b79;padding:4px 6px;border-radius:5px;white-space:nowrap}.qs-tree-fase .qs-tree-badge{background:#e6eef6;color:#375675}.qs-tree-ato .qs-tree-badge{background:#e9f0e9;color:#426747}.qs-tree-leaf .qs-tree-badge{background:#f0e9f8;color:#664a84}.qs-tree-count{font-size:.75rem;color:#6d7d8c;white-space:nowrap}.qs-tree-edit{font-size:.8rem;color:#1761a6;font-weight:700;text-decoration:none;white-space:nowrap}.qs-tree-edit:hover{text-decoration:underline}.qs-tree-children{margin-left:23px;padding:3px 0 4px 13px;border-left:2px solid #d5e1ed}.qs-tree-leaf{display:flex;align-items:center;gap:10px;padding:9px 11px;margin:4px 0;background:#fff;border:1px solid #e6ebf1;border-radius:8px;font-size:.83rem}.qs-tree-leaf>span:nth-child(2){flex:1;overflow-wrap:anywhere}.qs-tree-empty{color:#8490a0;font-size:.78rem;margin:7px 0 12px 10px}.qs-tree-node[hidden],.qs-tree-leaf[hidden]{display:none!important}@media(max-width:650px){.qs-tree-children{margin-left:8px;padding-left:8px}.qs-tree-node>summary{gap:5px;flex-wrap:wrap}.qs-tree-count{display:none}.qs-tree-search{align-items:stretch;flex-direction:column;gap:5px}}

.qs-note{font-size:.84rem;color:#52667c;margin:0 0 7px;line-height:1.5}

.qs-head{display:flex;justify-content:space-between;align-items:center;gap:18px;padding:22px 4px 16px}.qs-head h2{margin:4px 0 6px;font-size:1.45rem}.qs-head p{margin:0;color:#607084;font-size:.93rem}.qs-eyebrow{font-size:.71rem;letter-spacing:.11em;color:#58759a;font-weight:800}.qs-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:10px 0 20px}.qs-metrics>div{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px;display:flex;gap:10px;align-items:baseline}.qs-metrics b{font-size:1.7rem;color:#12375f}.qs-metrics span{color:#65758a;font-size:.86rem}.qs-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}.qs-tabs a{padding:10px 18px;background:#f1f5f9;border-radius:9px;text-decoration:none;color:#304760;font-weight:650}.qs-tabs a.qs-active{color:white;background:#173b63}.qs-tabs small{opacity:.75;margin-left:5px}.qs-grid{display:grid;grid-template-columns:minmax(300px,0.85fr) minmax(340px,1.15fr);gap:16px;align-items:start}.qs-panel{border:1px solid #dce5ef;border-radius:13px;background:#fff;padding:20px;min-width:0}.qs-panel-title{display:flex;align-items:center;justify-content:space-between;gap:10px;border-bottom:1px solid #e5ebf1;padding-bottom:13px;margin-bottom:15px}.qs-panel-title h3{margin:0;font-size:1.06rem}.qs-panel-title span{font-size:.79rem;color:#64748b}.qs-form{display:flex;flex-direction:column;gap:12px}.qs-form label{display:flex;flex-direction:column;gap:6px;font-size:.85rem;font-weight:600;color:#405268}.qs-form input,.qs-form select,.qs-form textarea,.qs-filter input{width:100%;box-sizing:border-box;border:1px solid #c9d5e3;border-radius:8px;padding:10px 11px;font:inherit;background:white;color:#172c42}.qs-form textarea{resize:vertical}.qs-form button{align-self:flex-start;margin-top:5px}.qs-inline{display:grid;grid-template-columns:1fr 2fr;gap:12px}.qs-filter{margin-bottom:11px}.qs-item{display:flex;justify-content:space-between;gap:12px;align-items:center;border-bottom:1px solid #ebeff4;padding:13px 2px}.qs-item-main{display:flex;gap:12px;min-width:0}.qs-code{min-width:50px;font-size:.78rem;font-weight:800;color:#3970a4}.qs-item strong{display:block;font-size:.89rem;overflow-wrap:anywhere}.qs-item small{display:block;color:#7c8797;margin-top:3px;font-size:.78rem}.qs-edit{white-space:nowrap;font-size:.83rem;text-decoration:none;font-weight:700;color:#1761a6}.qs-empty{padding:26px;color:#64748b;text-align:center}.qs-warning{padding:13px;border:1px solid #ead09b;background:#fff8eb;border-radius:9px;color:#87520d}@media(max-width:930px){.qs-grid{grid-template-columns:1fr}.qs-metrics{grid-template-columns:repeat(2,1fr)}.qs-head{align-items:flex-start;flex-direction:column}}
</style>
<script>(function(){const f=document.getElementById('qs-search');if(!f)return;f.addEventListener('input',()=>{const term=f.value.toLocaleLowerCase('pt-BR');document.querySelectorAll('#qs-items .qs-item').forEach(el=>{el.hidden=!el.dataset.search.includes(term);});});})();</script>


<script>
(function(){
 const root=document.getElementById('qs-tree-content');if(!root)return;
 const nodes=[...root.querySelectorAll('details.qs-tree-node')];
 document.getElementById('qs-tree-expand')?.addEventListener('click',()=>nodes.forEach(n=>n.open=true));
 document.getElementById('qs-tree-collapse')?.addEventListener('click',()=>nodes.forEach(n=>n.open=false));
 const filter=document.getElementById('qs-tree-filter');
 filter?.addEventListener('input',()=>{
  const q=filter.value.trim().toLocaleLowerCase('pt-BR');
  const visit=(element)=>{
   const self=(element.dataset.treeLabel||'').includes(q);
   const children=[...element.querySelectorAll(':scope > .qs-tree-children > .qs-tree-node, :scope > .qs-tree-children > .qs-tree-leaf')];
   const descendants=children.map(visit).some(Boolean);
   const visible=!q||self||descendants;
   element.hidden=!visible;
   if(q&&visible)element.open=true;
   return visible;
  };
  [...root.querySelectorAll(':scope > .qs-tree-node')].forEach(visit);
 });
 // Links de edição dentro do summary não devem alternar a expansão.
 root.querySelectorAll('summary a').forEach(a=>a.addEventListener('click',e=>e.stopPropagation()));
})();
</script>
