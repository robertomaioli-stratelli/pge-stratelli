<?php
/** Recebe $tipo, $parent, $parentId, $selectedId e $registro opcional do template pai. */
$r=$registro??[];
?>
<form method="post" action="/admin/modelos-processos/salvar" class="pge-v6-form">
  <input type="hidden" name="_token" value="<?=$h(Csrf::token())?>">
  <input type="hidden" name="tipo" value="<?=$h($tipo)?>">
  <input type="hidden" name="id" value="<?=$h($r['id']??0)?>">
  <input type="hidden" name="<?=$h($parent)?>" value="<?=$h($parentId)?>">
  <input type="hidden" name="modelo_retorno" value="<?=$h($selectedId)?>">
  <label>Código<input name="codigo" maxlength="60" required value="<?=$h($r['codigo']??'')?>" placeholder="Ex.: 2.5.1"></label>
  <label>Nome<?php if(in_array($tipo,['ato','atividade'],true)):?><textarea name="nome" required><?=$h($r['nome']??'')?></textarea><?php else:?><input name="nome" maxlength="255" required value="<?=$h($r['nome']??'')?>"><?php endif;?></label>
  <label>Ordem<input name="ordem" type="number" min="1" max="100000" required value="<?=$h($r['ordem']??1)?>"></label>
  <?php if($tipo==='ato'):?>
    <label>Prazo textual<textarea name="prazo_texto"><?=$h($r['prazo_texto']??'')?></textarea></label>
    <label>Fundamento normativo<textarea name="fundamento_normativo"><?=$h($r['fundamento_normativo']??'')?></textarea></label>
  <?php endif;?>
  <div style="align-self:end"><button type="submit" class="btn primary"><?=$r?'Salvar alterações':'Cadastrar '.ucfirst($tipo)?></button></div>
</form>
