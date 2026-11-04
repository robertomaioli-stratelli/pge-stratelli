<?php
use App\Core\Format;
use App\Core\Auth;
$hierarquiaResumo = $arvoreOperacional ?? [];
$tenant = $tenant ?? null;
$hr = static fn($x) => Format::h((string)($x ?? ''));
$clamp = static fn($n) => max(0, min(100, (int)round((float)$n)));
$procCount = count($hierarquiaResumo);
$totalEtapas = 0;
$totalFases = 0;
$totalAtos = 0;
foreach ($hierarquiaResumo as $procTmp) {
    $totalEtapas += count($procTmp['etapas'] ?? []);
    foreach (($procTmp['etapas'] ?? []) as $etTmp) {
        $totalFases += count($etTmp['fases'] ?? []);
        foreach (($etTmp['fases'] ?? []) as $faTmp) {
            $totalAtos += count($faTmp['atos'] ?? []);
        }
    }
}
$statusClass = static function ($pct) use ($clamp) {
    $p = $clamp($pct);
    if ($p >= 100) return 'done';
    if ($p > 0) return 'current';
    return 'pending';
};
?>
<?php if (Auth::isPlatformAdmin()): ?>
<section class="card pge-hs-wrap">
    <style>
        .pge-hs-wrap{padding:0;margin:14px 0;border:1px solid #d9e3ee;border-radius:18px;background:linear-gradient(180deg,#ffffff 0%,#f8fbff 100%);box-shadow:0 10px 24px rgba(8,42,85,.06)}
        .pge-hs-toggle{width:100%}
        .pge-hs-toggle-head{list-style:none;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:18px 20px;cursor:pointer;color:#082a55}
        .pge-hs-toggle-head::-webkit-details-marker{display:none}
        .pge-hs-toggle-head:focus-visible{outline:3px solid #176fdd;outline-offset:-3px;border-radius:16px}
        .pge-hs-toggle-copy{display:grid;gap:5px}
        .pge-hs-toggle-copy strong{font-size:17px;font-weight:900}
        .pge-hs-toggle-copy small{font-size:12px;color:#657b93}
        .pge-hs-toggle-action{white-space:nowrap;background:#eaf2fb;color:#17558c;border-radius:10px;padding:9px 13px;font-size:11px;font-weight:900}
        .pge-hs-toggle-action span{display:inline-block;margin-left:8px;transition:transform .15s}
        .pge-hs-toggle[open] .pge-hs-toggle-action span{transform:rotate(180deg)}
        .pge-hs-toggle[open] .pge-hs-toggle-head{border-bottom:1px solid #e3eaf3}
        .pge-hs-toggle-content{padding:16px 18px 18px}
        .pge-hs-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:16px}
        .pge-hs-head h2{margin:0 0 6px;font-size:18px;color:#082a55}
        .pge-hs-head p{margin:0;color:#667b91;font-size:12px;line-height:1.5;max-width:860px}
        .pge-hs-kpis{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
        .pge-hs-kpi{display:inline-flex;align-items:center;gap:7px;padding:7px 10px;border-radius:999px;background:#eef4fb;border:1px solid #d8e4f1;color:#274b73;font-size:11px;font-weight:800}
        .pge-hs-kpi b{font-size:12px;color:#082a55}
        .pge-hs-proc{border:1px solid #dbe5ef;border-radius:16px;background:#fff;overflow:hidden;margin-top:14px}
        .pge-hs-proc summary{list-style:none;cursor:pointer;padding:14px 16px;display:flex;align-items:center;justify-content:space-between;gap:16px;background:linear-gradient(180deg,#fbfdff 0%,#f2f7fc 100%)}
        .pge-hs-proc summary::-webkit-details-marker{display:none}
        .pge-hs-proc[open] summary{border-bottom:1px solid #e5edf5}
        .pge-hs-proc-left{display:grid;gap:5px;min-width:0}
        .pge-hs-proc-title{display:flex;align-items:center;gap:10px;flex-wrap:wrap;min-width:0}
        .pge-hs-proc-title strong{font-size:20px;color:#0b2f56}
        .pge-hs-chip{display:inline-flex;align-items:center;padding:4px 8px;border-radius:999px;font-size:10px;font-weight:900;border:1px solid #d8e4f1;background:#eef4fb;color:#35597f}
        .pge-hs-proc-meta{display:flex;gap:8px;flex-wrap:wrap;color:#62778e;font-size:11px;font-weight:700}
        .pge-hs-right{display:grid;justify-items:end;gap:7px;min-width:180px}
        .pge-hs-pct{font-size:20px;font-weight:900;color:#082a55}
        .pge-hs-bar{width:190px;max-width:100%;height:10px;border-radius:999px;background:#edf2f7;overflow:hidden}
        .pge-hs-bar>span{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#176fdd 0%,#0f58b9 100%)}
        .pge-hs-body{padding:16px}
        .pge-hs-stage-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px}
        .pge-hs-stage{border:1px solid #dbe5ef;border-radius:15px;background:#fff;box-shadow:0 6px 16px rgba(9,35,69,.04);overflow:hidden}
        .pge-hs-stage-top{padding:14px 14px 10px;border-bottom:1px solid #eef2f7}
        .pge-hs-stage-label{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:10px;font-weight:900;background:#eef4fb;color:#31597f;margin-bottom:8px}
        .pge-hs-stage-top strong{display:block;font-size:15px;color:#0b2f56;line-height:1.35;margin-bottom:9px}
        .pge-hs-stage-top .pge-hs-row{display:flex;justify-content:space-between;gap:10px;align-items:center;font-size:11px;color:#61758b;font-weight:800}
        .pge-hs-stage-progress{height:9px;background:#eef2f7;border-radius:999px;overflow:hidden;margin-top:10px}
        .pge-hs-stage-progress span{display:block;height:100%;border-radius:inherit}
        .pge-hs-stage.done .pge-hs-stage-progress span{background:linear-gradient(90deg,#1ba85a 0%,#0c8743 100%)}
        .pge-hs-stage.current .pge-hs-stage-progress span{background:linear-gradient(90deg,#176fdd 0%,#0f58b9 100%)}
        .pge-hs-stage.pending .pge-hs-stage-progress span{background:linear-gradient(90deg,#a9b6c5 0%,#8d9aae 100%)}
        .pge-hs-phase-list{padding:10px 12px 12px;display:grid;gap:8px;background:#fbfdff}
        .pge-hs-phase{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;padding:10px 11px;border:1px solid #e6edf4;border-radius:12px;background:#fff;text-decoration:none}
        .pge-hs-phase:hover{border-color:#c9daee;background:#f7fbff}
        .pge-hs-phase-main{display:grid;gap:4px;min-width:0}
        .pge-hs-phase-main b{color:#133b66;font-size:12px;line-height:1.35}
        .pge-hs-phase-main small{color:#6a7f95;font-size:10px;line-height:1.35}
        .pge-hs-phase-side{display:grid;justify-items:end;gap:5px;min-width:62px}
        .pge-hs-phase-side span{font-size:10px;font-weight:900;color:#274b73}
        .pge-hs-badge{display:inline-flex;align-items:center;padding:4px 8px;border-radius:999px;font-size:10px;font-weight:900}
        .pge-hs-badge.done{background:#e8f8ed;color:#12793a}
        .pge-hs-badge.current{background:#e8f1fe;color:#145fca}
        .pge-hs-badge.pending{background:#eef2f6;color:#72839a}
        .pge-hs-empty{padding:28px 18px;text-align:center;color:#6c8198;border:1px dashed #d6e0eb;border-radius:14px;background:#fbfdff}
        @media (max-width: 760px){
            .pge-hs-right{justify-items:start;min-width:0;width:100%}
            .pge-hs-bar{width:100%}
            .pge-hs-proc summary{align-items:flex-start}
        }
    </style>

    <details class="pge-hs-toggle">
      <summary class="pge-hs-toggle-head"><span class="pge-hs-toggle-copy"><strong>Andamento por Processo e Etapa</strong><small>Visualizar progresso de Etapas, Fases, Atos e Atividades</small></span><span class="pge-hs-toggle-action">Expandir painel <span aria-hidden="true">⌄</span></span></summary>
      <div class="pge-hs-toggle-content">
    <div class="pge-hs-head">
        <div>
            <h2>Andamento por Processo e Etapa</h2>
            <p>Acompanhamento consolidado de Etapas → Fases → Atos → Atividades. Os indicadores documentais específicos permanecem abaixo.</p>
            <div class="pge-hs-kpis">
                <span class="pge-hs-kpi"><b><?= $procCount ?></b> processo(s)</span>
                <span class="pge-hs-kpi"><b><?= $totalEtapas ?></b> etapa(s)</span>
                <span class="pge-hs-kpi"><b><?= $totalFases ?></b> fase(s)</span>
                <span class="pge-hs-kpi"><b><?= $totalAtos ?></b> ato(s)</span>
            </div>
        </div>
        <?php if ($tenant): ?>
            <a class="btn primary" href="/<?= $hr($tenant['slug']) ?>/hierarquia">Abrir hierarquia</a>
        <?php endif; ?>
    </div>

    <?php if (!$hierarquiaResumo): ?>
        <div class="pge-hs-empty">Nenhum processo ativo cadastrado nesta instância. Utilize Configurações → Estrutura do Processo.</div>
    <?php else: ?>
        <?php foreach ($hierarquiaResumo as $proc): ?>
            <?php
            $procPct = $clamp($proc['progresso'] ?? 0);
            $procEtapas = $proc['etapas'] ?? [];
            $procFases = 0;
            $procAtos = 0;
            foreach ($procEtapas as $etCount) {
                $procFases += count($etCount['fases'] ?? []);
                foreach (($etCount['fases'] ?? []) as $faCount) $procAtos += count($faCount['atos'] ?? []);
            }
            ?>
            <details class="pge-hs-proc" open>
                <summary>
                    <div class="pge-hs-proc-left">
                        <div class="pge-hs-proc-title">
                            <strong><?= $hr($proc['titulo'] ?? 'Processo') ?></strong>
                            <span class="pge-hs-chip">Visão consolidada</span>
                        </div>
                        <div class="pge-hs-proc-meta">
                            <span><?= count($procEtapas) ?> etapa(s)</span>
                            <span><?= $procFases ?> fase(s)</span>
                            <span><?= $procAtos ?> ato(s)</span>
                        </div>
                    </div>
                    <div class="pge-hs-right">
                        <div class="pge-hs-pct"><?= $procPct ?>%</div>
                        <div class="pge-hs-bar"><span style="width:<?= $procPct ?>%"></span></div>
                    </div>
                </summary>
                <div class="pge-hs-body">
                    <div class="pge-hs-stage-grid">
                        <?php foreach ($procEtapas as $et): ?>
                            <?php
                            $etPct = $clamp($et['progresso'] ?? 0);
                            $etCls = $statusClass($etPct);
                            $etFases = $et['fases'] ?? [];
                            $etAtos = 0;
                            foreach ($etFases as $faCount) $etAtos += count($faCount['atos'] ?? []);
                            ?>
                            <article class="pge-hs-stage <?= $hr($etCls) ?>">
                                <div class="pge-hs-stage-top">
                                    <span class="pge-hs-stage-label">Etapa <?= $hr($et['numero'] ?? '') ?></span>
                                    <strong><?= $hr($et['nome'] ?? 'Etapa') ?></strong>
                                    <div class="pge-hs-row">
                                        <span><?= count($etFases) ?> fase(s) · <?= $etAtos ?> ato(s)</span>
                                        <b><?= $etPct ?>%</b>
                                    </div>
                                    <div class="pge-hs-stage-progress"><span style="width:<?= $etPct ?>%"></span></div>
                                </div>
                                <div class="pge-hs-phase-list">
                                    <?php foreach ($etFases as $fa): ?>
                                        <?php
                                        $faPct = $clamp($fa['progresso'] ?? 0);
                                        $faCls = $statusClass($faPct);
                                        $faAtos = count($fa['atos'] ?? []);
                                        ?>
                                        <a class="pge-hs-phase" href="/<?= $hr($tenant['slug'] ?? '') ?>/workflow/fase/<?= $hr($fa['id'] ?? 0) ?>">
                                            <div class="pge-hs-phase-main">
                                                <b><?= $hr($fa['titulo'] ?? 'Fase') ?></b>
                                                <small><?= $faAtos ?> ato(s) vinculados</small>
                                            </div>
                                            <div class="pge-hs-phase-side">
                                                <span><?= $faPct ?>%</span>
                                                <i class="pge-hs-badge <?= $hr($faCls) ?>"><?= $faCls === 'done' ? 'Concluída' : ($faCls === 'current' ? 'Em andamento' : 'Não iniciada') ?></i>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </details>
        <?php endforeach; ?>
    <?php endif; ?>
      </div>
    </details>
</section>
<?php endif; ?>
