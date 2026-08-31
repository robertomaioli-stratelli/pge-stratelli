<?php use App\Core\Format; ?>
<div class="module-page admin-config-hub">
    <div class="module-hero">
        <div>
            <h1>Configurações</h1>
            <p>Administração central das estruturas que compõem o Workflow de cada município.</p>
        </div>
        <span class="module-context">⚙ Administração Stratelli</span>
    </div>

    <section class="card admin-config-selector">
        <div>
            <h2>Município a configurar</h2>
            <p>As configurações são individualizadas por instância. Selecione o cliente antes de alterar fases, secretarias, tipos ou documentos.</p>
        </div>
        <form method="get" action="/admin/configuracoes" class="admin-config-municipality-form">
            <label>Município
                <select name="municipio" onchange="this.form.submit()">
                    <?php foreach($municipios as $m):?>
                    <option value="<?=$m['id']?>" <?=($selected&&(int)$selected['id']===(int)$m['id'])?'selected':''?>><?=Format::h($m['nome'].' - '.$m['uf'])?></option>
                    <?php endforeach;?>
                </select>
            </label>
        </form>
    </section>

    <?php if($selected):$slug=$selected['slug'];?>
    <section class="admin-config-tenant card">
        <div class="admin-config-tenant-head">
            <div class="admin-config-tenant-identity">
                <?php if(!empty($selected['brasao_path'])):?><img src="/media/municipios/<?=$selected['id']?>/brasao?v=<?=substr(sha1((string)$selected['brasao_path']),0,12)?>" loading="eager" decoding="async" alt="Brasão de <?=Format::h($selected['nome'])?>"><?php else:?><span class="admin-config-coat-placeholder">▥</span><?php endif;?>
                <div><small>INSTÂNCIA SELECIONADA</small><h2><?=Format::h($selected['nome'].' - '.$selected['uf'])?></h2><p>/<?=Format::h($slug)?> · <?=Format::h($selected['status'])?></p></div>
            </div>
            <a class="btn primary" href="/<?=Format::h($slug)?>/configuracoes?secao=parametros">Abrir configurações</a>
        </div>

        <nav class="config-tabs admin-config-tabs">
            <a href="/<?=Format::h($slug)?>/configuracoes?secao=parametros"><strong>1. Parâmetros da Instância</strong><span>Regras operacionais, módulos, etapa e identidade</span></a>
            <a href="/<?=Format::h($slug)?>/configuracoes?secao=fases"><strong>2. Fases</strong><span><?=$stats['fases']?> cadastrada(s)</span></a>
            <a href="/<?=Format::h($slug)?>/configuracoes?secao=secretarias"><strong>3. Secretarias e Departamentos</strong><span><?=$stats['secretarias']?> secretaria(s) · <?=$stats['departamentos']?> departamento(s)</span></a>
            <a href="/<?=Format::h($slug)?>/configuracoes?secao=tipos"><strong>4. Tipos de Documento</strong><span><?=$stats['tipos']?> tipo(s) cadastrado(s)</span></a>
            <a href="/<?=Format::h($slug)?>/configuracoes?secao=documentos"><strong>5. Documentos e Modelos</strong><span><?=$stats['documentos']?> documento(s) · <?=$stats['modelos']?> com modelo</span></a>
            <a href="/<?=Format::h($slug)?>/territorio?modo=configuracao"><strong>6. Dados Territoriais</strong><span><?=$stats['camadas']?> camada(s) · <?=$stats['objetos_territoriais']?> objeto(s) · <?=((int)($selected['inteligencia_territorial_ativa']??0)===1)?'ATIVA PARA MUNICÍPIO':'INATIVA PARA MUNICÍPIO'?></span></a>
        </nav>

        <div class="admin-config-info-grid">
            <article><span class="admin-config-step">1</span><div><b>Parâmetros da Instância</b><p>Centralize alertas, limites de arquivo, notificações, observações obrigatórias, etapa atual e identidade visual.</p></div></article>
            <article><span class="admin-config-step">2</span><div><b>Fases</b><p>Cadastre novas fases, ordem, prazo, responsável e entregáveis do processo.</p></div></article>
            <article><span class="admin-config-step">3</span><div><b>Secretarias e Departamentos</b><p>Monte a estrutura administrativa e defina em quais fases cada unidade participa.</p></div></article>
            <article><span class="admin-config-step">4</span><div><b>Tipos de Documento</b><p>Defina categorias documentais e extensões de arquivos permitidas.</p></div></article>
            <article><span class="admin-config-step">5</span><div><b>Documentos e Modelos</b><p>Vincule cada documento à fase, secretaria, departamento, tipo, perfil de envio e modelo.</p></div></article>
            <article><span class="admin-config-step">6</span><div><b>Dados Territoriais</b><p>Crie camadas, cadastre pontos, linhas e polígonos e vincule objetos territoriais ao Workflow.</p></div></article>
        </div>
    </section>
    <?php else:?>
    <div class="empty-state">Cadastre um município para começar a configurar o Workflow.</div>
    <?php endif;?>
</div>
