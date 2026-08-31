<?php
use App\Core\Csrf;
use App\Services\MunicipalityCardService;
$municipios = (new MunicipalityCardService())->enrich($municipios ?? []);
$totalMunicipios = count($municipios ?? []);
$totalAtivos = count(array_filter($municipios ?? [], fn($m) => (string)($m['status']??'') === 'ATIVO'));
$totalTerritorial = count(array_filter($municipios ?? [], fn($m) => !empty($m['inteligencia_territorial_ativa'])));
$totalUsuarios = array_sum(array_map(fn($m) => (int)($m['usuarios_ativos'] ?? 0), $municipios ?? []));
$prontidaoMedia = $totalMunicipios ? (int)round(array_sum(array_map(fn($m)=>(int)($m['implantacao_prontidao']??0),$municipios))/$totalMunicipios) : 0;
?>
<div class="module-page municipality-admin" id="municipalityAdmin">
    <div class="module-hero">
        <div>
            <h1>Municípios</h1>
            <p>Cadastro e administração das instâncias clientes.</p>
        </div>
        <span class="municipality-total-chip"><?=$totalMunicipios?> cliente(s)</span>
    </div>

    <?php if(!empty($erro)):?><div class="flash error"><?=htmlspecialchars($erro)?></div><?php endif;?>
    <?php if(!empty($ok)):?><div class="flash ok"><?=htmlspecialchars($ok)?></div><?php endif;?>

    <div class="municipality-summary-grid">
        <div class="municipality-summary-card"><small>CLIENTES CADASTRADOS</small><strong><?=$totalMunicipios?></strong><span>Total da carteira</span></div>
        <div class="municipality-summary-card is-green"><small>CLIENTES ATIVOS</small><strong><?=$totalAtivos?></strong><span>Instâncias ativas</span></div>
        <div class="municipality-summary-card is-blue"><small>INTELIGÊNCIA TERRITORIAL</small><strong><?=$totalTerritorial?></strong><span>Municípios com módulo ativo</span></div>
        <div class="municipality-summary-card is-neutral"><small>USUÁRIOS ATIVOS</small><strong><?=$totalUsuarios?></strong><span>Somados em todos os clientes</span></div>
        <div class="municipality-summary-card is-readiness"><small>PRONTIDÃO MÉDIA</small><strong><?=$prontidaoMedia?>%</strong><span>Implantação da carteira</span></div>
    </div>

    <details class="card municipality-create-panel" <?=empty($municipios)?'open':''?>>
        <summary>
            <span><b>＋ Cadastrar novo município</b><small>Novo cliente + gestor inicial</small></span>
            <span class="municipality-summary-action">Abrir cadastro</span>
        </summary>
        <div class="municipality-create-body">
            <p>O cadastro exige um gestor inicial. A Inteligência Territorial será criada <b>inativa por padrão</b>.</p>
            <form method="post" action="/admin/municipios" class="production-form municipality-create-form">
                <input type="hidden" name="_token" value="<?=htmlspecialchars(Csrf::token())?>">
                <label>Município<input name="nome" required></label>
                <label>UF<input name="uf" maxlength="2" required></label>
                <label>Slug da instância<input name="slug" placeholder="ex.: maringa"></label>
                <div class="municipality-form-separator"><span>Gestor inicial</span></div>
                <label>Nome do gestor<input name="gestor_nome" required></label>
                <label>E-mail do gestor<input type="email" name="gestor_email" required></label>
                <label>Senha inicial<input type="password" name="gestor_senha" minlength="10" required></label>
                <div class="municipality-form-separator"><span>Identidade visual inicial</span></div>
                <label>Cor principal dos cabeçalhos<input type="color" name="cor_primaria" value="#082A55"></label>
                <label>Cor secundária<input type="color" name="cor_secundaria" value="#176FDD"></label>
                <label>Detalhe dos cabeçalhos<select name="estilo_decoracao_cabecalho"><option value="semicirculo" selected>Semicírculo suave</option><option value="nenhuma">Sem decoração</option></select></label>
                <div class="municipality-create-actions"><button class="btn primary">Criar município e gestor</button></div>
            </form>
        </div>
    </details>

    <section class="card municipality-catalog-card">
        <div class="municipality-catalog-head">
            <div>
                <h2>Clientes cadastrados</h2>
                <p>Localize, filtre e organize rapidamente as instâncias da carteira Stratelli.</p>
            </div>
            <span class="municipality-result-count" id="municipalityResultCount"><?=$totalMunicipios?> resultado(s)</span>
        </div>

        <div class="municipality-toolbar">
            <label class="municipality-search-field">
                <span>Buscar</span>
                <input type="search" id="municipalitySearch" placeholder="Município, UF ou slug..." autocomplete="off">
            </label>
            <label>
                <span>Status</span>
                <select id="municipalityStatusFilter">
                    <option value="all">Todos</option>
                    <option value="active">Ativos</option>
                    <option value="implementation">Implantação</option>
                    <option value="negotiation">Negociação</option>
                    <option value="presentation">Apresentação</option>
                    <option value="suspended">Suspensos</option>
                    <option value="inactive">Desativados</option>
                </select>
            </label>
            <label>
                <span>Inteligência Territorial</span>
                <select id="municipalityTerritorialFilter">
                    <option value="all">Todos</option>
                    <option value="active">Ativa</option>
                    <option value="inactive">Inativa</option>
                </select>
            </label>
            <label>
                <span>Ordenar por</span>
                <select id="municipalitySort">
                    <option value="name-asc">Município A–Z</option>
                    <option value="name-desc">Município Z–A</option>
                    <option value="users-desc">Mais usuários</option>
                    <option value="users-asc">Menos usuários</option>
                    <option value="managers-desc">Mais gestores</option>
                    <option value="readiness-desc">Maior prontidão</option>
                    <option value="readiness-asc">Menor prontidão</option>
                    <option value="status-desc">Ativos primeiro</option>
                </select>
            </label>
        </div>

        <div class="municipality-status-legend" aria-label="Legenda de status dos municípios">
            <span class="municipality-legend-title">Legenda:</span>
            <span class="municipality-legend-item"><i class="municipality-legend-dot active"></i> Ativo</span>
            <span class="municipality-legend-item"><i class="municipality-legend-dot implementation"></i> Implantação</span>
            <span class="municipality-legend-item"><i class="municipality-legend-dot negotiation"></i> Negociação</span>
            <span class="municipality-legend-item"><i class="municipality-legend-dot presentation"></i> Apresentação</span>
            <span class="municipality-legend-item"><i class="municipality-legend-dot suspended"></i> Suspenso</span>
            <span class="municipality-legend-item"><i class="municipality-legend-dot inactive"></i> Desativado</span>
        </div>

        <div class="municipality-grid municipality-management-grid" id="municipalityGrid">
            <?php foreach($municipios as $m):
                $territorial=(bool)($m['inteligencia_territorial_ativa']??false);
                $hasGeojson=trim((string)($m['geojson_delimitacao']??''))!=='';
                $nome=(string)($m['nome']??'');
                $uf=(string)($m['uf']??'');
                $slug=(string)($m['slug']??'');
                $ativo=!empty($m['ativo']);
                $usuarios=(int)($m['usuarios_ativos']??0);
                $gestores=(int)($m['gestores_ativos']??0);
                $faseAtual=$m['catalogo_fase_atual']??null;
                $etapaConcluida=!empty($m['catalogo_etapa_concluida']);
                $docsFaltantes=(int)($m['catalogo_documentos_faltantes']??0);
                $docsFaseTotal=(int)($m['catalogo_documentos_fase_total']??0);
                $docsFaseAprovados=(int)($m['catalogo_documentos_fase_aprovados']??0);
                $faseProntaEncerramento=!empty($m['catalogo_fase_pronta_encerramento']);
                $fasesTotal=(int)($m['catalogo_fases_total']??0);
                $fasesConcluidas=(int)($m['catalogo_fases_concluidas']??0);
                $readiness=(int)($m['implantacao_prontidao']??0);
                $readinessStatus=(string)($m['implantacao_status']??'initial');
                $readinessLabel=(string)($m['implantacao_status_label']??'CONFIGURAÇÃO INICIAL');
                $readinessChecklist=$m['implantacao_checklist']??[];
                $initial=htmlspecialchars(mb_strtoupper(mb_substr($nome,0,1,'UTF-8'),'UTF-8'));
                $statusOriginal=mb_strtolower((string)($m['status']??''),'UTF-8');
                $statusSemAcento=strtr($statusOriginal,['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c']);
                if(str_contains($statusSemAcento,'negoci')){
                    $lifecycle='negotiation';$lifecycleClass='status-negotiation';$lifecycleLabel='NEGOCIAÇÃO';
                } elseif(str_contains($statusSemAcento,'apresent')){
                    $lifecycle='presentation';$lifecycleClass='status-presentation';$lifecycleLabel='APRESENTAÇÃO';
                } elseif(str_contains($statusSemAcento,'implant')){
                    $lifecycle='implementation';$lifecycleClass='status-implementation';$lifecycleLabel='IMPLANTAÇÃO';
                } elseif(str_contains($statusSemAcento,'suspens')){
                    $lifecycle='suspended';$lifecycleClass='status-suspended';$lifecycleLabel='SUSPENSO';
                } elseif(str_contains($statusSemAcento,'desativ') || str_contains($statusSemAcento,'inativ') || !$ativo){
                    $lifecycle='inactive';$lifecycleClass='status-disabled';$lifecycleLabel='DESATIVADO';
                } else {
                    $lifecycle='active';$lifecycleClass='status-active';$lifecycleLabel='ATIVO';
                }
            ?>
                <article class="municipality-card municipality-management-card <?=$lifecycleClass?>"
                    data-name="<?=htmlspecialchars(mb_strtolower($nome,'UTF-8'))?>"
                    data-search="<?=htmlspecialchars(mb_strtolower($nome.' '.$uf.' '.$slug.' '.($m['status']??''),'UTF-8'))?>"
                    data-active="<?=$ativo?'1':'0'?>"
                    data-lifecycle="<?=$lifecycle?>"
                    data-territorial="<?=$territorial?'1':'0'?>"
                    data-users="<?=$usuarios?>"
                    data-managers="<?=$gestores?>"
                    data-readiness="<?=$readiness?>">
                    <div class="municipality-card-head municipality-management-head">
                        <div class="municipality-client-title">
                            <span class="municipality-initial municipality-client-brand">
                                <?php if(!empty($m['brasao_path'])):?><img class="municipality-client-coat" src="/media/municipios/<?=intval($m['id'])?>/brasao?v=<?=substr(sha1((string)$m['brasao_path']),0,12)?>" loading="lazy" decoding="async" alt="Brasão de <?=htmlspecialchars($nome)?>"><span class="municipality-client-fallback" hidden><?=$initial?></span><?php else:?><span class="municipality-client-fallback"><?=$initial?></span><?php endif;?>
                            </span>
                            <div>
                                <small>MUNICÍPIO CLIENTE</small>
                                <h3><?=htmlspecialchars($nome)?> <span>— <?=htmlspecialchars($uf)?></span></h3>
                                <p>/<?=htmlspecialchars($slug)?></p>
                            </div>
                        </div>
                        <span class="module-status municipality-lifecycle-badge <?=$lifecycleClass?>"><?=$lifecycleLabel?></span>
                    </div>

                    <div class="municipality-metrics municipality-management-metrics">
                        <div><b><?=$usuarios?></b><small>Usuários ativos</small></div>
                        <div><b><?=$gestores?></b><small>Gestores ativos</small></div>
                    </div>

                    <?php if($etapaConcluida):?>
                        <div class="municipality-process-card is-completed">
                            <div class="municipality-process-main"><small>ANDAMENTO DO PROCESSO</small><b>✓ Etapa concluída</b><span><?=$fasesConcluidas?> de <?=$fasesTotal?> fase(s) encerrada(s)</span></div>
                            <div class="municipality-process-count"><strong>100%</strong><small>PROCESSO CONCLUÍDO</small></div>
                        </div>
                    <?php elseif($faseAtual):?>
                        <div class="municipality-process-card">
                            <div class="municipality-process-main">
                                <small>FASE ATUAL</small>
                                <b>Fase <?=htmlspecialchars((string)($faseAtual['ordem']??''))?> — <?=htmlspecialchars((string)($faseAtual['aba']??$faseAtual['titulo']??'Fase'))?></b>
                                <span><?php if($docsFaseTotal>0):?><?=$docsFaseAprovados?> de <?=$docsFaseTotal?> documento(s) obrigatório(s) aprovado(s)<?php else:?>Conclusão depende da finalização da fase<?php endif;?></span>
                            </div>
                            <?php if($faseProntaEncerramento):?><div class="municipality-process-count is-ready"><strong>100%</strong><small>AGUARDA ENCERRAMENTO</small></div><?php else:?><div class="municipality-process-count <?=$docsFaltantes===0?'is-zero':''?>"><strong><?=$docsFaltantes?></strong><small><?=$docsFaltantes===1?'DOCUMENTO FALTANTE':'DOCUMENTOS FALTANTES'?></small></div><?php endif;?>
                        </div>
                    <?php else:?>
                        <div class="municipality-process-card is-empty">
                            <div class="municipality-process-main"><small>ANDAMENTO DO PROCESSO</small><b>Aguardando configuração</b><span>Nenhuma fase ativa cadastrada para esta instância.</span></div>
                            <div class="municipality-process-count is-zero"><strong>—</strong><small>FASE ATUAL</small></div>
                        </div>
                    <?php endif;?>

                    <details class="municipality-readiness-card readiness-<?=$readinessStatus?>">
                        <summary>
                            <div class="municipality-readiness-summary-copy">
                                <small>PRONTIDÃO DE IMPLANTAÇÃO</small>
                                <b><?=$readinessLabel?></b>
                                <span>Checklist calculado automaticamente com os dados da instância.</span>
                            </div>
                            <div class="municipality-readiness-score"><strong><?=$readiness?>%</strong><small>PRONTIDÃO</small></div>
                        </summary>
                        <div class="municipality-readiness-progress" aria-label="Prontidão da implantação em <?=$readiness?>%"><span style="width:<?=$readiness?>%"></span></div>
                        <div class="municipality-readiness-list">
                            <?php foreach($readinessChecklist as $item):
                                $informational=!empty($item['informational']);
                                $done=!empty($item['done']);
                                $partial=!empty($item['partial']);
                                $itemClass=$informational?'is-info':($done?'is-done':($partial?'is-partial':'is-pending'));
                                $itemIcon=$informational?'i':($done?'✓':($partial?'◐':'×'));
                            ?>
                                <div class="municipality-readiness-item <?=$itemClass?>">
                                    <span class="municipality-readiness-icon"><?=$itemIcon?></span>
                                    <div><b><?=htmlspecialchars((string)$item['label'])?></b><small><?=htmlspecialchars((string)$item['detail'])?></small></div>
                                </div>
                            <?php endforeach;?>
                        </div>
                        <div class="municipality-readiness-note">A Inteligência Territorial é informativa e não reduz a porcentagem, pois sua ativação é opcional por município.</div>
                    </details>

                    <div class="municipality-perimeter-row <?=$hasGeojson?'is-ready':'is-pending'?>">
                        <div class="municipality-perimeter-copy">
                            <span class="municipality-perimeter-icon">⌖</span>
                            <div>
                                <b>Delimitação territorial</b>
                                <small><?=$hasGeojson?'Perímetro GeoJSON cadastrado e disponível para visualização.':'Nenhum perímetro GeoJSON cadastrado para esta instância.'?></small>
                            </div>
                        </div>
                        <div class="municipality-perimeter-actions">
                            <span class="module-status <?=$hasGeojson?'approved':'pending'?>"><?=$hasGeojson?'CADASTRADA':'PENDENTE'?></span>
                            <?php if($hasGeojson):?><a class="mini-link municipality-perimeter-link" href="/admin/municipios/<?=intval($m['id'])?>#mapa-territorial">Visualizar perímetro</a><?php else:?><a class="mini-link municipality-perimeter-link" href="/admin/municipios/<?=intval($m['id'])?>#cadastro-territorial">Cadastrar delimitação</a><?php endif;?>
                        </div>
                    </div>

                    <div class="municipality-territorial-row <?=$territorial?'is-active':'is-inactive'?>">
                        <div>
                            <b>⌖ Inteligência Territorial</b>
                            <small>Disponibilidade para usuários municipais.</small>
                        </div>
                        <span class="module-status <?=$territorial?'approved':'pending'?>"><?=$territorial?'ATIVA':'INATIVA'?></span>
                    </div>

                    <div class="municipality-card-actions">
                        <a class="mini-link primary" href="/<?=htmlspecialchars($slug)?>/dashboard">Abrir instância</a>
                        <a class="mini-link" href="/admin/municipios/<?=intval($m['id'])?>">Cadastro</a>
                        <a class="mini-link" href="/admin/configuracoes?municipio=<?=intval($m['id'])?>">Configurações</a>
                        <form method="post" action="/admin/municipios/<?=intval($m['id'])?>/modulos/territorio" onsubmit="return confirm('<?=htmlspecialchars($territorial?'Desativar a Inteligência Territorial para este município? Os usuários municipais deixarão de visualizar a área.':'Ativar a Inteligência Territorial para este município?')?>')">
                            <input type="hidden" name="_token" value="<?=htmlspecialchars(Csrf::token())?>">
                            <input type="hidden" name="voltar" value="/admin/municipios">
                            <input type="hidden" name="ativo" value="<?=$territorial?'0':'1'?>">
                            <button class="mini-link municipality-territorial-action <?=$territorial?'turn-off':'turn-on'?>" type="submit">
                                <?=$territorial?'Desativar Territorial':'Ativar Territorial'?>
                            </button>
                        </form>
                    </div>
                </article>
            <?php endforeach;?>
        </div>

        <div class="municipality-empty-state" id="municipalityEmptyState" hidden>
            <b>Nenhum município encontrado.</b>
            <span>Ajuste a busca ou os filtros para visualizar outros clientes.</span>
        </div>

        <div class="municipality-pagination-bar">
            <span id="municipalityRangeText">Exibindo 0 de 0 registros</span>
            <nav class="municipality-pagination" id="municipalityPagination" aria-label="Paginação dos municípios"></nav>
        </div>
    </section>
