<?php
use App\Core\Format;
$slug=$tenant['slug'];
$approved=count(array_filter($documentosPagina,fn($x)=>$x['classe']==='approved'));
$waiting=count(array_filter($documentosPagina,fn($x)=>$x['classe']==='waiting'));
$correction=count(array_filter($documentosPagina,fn($x)=>$x['classe']==='correction'));
$pending=count(array_filter($documentosPagina,fn($x)=>$x['classe']==='pending'));
$models=count(array_filter($documentosPagina,fn($x)=>$x['tem_modelo']));
?>
<div class="module-page">
    <div class="module-hero">
        <div><h1>Documentos</h1><p>Catálogo documental com versão atual, integridade SHA-256, responsáveis, validação e trilha completa de auditoria.</p></div>
        <span class="module-context">▤ <?=$scope==='stratelli'?'Visão completa Stratelli':'Visão do Gestor Municipal'?></span>
    </div>

    <div class="module-kpis-grid">
        <div class="module-kpi"><small>TOTAL DE DOCUMENTOS</small><b><?=count($documentosPagina)?></b><span>No escopo deste perfil</span></div>
        <div class="module-kpi success"><small>APROVADOS</small><b><?=$approved?></b><span>Validação concluída</span></div>
        <div class="module-kpi"><small>AGUARDANDO VALIDAÇÃO</small><b><?=$waiting?></b><span>Em análise pela Stratelli</span></div>
        <div class="module-kpi warning"><small>EM CORREÇÃO</small><b><?=$correction?></b><span>Precisam de reenvio</span></div>
        <div class="module-kpi danger"><small>NÃO ENTREGUES</small><b><?=$pending?></b><span>Aguardando envio</span></div>
        <div class="module-kpi neutral"><small>MODELOS DISPONÍVEIS</small><b><?=$models?></b><span>Documentos com modelo</span></div>
    </div>

    <div class="document-audit-notice">
        <b>🔐 Gestão documental auditável</b>
        <span>Cada versão enviada possui checksum SHA-256, MIME, tamanho, autor, data/hora e vínculo com a versão anterior. Registros auditados não podem ser excluídos fisicamente pelo sistema.</span>
    </div>

    <div class="document-phase-list">
    <?php foreach($documentosPorFasePagina as $group): ?>
        <section class="card phase-document-section">
            <div class="phase-document-head">
                <div><span class="phase-document-number">FASE <?=Format::h($group['ordem'])?></span><h2><?=Format::h($group['titulo'])?></h2><p><?=Format::h($group['aba'])?> · <?=count($group['itens'])?> documento(s)</p></div>
                <a class="mini-link primary" href="/<?=Format::h($slug)?>/workflow/fase/<?=$group['fase_id']?>">Abrir fase</a>
            </div>
            <div class="table-scroll"><table class="document-catalog-table audit-document-table">
                <thead><tr><th>Documento</th><th>Secretaria</th><th>Tipo</th><th>Modelo</th><th>Status</th><th>Versão atual / Integridade</th><th>Ações</th></tr></thead>
                <tbody>
                <?php foreach($group['itens'] as $item): $r=$item['requisito'];$d=$item['documento']; ?>
                    <tr>
                        <td><b><?=Format::h($r['nome'])?></b><br><small><?=Format::h($r['descricao']?:'Sem orientação complementar.')?></small></td>
                        <td><b><?=Format::h($r['secretaria_sigla']?:$r['secretaria_nome'])?></b><br><small><?=Format::h($r['secretaria_nome'])?></small><?php if(!empty($r['departamento_nome'])):?><br><small><?=Format::h($r['departamento_nome'])?></small><?php endif;?></td>
                        <td><?=Format::h($r['tipo_nome'])?></td>
                        <td><span class="document-model-state <?=$item['tem_modelo']?'available':'missing'?>"><?=$item['tem_modelo']?'✓ Modelo disponível':'Modelo não enviado'?></span></td>
                        <td><span class="module-status <?=Format::h($item['classe'])?>"><?=Format::h($item['rotulo'])?></span></td>
                        <td class="document-last-file">
                            <?php if($d): ?>
                                <b>Versão <?=Format::h($d['versao'])?> · <?=Format::h($d['arquivo_original'])?></b><br>
                                <small><?=Format::h(Format::dateTime($d['enviado_em']))?><?php if(!empty($d['enviado_por_nome'])):?> · <?=Format::h($d['enviado_por_nome'])?><?php endif;?></small>
                                <div class="document-integrity-mini">
                                    <span><?=Format::h($d['mime_type']?:'MIME não identificado')?></span>
                                    <span><?=Format::h(Format::fileSize((int)$d['tamanho']))?></span>
                                    <?php if(!empty($d['checksum_sha256'])):?><span title="<?=Format::h($d['checksum_sha256'])?>">SHA-256 <?=Format::h(substr($d['checksum_sha256'],0,12))?>…</span><?php endif;?>
                                </div>
                            <?php else: ?><span>Sem arquivo enviado</span><?php endif;?>
                        </td>
                        <td><div class="document-actions">
                            <a class="mini-link primary" href="/<?=Format::h($slug)?>/workflow/fase/<?=$group['fase_id']?>">Abrir fase</a>
                            <a class="mini-link audit" href="/<?=Format::h($slug)?>/documentos/<?=$r['id']?>/auditoria">⌁ Auditoria</a>
                            <?php if($item['tem_modelo']):?><a class="mini-link" href="/<?=Format::h($slug)?>/arquivos/modelos/<?=$r['id']?>">Modelo</a><?php endif;?>
                            <?php if($d):?><a class="mini-link" href="/<?=Format::h($slug)?>/arquivos/documentos/<?=$d['id']?>">Arquivo</a><?php endif;?>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </section>
    <?php endforeach; ?>
    </div>
</div>
