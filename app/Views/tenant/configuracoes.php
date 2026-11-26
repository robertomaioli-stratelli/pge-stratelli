<?php use App\Core\Csrf;use App\Core\Format;$slug=$tenant['slug'];$token=Csrf::token();$parametrosInstancia=$parametrosInstancia??[];?>
<?php if($secao==='parametros'):?><link rel="stylesheet" href="/assets/instance-parameters.css?v=4209"><?php endif;?>
<?php if($secao==='importacao'):?><link rel="stylesheet" href="/assets/structure-import.css?v=42014"><?php endif;?>
<div class="module-page"><div class="module-hero"><div><h1>Configurações da Instância</h1><p>Cadastro hierárquico de processos, etapas, fases, atos e atividades, documentos e recursos do município.</p></div><span class="module-context">⚙ Estrutura do Processo</span></div>
<nav class="config-tabs"><a class="<?=$secao==='estrutura'?'active':''?>" href="/<?=Format::h($slug)?>/configuracoes?secao=estrutura">Etapas · Fases · Atos · Atividades</a><?php if(\App\Core\Auth::isPlatformAdmin()):?><a class="<?=$secao==='parametros'?'active':''?>" href="/<?=Format::h($slug)?>/configuracoes?secao=parametros">Parâmetros da Instância</a><a class="<?=$secao==='secretarias'?'active':''?>" href="/<?=Format::h($slug)?>/configuracoes?secao=secretarias">Secretarias e Departamentos</a><a class="<?=$secao==='importacao'?'active':''?>" href="/<?=Format::h($slug)?>/configuracoes?secao=importacao">Importação e Backup</a><a class="<?=$secao==='tipos'?'active':''?>" href="/<?=Format::h($slug)?>/configuracoes?secao=tipos">Tipos de documento</a><a class="<?=$secao==='documentos'?'active':''?>" href="/<?=Format::h($slug)?>/configuracoes?secao=documentos">Documentos e Modelos</a><a href="/<?=Format::h($slug)?>/territorio?modo=configuracao">Dados Territoriais</a><?php endif;?></nav>

<?php if($secao==='estrutura'):?>
<?php require __DIR__.'/estrutura.php';?>
<?php elseif($secao==='parametros'):$p=$parametrosInstancia;?>
<section class="instance-params-hero">
    <div>
        <span class="instance-params-eyebrow">CENTRAL DE PARÂMETROS</span>
        <h2>Parâmetros da Instância</h2>
        <p>Centralize as regras operacionais de <?=Format::h($tenant['nome'])?> sem depender de valores fixos no código.</p>
    </div>
    <div class="instance-params-hero-status"><small>ETAPA ATUAL</small><b><?=Format::h($p['nome_etapa_atual']??'Etapa 1')?></b><span>Configuração exclusiva da Stratelli</span></div>
</section>

<form class="instance-params-form" method="post" enctype="multipart/form-data" action="/<?=Format::h($slug)?>/configuracoes/parametros/salvar">
<input type="hidden" name="_token" value="<?=Format::h($token)?>">
<div class="instance-params-grid">
    <section class="instance-param-card instance-param-card-wide">
        <div class="instance-param-card-head"><span>◎</span><div><h3>Operação e prazos</h3><p>Regras que impactam diretamente o Workflow, alertas e recebimento de arquivos.</p></div></div>
        <div class="instance-param-fields">
            <label class="instance-param-field"><span>Nome da Etapa atual</span><input name="nome_etapa_atual" maxlength="120" value="<?=Format::h($p['nome_etapa_atual']??'Etapa 1')?>" required><small>Ex.: Etapa 1, Planejamento da Contratação, Implantação.</small></label>
            <label class="instance-param-field"><span>Prazo de alerta</span><div class="instance-param-input-suffix"><input type="number" min="1" max="30" name="prazo_alerta_dias" value="<?=intval($p['prazo_alerta_dias']??3)?>" required><b>dias</b></div><small>Quando restar esta quantidade de dias, a fase entra em atenção.</small></label>
            <label class="instance-param-field"><span>Tamanho máximo por arquivo</span><div class="instance-param-input-suffix"><input type="number" min="1" max="100" name="tamanho_maximo_arquivo_mb" value="<?=intval($p['tamanho_maximo_arquivo_mb']??20)?>" required><b>MB</b></div><small>Também respeita os limites configurados no PHP/servidor.</small></label>
            <label class="instance-param-field span-2"><span>Extensões permitidas globalmente</span><input name="extensoes_permitidas" value="<?=Format::h($p['extensoes_permitidas']??'pdf,doc,docx,xls,xlsx')?>" required><small>Separe por vírgula. O tipo documental continua podendo restringir ainda mais esse conjunto.</small></label>
        </div>
    </section>

    <section class="instance-param-card">
        <div class="instance-param-card-head"><span>◆</span><div><h3>Módulos e comunicação</h3><p>Controle funcionalidades opcionais desta instância.</p></div></div>
        <div class="instance-param-switches">
            <label class="instance-param-switch"><input type="checkbox" name="notificacoes_ativas" value="1" <?=!empty($p['notificacoes_ativas'])?'checked':''?>><span class="switch-ui"></span><div><b>Notificações internas</b><small>Gera novos alertas, aprovações, correções e avisos de prazo.</small></div></label>
            <label class="instance-param-switch"><input type="checkbox" name="inteligencia_territorial_ativa" value="1" <?=!empty($p['inteligencia_territorial_ativa'])?'checked':''?>><span class="switch-ui"></span><div><b>Inteligência Territorial</b><small>Libera o módulo aos usuários municipais. A Stratelli continua configurando mesmo inativo.</small></div></label>
        </div>
    </section>

    <section class="instance-param-card">
        <div class="instance-param-card-head"><span>◷</span><div><h3>Observações e trilha</h3><p>Defina em quais atos uma justificativa textual será obrigatória.</p></div></div>
        <div class="instance-param-switches compact">
            <label class="instance-param-switch"><input type="checkbox" name="observacao_envio_obrigatoria" value="1" <?=!empty($p['observacao_envio_obrigatoria'])?'checked':''?>><span class="switch-ui"></span><div><b>Envio / reenvio de documento</b><small>Exige observação do responsável no upload.</small></div></label>
            <label class="instance-param-switch"><input type="checkbox" name="observacao_aprovacao_obrigatoria" value="1" <?=!empty($p['observacao_aprovacao_obrigatoria'])?'checked':''?>><span class="switch-ui"></span><div><b>Aprovação documental</b><small>Exige observação da Stratelli ao aprovar.</small></div></label>
            <label class="instance-param-switch"><input type="checkbox" name="observacao_encerramento_obrigatoria" value="1" <?=!empty($p['observacao_encerramento_obrigatoria'])?'checked':''?>><span class="switch-ui"></span><div><b>Encerramento formal de fase</b><small>Exige registro textual no ato de encerramento.</small></div></label>
            <label class="instance-param-switch"><input type="checkbox" name="observacao_reabertura_obrigatoria" value="1" <?=!empty($p['observacao_reabertura_obrigatoria'])?'checked':''?>><span class="switch-ui"></span><div><b>Reabertura de fase</b><small>Exige justificativa para reabrir uma fase encerrada.</small></div></label>
            <div class="instance-param-fixed-rule"><span>!</span><p><b>Correções continuam sempre justificadas.</b> O motivo da solicitação de correção permanece obrigatório para preservar a rastreabilidade documental.</p></div>
        </div>
    </section>

    <section class="instance-param-card instance-param-card-wide">
        <div class="instance-param-card-head"><span>▦</span><div><h3>Identidade da instância</h3><p>Brasão e cores de destaque utilizados quando esta instância estiver ativa.</p></div></div>
        <div class="instance-identity-grid">
            <div id="instanceIdentityPreview" class="instance-identity-preview is-decoration-<?=Format::h($p['estilo_decoracao_cabecalho']??'semicirculo')?>" style="--instance-primary:<?=Format::h($p['cor_primaria']??'#082A55')?>;--instance-secondary:<?=Format::h($p['cor_secundaria']??'#176FDD')?>">
                <div class="instance-identity-band"></div>
                <div class="instance-identity-content">
                    <?php if(!empty($p['brasao_path'])):?><img src="/media/municipios/<?=$tenant['id']?>/brasao?v=<?=substr(sha1((string)$p['brasao_path']),0,12)?>" alt="Brasão de <?=Format::h($tenant['nome'])?>"><?php else:?><span class="instance-identity-placeholder"><?=Format::h(strtoupper(substr($tenant['nome'],0,1)))?></span><?php endif;?>
                    <div><small>INSTÂNCIA MUNICIPAL</small><b><?=Format::h($tenant['nome'].' - '.$tenant['uf'])?></b><span><?=Format::h($p['nome_etapa_atual']??'Etapa 1')?></span></div>
                </div>
            </div>
            <div class="instance-identity-fields">
                <label class="instance-param-field"><span>Cor primária dos cabeçalhos</span><div class="instance-color-input"><input id="instancePrimaryColor" type="color" name="cor_primaria" value="<?=Format::h($p['cor_primaria']??'#082A55')?>"><code id="instancePrimaryCode"><?=Format::h($p['cor_primaria']??'#082A55')?></code></div><small>Aplicada automaticamente nos cabeçalhos principais desta instância.</small></label>
                <label class="instance-param-field"><span>Cor secundária</span><div class="instance-color-input"><input id="instanceSecondaryColor" type="color" name="cor_secundaria" value="<?=Format::h($p['cor_secundaria']??'#176FDD')?>"><code id="instanceSecondaryCode"><?=Format::h($p['cor_secundaria']??'#176FDD')?></code></div><small>Usada para gradientes, realces e o detalhe decorativo.</small></label>
                <label class="instance-param-field span-2"><span>Estilo decorativo dos cabeçalhos</span><select id="instanceHeaderDecoration" name="estilo_decoracao_cabecalho"><option value="semicirculo" <?=($p['estilo_decoracao_cabecalho']??'semicirculo')==='semicirculo'?'selected':''?>>Semicírculo suave</option><option value="nenhuma" <?=($p['estilo_decoracao_cabecalho']??'')==='nenhuma'?'selected':''?>>Sem decoração</option></select><small>O semicírculo é aplicado no canto direito dos cabeçalhos usando as cores da própria instância.</small></label>
                <label class="instance-param-field span-2"><span>Brasão do município</span><input type="file" name="brasao" accept=".png,.jpg,.jpeg,.webp"><small>PNG, JPG ou WEBP. Máximo de 2 MB.</small></label>
                <?php if(!empty($p['brasao_path'])):?><label class="instance-remove-coat"><input type="checkbox" name="remover_brasao" value="1"> Remover brasão atual</label><?php endif;?>
            </div>
        </div>
    </section>
