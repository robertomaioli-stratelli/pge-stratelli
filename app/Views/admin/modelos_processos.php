<?php
use App\Core\Csrf;
use App\Core\Format;
$h=static fn($v)=>Format::h((string)($v??''));
$selectedId=(int)($selecionado['id']??0);
$counts=['etapas'=>count($arvore),'fases'=>0,'atos'=>0,'atividades'=>0];
foreach($arvore as $e)foreach($e['fases'] as $f){$counts['fases']++;foreach($f['atos'] as $a){$counts['atos']++;$counts['atividades']+=count($a['atividades']);}}
?>
<div class="module-page">
  <div class="module-hero"><div><h1>Modelos de Processos</h1><p>Estrutura de referência: Processo → Etapa → Fase → Ato → Atividade. Os dados operacionais existentes não são alterados por este módulo.</p></div><span class="module-context">PGE V6 · Catálogo</span></div>
  <?php if($erro):?><section class="card" role="alert" style="border-left:4px solid #bd4040;padding:16px"><?=$h($erro)?></section><?php endif;?>
  <?php if($ok):?><section class="card" role="status" style="border-left:4px solid #21895d;padding:16px"><?=$h($ok)?></section><?php endif;?>
  <section class="card" style="padding:20px;margin-bottom:18px">
    <h2>Selecionar modelo</h2>
    <form method="get" action="/admin/modelos-processos" style="display:flex;flex-wrap:wrap;align-items:end;gap:12px">
      <label style="flex:1;min-width:240px">Modelo<select name="modelo" onchange="this.form.submit()"><option value="">Selecione...</option><?php foreach($modelos as $m):?><option value="<?=$h($m['id'])?>" <?=((int)$m['id']===$selectedId?'selected':'')?>><?=$h($m['codigo'].' — '.$m['nome'].' ('.$m['versao'].')')?></option><?php endforeach;?></select></label>
      <button class="btn primary" type="submit">Abrir modelo</button>
    </form>
    <details style="margin-top:14px"><summary><strong>+ Cadastrar novo modelo de processo</strong></summary>
      <form method="post" action="/admin/modelos-processos/salvar" class="pge-v6-form">
        <input type="hidden" name="_token" value="<?=$h(Csrf::token())?>"><input type="hidden" name="tipo" value="modelo">
        <label>Código<input name="codigo" maxlength="100" required placeholder="EX: PROCESSO-2027"></label><label>Nome<input name="nome" maxlength="255" required></label><label>Versão<input name="versao" maxlength="50" value="1.0" required></label><label>Origem<input name="origem" maxlength="255" value="Cadastro PGE" required></label>
        <button type="submit" class="btn primary">Criar modelo</button>
      </form>
    </details>
  </section>
  <?php if($selecionado):?>
    <section class="card" style="padding:20px;margin-bottom:18px"><h2><?=$h($selecionado['nome'])?></h2><p>Código: <?=$h($selecionado['codigo'])?> · Versão: <?=$h($selecionado['versao'])?></p>
      <p><strong><?=$counts['etapas']?></strong> etapa(s) · <strong><?=$counts['fases']?></strong> fase(s) · <strong><?=$counts['atos']?></strong> ato(s) · <strong><?=$counts['atividades']?></strong> atividade(s)</p>
      <details><summary>+ Adicionar Etapa</summary><?php $tipo='etapa';$parent='modelo_id';$parentId=$selectedId;include __DIR__.'/partials/modelo_form.php';?></details>
    </section>
    <?php foreach($arvore as $etapa):?>
      <section class="card" style="padding:20px;margin-bottom:18px"><h2>Etapa <?=$h($etapa['codigo'])?> — <?=$h($etapa['nome'])?></h2>
        <details><summary>Editar Etapa</summary><?php $tipo='etapa';$parent='modelo_id';$parentId=$selectedId;$registro=$etapa;include __DIR__.'/partials/modelo_form.php';unset($registro);?></details>
        <details><summary>+ Adicionar Fase</summary><?php $tipo='fase';$parent='etapa_id';$parentId=(int)$etapa['id'];include __DIR__.'/partials/modelo_form.php';?></details>
        <?php foreach($etapa['fases'] as $fase):?>
          <article style="border-top:1px solid #cbd5e1;padding:16px 0"><h3>Fase <?=$h($fase['codigo'])?> — <?=$h($fase['nome'])?></h3>
            <details><summary>Editar Fase</summary><?php $tipo='fase';$parent='etapa_id';$parentId=(int)$etapa['id'];$registro=$fase;include __DIR__.'/partials/modelo_form.php';unset($registro);?></details>
            <details><summary>+ Adicionar Ato</summary><?php $tipo='ato';$parent='fase_id';$parentId=(int)$fase['id'];include __DIR__.'/partials/modelo_form.php';?></details>
            <?php foreach($fase['atos'] as $ato):?>
              <div style="margin:12px 0 12px 15px;padding:12px 15px;border-left:3px solid #7894b7;background:var(--surface,#f3f6fa);border-radius:6px">
                <strong>Ato <?=$h($ato['codigo'])?> — <?=$h($ato['nome'])?></strong>
                <?php if($ato['prazo_texto']):?><p style="margin:6px 0">Prazo: <?=$h($ato['prazo_texto'])?></p><?php endif;?>
                <?php if($ato['fundamento_normativo']):?><p style="margin:6px 0">Fundamento: <?=$h($ato['fundamento_normativo'])?></p><?php endif;?>
                <details><summary>Editar Ato</summary><?php $tipo='ato';$parent='fase_id';$parentId=(int)$fase['id'];$registro=$ato;include __DIR__.'/partials/modelo_form.php';unset($registro);?></details>
                <details><summary>+ Adicionar Atividade</summary><?php $tipo='atividade';$parent='ato_id';$parentId=(int)$ato['id'];include __DIR__.'/partials/modelo_form.php';?></details>
                <?php foreach($ato['atividades'] as $atividade):?>
                  <div style="padding:8px 0 6px 15px;border-top:1px solid #d5dce7"><span>↳ Atividade <?=$h($atividade['codigo'])?> — <?=$h($atividade['nome'])?></span>
                  <?php if($atividade['codigo_inferido']):?><small>(numeração técnica inferida)</small><?php endif;?>
                    <details><summary>Editar Atividade</summary><?php $tipo='atividade';$parent='ato_id';$parentId=(int)$ato['id'];$registro=$atividade;include __DIR__.'/partials/modelo_form.php';unset($registro);?></details>
                  </div>
                <?php endforeach;?>
              </div>
            <?php endforeach;?>
          </article>
        <?php endforeach;?>
      </section>
    <?php endforeach;?>
  <?php endif;?>
</div>
<style>
.pge-v6-form{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px;padding:14px 0}
.pge-v6-form label{display:flex;flex-direction:column;gap:5px;font-weight:600}
.pge-v6-form textarea{min-height:68px}
.pge-v6-form input,.pge-v6-form textarea{width:100%;max-width:100%;box-sizing:border-box}
.module-page details{margin:8px 0}.module-page summary{cursor:pointer}
</style>
