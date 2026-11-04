<?php
/** Editor de regras de prazo. Requer $ganttFases, $municipioGantt, $ganttRegras. */
use App\Core\Auth;
use App\Core\Csrf;
if (Auth::isPlatformAdmin() && !empty($ganttFases)):
$ganttNodes=[];$ganttPhaseOptions=[];$ganttActOptions=[];
foreach($ganttFases as $pf){
    $ganttPhaseOptions[(int)$pf['id']]=$pf['titulo'];
    $ganttNodes[]=['nivel'=>'FASE','id'=>$pf['id'],'nome'=>$pf['titulo']];
    foreach(($pf['atos']??[]) as $pa){
        $ganttActOptions[(int)$pa['id']]=$pa['titulo'];
        $ganttNodes[]=['nivel'=>'ATO','id'=>$pa['id'],'nome'=>$pa['titulo'],'quantidade'=>$pa['prazo_quantidade']??0,'unidade'=>$pa['prazo_tipo']??'DIAS_CORRIDOS','direcao'=>$pa['prazo_direcao']??'APOS_MARCO'];
        foreach(($pa['atividades']??[]) as $sub){
            $ganttNodes[]=['nivel'=>'ATIVIDADE','id'=>$sub['id'],'nome'=>$sub['titulo']];
        }
    }
}
?>
<details class="pge-deadline-editor">
  <summary>⚙ Regras de prazo — configurar os marcos de cada Fase, Ato e Atividade</summary>
  <p>Escolha a origem do prazo e a quantidade de dias. A entrada do documento formal ativa automaticamente as regras vinculadas a ele. Marcos de conclusão só passam a contar quando o respectivo Ato ou Fase estiver concluído.</p>
  <p><strong>Atenção:</strong> para dias úteis, a versão V32 exclui sábados e domingos, mas <strong>não desconta feriados</strong>. Confira o prazo jurídico antes de utilizá-lo oficialmente.</p>
  <?php foreach($ganttNodes as $node):
    $r=$ganttRegras[$node['nivel'].':'.$node['id']]??[];
    $rOrigin=$r['marco_tipo']??'INICIO_FORMAL';
    $rUnit=$r['unidade']??(in_array($node['unidade']??'', ['DIAS_CORRIDOS','DIAS_UTEIS'],true)?$node['unidade']:'DIAS_CORRIDOS');
    $rDirection=$r['direcao']??(($node['direcao']??'')==='ANTES_MARCO'?'ANTES':'APOS');
  ?>
  <details class="pge-deadline-item">
    <summary><span class="pge-deadline-level"><?=$ganttEsc($node['nivel'])?></span> <?=$ganttEsc($node['nome'])?> <?= $r?'✓ Regra cadastrada':'· Sem regra' ?></summary>
    <form method="post" action="/<?=$ganttEsc($tenant['slug'])?>/cronograma/prazos/regra" class="pge-deadline-form">
      <input type="hidden" name="_token" value="<?=$ganttEsc(Csrf::token())?>">
      <input type="hidden" name="nivel" value="<?=$ganttEsc($node['nivel'])?>">
      <input type="hidden" name="item_id" value="<?=(int)$node['id']?>">
      <label>Marco de referência<select name="marco_tipo">
      <?php foreach(['INICIO_FORMAL'=>'Documento formal de início','DATA_FIXA'=>'Data específica / evento confirmado','CONCLUSAO_ATO'=>'Conclusão de outro Ato','CONCLUSAO_FASE'=>'Conclusão formal de outra Fase'] as $val=>$title):?>
        <option value="<?=$val?>" <?=$rOrigin===$val?'selected':''?>><?=$ganttEsc($title)?></option>
      <?php endforeach;?></select></label>
      <label>Referência (Ato ou Fase)<select name="marco_id"><option value="">Nenhuma</option>
      <optgroup label="Atos"><?php foreach($ganttActOptions as $id=>$name):?><option value="<?=$id?>" <?=((int)($r['marco_id']??0)===$id && $rOrigin==='CONCLUSAO_ATO')?'selected':''?>>Ato <?=$ganttEsc($name)?></option><?php endforeach;?></optgroup>
      <optgroup label="Fases"><?php foreach($ganttPhaseOptions as $id=>$name):?><option value="<?=$id?>" <?=((int)($r['marco_id']??0)===$id && $rOrigin==='CONCLUSAO_FASE')?'selected':''?>>Fase <?=$ganttEsc($name)?></option><?php endforeach;?></optgroup>
      </select></label>
      <label>Data específica<input type="date" name="data_fixa" value="<?=$ganttEsc($r['data_fixa']??'')?>"></label>
      <label>Quantidade de dias<input type="number" min="0" max="3650" name="quantidade" value="<?=$ganttEsc($r['quantidade']??($node['quantidade']??0))?>" required></label>
      <label>Contagem<select name="unidade"><option value="DIAS_CORRIDOS" <?=$rUnit==='DIAS_CORRIDOS'?'selected':''?>>Dias corridos</option><option value="DIAS_UTEIS" <?=$rUnit==='DIAS_UTEIS'?'selected':''?>>Dias úteis (sem feriados)</option></select></label>
      <label>Direção<select name="direcao"><option value="APOS" <?=$rDirection==='APOS'?'selected':''?>>Após o marco</option><option value="ANTES" <?=$rDirection==='ANTES'?'selected':''?>>Antes do marco</option></select></label>
      <button class="btn primary" type="submit">Salvar regra e recalcular</button>
    </form>
  </details>
  <?php endforeach; ?>
</details>
<style>
.pge-deadline-editor{margin:16px 0;padding:16px;border:1px solid #d4e2f2;border-radius:13px;background:#f9fbff}
.pge-deadline-editor>summary,.pge-deadline-item>summary{cursor:pointer;color:#153b66;font-weight:800;list-style:revert}
.pge-deadline-editor p{font-size:12px;color:#5d7185;line-height:1.5}
.pge-deadline-item{margin:8px 0;padding:10px;background:#fff;border:1px solid #e1e9f2;border-radius:10px}
.pge-deadline-item>summary{font-size:12px}.pge-deadline-level{font-weight:900;background:#e8f2fc;color:#1b5890;border-radius:6px;padding:3px 6px;font-size:10px}
.pge-deadline-form{display:grid;grid-template-columns:repeat(auto-fit,minmax(185px,1fr));gap:12px;padding:15px 0 5px}
.pge-deadline-form label{display:flex;flex-direction:column;gap:6px;font-size:11px;font-weight:800;color:#274b73;min-width:0}
.pge-deadline-form input,.pge-deadline-form select{width:100%;min-height:40px;border:1px solid #d0dceb;border-radius:9px;background:#fff;padding:7px;color:#14385f}
.pge-deadline-form button{align-self:end;min-height:40px}
</style>
<?php endif; ?>