</div>
<div class="instance-params-savebar"><div><b>Aplicação imediata</b><span>As novas regras passam a valer para esta instância após salvar.</span></div><button class="btn primary" type="submit">Salvar Parâmetros da Instância</button></div>
</form>
<script>
(function(){
    const preview=document.getElementById('instanceIdentityPreview');
    const primary=document.getElementById('instancePrimaryColor');
    const secondary=document.getElementById('instanceSecondaryColor');
    const primaryCode=document.getElementById('instancePrimaryCode');
    const secondaryCode=document.getElementById('instanceSecondaryCode');
    const decoration=document.getElementById('instanceHeaderDecoration');
    if(!preview)return;
    const contrast=(hex)=>{const v=String(hex||'').replace('#','');if(v.length!==6)return '#FFFFFF';const r=parseInt(v.slice(0,2),16),g=parseInt(v.slice(2,4),16),b=parseInt(v.slice(4,6),16);return ((.299*r+.587*g+.114*b)/255)>.64?'#082A55':'#FFFFFF';};
    const sync=()=>{
        if(primary){preview.style.setProperty('--instance-primary',primary.value);preview.style.setProperty('--instance-header-text',contrast(primary.value));if(primaryCode)primaryCode.textContent=primary.value.toUpperCase();}
        if(secondary){preview.style.setProperty('--instance-secondary',secondary.value);if(secondaryCode)secondaryCode.textContent=secondary.value.toUpperCase();}
        preview.classList.remove('is-decoration-semicirculo','is-decoration-nenhuma');
        preview.classList.add('is-decoration-'+(decoration?.value||'semicirculo'));
    };
    primary?.addEventListener('input',sync);secondary?.addEventListener('input',sync);decoration?.addEventListener('change',sync);sync();
})();
</script>

