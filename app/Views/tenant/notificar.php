<?php
use App\Core\Csrf;
use App\Core\Format;
$h=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$slug=$tenant['slug'];
?>
<style>
.ops-backdrop{position:fixed;inset:0;background:#071b32a6;z-index:1100;display:flex;align-items:center;justify-content:center;padding:20px}
.ops-modal{background:#fff;border-radius:18px;width:min(1100px,100%);max-height:94vh;overflow:auto;padding:22px;color:#163451;box-shadow:0 24px 75px #0004}
.ops-top{display:flex;justify-content:space-between;align-items:center;gap:12px}.ops-form{display:grid;gap:12px}.ops-actions{display:flex;gap:12px;justify-content:flex-end;flex-wrap:wrap}.ops-table{width:100%;border-collapse:collapse;font-size:13px}.ops-table td,.ops-table th{border-bottom:1px solid #dfebf5;padding:10px;text-align:left;vertical-align:top}.ops-table th{position:sticky;top:0;background:#eaf1fa}.ops-list{max-height:45vh;overflow:auto;border:1px solid #d8e4ee;border-radius:12px}.ops-input{width:100%;border:1px solid #bed0e1;border-radius:8px;padding:10px}.ops-btn{padding:11px 20px;border-radius:9px;background:#124c83;color:#fff;border:0;cursor:pointer;font-weight:700}.ops-btn:disabled{background:#9ab0c5;cursor:not-allowed}.ops-soft{background:#e8edf3;color:#153252}.ops-due{color:#ad2525;font-weight:bold}.ops-note{font-size:12px;color:#64748b}.ops-warn{background:#fff4e6;border-radius:9px;padding:12px}
</style>
<div class="ops-backdrop"><div class="ops-modal" role="dialog" aria-modal="true" aria-label="Notificar e cobrar documentos">
<div class="ops-top"><h2>✉ Notificar / Cobrar documentos pendentes</h2><a class="ops-btn ops-soft" href="/<?=$h($slug)?>/dashboard">✕ Fechar</a></div>
<p class="ops-note">Escopo: <strong><?=$h($escopo??'ETAPA')?></strong>. Os itens sem prazo permanecem <b>Pendentes</b>. Somente data-limite calculada e vencida gera a classificação <b>Atrasado</b>. Nenhum envio é realizado sem confirmação.</p>
<?php if(!empty($erro)):?><p class="ops-warn"><?=$h($erro)?></p><?php endif;?>
<?php if(!empty($ok)):?><p style="color:#087344"><?=$h($ok)?></p><?php endif;?>
<form class="ops-form" action="/<?=$h($slug)?>/notificar/enviar" method="post" id="opsForm">
<input type="hidden" name="escopo" value="<?=$h($escopo??'ETAPA')?>"><input type="hidden" name="item" value="<?=(int)($escopoId??0)?>">
<input type="hidden" name="_token" value="<?=$h(Csrf::token())?>">
<label><b>Selecionar grupo</b>
<select class="ops-input" name="filtro" id="opsFilter"><option value="TODOS">Todos os pendentes e atrasados</option><option value="PENDENTE">Somente pendentes (inclusive sem prazo)</option><option value="ATRASADO">Somente atrasados com prazo vencido</option></select></label>
<label><b>Buscar</b><input class="ops-input" id="opsSearch" placeholder="Documento, fase, ato, atividade, secretaria..."></label>
<div><label><input type="checkbox" id="opsAll"> Selecionar todos os itens visíveis</label> <span class="ops-note" id="opsCount"></span></div>
<div class="ops-list"><table class="ops-table"><thead><tr><th>Selecionar</th><th>Exigência documental / vínculo</th><th>Situação</th><th>Destinatários</th></tr></thead><tbody>
<?php foreach($itens as $r):$emails=$r['destinatarios'];$search=mb_strtolower(implode(' ',[$r['documento'],$r['fase'],$r['ato'],$r['atividade'],$r['secretaria'],$r['departamento']]));?>
<tr data-classe="<?=$h($r['classificacao'])?>" data-search="<?=$h($search)?>" data-has-email="<?=$emails?'1':'0'?>">
<td><input class="ops-check" type="checkbox" name="requisito_ids[]" value="<?=(int)$r['requisito_id']?>" <?=$emails?'':'disabled'?>></td>
<td><strong><?=$h($r['documento'])?></strong><div class="ops-note">Fase: <?=$h($r['fase'])?> · Ato: <?=$h($r['ato']?:'—')?><?php if($r['atividade']):?> · Atividade: <?=$h($r['atividade'])?><?php endif;?></div><div class="ops-note"><?=$h($r['orientacao'])?></div></td>
<td><b class="<?=$r['classificacao']==='ATRASADO'?'ops-due':''?>"><?=$h($r['classificacao'])?></b><div class="ops-note"><?=$h($r['estado_documental'])?> · <?=$h($r['data_limite']?:'Sem prazo definido')?></div></td>
<td><?=$emails?$h(implode(', ',$emails)):'<span class="ops-due">Sem e-mail cadastrado</span>'?><div class="ops-note"><?=$h($r['secretaria'])?><?=$r['departamento']?' / '.$h($r['departamento']):''?></div></td>
</tr><?php endforeach;?></tbody></table></div>
<label><b>Mensagem complementar (opcional)</b><textarea class="ops-input" name="mensagem" maxlength="1000" rows="3" placeholder="Solicitamos a regularização dos documentos relacionados..."></textarea></label>
<p class="ops-warn">A plataforma enviará um e-mail individual por documento e destinatário. O envio registra resultado na auditoria de e-mails e evita repetição dentro de 15 minutos. Confira os destinatários antes de prosseguir.</p>
<div class="ops-actions"><label><input type="checkbox" name="confirmacao" value="SIM" id="opsConfirm"> Confirmo a cobrança aos destinatários selecionados</label><button type="submit" id="opsSubmit" class="ops-btn" disabled>Enviar cobranças</button></div>
</form></div></div>
<script>
(()=>{const rows=[...document.querySelectorAll('.ops-table tbody tr')],filter=document.getElementById('opsFilter'),search=document.getElementById('opsSearch'),all=document.getElementById('opsAll'),confirm=document.getElementById('opsConfirm'),submit=document.getElementById('opsSubmit'),count=document.getElementById('opsCount');
function refresh(){let visible=0,selected=0;rows.forEach(r=>{const show=(filter.value==='TODOS'||filter.value===r.dataset.classe)&&r.dataset.search.includes(search.value.trim().toLocaleLowerCase('pt-BR'));r.hidden=!show;const box=r.querySelector('.ops-check');if(!show)box.checked=false;if(show)visible++;if(box.checked)selected++;});count.textContent=selected+' selecionado(s) de '+visible+' visível(is)';submit.disabled=!selected||!confirm.checked;}
filter.onchange=search.oninput=refresh;all.onchange=()=>{rows.forEach(r=>{const box=r.querySelector('.ops-check');if(!r.hidden&&!box.disabled)box.checked=all.checked;});refresh();};confirm.onchange=refresh;rows.forEach(r=>r.querySelector('.ops-check').onchange=refresh);
document.getElementById('opsForm').onsubmit=e=>{refresh();if(submit.disabled||!window.confirm('Enviar agora as cobranças selecionadas?'))e.preventDefault();};refresh();})();
</script>
