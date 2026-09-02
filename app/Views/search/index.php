<?php use App\Core\Format; ?>
<div class="inp-search-page">
    <section class="inp-search-hero">
        <div class="inp-search-hero-main">
            <span class="inp-search-eyebrow"><i>⌕</i> Pesquisa integrada</span>
            <h1>Busca Global</h1>
            <p>Encontre rapidamente informações em toda a plataforma, com resultados organizados por contexto e respeitando as permissões do perfil autenticado.</p>
            <div class="inp-search-hero-domains">
                <span>▥ Municípios</span>
                <span>◉ Usuários</span>
                <span>▤ Secretarias</span>
                <span>▣ Documentos</span>
                <span>◇ Fases</span>
                <span>⌁ Arquivos</span>
                <span>⌖ Território</span>
            </div>
        </div>
        <div class="inp-search-hero-side">
            <article><small>Pesquisa simultânea</small><b>7 áreas</b><span>Uma consulta, múltiplas fontes da plataforma.</span></article>
            <article><small>Acesso protegido</small><b>Escopo seguro</b><span>Você só encontra o que já tem permissão para acessar.</span></article>
            <article class="shortcut"><small>Atalho rápido</small><b>Ctrl + K</b><span>Abra a busca de qualquer tela do INPACTA by Stratelli.</span></article>
        </div>
    </section>

    <section class="inp-search-console">
        <div class="inp-search-console-head">
            <div><small>LOCALIZAR NA PLATAFORMA</small><h2>O que você procura?</h2></div>
            <span class="inp-search-console-status">● Pesquisa em tempo real</span>
        </div>
        <form class="inp-search-form" action="/busca" method="get">
            <div class="inp-search-field">
                <span class="inp-search-field-icon">⌕</span>
                <input type="search" name="q" value="<?=Format::h($query)?>" placeholder="Digite ETP, Secretaria de Obras, contrato, usuário, município, arquivo..." autocomplete="off" autofocus>
                <?php if($query!==''):?><a class="inp-search-clear" href="/busca" aria-label="Limpar busca">×</a><?php endif;?>
            </div>
            <button type="submit"><span>Pesquisar</span><b>→</b></button>
        </form>
        <div class="inp-search-quick">
            <span>Exemplos rápidos:</span>
            <a href="/busca?q=ETP">ETP</a>
            <a href="/busca?q=Obras">Obras</a>
            <a href="/busca?q=Contrato">Contrato</a>
            <a href="/busca?q=Maring%C3%A1">Maringá</a>
            <a href="/busca?q=Termo+de+Refer%C3%AAncia">Termo de Referência</a>
        </div>
        <div class="inp-search-security"><span>✓</span><div><b>Escopo de segurança aplicado</b><p>A pesquisa respeita o município, a Secretaria, o Departamento e as permissões do perfil. Para a Stratelli, a busca alcança as instâncias sob administração da plataforma.</p></div></div>
    </section>

    <?php if((function_exists('mb_strlen')?mb_strlen($query,'UTF-8'):strlen($query))<2): ?>
        <section class="inp-search-discovery">
            <div class="inp-search-section-title"><div><small>EXPLORAR</small><h2>Pesquise por qualquer referência operacional</h2><p>A Busca Global conecta os principais cadastros e registros do INPACTA by Stratelli em uma única experiência.</p></div></div>
            <div class="inp-search-discovery-grid">
                <article><div class="inp-search-discovery-icon">▣</div><div><h3>Processos e documentos</h3><p>Localize fases, ETPs, termos, requisitos, entregáveis e documentos configurados.</p></div><span>Fases · Documentos</span></article>
                <article><div class="inp-search-discovery-icon">⌁</div><div><h3>Arquivos e versões</h3><p>Encontre arquivos enviados, modelos documentais e versões auditadas.</p></div><span>Uploads · Modelos</span></article>
                <article><div class="inp-search-discovery-icon">◉</div><div><h3>Pessoas e unidades</h3><p>Pesquise usuários, Secretarias e unidades vinculadas às instâncias municipais.</p></div><span>Usuários · Secretarias</span></article>
                <article><div class="inp-search-discovery-icon">⌖</div><div><h3>Municípios e território</h3><p>Consulte clientes, objetos territoriais, endereços e camadas de inteligência.</p></div><span>Municípios · Território</span></article>
            </div>
        </section>
    <?php elseif(!$groups): ?>
        <section class="inp-search-no-results">
            <div class="inp-search-no-results-icon">⌕</div>
            <small>NENHUMA CORRESPONDÊNCIA</small>
            <h2>Não encontramos resultados para “<?=Format::h($query)?>”</h2>
            <p>Tente usar uma parte menor do nome, uma sigla, o título do documento, o nome do arquivo, a fase ou o município.</p>
            <a href="/busca">Limpar pesquisa</a>
        </section>
    <?php else: ?>
        <section class="inp-search-result-summary">
            <div class="inp-search-result-summary-copy">
                <small>RESULTADOS ENCONTRADOS</small>
                <h2>“<?=Format::h($query)?>”</h2>
                <p><?=$displayed?> correspondência(s) exibida(s), distribuída(s) em <?=$groupCount?> área(s).</p>
            </div>
            <div class="inp-search-result-stats">
                <article><small>Resultados</small><b><?=$displayed?></b></article>
                <article><small>Áreas</small><b><?=$groupCount?></b></article>
            </div>
        </section>

        <nav class="inp-search-tabs" aria-label="Categorias da busca">
            <?php foreach($groups as $g):?>
                <a href="#busca-<?=Format::h($g['key'])?>"><span><?=Format::h($g['icon'])?></span><em><?=Format::h($g['label'])?></em><b><?=$g['count']?></b></a>
            <?php endforeach;?>
        </nav>

        <div class="inp-search-groups">
        <?php foreach($groups as $g): ?>
            <section class="inp-search-group" id="busca-<?=Format::h($g['key'])?>">
                <header class="inp-search-group-head">
                    <div class="inp-search-group-icon"><?=Format::h($g['icon'])?></div>
                    <div><small>CATEGORIA</small><h2><?=Format::h($g['label'])?></h2><p><?=$g['count']?> correspondência(s)</p></div>
                    <span><?=$g['count']?></span>
                </header>
                <div class="inp-search-items">
                <?php foreach($g['items'] as $item): ?>
                    <a class="inp-search-item" href="<?=Format::h($item['url'])?>">
                        <div class="inp-search-item-mark"><?=Format::h($g['icon'])?></div>
                        <div class="inp-search-item-content">
                            <div class="inp-search-item-title"><strong><?=Format::h($item['title'])?></strong><span><?=Format::h($item['badge'])?></span></div>
                            <p><?=Format::h($item['subtitle'])?></p>
                            <small><?=Format::h($item['meta'])?></small>
                        </div>
                        <div class="inp-search-item-side"><span><?=Format::h($item['municipio'])?></span><b>Abrir resultado <i>→</i></b></div>
                    </a>
                <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