<?php elseif($secao==='fases'):?>
<section class='card' style='padding:18px;margin:15px 0;border-left:4px solid #bd8217'><h2>Cadastro anterior — compatibilidade</h2><p>Esta seção contém registros da estrutura antiga (Fase → Ato). Para novos cadastros e edições estruturais utilize <a href='/<?=Format::h($slug)?>/configuracoes?secao=estrutura'>Etapa → Fase → Ato → Atividade</a>. Os dados históricos permanecem preservados.</p></section>
<?php $atosPorFase=[];foreach(($atividadesFase??[])as$ato)$atosPorFase[(int)$ato['fase_id']][]=$ato;$prazoLabels=['DIAS_CORRIDOS'=>'dias corridos','DIAS_UTEIS'=>'dias úteis','DATA_FIXADA'=>'data fixada','DURANTE_FASE'=>'durante a fase','IMEDIATO'=>'imediato','CONFORME_CRONOGRAMA'=>'conforme cronograma','SEM_PRAZO'=>'sem prazo numérico'];?>
<div class="local-form-tabs" data-local-tabs="fase-ato">
<button type="button" class="local-form-tab active" data-local-target="fase">Nova fase</button>
<button type="button" class="local-form-tab" data-local-target="ato">Novo ato / atividade</button>
</div>
<div class="local-form-panels">
<section class="config-card js-local-tab-panel" data-local-panel="fase"><h2><?=$editarFase?'Editar fase':'Nova fase'?></h2><p>Cadastro anterior para consulta/compatibilidade. Para novos procedimentos, use a aba Etapas · Fases · Atos · Atividades.</p><form method="post" action="/<?=Format::h($slug)?>/configuracoes/fases/salvar"><input type="hidden" name="_token" value="<?=Format::h($token)?>"><input type="hidden" name="id" value="<?=Format::h($editarFase['id']??0)?>"><div class="form-grid"><div class="field"><label>Etapa do processo</label><select name="etapa_execucao_id" required><option value="">Selecione</option><?php foreach(($etapasExecucao??[]) as $etapaExec):?><option value="<?=$etapaExec['id']?>" <?=((int)($editarFase['etapa_execucao_id']??0)===(int)$etapaExec['id'])?'selected':''?>><?=Format::h($etapaExec['processo_titulo'].' / '.$etapaExec['numero'].' — '.$etapaExec['nome'])?></option><?php endforeach;?></select></div><div class="field"><label>Ordem</label><input type="number" min="0" name="ordem" value="<?=Format::h($editarFase['ordem']??count($fasesTodas))?>" required></div><div class="field"><label>Código</label><input name="codigo" value="<?=Format::h($editarFase['codigo']??'')?>" placeholder="DC-01"></div><div class="field"><label>Nome curto / aba</label><input name="aba" value="<?=Format::h($editarFase['aba']??'')?>" required></div><div class="field"><label>Título</label><input name="titulo" value="<?=Format::h($editarFase['titulo']??'')?>" required></div><div class="field span-2"><label>Descrição</label><textarea name="descricao"><?=Format::h($editarFase['descricao']??'')?></textarea></div><div class="field"><label>Responsável</label><input name="responsavel" value="<?=Format::h($editarFase['responsavel']??'')?>"></div><div class="field"><label>Entregável</label><input name="entregavel" value="<?=Format::h($editarFase['entregavel']??'')?>"></div><div class="field"><label>Dia inicial</label><input type="number" min="1" name="dia_inicio" value="<?=Format::h($editarFase['dia_inicio']??1)?>"></div><div class="field"><label>Dia final</label><input type="number" min="1" name="dia_fim" value="<?=Format::h($editarFase['dia_fim']??1)?>"></div><div class="field span-2"><label>Critério de conclusão</label><textarea name="criterio"><?=Format::h($editarFase['criterio']??'')?></textarea></div><label class="check-line span-2"><input type="checkbox" name="exclusivo_stratelli" value="1" <?=!empty($editarFase['exclusivo_stratelli'])?'checked':''?>> Fase exclusiva da Stratelli</label></div><div class="form-actions"><button class="btn primary">Salvar fase</button><?php if($editarFase):?><a class="btn neutral" href="/<?=Format::h($slug)?>/configuracoes?secao=fases">Cancelar</a><?php endif;?></div></form></section>
<section class="config-card act-editor-card js-local-tab-panel" data-local-panel="ato" id="cadastro-ato">
<div class="act-editor-heading"><div><h2><?=$editarAtividade?'Editar Ato':'Novo Ato'?></h2><p>Cadastre o nível operacional da fase, com prazo, fundamento, responsável, entregável e critério próprios.</p></div></div>
<form method="post" action="/<?=Format::h($slug)?>/configuracoes/atividades/salvar">
<input type="hidden" name="_token" value="<?=Format::h($token)?>"><input type="hidden" name="id" value="<?=Format::h($editarAtividade['id']??0)?>">
<div class="form-grid">
<div class="field"><label>Fase</label><select name="fase_id" required><option value="">Selecione a fase</option><?php foreach($fasesTodas as$f):?><option value="<?=$f['id']?>" <?=((int)($editarAtividade['fase_id']??0)===(int)$f['id'])?'selected':''?>>Fase <?=Format::h($f['ordem'])?> — <?=Format::h($f['titulo'])?></option><?php endforeach;?></select></div>
<div class="field"><label>Ordem dentro da fase</label><input type="number" min="1" name="ordem" value="<?=Format::h($editarAtividade['ordem']??1)?>" required></div>
<div class="field"><label>Código</label><input name="codigo" value="<?=Format::h($editarAtividade['codigo']??'')?>" placeholder="DC-02.1"></div>
<div class="field"><label>Nome do Ato</label><input name="titulo" value="<?=Format::h($editarAtividade['titulo']??'')?>" required></div>
<div class="field span-2"><label>Descrição</label><textarea name="descricao"><?=Format::h($editarAtividade['descricao']??'')?></textarea></div>
<div class="field"><label>Responsável</label><input name="responsavel" value="<?=Format::h($editarAtividade['responsavel']??'')?>"></div>
<div class="field"><label>Entregável</label><input name="entregavel" value="<?=Format::h($editarAtividade['entregavel']??'')?>"></div>
<div class="field"><label>Prazo numérico</label><input type="number" min="1" name="prazo_quantidade" value="<?=Format::h($editarAtividade['prazo_quantidade']??'')?>"></div>
<div class="field"><label>Tipo de prazo</label><select name="prazo_tipo"><?php $pt=$editarAtividade['prazo_tipo']??'SEM_PRAZO';foreach($prazoLabels as$k=>$v):?><option value="<?=$k?>" <?=$pt===$k?'selected':''?>><?=Format::h(ucfirst($v))?></option><?php endforeach;?></select></div>
<div class="field span-2"><label>Referência do prazo</label><input name="prazo_referencia" value="<?=Format::h($editarAtividade['prazo_referencia']??'')?>" placeholder="Ex.: após publicação dos pré-selecionados"></div>
<div class="field span-2"><label>Fundamento normativo / item do edital</label><input name="fundamento_normativo" value="<?=Format::h($editarAtividade['fundamento_normativo']??'')?>"></div>
<div class="field span-2"><label>Critério de conclusão</label><textarea name="criterio"><?=Format::h($editarAtividade['criterio']??'')?></textarea></div>
<label class="check-line span-2"><input type="checkbox" name="obrigatoria" value="1" <?=!isset($editarAtividade['obrigatoria'])||$editarAtividade['obrigatoria']?'checked':''?>> Ato obrigatório</label>
</div><div class="form-actions"><button class="btn primary">Salvar Ato</button><?php if($editarAtividade):?><a class="btn neutral" href="/<?=Format::h($slug)?>/configuracoes?secao=fases#cadastro-ato">Cancelar</a><?php endif;?></div>
</form></section>
</div>
<section class="config-card phase-tree-card"><div class="phase-tree-title"><div><h2>Fases e atos cadastrados</h2><p>Os atos abaixo pertencem à respectiva fase e representam o fluxo interno do procedimento.</p></div><span class="phase-tree-total"><?=count($atividadesFase??[])?> ato(s)</span></div>
<div class="phase-tree">
<?php foreach($fasesTodas as$f):$atos=$atosPorFase[(int)$f['id']]??[];?>
<article class="phase-tree-phase <?=!(int)$f['ativo']?'is-inactive':''?>">
<div class="phase-tree-phase-head">
<div class="phase-tree-index"><?=Format::h($f['ordem'])?></div>
<div class="phase-tree-main"><div class="phase-tree-name"><b><?=Format::h($f['titulo'])?></b><span><?=Format::h($f['codigo'])?></span></div><small><?=Format::h($f['descricao'])?></small><div class="phase-tree-meta"><span>Dia <?=Format::h($f['dia_inicio'])?> ao <?=Format::h($f['dia_fim'])?></span><span><?=count($atos)?> ato(s)</span><span><?=Format::h($f['responsavel'])?></span></div></div>
<div class="phase-tree-actions"><span class="status-mini <?=!(int)$f['ativo']?'inactive':''?>"><?=$f['ativo']?'ATIVA':'INATIVA'?></span><a class="btn tiny secondary" href="/<?=Format::h($slug)?>/configuracoes?secao=fases&editar_fase=<?=$f['id']?>">Editar fase</a></div>
</div>
<div class="phase-tree-acts">
<?php if($atos):foreach($atos as$a):?>
<div class="phase-tree-act <?=!(int)$a['ativo']?'is-inactive':''?>">
<div class="phase-tree-connector"></div><div class="phase-tree-act-order"><?=Format::h($f['ordem'])?>.<?=Format::h($a['ordem'])?></div>
<div class="phase-tree-act-body"><div class="phase-tree-act-title"><b><?=Format::h($a['titulo'])?></b><span><?=Format::h($a['codigo'])?></span></div>
<div class="phase-tree-act-meta"><?php if($a['prazo_quantidade']):?><span>Prazo: <?=Format::h($a['prazo_quantidade'])?> <?=Format::h($prazoLabels[$a['prazo_tipo']]??$a['prazo_tipo'])?></span><?php elseif($a['prazo_tipo']!=='SEM_PRAZO'):?><span>Prazo: <?=Format::h($prazoLabels[$a['prazo_tipo']]??$a['prazo_tipo'])?></span><?php endif;?><?php if($a['prazo_referencia']):?><span><?=Format::h($a['prazo_referencia'])?></span><?php endif;?><?php if($a['fundamento_normativo']):?><span><?=Format::h($a['fundamento_normativo'])?></span><?php endif;?></div></div>
<div class="phase-tree-act-actions"><a class="btn tiny secondary" href="/<?=Format::h($slug)?>/configuracoes?secao=fases&editar_atividade=<?=$a['id']?>#cadastro-ato">Editar</a><form method="post" action="/<?=Format::h($slug)?>/configuracoes/atividades/<?=$a['id']?>/alternar"><input type="hidden" name="_token" value="<?=Format::h($token)?>"><button class="btn tiny <?=$a['ativo']?'off':'approve'?>"><?=$a['ativo']?'Desativar':'Ativar'?></button></form></div>
</div>
<?php endforeach;else:?><div class="phase-tree-empty">Nenhum ato cadastrado nesta fase.</div><?php endif;?>