</div>

<style>
.municipality-admin{min-width:0}.municipality-total-chip,.municipality-result-count{display:inline-flex;align-items:center;justify-content:center;border:1px solid #cfe0f5;background:#f6faff;color:#174c84;border-radius:999px;padding:7px 11px;font-size:11px;font-weight:900;white-space:nowrap}.municipality-summary-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;margin-bottom:16px}.municipality-summary-card{min-width:0;background:#fff;border:1px solid #d8e1eb;border-top:3px solid #0d4c85;border-radius:14px;padding:14px 16px;display:grid;gap:5px}.municipality-summary-card.is-green{border-top-color:#20a454}.municipality-summary-card.is-blue{border-top-color:#176fdd}.municipality-summary-card.is-neutral{border-top-color:#8d9bad}.municipality-summary-card.is-readiness{border-top-color:#6b35da;background:linear-gradient(180deg,#fff 0%,#faf8ff 100%)}.municipality-summary-card small{font-size:10px;font-weight:900;color:#5e7088}.municipality-summary-card strong{font-size:25px;color:#062e5b}.municipality-summary-card span{font-size:11px;color:#60708a}.municipality-create-panel{margin-bottom:16px;overflow:hidden}.municipality-create-panel>summary{list-style:none;cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:14px;padding:15px 18px}.municipality-create-panel>summary::-webkit-details-marker{display:none}.municipality-create-panel>summary span:first-child{display:grid;gap:3px}.municipality-create-panel>summary small{font-size:11px;color:#60708a;font-weight:500}.municipality-summary-action{font-size:11px;font-weight:900;color:#0d4c85}.municipality-create-body{border-top:1px solid #e4ebf2;padding:18px}.municipality-create-body>p{margin-top:0;color:#60708a;font-size:12px}.municipality-create-form{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px!important}.municipality-form-separator{grid-column:1/-1;border-top:1px solid #e1e8f0;margin-top:3px;padding-top:10px}.municipality-form-separator span{font-size:11px;font-weight:900;color:#173f69}.municipality-create-actions{grid-column:1/-1}.municipality-catalog-card{overflow:hidden}.municipality-catalog-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;padding:18px 18px 12px}.municipality-catalog-head h2{margin:0 0 4px}.municipality-catalog-head p{margin:0;font-size:11px;color:#60708a}.municipality-toolbar{display:grid;grid-template-columns:minmax(220px,1.5fr) repeat(3,minmax(145px,.7fr));gap:10px;padding:0 18px 16px;border-bottom:1px solid #e2e9f1}.municipality-toolbar label{display:grid;gap:5px;min-width:0}.municipality-toolbar label>span{font-size:9px;font-weight:900;color:#536781;text-transform:uppercase}.municipality-toolbar input,.municipality-toolbar select{width:100%;min-width:0;height:40px;border:1px solid #cfdae7;border-radius:10px;background:#fff;padding:0 11px;color:#183153}.municipality-status-legend{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:10px 18px 0;color:#536781;font-size:10px}.municipality-legend-title{font-weight:900;color:#173f69}.municipality-legend-item{display:inline-flex;align-items:center;gap:6px;font-weight:800}.municipality-legend-dot{display:inline-block;width:10px;height:10px;border-radius:999px;border:1px solid transparent}.municipality-legend-dot.active{background:#20a454;border-color:#16823f}.municipality-legend-dot.implementation{background:#176fdd;border-color:#0f59b5}.municipality-legend-dot.inactive{background:#dc4a42;border-color:#b8322b}.municipality-management-grid{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:14px!important;padding:18px}.municipality-management-card{min-width:0;margin:0!important;border-top-width:4px!important;transition:border-color .18s ease,background-color .18s ease}.municipality-management-card.status-active{border-color:#bfe8cc!important;border-top-color:#20a454!important;background:#f7fcf8!important}.municipality-management-card.status-implementation{border-color:#c9ddf7!important;border-top-color:#176fdd!important;background:#f6faff!important}.municipality-management-card.status-disabled{border-color:#efcbc8!important;border-top-color:#dc4a42!important;background:#fff8f7!important}
.municipality-legend-dot.negotiation{background:#7a3fe0;border-color:#5e2fb3}
.municipality-legend-dot.presentation{background:#0f8b8d;border-color:#0a6f71}
.municipality-legend-dot.suspended{background:#e3a11a;border-color:#b87c08}
.municipality-management-card.status-negotiation{border-color:#d8c5f8!important;border-top-color:#7a3fe0!important;background:#fbf8ff!important}
.municipality-management-card.status-presentation{border-color:#bfe5e5!important;border-top-color:#0f8b8d!important;background:#f6fdfd!important}
.municipality-management-card.status-suspended{border-color:#efd28f!important;border-top-color:#e3a11a!important;background:#fffbf2!important}
.municipality-lifecycle-badge.status-active{background:#e6f7eb!important;color:#117438!important;border-color:#bfe8cc!important}.municipality-lifecycle-badge.status-implementation{background:#e8f2ff!important;color:#176fdd!important;border-color:#c9ddf7!important}.municipality-lifecycle-badge.status-negotiation{background:#f1e9ff!important;color:#6d31c9!important;border-color:#d9c5fb!important}.municipality-lifecycle-badge.status-presentation{background:#e8f8f8!important;color:#087b80!important;border-color:#bfe5e5!important}.municipality-lifecycle-badge.status-suspended{background:#fff4dc!important;color:#9a6300!important;border-color:#efd28f!important}.municipality-lifecycle-badge.status-disabled{background:#fdebea!important;color:#b8322b!important;border-color:#efcbc8!important}.municipality-management-card.status-active .municipality-management-head{border-bottom-color:#d9eee0}.municipality-management-card.status-implementation .municipality-management-head{border-bottom-color:#dbe8f8}.municipality-management-card.status-negotiation .municipality-management-head{border-bottom-color:#eadffb}.municipality-management-card.status-presentation .municipality-management-head{border-bottom-color:#d9eeee}.municipality-management-card.status-suspended .municipality-management-head{border-bottom-color:#f3e2b7}.municipality-management-card.status-disabled .municipality-management-head{border-bottom-color:#f2dedd}.municipality-management-head{align-items:flex-start}.municipality-client-title{display:flex;align-items:center;gap:11px;min-width:0}.municipality-initial{flex:0 0 42px;width:42px;height:42px;border-radius:12px;background:#edf5ff;border:1px solid #d4e5fa;display:flex;align-items:center;justify-content:center;color:#0c4a83;font-size:19px;font-weight:900}.municipality-client-title>div{min-width:0}.municipality-client-brand{overflow:hidden;padding:3px;background:#fff}.municipality-client-coat{display:block;width:100%;height:100%;object-fit:contain;border-radius:8px}.municipality-client-fallback{width:100%;height:100%;align-items:center;justify-content:center}.municipality-client-fallback:not([hidden]){display:flex}.municipality-process-card{display:grid;grid-template-columns:minmax(0,1fr) minmax(105px,135px);gap:12px;align-items:stretch;margin:12px 0;padding:11px 12px;border:1px solid #cfe0f5;border-left:4px solid #176fdd;border-radius:11px;background:#f7fbff}.municipality-process-main{min-width:0;display:grid;align-content:center;gap:3px}.municipality-process-main small{font-size:8px;font-weight:900;color:#5e7088}.municipality-process-main b{font-size:12px;color:#082f5d;overflow-wrap:anywhere}.municipality-process-main span{font-size:9px;color:#60708a}.municipality-process-count{display:grid;place-content:center;text-align:center;border:1px solid #f0c7c3;background:#fff4f3;border-radius:9px;padding:8px;min-width:0}.municipality-process-count strong{font-size:22px;line-height:1;color:#c23b33}.municipality-process-count small{margin-top:4px;font-size:7px;font-weight:900;color:#8d4540}.municipality-process-count.is-zero{border-color:#cfe0f5;background:#fff}.municipality-process-count.is-zero strong{color:#176fdd}.municipality-process-count.is-zero small{color:#536781}.municipality-process-card.is-completed{border-color:#bfe8cc;border-left-color:#20a454;background:#f4fbf6}.municipality-process-card.is-completed .municipality-process-main b{color:#126d37}.municipality-process-card.is-completed .municipality-process-count{border-color:#bfe8cc;background:#fff}.municipality-process-card.is-completed .municipality-process-count strong,.municipality-process-card.is-completed .municipality-process-count small{color:#15813f}.municipality-process-card.is-empty{border-color:#dbe3ec;border-left-color:#8d9bad;background:#f8fafc}.municipality-process-card.is-empty .municipality-process-main b{color:#536781}.municipality-client-title small{font-size:8px;font-weight:900;color:#60708a}.municipality-client-title h3{margin:2px 0 1px;font-size:16px;overflow-wrap:anywhere}.municipality-client-title h3 span{font-size:11px;color:#5c7088;font-weight:700}.municipality-client-title p{margin:0;font-size:10px;color:#708095}.municipality-management-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.municipality-readiness-card{margin:12px 0;border:1px solid #d8e2ee;border-radius:12px;background:#fff;overflow:hidden}.municipality-readiness-card>summary{list-style:none;cursor:pointer;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:center;padding:12px 13px}.municipality-readiness-card>summary::-webkit-details-marker{display:none}.municipality-readiness-summary-copy{display:grid;gap:2px;min-width:0}.municipality-readiness-summary-copy small{font-size:8px;font-weight:900;letter-spacing:.04em;color:#62748a}.municipality-readiness-summary-copy b{font-size:12px;color:#113c69}.municipality-readiness-summary-copy span{font-size:9px;line-height:1.35;color:#6b7d91}.municipality-readiness-score{display:grid;place-items:center;min-width:78px;padding:8px 10px;border-radius:10px;background:#f3f6fa;border:1px solid #dce5ef}.municipality-readiness-score strong{font-size:21px;line-height:1;color:#37516d}.municipality-readiness-score small{margin-top:4px;font-size:7px;font-weight:900;color:#65778c}.municipality-readiness-card.readiness-ready{border-color:#bfe8cc;background:#f7fcf8}.municipality-readiness-card.readiness-ready .municipality-readiness-score{background:#eaf8ef;border-color:#bfe8cc}.municipality-readiness-card.readiness-ready .municipality-readiness-score strong{color:#15813f}.municipality-readiness-card.readiness-advanced{border-color:#c9ddf7;background:#f8fbff}.municipality-readiness-card.readiness-advanced .municipality-readiness-score{background:#eaf3ff;border-color:#c9ddf7}.municipality-readiness-card.readiness-advanced .municipality-readiness-score strong{color:#176fdd}.municipality-readiness-card.readiness-partial{border-color:#efd28f;background:#fffcf5}.municipality-readiness-card.readiness-partial .municipality-readiness-score{background:#fff4dc;border-color:#efd28f}.municipality-readiness-card.readiness-partial .municipality-readiness-score strong{color:#a66d00}.municipality-readiness-card.readiness-initial{border-color:#e0d5f2;background:#fcfaff}.municipality-readiness-card.readiness-initial .municipality-readiness-score{background:#f4effb;border-color:#e0d5f2}.municipality-readiness-card.readiness-initial .municipality-readiness-score strong{color:#6b35da}.municipality-readiness-progress{height:7px;margin:0 13px 12px;border-radius:999px;background:#e9eef4;overflow:hidden}.municipality-readiness-progress span{display:block;height:100%;border-radius:inherit;background:#176fdd}.municipality-readiness-card.readiness-ready .municipality-readiness-progress span{background:#20a454}.municipality-readiness-card.readiness-partial .municipality-readiness-progress span{background:#e3a11a}.municipality-readiness-card.readiness-initial .municipality-readiness-progress span{background:#7a3fe0}.municipality-readiness-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px;padding:0 13px 13px}.municipality-readiness-item{display:grid;grid-template-columns:24px minmax(0,1fr);gap:8px;align-items:center;padding:8px 9px;border:1px solid #e2e8f0;border-radius:9px;background:#fff}.municipality-readiness-icon{display:grid;place-items:center;width:24px;height:24px;border-radius:999px;background:#eef2f6;color:#60708a;font-size:11px;font-weight:900}.municipality-readiness-item div{display:grid;gap:2px;min-width:0}.municipality-readiness-item b{font-size:9px;color:#173f69}.municipality-readiness-item small{font-size:8px;line-height:1.3;color:#6c7e92}.municipality-readiness-item.is-done{border-color:#d5eadc;background:#f8fcf9}.municipality-readiness-item.is-done .municipality-readiness-icon{background:#e5f6eb;color:#15813f}.municipality-readiness-item.is-partial{border-color:#f0dfb7;background:#fffaf0}.municipality-readiness-item.is-partial .municipality-readiness-icon{background:#fff1cf;color:#9a6300}.municipality-readiness-item.is-pending{border-color:#efd4d1;background:#fff9f8}.municipality-readiness-item.is-pending .municipality-readiness-icon{background:#fdeae8;color:#b8322b}.municipality-readiness-item.is-info{border-color:#d9e5f4;background:#f8fbff}.municipality-readiness-item.is-info .municipality-readiness-icon{background:#e7f1ff;color:#176fdd}.municipality-readiness-note{margin:0 13px 13px;padding:8px 10px;border-radius:8px;background:#f4f7fa;color:#63758a;font-size:8px;line-height:1.4}.municipality-territorial-row{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;padding:10px 11px;margin:12px 0;border:1px solid #e2e9f1;border-radius:10px;background:#f8fafc}.municipality-territorial-row.is-active{background:#f4fbf6;border-color:#cdebd6}.municipality-territorial-row b{display:block;font-size:11px;color:#173f69}.municipality-territorial-row small{display:block;margin-top:2px;font-size:9px;color:#60708a}.municipality-card-actions{display:flex;gap:7px;align-items:center;flex-wrap:wrap}.municipality-card-actions form{display:inline;margin:0}.municipality-territorial-action{cursor:pointer}.municipality-territorial-action.turn-on{background:#eefaf2!important;color:#15733a!important;border-color:#bfe8cc!important}.municipality-territorial-action.turn-off{background:#fff!important;color:#8a3a35!important;border-color:#efcbc8!important}.municipality-perimeter-row{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:11px 12px;margin:12px 0;border:1px solid #dbe5ef;border-radius:10px;background:#f8fbff}.municipality-perimeter-row.is-ready{border-color:#c9ddf7;background:#f5f9ff}.municipality-perimeter-row.is-pending{border-color:#e3e8ef;background:#fafbfc}.municipality-perimeter-copy{display:flex;align-items:center;gap:9px;min-width:0}.municipality-perimeter-icon{display:grid;place-items:center;flex:0 0 31px;width:31px;height:31px;border-radius:9px;background:#e7f1ff;color:#176fdd;font-size:15px;font-weight:900}.municipality-perimeter-row.is-pending .municipality-perimeter-icon{background:#eef2f6;color:#7b8998}.municipality-perimeter-copy div{display:grid;gap:2px;min-width:0}.municipality-perimeter-copy b{font-size:11px;color:#173f69}.municipality-perimeter-copy small{font-size:9px;line-height:1.35;color:#60708a}.municipality-perimeter-actions{display:flex;align-items:center;gap:7px;flex-wrap:wrap}.municipality-perimeter-link{white-space:nowrap}.municipality-perimeter-row .module-status{font-size:8px}..municipality-empty-state{margin:10px 18px 18px;padding:28px;border:1px dashed #c9d6e4;border-radius:12px;background:#fafcff;text-align:center;color:#61728a}.municipality-empty-state b,.municipality-empty-state span{display:block}.municipality-empty-state span{margin-top:5px;font-size:11px}.municipality-pagination-bar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 18px;border-top:1px solid #e2e9f1;font-size:10px;color:#60708a}.municipality-pagination{display:flex;align-items:center;gap:5px;flex-wrap:wrap;justify-content:flex-end}.municipality-page-btn{min-width:32px;height:32px;padding:0 9px;border:1px solid #d2deeb;border-radius:8px;background:#fff;color:#174c84;font-weight:800;cursor:pointer}.municipality-page-btn:hover:not(:disabled){background:#edf5ff}.municipality-page-btn.active{background:#0d4c85;color:#fff;border-color:#0d4c85}.municipality-page-btn:disabled{opacity:.45;cursor:default}@media(max-width:1200px){.municipality-summary-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:1100px){.municipality-summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.municipality-toolbar{grid-template-columns:repeat(2,minmax(0,1fr))}.municipality-management-grid{grid-template-columns:1fr!important}}@media(max-width:720px){.municipality-summary-grid,.municipality-toolbar,.municipality-create-form{grid-template-columns:1fr!important}.municipality-management-grid{padding:12px}.municipality-catalog-head,.municipality-pagination-bar{align-items:stretch;flex-direction:column}.municipality-pagination{justify-content:flex-start}.municipality-create-panel>summary{align-items:flex-start;flex-direction:column}}
@media(max-width:720px){.municipality-readiness-list{grid-template-columns:1fr}}@media(max-width:560px){.municipality-process-card{grid-template-columns:1fr}.municipality-process-count{grid-template-columns:auto 1fr;place-content:initial;align-items:center;text-align:left;gap:8px}.municipality-process-count small{margin:0}}
</style>

<script>
(function(){
    const root=document.getElementById('municipalityAdmin');
    if(!root)return;
    root.querySelectorAll('.municipality-client-coat').forEach(img=>{img.addEventListener('error',()=>{img.hidden=true;const fallback=img.nextElementSibling;if(fallback)fallback.hidden=false;},{once:true});});
    const grid=document.getElementById('municipalityGrid');
    const allCards=[...grid.querySelectorAll('.municipality-management-card')];
    const search=document.getElementById('municipalitySearch');
    const status=document.getElementById('municipalityStatusFilter');
    const territorial=document.getElementById('municipalityTerritorialFilter');
    const sort=document.getElementById('municipalitySort');
    const pagination=document.getElementById('municipalityPagination');
    const rangeText=document.getElementById('municipalityRangeText');
    const resultCount=document.getElementById('municipalityResultCount');
    const empty=document.getElementById('municipalityEmptyState');
    const perPage=10;
    let page=1;

    const normalize=s=>(s||'').toString().normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().trim();
    function filtered(){
        const q=normalize(search.value);
        const s=status.value,t=territorial.value;
        let rows=allCards.filter(card=>{
            const text=normalize(card.dataset.search);
            const okSearch=!q||text.includes(q);
            const okStatus=s==='all'||card.dataset.lifecycle===s;
            const okTerr=t==='all'||(t==='active'&&card.dataset.territorial==='1')||(t==='inactive'&&card.dataset.territorial==='0');
            return okSearch&&okStatus&&okTerr;
        });
        const mode=sort.value;
        rows.sort((a,b)=>{
            if(mode==='name-asc')return a.dataset.name.localeCompare(b.dataset.name,'pt-BR');
            if(mode==='name-desc')return b.dataset.name.localeCompare(a.dataset.name,'pt-BR');
            if(mode==='users-desc')return Number(b.dataset.users)-Number(a.dataset.users)||a.dataset.name.localeCompare(b.dataset.name,'pt-BR');
            if(mode==='users-asc')return Number(a.dataset.users)-Number(b.dataset.users)||a.dataset.name.localeCompare(b.dataset.name,'pt-BR');
            if(mode==='managers-desc')return Number(b.dataset.managers)-Number(a.dataset.managers)||a.dataset.name.localeCompare(b.dataset.name,'pt-BR');
            if(mode==='readiness-desc')return Number(b.dataset.readiness)-Number(a.dataset.readiness)||a.dataset.name.localeCompare(b.dataset.name,'pt-BR');
            if(mode==='readiness-asc')return Number(a.dataset.readiness)-Number(b.dataset.readiness)||a.dataset.name.localeCompare(b.dataset.name,'pt-BR');
            if(mode==='status-desc'){const rank={active:6,implementation:5,presentation:4,negotiation:3,suspended:2,inactive:1};return (rank[b.dataset.lifecycle]||0)-(rank[a.dataset.lifecycle]||0)||a.dataset.name.localeCompare(b.dataset.name,'pt-BR');}
            return 0;
        });
        return rows;
    }
    function button(label,target,active=false,disabled=false){
        const b=document.createElement('button');b.type='button';b.className='municipality-page-btn'+(active?' active':'');b.textContent=label;b.disabled=disabled;
        if(!disabled)b.addEventListener('click',()=>{page=target;render();document.querySelector('.municipality-catalog-card')?.scrollIntoView({behavior:'smooth',block:'start'});});
        return b;
    }
    function renderPagination(totalPages){
        pagination.innerHTML='';
        if(totalPages<=1)return;
        pagination.appendChild(button('‹',Math.max(1,page-1),false,page===1));
        const candidates=[];
        for(let i=1;i<=totalPages;i++)if(i===1||i===totalPages||Math.abs(i-page)<=2)candidates.push(i);
        let prev=0;
        candidates.forEach(i=>{if(prev&&i-prev>1){const dots=document.createElement('span');dots.textContent='…';dots.style.padding='0 3px';pagination.appendChild(dots);}pagination.appendChild(button(String(i),i,i===page));prev=i;});
        pagination.appendChild(button('›',Math.min(totalPages,page+1),false,page===totalPages));
    }
    function render(){
        const rows=filtered();
        const total=rows.length,totalPages=Math.max(1,Math.ceil(total/perPage));
        if(page>totalPages)page=totalPages;
        const start=(page-1)*perPage,end=Math.min(start+perPage,total);
        allCards.forEach(c=>c.hidden=true);
        rows.forEach((c,index)=>{grid.appendChild(c);c.hidden=index<start||index>=end;});
        empty.hidden=total!==0;
        grid.hidden=total===0;
        resultCount.textContent=total+' resultado(s)';
        rangeText.textContent=total?`Exibindo ${start+1} a ${end} de ${total} município(s)`:'Nenhum município para exibir';
        renderPagination(totalPages);
    }
    [search,status,territorial,sort].forEach(el=>el.addEventListener(el===search?'input':'change',()=>{page=1;render();}));
    render();
})();
</script>
