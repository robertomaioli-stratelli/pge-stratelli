<?php
use App\Core\Format;
$slug=$tenant['slug'];$r=$requisito;$latest=$ultimaVersao;$integrity=$latest['integridade']??null;
$statusLabel=$latest?match($latest['status']){'APROVADO'=>'APROVADO','CORRECAO'=>'EM CORREÇÃO',default=>'AGUARDANDO VALIDAÇÃO'}:'NÃO ENTREGUE';
$statusClass=$latest?match($latest['status']){'APROVADO'=>'approved','CORRECAO'=>'correction',default=>'waiting'}:'pending';
?>
<div class="module-page document-audit-page">
    <div class="module-hero document-audit-hero">
        <div>
            <span class="audit-eyebrow">🔐 TRILHA DE AUDITORIA DOCUMENTAL</span>
            <h1><?=Format::h($r['nome'])?></h1>
            <p>Fase <?=Format::h($r['fase_ordem'])?> — <?=Format::h($r['fase_aba'])?> · <?=Format::h(($r['secretaria_sigla']?$r['secretaria_sigla'].' — ':'').$r['secretaria_nome'])?><?php if($r['departamento_nome']):?> · <?=Format::h($r['departamento_nome'])?><?php endif;?></p>
        </div>
        <div class="document-audit-hero-actions">
            <button class="btn neutral" type="button" onclick="window.print()">🖨 Imprimir ficha</button>
            <a class="btn primary" href="/<?=Format::h($slug)?>/workflow/fase/<?=$r['fase_id']?>">← Voltar à fase</a>
        </div>
    </div>

    <section class="document-audit-summary-grid">
        <article class="audit-summary-card"><small>VERSÕES REGISTRADAS</small><strong><?=count($versoes)?></strong><span>Histórico preservado</span></article>
        <article class="audit-summary-card <?=$statusClass?>"><small>SITUAÇÃO ATUAL</small><strong><?=Format::h($statusLabel)?></strong><span><?= $latest?'Versão '.Format::h($latest['versao']):'Aguardando primeiro envio' ?></span></article>
        <article class="audit-summary-card <?=($integrity&&$integrity['exists']&&$integrity['valid'])?'approved':'danger'?>"><small>INTEGRIDADE DO ARQUIVO ATUAL</small><strong><?=!$latest?'—':(($integrity&&$integrity['exists']&&$integrity['valid'])?'ÍNTEGRO':'ATENÇÃO')?></strong><span><?=!$latest?'Sem arquivo':(($integrity&&$integrity['exists'])?'Checksum conferido':'Arquivo indisponível')?></span></article>
        <article class="audit-summary-card"><small>TIPO DOCUMENTAL</small><strong><?=Format::h($r['tipo_nome'])?></strong><span><?=Format::h($r['perfil_envio'])?> · <?=$r['obrigatorio']?'Obrigatório':'Opcional'?></span></article>
    </section>

    <?php if($latest):?>
    <section class="card current-document-certificate">
        <div class="current-document-certificate-head">
            <div><span class="audit-eyebrow">CERTIFICADO DA VERSÃO ATUAL</span><h2>Versão <?=Format::h($latest['versao'])?> · <?=Format::h($latest['arquivo_original'])?></h2></div>
            <span class="module-status <?=$statusClass?>"><?=Format::h($statusLabel)?></span>
        </div>
        <div class="certificate-grid">
            <div><small>SHA-256</small><code><?=Format::h($latest['checksum_sha256']?:'Não calculado')?></code></div>
            <div><small>Tipo MIME</small><b><?=Format::h($latest['mime_type']?:'Não identificado')?></b></div>
            <div><small>Tamanho</small><b><?=Format::h(Format::fileSize((int)$latest['tamanho']))?></b></div>
            <div><small>Enviado por</small><b><?=Format::h($latest['enviado_por_nome']?:'Usuário não identificado')?></b><span><?=Format::h($latest['enviado_por_email']?:'')?></span></div>
            <div><small>Data / hora do envio</small><b><?=Format::h(Format::dateTime($latest['enviado_em']))?></b></div>
            <div><small>Validado por</small><b><?=Format::h($latest['validado_por_nome']?:'—')?></b><span><?=Format::h($latest['validado_em']?Format::dateTime($latest['validado_em']):'Ainda não validado')?></span></div>
        </div>
        <?php if(!empty($latest['observacao_envio'])||!empty($latest['observacao_validacao'])):?><div class="certificate-observations">
            <?php if(!empty($latest['observacao_envio'])):?><p><b>Observação do envio:</b> <?=Format::h($latest['observacao_envio'])?></p><?php endif;?>
            <?php if(!empty($latest['observacao_validacao'])):?><p><b>Observação da validação:</b> <?=Format::h($latest['observacao_validacao'])?></p><?php endif;?>
        </div><?php endif;?>
        <div class="certificate-integrity <?=($integrity&&$integrity['exists']&&$integrity['valid'])?'ok':'problem'?>">
            <b><?=($integrity&&$integrity['exists']&&$integrity['valid'])?'✓ Integridade verificada':'⚠ Integridade não confirmada'?></b>
            <span><?=($integrity&&$integrity['exists']&&$integrity['valid'])?'O conteúdo físico armazenado corresponde exatamente ao SHA-256 registrado no banco.':(!$integrity['exists']?'O arquivo físico não foi localizado no armazenamento.':'O conteúdo atual não corresponde ao checksum registrado. Não utilize o arquivo antes da verificação técnica.')?></span>
        </div>
        <a class="btn primary" href="/<?=Format::h($slug)?>/arquivos/documentos/<?=$latest['id']?>">⬇ Baixar versão atual verificada</a>
    </section>
    <?php endif;?>

    <section class="card document-version-history">
        <div class="section-heading"><div><h2>Histórico completo de versões</h2><p>Nenhuma versão substitui ou apaga a anterior. Cada envio permanece individualmente identificado.</p></div><span class="audit-lock-chip">🔒 Registros imutáveis</span></div>
        <?php if($versoes):?><div class="table-scroll"><table class="document-version-table"><thead><tr><th>Versão</th><th>Arquivo</th><th>Envio</th><th>Validação</th><th>Integridade</th><th>Versão anterior</th><th>Ação</th></tr></thead><tbody>
        <?php foreach($versoes as $v):$int=$v['integridade'];$vClass=match($v['status']){'APROVADO'=>'approved','CORRECAO'=>'correction',default=>'waiting'};?>
            <tr>
                <td><strong>v<?=Format::h($v['versao'])?></strong><br><span class="module-status <?=$vClass?>"><?=Format::h($v['status'])?></span></td>
                <td><b><?=Format::h($v['arquivo_original'])?></b><br><small><?=Format::h($v['mime_type']?:'MIME não identificado')?> · <?=Format::h(Format::fileSize((int)$v['tamanho']))?></small><code class="audit-hash-block">SHA-256 <?=Format::h($v['checksum_sha256']?:'—')?></code><?php if($v['observacao_envio']):?><small><b>Obs. envio:</b> <?=Format::h($v['observacao_envio'])?></small><?php endif;?></td>
                <td><b><?=Format::h($v['enviado_por_nome']?:'—')?></b><br><small><?=Format::h(Format::dateTime($v['enviado_em']))?></small></td>
                <td><b><?=Format::h($v['validado_por_nome']?:'—')?></b><br><small><?=Format::h($v['validado_em']?Format::dateTime($v['validado_em']):'Não validado')?></small><?php if($v['observacao_validacao']):?><small><b>Obs.:</b> <?=Format::h($v['observacao_validacao'])?></small><?php endif;?></td>
                <td><span class="integrity-badge <?=($int['exists']&&$int['valid'])?'ok':'problem'?>"><?=($int['exists']&&$int['valid'])?'✓ Íntegro':(!$int['exists']?'Arquivo ausente':'⚠ Divergente')?></span></td>
                <td><?=!empty($v['versao_anterior'])?'v'.Format::h($v['versao_anterior']).' · '.Format::h($v['arquivo_anterior']):'— Primeiro envio'?></td>
                <td><a class="mini-link" href="/<?=Format::h($slug)?>/arquivos/documentos/<?=$v['id']?>">Baixar</a></td>
            </tr>
        <?php endforeach;?></tbody></table></div><?php else:?><div class="empty-state">Nenhuma versão enviada até o momento.</div><?php endif;?>
    </section>

    <?php if($modelos):?>
    <section class="card document-version-history">
        <div class="section-heading"><div><h2>Versões do modelo</h2><p>Arquivos-modelo disponibilizados pela Stratelli também possuem identidade digital e histórico de substituição.</p></div></div>
        <div class="table-scroll"><table class="document-version-table"><thead><tr><th>Versão</th><th>Modelo</th><th>Responsável</th><th>Integridade</th><th>Situação</th></tr></thead><tbody>
        <?php foreach($modelos as $m):$mi=$m['integridade'];?><tr><td><strong>v<?=Format::h($m['versao'])?></strong></td><td><b><?=Format::h($m['arquivo_original'])?></b><br><small><?=Format::h($m['mime_type']?:'MIME não identificado')?> · <?=Format::h(Format::fileSize((int)$m['tamanho']))?></small><code class="audit-hash-block">SHA-256 <?=Format::h($m['checksum_sha256']?:'—')?></code></td><td><?=Format::h($m['usuario_nome']?:'—')?><br><small><?=Format::h(Format::dateTime($m['criado_em']))?></small></td><td><span class="integrity-badge <?=($mi['exists']&&$mi['valid'])?'ok':'problem'?>"><?=($mi['exists']&&$mi['valid'])?'✓ Íntegro':'⚠ Verificar'?></span></td><td><?=$m['ativo']?'<span class="module-status approved">ATUAL</span>':'<span class="module-status pending">ANTERIOR</span>'?></td></tr><?php endforeach;?>
        </tbody></table></div>
    </section>
    <?php endif;?>

    <section class="card document-audit-events">
        <div class="section-heading"><div><h2>Eventos de auditoria</h2><p>Registro cronológico de envios, substituições, aprovações, correções e modelos.</p></div><span><?=count($eventos)?> evento(s)</span></div>
        <?php if($eventos):?><div class="audit-event-list"><?php foreach($eventos as $e):?><article class="audit-event-item"><div class="audit-event-dot"></div><div class="audit-event-content"><div><strong><?=Format::h($e['evento'])?></strong><span><?=Format::h(Format::dateTime($e['criado_em']))?></span></div><p><?=Format::h($e['motivo']?:'Movimentação documental registrada.')?></p><small><?=Format::h($e['usuario_nome']?:'Sistema')?><?php if($e['versao']):?> · versão <?=Format::h($e['versao'])?><?php endif;?><?php if($e['checksum_sha256']):?> · SHA-256 <?=Format::h(substr($e['checksum_sha256'],0,16))?>…<?php endif;?></small></div></article><?php endforeach;?></div><?php else:?><div class="empty-state">Nenhum evento registrado.</div><?php endif;?>
    </section>
</div>