</div>
</article>
<?php endforeach;?>
</div></section>
<?php elseif($secao==='secretarias'):?>
<div class="config-grid"><section class="config-card"><h2><?=$editarSecretaria?'Editar secretaria':'Nova secretaria'?></h2><form method="post" action="/<?=Format::h($slug)?>/configuracoes/secretarias/salvar"><input type="hidden" name="_token" value="<?=Format::h($token)?>"><input type="hidden" name="id" value="<?=Format::h($editarSecretaria['id']??0)?>"><div class="form-grid"><div class="field"><label>Nome</label><input name="nome" required value="<?=Format::h($editarSecretaria['nome']??'')?>"></div><div class="field"><label>Sigla</label><input name="sigla" value="<?=Format::h($editarSecretaria['sigla']??'')?>"></div><div class="field"><label>Cargo do responsável</label><input name="cargo_responsavel" maxlength="180" placeholder="Ex.: Secretário Municipal" value="<?=Format::h($editarSecretaria['cargo_responsavel']??'')?>"></div><div class="field span-2"><label>E-mails dos responsáveis</label><textarea name="emails_responsaveis" rows="3" placeholder="responsavel@orgao.gov.br, substituto@orgao.gov.br"><?=Format::h($editarSecretaria['emails_responsaveis']??'')?></textarea><small>Um ou mais e-mails, separados por vírgula, ponto e vírgula ou linha. Todos serão destinatários quando os alertas forem ativados.</small></div><div class="field span-2"><label>Fases e atos/atividades vinculados</label><small>Selecione a fase e, dentro dela, os atos sob responsabilidade desta secretaria.</small><div class="secretaria-phase-links"><?php foreach($fasesTodas as$f):$fa=$atosPorFase[(int)$f['id']]??[];?><div class="secretaria-phase-link"><label class="secretaria-phase-check"><input type="checkbox" class="js-phase-link" name="fase_ids[]" value="<?=$f['id']?>" <?=in_array((int)$f['id'],$faseIdsSecretaria,true)?'checked':''?>> <b>Fase <?=Format::h($f['ordem'])?> — <?=Format::h($f['titulo'])?></b></label><div class="secretaria-activity-links"><?php foreach($fa as$a):?><label><input type="checkbox" class="js-activity-link" name="atividade_ids[]" value="<?=$a['id']?>" <?=in_array((int)$a['id'],($atividadeIdsSecretaria??[]),true)?'checked':''?>> <?=Format::h($f['ordem'].'.'.$a['ordem'].' — '.$a['titulo'])?></label><?php endforeach;?><?php if(!$fa):?><small>Nenhum ato cadastrado.</small><?php endif;?></div></div><?php endforeach;?></div></div></div><div class="form-actions"><button class="btn primary">Salvar secretaria</button><?php if($editarSecretaria):?><a class="btn neutral" href="/<?=Format::h($slug)?>/configuracoes?secao=secretarias">Cancelar</a><?php endif;?></div></form><hr><h2><?=$editarDepartamento?'Editar departamento':'Novo departamento'?></h2><form method="post" action="/<?=Format::h($slug)?>/configuracoes/departamentos/salvar"><input type="hidden" name="_token" value="<?=Format::h($token)?>"><input type="hidden" name="id" value="<?=Format::h($editarDepartamento['id']??0)?>"><div class="form-grid"><div class="field"><label>Secretaria</label><select name="secretaria_id" required><option value="">Selecione</option><?php foreach($secretarias as$s):?><option value="<?=$s['id']?>" <?=((int)($editarDepartamento['secretaria_id']??0)===(int)$s['id'])?'selected':''?>><?=Format::h(($s['sigla']?$s['sigla'].' — ':'').$s['nome'])?></option><?php endforeach;?></select></div><div class="field"><label>Nome</label><input name="nome" value="<?=Format::h($editarDepartamento['nome']??'')?>" required></div><div class="field"><label>Sigla</label><input name="sigla" value="<?=Format::h($editarDepartamento['sigla']??'')?>"></div><div class="field"><label>Cargo do responsável</label><input name="cargo_responsavel" maxlength="180" placeholder="Ex.: Diretor de Departamento" value="<?=Format::h($editarDepartamento['cargo_responsavel']??'')?>"></div><div class="field span-2"><label>E-mails dos responsáveis</label><textarea name="emails_responsaveis" rows="3" placeholder="gestor@orgao.gov.br; equipe@orgao.gov.br"><?=Format::h($editarDepartamento['emails_responsaveis']??'')?></textarea><small>Aceita múltiplos endereços, separados por vírgula, ponto e vírgula ou linha.</small></div></div><div class="form-actions"><button class="btn primary">Salvar departamento</button></div></form></section>
<section class="config-card"><h2>Estrutura administrativa</h2><p>Pesquise, filtre e ordene os registros; 20 itens por página.</p>
<div class="pge-admin-grid" data-admin-grid="secretarias">
<div class="pge-admin-grid-toolbar"><label>Pesquisar secretaria<input type="search" data-grid-search placeholder="Nome, sigla, cargo, e-mail ou fase" autocomplete="off"></label><label>Situação<select data-grid-status><option value="todos">Todas</option><option value="ativo">Ativas</option><option value="inativo">Inativas</option></select></label><span data-grid-count aria-live="polite"></span></div>
<div class="table-scroll"><table class="config-table" data-grid-table><thead><tr><th><button type="button" class="pge-admin-grid-sort" data-grid-sort>Secretaria <span aria-hidden="true">↕</span></button></th><th><button type="button" class="pge-admin-grid-sort" data-grid-sort>Contatos responsáveis <span aria-hidden="true">↕</span></button></th><th><button type="button" class="pge-admin-grid-sort" data-grid-sort>Fases <span aria-hidden="true">↕</span></button></th><th><button type="button" class="pge-admin-grid-sort" data-grid-sort>Status <span aria-hidden="true">↕</span></button></th><th>Ações</th></tr></thead><tbody><?php foreach($secretarias as$s):?><tr data-grid-active="<?=$s['ativo']?'1':'0'?>"><td><b><?=Format::h(($s['sigla']?$s['sigla'].' — ':'').$s['nome'])?></b></td><td><small><b><?=Format::h($s['cargo_responsavel']?:'Cargo não informado')?></b><br><?=Format::h(str_replace(',', ' · ', $s['emails_responsaveis']??'') ?: 'Sem e-mails')?></small></td><td><small><?=Format::h($s['fases_vinculadas']?:'Sem fase vinculada')?></small></td><td><span class="status-mini <?=!(int)$s['ativo']?'inactive':''?>"><?=$s['ativo']?'ATIVA':'INATIVA'?></span></td><td><div class="actions"><a class="btn tiny secondary" href="/<?=Format::h($slug)?>/configuracoes?secao=secretarias&editar_secretaria=<?=$s['id']?>">Editar</a><form method="post" action="/<?=Format::h($slug)?>/configuracoes/secretarias/<?=$s['id']?>/alternar"><input type="hidden" name="_token" value="<?=Format::h($token)?>"><button class="btn tiny <?=$s['ativo']?'off':'approve'?>"><?=$s['ativo']?'Desativar':'Ativar'?></button></form></div></td></tr><?php endforeach;?></tbody></table></div><div class="pge-admin-grid-footer"><span data-grid-range></span><div class="pge-admin-grid-controls"><button type="button" class="btn tiny neutral" data-grid-prev>Anterior</button><span data-grid-page></span><button type="button" class="btn tiny neutral" data-grid-next>Próxima</button></div></div></div>
<h3>Departamentos</h3><div class="pge-admin-grid" data-admin-grid="departamentos"><div class="pge-admin-grid-toolbar"><label>Pesquisar departamento<input type="search" data-grid-search placeholder="Secretaria, departamento, cargo ou e-mail" autocomplete="off"></label><label>Situação<select data-grid-status><option value="todos">Todos</option><option value="ativo">Ativos</option><option value="inativo">Inativos</option></select></label><span data-grid-count aria-live="polite"></span></div><div class="table-scroll"><table class="config-table" data-grid-table><thead><tr><th><button type="button" class="pge-admin-grid-sort" data-grid-sort>Secretaria <span aria-hidden="true">↕</span></button></th><th><button type="button" class="pge-admin-grid-sort" data-grid-sort>Departamento <span aria-hidden="true">↕</span></button></th><th><button type="button" class="pge-admin-grid-sort" data-grid-sort>Contatos responsáveis <span aria-hidden="true">↕</span></button></th><th><button type="button" class="pge-admin-grid-sort" data-grid-sort>Status <span aria-hidden="true">↕</span></button></th><th>Ações</th></tr></thead><tbody><?php foreach($departamentos as$d):?><tr data-grid-active="<?=$d['ativo']?'1':'0'?>"><td><?=Format::h($d['secretaria_nome'])?></td><td><b><?=Format::h(($d['sigla']?$d['sigla'].' — ':'').$d['nome'])?></b></td><td><small><b><?=Format::h($d['cargo_responsavel']?:'Cargo não informado')?></b><br><?=Format::h(str_replace(',', ' · ', $d['emails_responsaveis']??'') ?: 'Sem e-mails')?></small></td><td><span class="status-mini <?=!(int)$d['ativo']?'inactive':''?>"><?=$d['ativo']?'ATIVO':'INATIVO'?></span></td><td><div class="actions"><a class="btn tiny secondary" href="/<?=Format::h($slug)?>/configuracoes?secao=secretarias&editar_departamento=<?=$d['id']?>">Editar</a><form method="post" action="/<?=Format::h($slug)?>/configuracoes/departamentos/<?=$d['id']?>/alternar"><input type="hidden" name="_token" value="<?=Format::h($token)?>"><button class="btn tiny <?=$d['ativo']?'off':'approve'?>"><?=$d['ativo']?'Desativar':'Ativar'?></button></form></div></td></tr><?php endforeach;?></tbody></table></div><div class="pge-admin-grid-footer"><span data-grid-range></span><div class="pge-admin-grid-controls"><button type="button" class="btn tiny neutral" data-grid-prev>Anterior</button><span data-grid-page></span><button type="button" class="btn tiny neutral" data-grid-next>Próxima</button></div></div></div></section></div>


