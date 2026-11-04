<?php
/** Relatórios complementares – usa exclusivamente o estado já filtrado por perfil do WorkflowService. */
use App\Core\Format;
$rp = static fn($v) => Format::h((string)($v ?? ''));
$tipoRelatorio = (string)($_GET['tipo'] ?? '');
$relatoriosPermitidos = [
 'executivo' => 'Resumo executivo',
 'pendencias' => 'Pendências por secretaria',
 'prazos' => 'Controle de prazos',
 'validacao' => 'Produtividade de validação',
 'auditoria' => 'Auditoria documental',
 'territorial' => 'Inteligência territorial',
 'entregas' => 'Entregas e documentos',
 'correcoes' => 'Correções e pendências'
];
if (!isset($relatoriosPermitidos[$tipoRelatorio])) return;
$fas = $relatorioFases ?? [];
$secs = $relatorioSecretarias ?? [];
$reqs = $requisitosVisiveis ?? [];
$docs = $documentosPagina ?? [];
$pend = $dashboardSecretariasPendentes ?? [];
$tp = (int)($totalDocs ?? 0);
$env = (int)($totalEnviados ?? 0);
$apr = (int)($totalAprovados ?? 0);
$cor = (int)($totalCorrecoes ?? 0);
$ana = (int)($aguardando ?? 0);
$sem = max(0, $tp - $env);
$perc = max(0, min(100, (int)($progresso ?? 0)));
$prazoAtual = $prazoDashboard ?? [];
$rotuloPrazo = (string)($prazoAtual['rotulo'] ?? 'Sem cronograma');
$detalhes = [
 'executivo' => 'Panorama consolidado do andamento e da documentação visível para este perfil.',
 'pendencias' => 'Documentos pendentes e solicitações de correção por unidade.',
 'prazos' => 'Situação dos prazos por fase e datas calculadas no cronograma.',
 'validacao' => 'Volumes de documentação aprovada, em análise, em correção ou não entregue.',
 'auditoria' => 'Situação documental atual e acesso ao histórico auditável de eventos.',
 'territorial' => 'Resumo de camadas e objetos da inteligência territorial.',
 'entregas' => 'Quantitativo de documentos e desempenho das entregas por fase.',
 'correcoes' => 'Documentos que exigem ajustes ou ainda não foram enviados.'
];
$tenantSlug = (string)($tenant['slug'] ?? '');
$baseRel = '/'.$rp($tenantSlug).'/relatorios';
?>
<style>
.pge-r22{border:1px solid #d8e4f1;border-radius:17px;background:#fff;margin:18px 0 22px;padding:20px;box-shadow:0 8px 28px rgba(8,42,85,.05)}
.pge-r22-head{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:18px}
.pge-r22-head h2{font-size:21px;margin:3px 0 7px;color:#082a55}.pge-r22-head p{color:#61758c;margin:0;font-size:12px}
.pge-r22-kicker{color:#176fdd;font-size:10px;font-weight:900;letter-spacing:.075em;text-transform:uppercase}
.pge-r22-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.pge-r22-actions a,.pge-r22-actions button{border:1px solid #cbd9e9;padding:9px 12px;border-radius:9px;background:#fff;color:#164876;font:inherit;font-size:11px;font-weight:800;text-decoration:none;cursor:pointer}
.pge-r22-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:18px}
.pge-r22-stat{background:#f6f9fd;border:1px solid #e0e9f3;padding:14px;border-radius:12px;display:grid;gap:5px}.pge-r22-stat strong{color:#123f71;font-size:23px}.pge-r22-stat small{color:#637b93;font-weight:750;font-size:10px}
.pge-r22 h3{color:#102f52;font-size:15px;margin:20px 0 11px}.pge-r22-table-wrap{overflow-x:auto}
.pge-r22-table{border-collapse:collapse;width:100%;min-width:640px;font-size:12px}.pge-r22-table th{text-align:left;background:#f2f6fb;color:#4d6782;font-size:10px;text-transform:uppercase;padding:11px 12px}.pge-r22-table td{padding:11px 12px;border-bottom:1px solid #e7edf4;color:#1c3d5e;vertical-align:top}
.pge-r22-empty{padding:25px;text-align:center;background:#fafcff;border:1px dashed #cfdeee;border-radius:12px;color:#607893;font-size:12px}
.pge-r22-track{height:7px;border-radius:8px;background:#e9eff5;overflow:hidden;min-width:70px;margin-top:7px}.pge-r22-track>span{display:block;height:100%;background:#176fdd;border-radius:8px}
.pge-r22-note{color:#6a7f95;font-size:11px;margin-top:12px;line-height:1.55}
@media print{.pge-r22-actions,.report-catalog,.config-tabs,.side,.top{display:none!important}.pge-r22{box-shadow:none;border:0}}
</style>
<section id="relatorio-detalhado" class="pge-r22">
 <div class="pge-r22-head">
  <div><span class="pge-r22-kicker">Relatório operacional · <?= $rp($tenant['nome'] ?? 'Instância') ?></span><h2><?= $rp($relatoriosPermitidos[$tipoRelatorio]) ?></h2><p><?= $rp($detalhes[$tipoRelatorio]) ?></p></div>
  <div class="pge-r22-actions"><a href="<?= $baseRel ?>">Voltar ao catálogo</a><button type="button" onclick="window.print()">Imprimir / salvar PDF</button></div>
 </div>
 <?php if (in_array($tipoRelatorio, ['executivo','validacao','auditoria','entregas','correcoes'], true)): ?>
 <div class="pge-r22-grid">
  <?php foreach ([['Documentos previstos',$tp],['Enviados',$env],['Aprovados',$apr],['Em análise',$ana],['Correções',$cor],['Não enviados',$sem]] as $stat): ?>
  <div class="pge-r22-stat"><small><?= $rp($stat[0]) ?></small><strong><?= (int)$stat[1] ?></strong></div>
  <?php endforeach; ?>
 </div>
 <?php endif; ?>
 <?php if ($tipoRelatorio === 'executivo'): ?>
  <div class="pge-r22-grid">
   <div class="pge-r22-stat"><small>Progresso documental</small><strong><?= $perc ?>%</strong></div>
   <div class="pge-r22-stat"><small>Fases visíveis</small><strong><?= count($fas) ?></strong></div>
   <div class="pge-r22-stat"><small>Secretarias com documentos</small><strong><?= count($secs) ?></strong></div>
   <div class="pge-r22-stat"><small>Prazo atual</small><strong style="font-size:13px"><?= $rp($rotuloPrazo) ?></strong></div>
  </div>
 <?php endif; ?>
 <?php if (in_array($tipoRelatorio,['executivo','prazos','entregas'],true)): ?>
  <h3>Desempenho por fase</h3>
  <?php if (!$fas): ?><div class="pge-r22-empty">Sem fases para apresentar neste perfil.</div>
  <?php else: ?><div class="pge-r22-table-wrap"><table class="pge-r22-table"><thead><tr><th>Fase</th><th>Progresso</th><th>Documentos</th><th>Pendentes</th><th>Prazo</th><th>Início previsto</th><th>Término previsto</th></tr></thead><tbody>
   <?php foreach ($fas as $item): $prazo=$item['prazo']??[]; ?>
   <tr><td><?= $rp($item['fase']['titulo'] ?? $item['fase']['aba'] ?? 'Fase') ?></td><td><strong><?= (int)($item['progresso']??0) ?>%</strong><div class="pge-r22-track"><span style="width:<?= max(0,min(100,(int)($item['progresso']??0))) ?>%"></span></div></td><td><?= (int)($item['total']??0) ?></td><td><?= (int)($item['pendentes']??0) ?></td><td><?= $rp($prazo['rotulo']??'Não definido') ?><?= !empty($prazo['provisorio'])?' (previsão provisória)':'' ?></td><td><?= $rp($prazo['inicio']??'—') ?></td><td><?= $rp($prazo['fim']??'—') ?></td></tr>
   <?php endforeach; ?></tbody></table></div><?php endif; ?>
 <?php endif; ?>
 <?php if (in_array($tipoRelatorio,['pendencias','correcoes'],true)): ?>
  <h3>Pendências por unidade</h3>
  <?php if (!$secs): ?><div class="pge-r22-empty">Nenhuma secretaria com documentos cadastrados no escopo deste perfil.</div>
  <?php else: ?><div class="pge-r22-table-wrap"><table class="pge-r22-table"><thead><tr><th>Secretaria</th><th>Documentos</th><th>Não enviados</th><th>Correções</th><th>Em análise</th></tr></thead><tbody>
   <?php foreach($secs as $s): ?><tr><td><?= $rp(($s['sigla']??'') ? $s['sigla'].' — '.($s['nome']??'') : ($s['nome']??'Não informada')) ?></td><td><?= (int)($s['total']??0) ?></td><td><?= (int)($s['pendentes']??0) ?></td><td><?= (int)($s['correcao']??0) ?></td><td><?= (int)($s['analise']??0) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
 <?php endif; ?>
 <?php if ($tipoRelatorio === 'validacao'): ?>
  <h3>Produtividade documental por fase</h3>
  <?php if (!$fas): ?><div class="pge-r22-empty">Não existem fases com dados documentais disponíveis.</div>
  <?php else: ?><div class="pge-r22-table-wrap"><table class="pge-r22-table"><thead><tr><th>Fase</th><th>Aprovados</th><th>Em análise</th><th>Correções</th><th>Não enviados</th></tr></thead><tbody>
   <?php foreach($fas as $f): ?><tr><td><?= $rp($f['fase']['titulo']??'Fase') ?></td><td><?= (int)($f['aprovados']??0) ?></td><td><?= (int)($f['analise']??0) ?></td><td><?= (int)($f['correcao']??0) ?></td><td><?= (int)($f['pendentes']??0) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
  <p class="pge-r22-note">Estes indicadores representam o estado atual dos documentos. O tempo médio de validação e a produção por usuário são analisados em Indicadores Históricos.</p>
 <?php endif; ?>
 <?php if ($tipoRelatorio === 'auditoria'): ?>
  <h3>Trilha de auditoria</h3><p class="pge-r22-note">A tabela detalhada de eventos, autores, versões e datas está disponível no histórico documental. O resumo acima mostra somente o estado atual dos documentos.</p>
  <div class="pge-r22-actions"><a href="/<?= $rp($tenantSlug) ?>/historico">Abrir histórico auditável</a></div>
 <?php endif; ?>
 <?php if ($tipoRelatorio === 'territorial'): $ts=$territorialSummary??[]; ?>
  <div class="pge-r22-grid">
  <?php foreach ([['Objetos mapeados','objetos'],['Camadas','camadas'],['Fases vinculadas','processos_vinculados'],['Em atenção','atencao'],['Críticos','criticos']] as $pair): ?><div class="pge-r22-stat"><small><?= $rp($pair[0]) ?></small><strong><?= (int)($ts[$pair[1]]??0) ?></strong></div><?php endforeach; ?>
  </div><div class="pge-r22-actions"><a href="/<?= $rp($tenantSlug) ?>/territorio">Abrir Inteligência Territorial</a></div>
 <?php endif; ?>
 <p class="pge-r22-note">Fonte: registros disponíveis na PGE no momento da consulta, conforme as permissões do perfil. Valores zero indicam ausência de registros computáveis, não necessariamente execução concluída. Prazos provisórios dependem de confirmação dos marcos de início.</p>
</section>
