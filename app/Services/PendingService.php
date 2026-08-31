<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Format;
use App\Core\Tenant;
use PDO;

/**
 * Central de Pendências / Minha Mesa.
 *
 * Não cria um segundo motor de Workflow: consome o estado calculado pelo
 * WorkflowService e transforma esse estado em ações contextualizadas por perfil.
 */
final class PendingService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function tenantFromState(array $state): array
    {
        $scope = (string)($state['scope'] ?? 'municipio');
        $tenant = $state['tenant'] ?? Tenant::current() ?? [];
        $slug = (string)($tenant['slug'] ?? '');
        $requirements = $state['requisitosVisiveis'] ?? [];
        $latest = $state['ultimosDocs'] ?? [];
        $access = $state['faseAcesso'] ?? [];
        $phases = [];
        foreach (($state['fasesTodas'] ?? []) as $f) $phases[(int)$f['id']] = $f;

        $items = [];
        $stats = [
            'actions' => 0,
            'send' => 0,
            'correction' => 0,
            'validation' => 0,
            'deadline' => 0,
            'waiting' => 0,
            'blocked' => 0,
            'monitoring' => 0,
            'closure' => 0,
        ];

        foreach ($requirements as $r) {
            $fid = (int)($r['fase_id'] ?? 0);
            $phase = $phases[$fid] ?? null;
            if (!$phase) continue;
            $phaseAccess = $access[$fid] ?? ['visivel' => true, 'pode_acessar' => true];
            if (empty($phaseAccess['visivel'])) continue;
            $canAccess = !empty($phaseAccess['pode_acessar']);
            $doc = $latest[(int)$r['id']] ?? null;
            $responsibility = (string)($r['perfil_envio'] ?? 'MUNICIPIO');
            $phaseText = 'Fase '.($phase['ordem'] ?? '—').' — '.($phase['aba'] ?? '');
            $unit = trim((string)(($r['secretaria_sigla'] ?? '') ?: ($r['secretaria_nome'] ?? '')));
            if (!empty($r['departamento_nome'])) $unit .= ($unit ? ' · ' : '').$r['departamento_nome'];
            $workflowUrl = '/'.$slug.'/workflow/fase/'.$fid;

            if ($scope === 'stratelli') {
                if ($doc && ($doc['status'] ?? '') === 'AGUARDANDO') {
                    $items[] = $this->item('validation', 'waiting', '…', 'Validar documento recebido',
                        (string)$r['nome'], $phaseText, $unit,
                        'Documento recebido e aguardando análise da Stratelli.', $workflowUrl, 'Validar documento', true, 880,
                        (string)($doc['enviado_em'] ?? ''));
                    $stats['validation']++; $stats['actions']++;
                } elseif ($responsibility === 'STRATELLI' && !$doc && $canAccess) {
                    $items[] = $this->item('send', 'info', '↑', 'Enviar documento da Stratelli',
                        (string)$r['nome'], $phaseText, $unit,
                        'Documento sob responsabilidade direta da Stratelli ainda não enviado.', $workflowUrl, 'Abrir fase', true, 760);
                    $stats['send']++; $stats['actions']++;
                } elseif ($responsibility === 'STRATELLI' && $doc && ($doc['status'] ?? '') === 'CORRECAO' && $canAccess) {
                    $items[] = $this->item('correction', 'correction', '!', 'Corrigir documento da Stratelli',
                        (string)$r['nome'], $phaseText, $unit,
                        (string)($doc['observacao_validacao'] ?? 'Documento requer nova versão.'), $workflowUrl, 'Abrir correção', true, 920,
                        (string)($doc['validado_em'] ?? $doc['enviado_em'] ?? ''));
                    $stats['correction']++; $stats['actions']++;
                } elseif ($responsibility === 'MUNICIPIO' && !$doc && $canAccess) {
                    $items[] = $this->item('monitoring', 'pending', '×', 'Aguardando envio municipal',
                        (string)$r['nome'], $phaseText, $unit,
                        'A unidade responsável ainda não realizou o envio.', $workflowUrl, 'Acompanhar fase', false, 340);
                    $stats['monitoring']++;
                } elseif ($responsibility === 'MUNICIPIO' && $doc && ($doc['status'] ?? '') === 'CORRECAO') {
                    $items[] = $this->item('monitoring', 'correction', '!', 'Aguardando reenvio corrigido',
                        (string)$r['nome'], $phaseText, $unit,
                        (string)($doc['observacao_validacao'] ?? 'Correção solicitada; aguardando nova versão municipal.'), $workflowUrl, 'Acompanhar fase', false, 410,
                        (string)($doc['validado_em'] ?? ''));
                    $stats['monitoring']++;
                }
            } else {
                if (!$canAccess) continue;
                if (!$doc) {
                    $items[] = $this->item('send', 'pending', '↑', 'Documento para enviar',
                        (string)$r['nome'], $phaseText, $unit,
                        'Este documento ainda precisa ser enviado.', $workflowUrl, 'Enviar documento', true, 720);
                    $stats['send']++; $stats['actions']++;
                } elseif (($doc['status'] ?? '') === 'CORRECAO') {
                    $items[] = $this->item('correction', 'correction', '!', 'Correção solicitada',
                        (string)$r['nome'], $phaseText, $unit,
                        (string)($doc['observacao_validacao'] ?? 'A Stratelli solicitou uma nova versão deste documento.'), $workflowUrl, 'Corrigir documento', true, 940,
                        (string)($doc['validado_em'] ?? $doc['enviado_em'] ?? ''));
                    $stats['correction']++; $stats['actions']++;
                } elseif (($doc['status'] ?? '') === 'AGUARDANDO') {
                    $items[] = $this->item('waiting', 'waiting', '…', 'Aguardando validação da Stratelli',
                        (string)$r['nome'], $phaseText, $unit,
                        'O envio foi realizado. Nenhuma nova ação é necessária até a análise.', $workflowUrl, 'Ver envio', false, 260,
                        (string)($doc['enviado_em'] ?? ''));
                    $stats['waiting']++;
                }
            }
        }

        // Quando 100% dos documentos obrigatórios estão aprovados, a fase ainda precisa de encerramento formal.
        $current = $state['faseSituacional'] ?? null;
        $readyForClosure = !empty($state['faseElegivelEncerramento']) && $current && (int)($state['atividade']['id'] ?? 0)===(int)$current['id'];
        if ($readyForClosure) {
            if ($scope === 'stratelli') {
                $items[] = $this->item('closure', 'ready', '✓', 'Encerrar fase formalmente',
                    'Fase '.($current['ordem'] ?? '—').' — '.($current['aba'] ?? ''), '100% documental aprovado', '',
                    'A documentação obrigatória está aprovada. Registre responsável, data, observação e snapshot para liberar a próxima fase.',
                    '/'.$slug.'/workflow/fase/'.(int)$current['id'], 'Encerrar fase', true, 980, date('Y-m-d'));
                $stats['closure']++; $stats['actions']++;
            } else {
                $items[] = $this->item('closure', 'ready', '◎', 'Aguardando encerramento formal da fase',
                    'Fase '.($current['ordem'] ?? '—').' — '.($current['aba'] ?? ''), '100% documental aprovado', '',
                    'Nenhuma nova entrega é necessária. A próxima fase será liberada depois do encerramento formal pela Stratelli.',
                    '/'.$slug.'/workflow/fase/'.(int)$current['id'], 'Acompanhar fase', false, 300, date('Y-m-d'));
                $stats['closure']++;
            }
        }

        // Prazo da fase situacional: entra como ação apenas se houver algo que o perfil ainda pode resolver.
        $deadline = $state['prazoDashboard'] ?? null;
        $actionableDocs = $stats['send'] + $stats['correction'] + $stats['validation'];
        if ($deadline && $current && in_array((string)($deadline['status'] ?? ''), ['attention', 'overdue'], true)) {
            $isOverdue = ($deadline['status'] ?? '') === 'overdue';
            $items[] = $this->item('deadline', $isOverdue ? 'danger' : 'attention', '⌛',
                $isOverdue ? 'Prazo da fase em atraso' : 'Prazo da fase exige atenção',
                'Fase '.($current['ordem'] ?? '—').' — '.($current['aba'] ?? ''),
                (string)($deadline['rotulo'] ?? ''), '',
                (string)($deadline['texto'] ?? ''), '/'.$slug.'/workflow/fase/'.(int)$current['id'], 'Abrir fase', $actionableDocs > 0,
                $isOverdue ? 1000 : 820, date('Y-m-d'));
            $stats['deadline']++;
            if ($actionableDocs > 0) $stats['actions']++;
        }

        // Dependências visíveis, mas ainda não liberadas. Não entram no total de ações.
        $blockedAdded = 0;
        foreach (($state['fasesVisiveis'] ?? []) as $f) {
            $fid = (int)$f['id'];
            if (!empty($access[$fid]['visivel']) && empty($access[$fid]['pode_acessar'])) {
                $items[] = $this->item('blocked', 'neutral', '🔒', 'Fase aguardando liberação',
                    'Fase '.($f['ordem'] ?? '—').' — '.($f['aba'] ?? ''),
                    'Dependência do Workflow', '',
                    (string)($access[$fid]['motivo'] ?? 'A fase anterior precisa ser concluída.'), '/'.$slug.'/workflow', 'Ver Workflow', false, 120);
                $stats['blocked']++; $blockedAdded++;
                if ($blockedAdded >= 3) break;
            }
        }

        usort($items, fn($a, $b) => [$b['priority'], strtotime($b['date'] ?: '1970-01-01'), $a['title']] <=> [$a['priority'], strtotime($a['date'] ?: '1970-01-01'), $b['title']]);

        return [
            'scope' => $scope,
            'items' => $items,
            'stats' => $stats,
            'actionCount' => (int)$stats['actions'],
            'trackingCount' => count($items) - (int)$stats['actions'],
        ];
    }

    public function macro(?array $macroData = null): array
    {
        $macroData ??= (new MacroDashboardService())->load();
        $items = [];
        $stats = ['actions'=>0,'validation'=>0,'send'=>0,'deadline'=>0,'monitoring'=>0,'closure'=>0,'clients'=>0];
        $clientIds = [];

        foreach (($macroData['clientes'] ?? []) as $client) {
            $phase = $client['fase_atual'] ?? null;
            if (!$phase || (int)($client['ativo'] ?? 0) !== 1) continue;
            $stats['clients']++;
            $mid = (int)$client['id']; $fid = (int)$phase['id']; $clientIds[$mid] = true;
            $reqs = $this->currentPhaseRequirements($mid, $fid);
            $url = '/'.$client['slug'].'/workflow/fase/'.$fid;
            $phaseText = 'Fase '.$phase['ordem'].' — '.$phase['aba'];

            foreach ($reqs as $r) {
                $status = (string)($r['doc_status'] ?? '');
                $unit = trim((string)(($r['secretaria_sigla'] ?? '') ?: ($r['secretaria_nome'] ?? '')));
                if ($status === 'AGUARDANDO') {
                    $items[] = $this->item('validation','waiting','…','Validar documento recebido',(string)$r['nome'],
                        $client['nome'].' - '.$client['uf'].' · '.$phaseText,$unit,
                        'Documento aguardando validação da Stratelli.',$url,'Validar documento',true,900,(string)($r['enviado_em']??''),$client);
                    $stats['validation']++;$stats['actions']++;
                } elseif ((string)$r['perfil_envio'] === 'STRATELLI' && !$status) {
                    $items[] = $this->item('send','info','↑','Documento da Stratelli para enviar',(string)$r['nome'],
                        $client['nome'].' - '.$client['uf'].' · '.$phaseText,$unit,
                        'Documento sob responsabilidade da Stratelli ainda não enviado.',$url,'Abrir fase',true,780,'',$client);
                    $stats['send']++;$stats['actions']++;
                } elseif ((string)$r['perfil_envio'] === 'MUNICIPIO' && !$status) {
                    $items[] = $this->item('monitoring','pending','×','Aguardando envio municipal',(string)$r['nome'],
                        $client['nome'].' - '.$client['uf'].' · '.$phaseText,$unit,
                        'A unidade municipal ainda não realizou o envio.',$url,'Acompanhar',false,320,'',$client);
                    $stats['monitoring']++;
                } elseif ($status === 'CORRECAO') {
                    $items[] = $this->item('monitoring','correction','!','Aguardando correção municipal',(string)$r['nome'],
                        $client['nome'].' - '.$client['uf'].' · '.$phaseText,$unit,
                        (string)($r['observacao_validacao'] ?: 'Correção solicitada; aguardando reenvio.'),$url,'Acompanhar',false,420,(string)($r['validado_em']??''),$client);
                    $stats['monitoring']++;
                }
            }

            if (!empty($client['fase_pronta_encerramento'])) {
                $items[] = $this->item('closure','ready','✓','Encerrar fase formalmente',
                    $client['nome'].' - '.$client['uf'],$phaseText,'',
                    'Todos os documentos obrigatórios estão aprovados. Falta registrar o encerramento formal para liberar a próxima fase.',
                    $url,'Encerrar fase',true,990,date('Y-m-d'),$client);
                $stats['closure']++;$stats['actions']++;
            }

            $deadline = $client['prazo_atual'] ?? null;
            if ($deadline && in_array((string)($deadline['status'] ?? ''), ['attention','overdue'], true)) {
                $overdue = ($deadline['status'] ?? '') === 'overdue';
                $items[] = $this->item('deadline',$overdue?'danger':'attention','⌛',
                    $overdue?'Cliente com prazo em atraso':'Cliente em atenção de prazo',
                    $client['nome'].' - '.$client['uf'],$phaseText,'',
                    (string)($deadline['texto'] ?? ''),'/'.$client['slug'].'/dashboard','Abrir Dashboard',true,$overdue?1050:840,date('Y-m-d'),$client);
                $stats['deadline']++;$stats['actions']++;
            }
        }

        usort($items, fn($a, $b) => [$b['priority'], strtotime($b['date'] ?: '1970-01-01'), $a['title']] <=> [$a['priority'], strtotime($a['date'] ?: '1970-01-01'), $b['title']]);
        return ['items'=>$items,'stats'=>$stats,'actionCount'=>(int)$stats['actions'],'trackingCount'=>count($items)-(int)$stats['actions']];
    }

    private function currentPhaseRequirements(int $mid, int $fid): array
    {
        $st = $this->pdo->prepare('SELECT r.*,s.nome secretaria_nome,s.sigla secretaria_sigla,d.nome departamento_nome,
            doc.status doc_status,doc.observacao_validacao,doc.enviado_em,doc.validado_em
            FROM requisitos_documentais r
            JOIN secretarias s ON s.id=r.secretaria_id AND s.municipio_id=r.municipio_id
            LEFT JOIN departamentos d ON d.id=r.departamento_id AND d.municipio_id=r.municipio_id
            LEFT JOIN documentos_enviados doc ON doc.id=(SELECT MAX(d2.id) FROM documentos_enviados d2 WHERE d2.municipio_id=r.municipio_id AND d2.requisito_id=r.id)
            WHERE r.municipio_id=? AND r.fase_id=? AND r.ativo=1 ORDER BY r.ordem,r.id');
        $st->execute([$mid,$fid]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function item(string $type,string $tone,string $icon,string $title,string $document,string $phase,string $unit,string $text,string $url,string $actionLabel,bool $action,int $priority,string $date='',?array $client=null): array
    {
        return [
            'type'=>$type,'tone'=>$tone,'icon'=>$icon,'title'=>$title,'document'=>$document,'phase'=>$phase,'unit'=>$unit,'text'=>$text,
            'url'=>$url,'action_label'=>$actionLabel,'action'=>$action,'priority'=>$priority,'date'=>$date,
            'client_id'=>(int)($client['id']??0),'client_name'=>(string)($client['nome']??''),'client_uf'=>(string)($client['uf']??''),'client_slug'=>(string)($client['slug']??''),
        ];
    }
}