<style>
.pge-admin-grid-toolbar{display:flex;align-items:end;gap:12px;flex-wrap:wrap;margin:14px 0 10px}
.pge-admin-grid-toolbar label{font-size:12px;font-weight:700;display:flex;flex-direction:column;gap:5px;min-width:160px}
.pge-admin-grid-toolbar label:first-child{flex:1;min-width:220px}
.pge-admin-grid-toolbar input,.pge-admin-grid-toolbar select{width:100%;padding:9px 11px;border:1px solid #cbd5e1;border-radius:7px;background:#fff;color:#152b48;font-size:13px}
.pge-admin-grid-toolbar [data-grid-count]{font-size:12px;color:#53657b;margin-bottom:10px}
.pge-admin-grid-sort{border:0;background:transparent;color:inherit;font:inherit;font-weight:700;cursor:pointer;text-align:left;padding:3px 0;white-space:nowrap}
.pge-admin-grid-sort:focus-visible{outline:2px solid #2563eb;outline-offset:3px}
.pge-admin-grid-sort span{color:#72849a;padding-left:4px}
.pge-admin-grid-footer{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;padding:12px 0;font-size:12px;color:#56677d}
.pge-admin-grid-controls{display:flex;align-items:center;gap:10px}
.pge-admin-grid-controls button:disabled{opacity:.45;cursor:not-allowed}
</style>
<script>
(function(){
 'use strict';
 function setup(root){
  var table=root.querySelector('[data-grid-table]'),tbody=table&&table.tBodies[0];if(!tbody)return;
  var entries=Array.from(tbody.rows).map(function(tr,i){return {tr:tr,index:i,values:Array.from(tr.cells).map(function(td){return td.textContent.trim()}),active:tr.getAttribute('data-grid-active')==='1'}});
  var search=root.querySelector('[data-grid-search]'),status=root.querySelector('[data-grid-status]'),count=root.querySelector('[data-grid-count]'),range=root.querySelector('[data-grid-range]'),pageText=root.querySelector('[data-grid-page]'),prev=root.querySelector('[data-grid-prev]'),next=root.querySelector('[data-grid-next]');
  var page=1,size=20,sortCol=-1,sortDir=1,collator=new Intl.Collator('pt-BR',{numeric:true,sensitivity:'base'});
  function normalize(s){return String(s||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('pt-BR')}
  function render(){
   var q=normalize(search.value.trim()),filter=status.value;
   var filtered=entries.filter(function(e){return (!q||normalize(e.values.slice(0,-1).join(' ')).includes(q))&&(filter==='todos'||((filter==='ativo')===e.active))});
   filtered.sort(function(a,b){return sortCol<0?a.index-b.index:collator.compare(a.values[sortCol],b.values[sortCol])*sortDir||a.index-b.index});
   var pages=Math.max(1,Math.ceil(filtered.length/size));page=Math.min(Math.max(1,page),pages);var from=(page-1)*size;
   entries.forEach(function(e){e.tr.hidden=true;e.tr.style.display='none'});
   filtered.slice(from,from+size).forEach(function(e){tbody.appendChild(e.tr);e.tr.hidden=false;e.tr.style.display=''});
   count.textContent=filtered.length+' registro(s)';range.textContent=filtered.length?(from+1)+'–'+Math.min(from+size,filtered.length)+' de '+filtered.length:'Nenhum registro encontrado';pageText.textContent='Página '+page+' de '+pages;prev.disabled=page===1;next.disabled=page===pages;
  }
  search.addEventListener('input',function(){page=1;render()});status.addEventListener('change',function(){page=1;render()});
  prev.addEventListener('click',function(){if(page>1){page--;render()}});next.addEventListener('click',function(){page++;render()});
  table.querySelectorAll('[data-grid-sort]').forEach(function(btn,col){btn.addEventListener('click',function(){sortDir=sortCol===col?-sortDir:1;sortCol=col;page=1;table.querySelectorAll('[data-grid-sort] span').forEach(function(span){span.textContent='↕'});btn.querySelector('span').textContent=sortDir>0?'↑':'↓';render()})});
  render();
 }
 function init(){document.querySelectorAll('[data-admin-grid]').forEach(setup)}
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
</script>
<?php elseif($secao==='importacao'):$preview=$importacaoPreview??null;?>
<section class="structure-import-hero">
    <div><span class="structure-import-eyebrow">IMPLANTAÇÃO EM LOTE</span><h2>Importação de Estrutura</h2><p>Replique estruturas já homologadas ou utilize a planilha oficial da INPACTA para acelerar a implantação de <?=Format::h($tenant['nome'])?>.</p></div>
    <div class="structure-import-hero-badge"><small>DESTINO</small><b><?=Format::h($tenant['nome'].' - '.$tenant['uf'])?></b><span>Somente a Stratelli pode executar importações</span></div>
</section>

<div class="structure-import-methods">
    <section class="structure-import-card">
        <div class="structure-import-card-icon">⇄</div><div class="structure-import-card-head"><small>OPÇÃO 1</small><h3>Copiar de outro município</h3><p>Use uma instância já configurada como referência. Nenhum histórico, usuário ou documento enviado será copiado.</p></div>
        <form method="post" action="/<?=Format::h($slug)?>/configuracoes/importacao/municipio/validar" class="structure-import-form">
            <input type="hidden" name="_token" value="<?=Format::h($token)?>">
            <label><span>Município de origem</span><select name="municipio_origem_id" required><option value="">Selecione uma instância</option><?php foreach(($municipiosOrigem??[]) as$m):?><option value="<?=$m['id']?>"><?=Format::h($m['nome'].' - '.$m['uf'])?> · <?=Format::h($m['status'])?></option><?php endforeach;?></select></label>
            <div class="structure-import-checks"><label><input type="checkbox" name="importar_fases" value="1" checked><span><b>Fases</b><small>Ordem, prazos, títulos, critérios e entregáveis.</small></span></label><label><input type="checkbox" name="importar_secretarias" value="1" checked><span><b>Secretarias</b><small>Nome, sigla, status e vínculos com fases.</small></span></label><label><input type="checkbox" name="importar_departamentos" value="1" checked><span><b>Departamentos</b><small>Estrutura interna vinculada às Secretarias.</small></span></label></div>
            <label><span>Quando já existir um registro equivalente</span><select name="estrategia_conflito"><option value="IGNORAR" selected>Ignorar existente — recomendado</option><option value="ATUALIZAR">Atualizar existente com os dados da origem</option></select><small>O sistema sempre apresenta uma prévia antes de gravar.</small></label>
            <button class="btn primary" type="submit">Validar estrutura selecionada</button>
        </form>
    </section>

    <section class="structure-import-card accent">
        <div class="structure-import-card-icon">▦</div><div class="structure-import-card-head"><small>OPÇÃO 2</small><h3>Importar planilha Excel</h3><p>Preencha o modelo oficial com Fases, Secretarias, Departamentos e vínculos. O arquivo é validado antes de qualquer gravação.</p></div>
        <a class="structure-template-download" href="/<?=Format::h($slug)?>/configuracoes/importacao/modelo"><span>↓</span><div><b>Baixar planilha modelo</b><small>Modelo_Importacao_Estrutura_INPACTA.xlsx</small></div></a>
        <form method="post" enctype="multipart/form-data" action="/<?=Format::h($slug)?>/configuracoes/importacao/planilha/validar" class="structure-import-form">
            <input type="hidden" name="_token" value="<?=Format::h($token)?>">
            <label class="structure-file-field"><span>Planilha preenchida</span><input type="file" name="planilha_estrutura" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required><small>Somente .xlsx · máximo de 5 MB.</small></label>
            <label><span>Quando já existir um registro equivalente</span><select name="estrategia_conflito"><option value="IGNORAR" selected>Ignorar existente — recomendado</option><option value="ATUALIZAR">Atualizar existente com os dados da planilha</option></select></label>
            <button class="btn primary" type="submit">Validar planilha</button>
        </form>
    </section>
</div>

<section class="structure-import-safety"><span>✓</span><div><b>Validação antes da gravação</b><p>Importações são executadas em transação. Erros de sequência, referências inexistentes e conflitos impeditivos bloqueiam a confirmação sem alterar a instância.</p></div><div class="structure-import-safety-tags"><em>Sem usuários</em><em>Sem históricos</em><em>Sem arquivos enviados</em></div></section>

<?php if($preview):$sum=$preview['summary']??[];?>
<section class="structure-preview-card <?=!empty($preview['errors'])?'has-errors':'is-ready'?>">
    <div class="structure-preview-head"><div><small>PRÉVIA DA IMPORTAÇÃO</small><h3><?=Format::h($preview['source_label']??'Estrutura')?></h3><p><?=($preview['source_type']??'')==='PLANILHA'?'Arquivo: '.Format::h($preview['file_name']??'planilha.xlsx'):'Origem selecionada para replicação'?> · Estratégia: <b><?=Format::h($preview['strategy']??'IGNORAR')?></b></p></div><span class="structure-preview-status"><?=empty($preview['errors'])?'PRONTA PARA IMPORTAR':'CORREÇÕES NECESSÁRIAS'?></span></div>
    <div class="structure-preview-kpis"><div><small>Fases</small><b><?=intval($sum['fases']??0)?></b></div><div><small>Secretarias</small><b><?=intval($sum['secretarias']??0)?></b></div><div><small>Departamentos</small><b><?=intval($sum['departamentos']??0)?></b></div><div><small>Vínculos válidos</small><b><?=intval($sum['vinculos']??0)?></b></div><div class="create"><small>A criar</small><b><?=intval($sum['criar']??0)?></b></div><div class="update"><small>A atualizar</small><b><?=intval($sum['atualizar']??0)?></b></div><div class="skip"><small>A ignorar</small><b><?=intval($sum['ignorar']??0)?></b></div></div>
    <?php if(!empty($preview['errors'])):?><div class="structure-preview-messages error"><b>Erros impeditivos</b><ul><?php foreach($preview['errors'] as$e):?><li><?=Format::h($e)?></li><?php endforeach;?></ul></div><?php endif;?>
    <?php if(!empty($preview['warnings'])):?><div class="structure-preview-messages warning"><b>Avisos</b><ul><?php foreach($preview['warnings'] as$w):?><li><?=Format::h($w)?></li><?php endforeach;?></ul></div><?php endif;?>
    <div class="structure-preview-table-wrap"><table class="structure-preview-table"><thead><tr><th>Tipo</th><th>Registro</th><th>Tratamento</th></tr></thead><tbody><?php foreach(array_slice(($preview['items']??[]),0,250) as$item):?><tr><td><?=Format::h($item['type'])?></td><td><b><?=Format::h($item['label'])?></b></td><td><span class="structure-action-status <?=strtolower(Format::h($item['status']))?>"><?=Format::h($item['message'])?></span></td></tr><?php endforeach;?></tbody></table></div><?php if(count($preview['items']??[])>250):?><div class="structure-preview-limit-note">Exibindo os primeiros 250 itens de <?=count($preview['items'])?>. Todos serão processados na confirmação.</div><?php endif;?>
    <div class="structure-preview-actions"><form method="post" action="/<?=Format::h($slug)?>/configuracoes/importacao/cancelar"><input type="hidden" name="_token" value="<?=Format::h($token)?>"><button class="btn neutral" type="submit">Descartar prévia</button></form><?php if(empty($preview['errors'])):?><form method="post" action="/<?=Format::h($slug)?>/configuracoes/importacao/confirmar" onsubmit="return confirm('Confirmar a importação desta estrutura em <?=Format::h($tenant['nome'])?>?');"><input type="hidden" name="_token" value="<?=Format::h($token)?>"><button class="btn approve" type="submit">✓ Confirmar importação</button></form><?php endif;?></div>
</section>
<?php endif;?>

<section class="structure-import-history">
    <div class="structure-import-history-head"><div><h3>Histórico de importações</h3><p>Últimas estruturas aplicadas nesta instância. Importações que apenas criaram registros podem ser desfeitas enquanto esses registros ainda não estiverem em uso.</p></div></div>
    <?php if(!empty($importacoesEstrutura)):?><div class="table-scroll"><table class="config-table structure-history-table"><thead><tr><th>Data</th><th>Origem</th><th>Resultado</th><th>Status</th><th>Ação</th></tr></thead><tbody><?php foreach($importacoesEstrutura as$imp):$r=$imp['resumo']??[];?><tr><td><?=Format::h(Format::dateTime($imp['criado_em']))?><br><small><?=Format::h($imp['usuario_nome']?:'Stratelli')?></small></td><td><b><?=Format::h($imp['origem_tipo']==='PLANILHA'?($imp['arquivo_nome']?:'Planilha Excel'):(($imp['origem_nome']??'Município').' - '.($imp['origem_uf']??'')))?></b><br><small><?=Format::h($imp['estrategia_conflito'])?></small></td><td><span><?=intval($r['created_total']??0)?> criado(s)</span><br><small><?=intval($r['updated_total']??0)?> atualizado(s)</small></td><td><span class="status-mini <?=$imp['status']==='DESFEITA'?'inactive':''?>"><?=Format::h($imp['status'])?></span></td><td><?php if(!empty($imp['pode_desfazer'])):?><form method="post" action="/<?=Format::h($slug)?>/configuracoes/importacao/<?=$imp['id']?>/desfazer" onsubmit="return confirm('Desfazer esta importação? O sistema bloqueará a operação se algum registro já estiver em uso.');"><input type="hidden" name="_token" value="<?=Format::h($token)?>"><button class="btn tiny off" type="submit">Desfazer</button></form><?php else:?><small><?=($imp['status']==='DESFEITA')?'Já desfeita':'Sem reversão automática'?></small><?php endif;?></td></tr><?php endforeach;?></tbody></table></div><?php else:?><div class="structure-import-empty">Nenhuma importação estrutural registrada nesta instância.</div><?php endif;?>
</section>

<section class="instance-snapshot-section" id="pontos-restauracao">
    <div class="instance-snapshot-head">
        <div><span class="instance-snapshot-eyebrow">SEGURANÇA DA INSTÂNCIA</span><h3>Pontos de restauração</h3><p>Salve o estado atual de <?=Format::h($tenant['nome'])?> antes de alterações importantes. O pacote pode ser exportado e restaurado posteriormente.</p></div>
        <div class="instance-snapshot-shield"><span>⟲</span><div><b>Restauração protegida</b><small>Antes de restaurar, o INPACTA cria automaticamente um novo ponto de segurança.</small></div></div>
    </div>
    <div class="instance-snapshot-grid">
        <article class="instance-snapshot-card primary">
            <div class="instance-snapshot-card-icon">◫</div><div><small>SALVAR ESTADO ATUAL</small><h4>Criar ponto de restauração</h4><p>Registra configurações, estrutura administrativa, usuários, cronograma, território e arquiva a trilha documental e os arquivos vinculados.</p></div>
            <form method="post" action="/<?=Format::h($slug)?>/configuracoes/restauracao/criar"><input type="hidden" name="_token" value="<?=Format::h($token)?>"><label>Nome do ponto<input name="nome_snapshot" maxlength="180" placeholder="Ex.: Antes de reconfigurar as fases"></label><button class="btn primary" type="submit">＋ Salvar estado atual</button></form>
        </article>
        <article class="instance-snapshot-card">
            <div class="instance-snapshot-card-icon">⇧</div><div><small>RECUPERAR PACOTE EXTERNO</small><h4>Importar backup exportado</h4><p>Recarregue um pacote `.zip` exportado anteriormente desta mesma instância para disponibilizá-lo novamente para restauração.</p></div>
            <form method="post" enctype="multipart/form-data" action="/<?=Format::h($slug)?>/configuracoes/restauracao/importar"><input type="hidden" name="_token" value="<?=Format::h($token)?>"><input type="file" name="pacote_backup" accept=".zip,application/zip" required><button class="btn neutral" type="submit">Importar pacote</button></form>
        </article>
    </div>
    <div class="instance-snapshot-policy"><b>Como funciona a restauração?</b><span>O estado configurável e operacional retorna ao ponto escolhido. Por segurança jurídica e rastreabilidade, auditoria, histórico documental e histórico formal de fases nunca são apagados; permanecem preservados como evidência do que ocorreu.</span></div>
    <div class="instance-snapshot-list-head"><div><h4>Estados salvos</h4><p>Você pode exportar qualquer ponto para armazenamento externo ou restaurá-lo diretamente.</p></div><span><?=count($pontosRestauracao??[])?> ponto(s)</span></div>
    <?php if(!empty($pontosRestauracao)):?><div class="instance-snapshot-list">
    <?php foreach($pontosRestauracao as$pt):$sum=$pt['resumo']??[];?>
        <article class="instance-snapshot-item <?=($pt['origem']??'')==='AUTOMATICO'?'automatic':''?>">
            <div class="instance-snapshot-item-main"><div class="instance-snapshot-state-icon"><?=($pt['origem']??'')==='AUTOMATICO'?'⛨':'◫'?></div><div><div class="instance-snapshot-item-title"><b><?=Format::h($pt['nome'])?></b><span><?=Format::h($pt['origem'])?></span></div><small><?=Format::h(Format::dateTime($pt['criado_em']))?> · <?=Format::h($pt['usuario_nome']?:'Stratelli')?> · <?=Format::h(Format::fileSize((int)$pt['tamanho']))?></small><div class="instance-snapshot-metrics"><span><?=$sum['fases']??0?> fases</span><span><?=$sum['secretarias']??0?> Secretarias</span><span><?=$sum['usuarios']??0?> usuários</span><span><?=$sum['documentos']??0?> docs.</span><span><?=$sum['arquivos']??0?> arquivos</span></div><?php if(!empty($pt['restaurado_em'])):?><em>Restaurado <?=intval($pt['restauracoes']??0)?> vez(es) · último em <?=Format::h(Format::dateTime($pt['restaurado_em']))?></em><?php endif;?></div></div>
            <div class="instance-snapshot-actions"><a class="btn tiny secondary" href="/<?=Format::h($slug)?>/configuracoes/restauracao/<?=$pt['id']?>/download">↓ Exportar</a><form method="post" action="/<?=Format::h($slug)?>/configuracoes/restauracao/<?=$pt['id']?>/restaurar" onsubmit="return confirm('RESTAURAR A INSTÂNCIA para este estado? O INPACTA criará automaticamente um ponto de segurança do estado atual antes de prosseguir.');"><input type="hidden" name="_token" value="<?=Format::h($token)?>"><button class="btn tiny approve" type="submit">⟲ Restaurar</button></form><form method="post" action="/<?=Format::h($slug)?>/configuracoes/restauracao/<?=$pt['id']?>/remover" onsubmit="return confirm('Remover este ponto de restauração? Se ele não estiver exportado externamente, não poderá mais ser utilizado.');"><input type="hidden" name="_token" value="<?=Format::h($token)?>"><button class="btn tiny off" type="submit">Remover</button></form></div>
        </article>
    <?php endforeach;?>
    </div><?php else:?><div class="structure-import-empty">Nenhum ponto de restauração salvo nesta instância. Crie um antes da próxima alteração estrutural.</div><?php endif;?>
</section>

<?php elseif($secao==='tipos'):?>
<div class="config-grid"><section class="config-card"><h2><?=$editarTipo?'Editar tipo':'Novo tipo de documento'?></h2><form method="post" action="/<?=Format::h($slug)?>/configuracoes/tipos/salvar"><input type="hidden" name="_token" value="<?=Format::h($token)?>"><input type="hidden" name="id" value="<?=Format::h($editarTipo['id']??0)?>"><div class="form-grid"><div class="field span-2"><label>Nome</label><input name="nome" value="<?=Format::h($editarTipo['nome']??'')?>" required></div><div class="field span-2"><label>Descrição</label><textarea name="descricao"><?=Format::h($editarTipo['descricao']??'')?></textarea></div><div class="field span-2"><label>Extensões permitidas</label><input name="extensoes" value="<?=Format::h($editarTipo['extensoes']??'pdf,doc,docx,xls,xlsx')?>" required><small>Separe por vírgula.</small></div></div><div class="form-actions"><button class="btn primary">Salvar tipo</button></div></form></section><section class="config-card"><h2>Tipos cadastrados</h2><div class="table-scroll"><table class="config-table"><thead><tr><th>Tipo</th><th>Extensões</th><th>Status</th><th>Ações</th></tr></thead><tbody><?php foreach($tiposDocumento as$t):?><tr><td><b><?=Format::h($t['nome'])?></b><br><small><?=Format::h($t['descricao'])?></small></td><td><?=Format::h($t['extensoes'])?></td><td><span class="status-mini <?=!(int)$t['ativo']?'inactive':''?>"><?=$t['ativo']?'ATIVO':'INATIVO'?></span></td><td><div class="actions"><a class="btn tiny secondary" href="/<?=Format::h($slug)?>/configuracoes?secao=tipos&editar_tipo=<?=$t['id']?>">Editar</a><form method="post" action="/<?=Format::h($slug)?>/configuracoes/tipos/<?=$t['id']?>/alternar"><input type="hidden" name="_token" value="<?=Format::h($token)?>"><button class="btn tiny <?=$t['ativo']?'off':'approve'?>"><?=$t['ativo']?'Desativar':'Ativar'?></button></form></div></td></tr><?php endforeach;?></tbody></table></div></section></div>

<?php else:?>
<div class="document-tab-page"><section class="config-card"><h2><?=$editarRequisito?'Editar documento':'Novo documento / modelo'?></h2><p>Todo documento deve ser vinculado a um Ato; opcionalmente, a uma Atividade subordinada. A fase é determinada automaticamente pelo ato selecionado.</p><form method="post" enctype="multipart/form-data" action="/<?=Format::h($slug)?>/configuracoes/documentos/salvar"><input type="hidden" name="_token" value="<?=Format::h($token)?>"><input type="hidden" name="id" value="<?=Format::h($editarRequisito['id']??0)?>"><div class="local-form-tabs compact" data-local-tabs="documento">
<button type="button" class="local-form-tab active" data-local-target="doc-dados">Dados do documento</button>
<button type="button" class="local-form-tab" data-local-target="doc-vinculo">Vínculo e arquivo modelo</button>
</div>
<div class="local-form-panels document-local-panels">
<div class="form-grid js-local-tab-panel" data-local-panel="doc-dados"><div class="field"><label>Ordem</label><input type="number" min="1" name="ordem" value="<?=Format::h($editarRequisito['ordem']??1)?>"></div><div class="field"><label>Secretaria</label><select name="secretaria_id" required><option value="">Selecione</option><?php foreach($secretarias as$s):?><option value="<?=$s['id']?>" <?=((int)($editarRequisito['secretaria_id']??0)===(int)$s['id'])?'selected':''?>><?=Format::h(($s['sigla']?$s['sigla'].' — ':'').$s['nome'])?></option><?php endforeach;?></select></div><div class="field"><label>Departamento</label><select name="departamento_id"><option value="">Sem departamento</option><?php foreach($departamentos as$d):?><option value="<?=$d['id']?>" <?=((int)($editarRequisito['departamento_id']??0)===(int)$d['id'])?'selected':''?>><?=Format::h($d['secretaria_nome'].' — '.$d['nome'])?></option><?php endforeach;?></select></div><div class="field"><label>Tipo</label><select name="tipo_documento_id" required><option value="">Selecione</option><?php foreach($tiposDocumento as$t):?><option value="<?=$t['id']?>" <?=((int)($editarRequisito['tipo_documento_id']??0)===(int)$t['id'])?'selected':''?>><?=Format::h($t['nome'])?></option><?php endforeach;?></select></div><div class="field"><label>Responsável pelo envio</label><select name="perfil_envio"><option value="MUNICIPIO" <?=($editarRequisito['perfil_envio']??'MUNICIPIO')==='MUNICIPIO'?'selected':''?>>Município</option><option value="STRATELLI" <?=($editarRequisito['perfil_envio']??'')==='STRATELLI'?'selected':''?>>Stratelli</option></select></div><div class="field span-2"><label>Nome do documento</label><input name="nome" value="<?=Format::h($editarRequisito['nome']??'')?>" required></div><div class="field span-2"><label>Orientação / descrição</label><textarea name="descricao"><?=Format::h($editarRequisito['descricao']??'')?></textarea></div><label class="check-line span-2"><input type="checkbox" name="obrigatorio" value="1" <?=!isset($editarRequisito['obrigatorio'])||$editarRequisito['obrigatorio']?'checked':''?>> Documento obrigatório para concluir o Ato</label></div>
<div class="form-grid js-local-tab-panel" data-local-panel="doc-vinculo"><div class="field"><label>Fase <b>(obrigatória)</b></label><select name="fase_id" id="doc-fase" required><option value="">Selecione</option><?php $docFid=(int)($editarRequisito['fase_id']??0);foreach($fasesTodas as$f):if(!(int)$f['ativo'])continue;?><option value="<?=$f['id']?>" <?=$docFid===(int)$f['id']?'selected':''?>>Fase <?=Format::h($f['ordem'])?> — <?=Format::h($f['titulo'])?></option><?php endforeach;?></select></div><div class="field"><label>Ato / atividade <b>(obrigatório)</b></label><select name="atividade_id" id="doc-atividade" required><option value="">Selecione primeiro a fase</option><?php foreach(($atividadesFase??[])as$a):if(!(int)$a['ativo'])continue;?><option data-phase="<?=$a['fase_id']?>" value="<?=$a['id']?>" <?=((int)($editarRequisito['atividade_id']??0)===(int)$a['id'])?'selected':''?>><?=Format::h($a['ordem'].'. '.$a['titulo'])?></option><?php endforeach;?></select><small>Somente atos da fase escolhida serão exibidos.</small></div><div class="field"><label>Atividade do Ato (opcional)</label><select name="atividade_ato_id" id="doc-atividade-filho"><option value="">Documento vinculado diretamente ao Ato</option><?php foreach(($atividadesAto??[]) as $child):?><option data-ato="<?=$child['ato_legado_id']?>" value="<?=$child['id']?>" <?=((int)($editarRequisito['atividade_ato_id']??0)===(int)$child['id'])?'selected':''?>><?=Format::h($child['codigo'].' — '.$child['titulo'])?></option><?php endforeach;?></select></div><div class="field span-2"><label>Arquivo modelo (opcional)</label><input type="file" name="arquivo_modelo"><small>O arquivo será versionado e preservado no histórico.</small></div></div>
</div><div class="form-actions"><button class="btn primary">Salvar regra documental</button><?php if($editarRequisito):?><a class="btn neutral" href="/<?=Format::h($slug)?>/configuracoes?secao=documentos">Cancelar</a><?php endif;?></div></form></section><section class="config-card"><h2>Documentos configurados</h2><p>Os modelos são versionados; o anterior permanece no histórico.</p><div class="pge-doc-grid-toolbar" aria-label="Filtros de documentos configurados">
<label class="pge-doc-grid-field">Buscar documento, fase, secretaria ou tipo
<input type="search" id="pge-doc-grid-search" placeholder="Buscar nos documentos configurados…" autocomplete="off"></label>
<label class="pge-doc-grid-field">Status
<select id="pge-doc-grid-status"><option value="todos">Todos</option><option value="ativo">Ativos</option><option value="inativo">Inativos</option></select></label>
<span id="pge-doc-grid-count" class="pge-doc-grid-count" aria-live="polite"></span>
</div>
<div class="table-scroll"><table class="config-table" id="pge-doc-grid-table"><thead><tr><th><button type="button" class="pge-doc-grid-sort" data-col="0" aria-label="Ordenar por fase e documento">Fase / Documento <span aria-hidden="true">↕</span></button></th><th><button type="button" class="pge-doc-grid-sort" data-col="1" aria-label="Ordenar por responsável">Responsável <span aria-hidden="true">↕</span></button></th><th><button type="button" class="pge-doc-grid-sort" data-col="2" aria-label="Ordenar por tipo e modelo">Tipo / Modelo <span aria-hidden="true">↕</span></button></th><th><button type="button" class="pge-doc-grid-sort" data-col="3" aria-label="Ordenar por status">Status <span aria-hidden="true">↕</span></button></th><th>Ações</th></tr></thead><tbody><?php foreach($requisitosTodos as$r):?><tr class="<?=!(int)$r['ativo']?'muted-row':''?>"><td><b>Fase <?=Format::h($r['fase_ordem'])?> — <?=Format::h($r['nome'])?></b><br><small><?=Format::h($r['fase_aba'])?><?php if($r['atividade_titulo']):?> · Ato <?=Format::h($r['atividade_ordem'].'. '.$r['atividade_titulo'])?><?php endif;?> · Ordem <?=Format::h($r['ordem'])?> · <?=$r['obrigatorio']?'Obrigatório':'Opcional'?></small></td><td><b><?=Format::h($r['secretaria_nome'])?></b><?php if($r['departamento_nome']):?><br><small><?=Format::h($r['departamento_nome'])?></small><?php endif;?><br><span class="relation-tag">Envio: <?=Format::h($r['perfil_envio'])?></span></td><td><b><?=Format::h($r['tipo_nome'])?></b><br><?php if($r['modelo_nome']):?><small>📎 <?=Format::h($r['modelo_nome'])?><br><?=Format::h(Format::fileSize((int)$r['modelo_tamanho']))?> · <?=Format::h(Format::dateTime($r['modelo_data']))?></small><?php else:?><small>Sem modelo</small><?php endif;?></td><td><span class="status-mini <?=!(int)$r['ativo']?'inactive':''?>"><?=$r['ativo']?'ATIVO':'INATIVO'?></span></td><td><div class="actions"><a class="btn tiny secondary" href="/<?=Format::h($slug)?>/configuracoes?secao=documentos&editar_requisito=<?=$r['id']?>">Editar</a><?php if($r['modelo_id']):?><a class="btn tiny neutral" href="/<?=Format::h($slug)?>/arquivos/modelos/<?=$r['id']?>">Modelo</a><?php endif;?><form method="post" action="/<?=Format::h($slug)?>/configuracoes/documentos/<?=$r['id']?>/alternar"><input type="hidden" name="_token" value="<?=Format::h($token)?>"><button class="btn tiny <?=$r['ativo']?'off':'approve'?>"><?=$r['ativo']?'Desativar':'Ativar'?></button></form></div></td></tr><?php endforeach;?></tbody></table></div>
<div class="pge-doc-grid-footer">
<span id="pge-doc-grid-range" aria-live="polite"></span>
<div class="pge-doc-grid-pages"><button type="button" id="pge-doc-grid-prev" class="btn tiny neutral">Anterior</button><span id="pge-doc-grid-page" aria-live="polite"></span><button type="button" id="pge-doc-grid-next" class="btn tiny neutral">Próxima</button></div>
</div>
</section></div>
<style>
.pge-doc-grid-toolbar{display:flex;align-items:end;flex-wrap:wrap;gap:12px;margin:14px 0}.pge-doc-grid-field{display:flex;flex-direction:column;gap:5px;font-size:12px;font-weight:700;color:#243e60;min-width:175px}.pge-doc-grid-field:first-child{flex:1;min-width:240px}.pge-doc-grid-field input,.pge-doc-grid-field select{width:100%;min-height:39px;border:1px solid #cbd9e9;border-radius:8px;padding:7px 10px;background:#fff;color:#173457;font:inherit}.pge-doc-grid-count{font-size:12px;color:#526784;padding-bottom:10px}.pge-doc-grid-sort{border:0;background:none;color:inherit;font:inherit;font-weight:inherit;padding:4px 0;text-align:left;cursor:pointer}.pge-doc-grid-sort:hover,.pge-doc-grid-sort:focus-visible{text-decoration:underline}.pge-doc-grid-sort span{color:#7185a0;margin-left:4px}.pge-doc-grid-footer{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;padding:14px 2px;color:#526784;font-size:12px}.pge-doc-grid-pages{display:flex;align-items:center;gap:10px}.pge-doc-grid-pages button:disabled{opacity:.4;cursor:not-allowed}
</style>
<script>
(function(){
 const table=document.getElementById('pge-doc-grid-table');
 if(!table)return;
 const tbody=table.tBodies[0];
 const all=Array.from(tbody.rows);
 const search=document.getElementById('pge-doc-grid-search');
 const status=document.getElementById('pge-doc-grid-status');
 const count=document.getElementById('pge-doc-grid-count');
 const range=document.getElementById('pge-doc-grid-range');
 const pager=document.getElementById('pge-doc-grid-page');
 const prev=document.getElementById('pge-doc-grid-prev');
 const next=document.getElementById('pge-doc-grid-next');
 const size=20;
 let current=1, sortCol=-1, sortDir=1;
 const collator=new Intl.Collator('pt-BR',{numeric:true,sensitivity:'base'});
 const normal=v=>String(v||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('pt-BR');
 const rows=all.map((tr,index)=>({tr,index,parts:Array.from(tr.cells).slice(0,4).map(td=>td.textContent.trim()),text:normal(Array.from(tr.cells).slice(0,4).map(td=>td.textContent).join(' ')),active:!tr.classList.contains('muted-row')}));
 function render(){
  const q=normal(search.value.trim());const st=status.value;
  const selected=rows.filter(r=>(!q||r.text.includes(q))&&(st==='todos'||(st==='ativo'?r.active:!r.active)));
  if(sortCol>=0)selected.sort((a,b)=>collator.compare(a.parts[sortCol],b.parts[sortCol])*sortDir||(a.index-b.index));
  else selected.sort((a,b)=>a.index-b.index);
  const pages=Math.max(1,Math.ceil(selected.length/size));current=Math.min(Math.max(1,current),pages);
  const start=(current-1)*size;
  rows.forEach(r=>{r.tr.hidden=true;r.tr.style.display='none'});
  selected.slice(start,start+size).forEach(r=>{tbody.appendChild(r.tr);r.tr.hidden=false;r.tr.style.display=''});
  count.textContent=selected.length+' registro(s) encontrado(s)';
  range.textContent=selected.length?(start+1)+'–'+Math.min(start+size,selected.length)+' de '+selected.length+' registros':'Nenhum registro encontrado';
  pager.textContent='Página '+current+' de '+pages;
  prev.disabled=current===1;next.disabled=current===pages;
 }
 search.addEventListener('input',()=>{current=1;render()});
 status.addEventListener('change',()=>{current=1;render()});
 prev.addEventListener('click',()=>{if(current>1){current--;render()}});
 next.addEventListener('click',()=>{current++;render()});
 table.querySelectorAll('.pge-doc-grid-sort').forEach(button=>button.addEventListener('click',()=>{
  const col=Number(button.dataset.col);sortDir=col===sortCol?-sortDir:1;sortCol=col;current=1;
  table.querySelectorAll('.pge-doc-grid-sort span').forEach(span=>span.textContent='↕');
  button.querySelector('span').textContent=sortDir===1?'↑':'↓';render();
 }));
 render();
})();
</script>
<?php endif;?>
</div>

<script>
document.addEventListener('DOMContentLoaded',function(){
  const atoEl=document.getElementById('doc-atividade'), childEl=document.getElementById('doc-atividade-filho');
  if(atoEl&&childEl){const update=()=>{const val=atoEl.value;[...childEl.options].forEach((o,i)=>{if(i===0)return;o.hidden=(o.dataset.ato!==val);o.disabled=(o.dataset.ato!==val);});if(childEl.selectedOptions.length && childEl.selectedOptions[0].disabled) childEl.value='';};atoEl.addEventListener('change',update);document.getElementById('doc-fase')?.addEventListener('change',()=>setTimeout(update,0));update();}

  /* Abas locais: Fases/Atos e Documentos/Modelos */
  document.querySelectorAll('[data-local-tabs]').forEach(function(nav){
    var buttons=Array.prototype.slice.call(nav.querySelectorAll('[data-local-target]'));
    var panelsWrap=nav.nextElementSibling;
    if(!panelsWrap)return;
    var panels=Array.prototype.slice.call(panelsWrap.querySelectorAll('.js-local-tab-panel'));
    function activate(name){
      buttons.forEach(function(b){b.classList.toggle('active',b.dataset.localTarget===name);});
      panels.forEach(function(p){p.classList.toggle('active',p.dataset.localPanel===name);});
    }
    buttons.forEach(function(b){
      b.addEventListener('click',function(){activate(b.dataset.localTarget);});
    });
    var first=buttons.length?buttons[0].dataset.localTarget:'';
    var editAct=panelsWrap.querySelector('[data-local-panel="ato"] input[name="id"]');
    if(editAct && editAct.value && editAct.value!=='0')first='ato';
    activate(first);
  });

  /* Secretaria -> atos vinculados */
  document.querySelectorAll('.secretaria-phase-link').forEach(function(box){
    var phase=box.querySelector('.js-phase-link');
    var acts=box.querySelectorAll('.js-activity-link');
    function sync(){
      acts.forEach(function(a){
        a.disabled=!phase.checked;
        if(!phase.checked)a.checked=false;
      });
    }
    if(phase){phase.addEventListener('change',sync);sync();}
  });

  /* Documento: Fase -> somente atos daquela fase */
  var fase=document.getElementById('doc-fase');
  var ato=document.getElementById('doc-atividade');
  if(fase&&ato){
    var opts=ato.querySelectorAll('option[data-phase]');
    function syncAtos(){
      var keep=false;
      opts.forEach(function(o){
        var show=!!fase.value && o.dataset.phase===fase.value;
        o.hidden=!show;
        o.disabled=!show;
        if(show&&o.selected)keep=true;
      });
      if(!keep)ato.value='';
      ato.options[0].textContent=fase.value?'Selecione o Ato':'Selecione primeiro a fase';
    }
    fase.addEventListener('change',syncAtos);
    syncAtos();
  }
});
</script>

