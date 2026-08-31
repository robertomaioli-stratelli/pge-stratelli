<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Format;
use App\Core\Tenant;
use PDO;

final class WorkflowService
{
    private PDO $pdo;
    private int $municipioId;
    private array $user;
    private string $scope;

    public function __construct()
    {
        $this->pdo=Database::connection();
        $this->municipioId=(int)Tenant::id();
        $this->user=Auth::user()??[];
        $this->scope=Auth::isPlatformAdmin()?'stratelli':(($this->user['grupo']??'')==='USUARIO'?'secretaria':'municipio');
    }

    public function scope(): string { return $this->scope; }

    public function load(?int $requestedPhaseId=null): array
    {
        $mid=$this->municipioId;
        $parametrosInstancia=(new InstanceParameterService())->forMunicipio($mid);
        $secretariaId=(int)($this->user['secretaria_id']??0);
        $departamentoId=(int)($this->user['departamento_id']??0);
        $fasesTodas=$this->all('SELECT * FROM fases WHERE municipio_id=? ORDER BY ordem,id',[$mid]);
        $secretarias=$this->all('SELECT s.*,
            GROUP_CONCAT(CONCAT(f.ordem,": ",f.aba) ORDER BY f.ordem SEPARATOR " • ") fases_vinculadas,
            GROUP_CONCAT(f.id ORDER BY f.ordem) fase_ids
            FROM secretarias s
            LEFT JOIN fase_secretarias fs ON fs.secretaria_id=s.id AND fs.municipio_id=s.municipio_id
            LEFT JOIN fases f ON f.id=fs.fase_id AND f.municipio_id=s.municipio_id
            WHERE s.municipio_id=? GROUP BY s.id ORDER BY s.nome',[$mid]);
        $departamentos=$this->all('SELECT d.*,s.nome secretaria_nome FROM departamentos d JOIN secretarias s ON s.id=d.secretaria_id AND s.municipio_id=d.municipio_id WHERE d.municipio_id=? ORDER BY s.nome,d.nome',[$mid]);
        $tiposDocumento=$this->all('SELECT * FROM tipos_documento WHERE municipio_id=? ORDER BY nome',[$mid]);
        $requisitosTodos=$this->all('SELECT r.*,f.ordem fase_ordem,f.aba fase_aba,f.titulo fase_titulo,s.nome secretaria_nome,s.sigla secretaria_sigla,
            d.nome departamento_nome,t.nome tipo_nome,t.extensoes,
            (SELECT m.id FROM modelos_documentos m WHERE m.municipio_id=r.municipio_id AND m.requisito_id=r.id AND m.ativo=1 ORDER BY m.id DESC LIMIT 1) modelo_id,
            (SELECT m.arquivo_original FROM modelos_documentos m WHERE m.municipio_id=r.municipio_id AND m.requisito_id=r.id AND m.ativo=1 ORDER BY m.id DESC LIMIT 1) modelo_nome,
            (SELECT m.arquivo_salvo FROM modelos_documentos m WHERE m.municipio_id=r.municipio_id AND m.requisito_id=r.id AND m.ativo=1 ORDER BY m.id DESC LIMIT 1) modelo_caminho,
            (SELECT m.tamanho FROM modelos_documentos m WHERE m.municipio_id=r.municipio_id AND m.requisito_id=r.id AND m.ativo=1 ORDER BY m.id DESC LIMIT 1) modelo_tamanho,
            (SELECT m.criado_em FROM modelos_documentos m WHERE m.municipio_id=r.municipio_id AND m.requisito_id=r.id AND m.ativo=1 ORDER BY m.id DESC LIMIT 1) modelo_data
            FROM requisitos_documentais r
            JOIN fases f ON f.id=r.fase_id AND f.municipio_id=r.municipio_id
            JOIN secretarias s ON s.id=r.secretaria_id AND s.municipio_id=r.municipio_id
            LEFT JOIN departamentos d ON d.id=r.departamento_id AND d.municipio_id=r.municipio_id
            JOIN tipos_documento t ON t.id=r.tipo_documento_id AND t.municipio_id=r.municipio_id
            WHERE r.municipio_id=? ORDER BY f.ordem,r.ordem,r.id',[$mid]);

        $ultimosDocs=[];
        foreach($this->all('SELECT d.*,ue.nome enviado_por_nome,ue.email enviado_por_email,uv.nome validado_por_nome,uv.email validado_por_email FROM documentos_enviados d JOIN (
            SELECT requisito_id,MAX(id) max_id FROM documentos_enviados WHERE municipio_id=? GROUP BY requisito_id
        ) x ON x.max_id=d.id LEFT JOIN usuarios ue ON ue.id=d.enviado_por_usuario_id LEFT JOIN usuarios uv ON uv.id=d.validado_por_usuario_id WHERE d.municipio_id=?',[$mid,$mid]) as $d) $ultimosDocs[(int)$d['requisito_id']]=$d;

        $fasesVisiveis=array_values(array_filter($fasesTodas,function($f) use($requisitosTodos){
            if(!(int)$f['ativo']) return false;
            if($this->scope==='stratelli') return true;
            if((int)$f['exclusivo_stratelli']) return false;
            if($this->scope==='secretaria'){
                foreach($requisitosTodos as $r){
                    if((int)$r['fase_id']===(int)$f['id'] && (int)$r['ativo']===1 && $r['perfil_envio']==='MUNICIPIO' && $this->commonOwnsRequirement($r)) return true;
                }
                return false;
            }
            return true;
        }));

        $requisitosVisiveis=array_values(array_filter($requisitosTodos,function($r){
            if(!(int)$r['ativo']) return false;
            if($this->scope==='stratelli') return true;
            if($this->scope==='municipio') return $r['perfil_envio']==='MUNICIPIO';
            return $r['perfil_envio']==='MUNICIPIO' && $this->commonOwnsRequirement($r);
        }));

        $totalDocs=count($requisitosVisiveis);$totalEnviados=$totalAprovados=$totalCorrecoes=$aguardando=0;
        foreach($requisitosVisiveis as $r){
            $d=$ultimosDocs[(int)$r['id']]??null;
            if(!$d) continue;
            $totalEnviados++;
            if($d['status']==='APROVADO')$totalAprovados++;
            elseif($d['status']==='CORRECAO')$totalCorrecoes++;
            else $aguardando++;
        }
        $progresso=$totalDocs?round(($totalAprovados/$totalDocs)*100):0;

        [$dataInicioProcesso,$cronogramaPorFase,$diaAtualProjeto,$maxDiaCronograma,$percentualHojeGantt]=$this->buildSchedule($fasesTodas,$requisitosTodos,$ultimosDocs);

        $statusGlobalPorFase=[];$faseGlobalAtual=null;$faseGlobalAtualStatus='pending';
        foreach(array_values(array_filter($fasesTodas,fn($f)=>(int)$f['ativo']===1)) as $f){
            $prazo=$cronogramaPorFase[(int)$f['id']]??null;
            $status=!empty($prazo['conclusao_real'])?'done':$this->phaseStatus($f,$requisitosTodos,$ultimosDocs);
            $statusGlobalPorFase[(int)$f['id']]=$status;
            if(!$faseGlobalAtual&&$status!=='done'){$faseGlobalAtual=$f;$faseGlobalAtualStatus=$status;}
        }
        if(!$faseGlobalAtual&&$fasesTodas){$ativos=array_values(array_filter($fasesTodas,fn($f)=>(int)$f['ativo']===1));if($ativos){$faseGlobalAtual=end($ativos);$faseGlobalAtualStatus='done';}}

        // Controle de acesso sequencial: uma fase só é liberada quando TODAS as anteriores estiverem formalmente encerradas.
        $faseAcesso=[];$anterioresConcluidas=true;$idsVisiveis=array_map(fn($f)=>(int)$f['id'],$fasesVisiveis);
        foreach(array_values(array_filter($fasesTodas,fn($f)=>(int)$f['ativo']===1)) as $f){
            $fid=(int)$f['id'];$visivel=in_array($fid,$idsVisiveis,true);$pode=$this->scope==='stratelli'?true:($visivel&&$anterioresConcluidas);
            $motivo='';
            if(!$visivel)$motivo=$this->scope==='secretaria'?'Fase sem documentos atribuídos à sua unidade.':'Fase indisponível para este perfil.';
            elseif(!$pode)$motivo='A fase anterior ainda não foi encerrada formalmente pela Stratelli.';
            $faseAcesso[$fid]=['pode_acessar'=>$pode,'visivel'=>$visivel,'motivo'=>$motivo];
            if(($statusGlobalPorFase[$fid]??'pending')!=='done')$anterioresConcluidas=false;
        }

        $dashboardRequisitosBase=$requisitosVisiveis;
        $fasesSituacionais=[];$faseSituacional=null;$faseSituacionalStatus='pending';
        foreach($fasesVisiveis as $f){
            $prazo=$cronogramaPorFase[(int)$f['id']]??null;
            $status=$statusGlobalPorFase[(int)$f['id']]??'pending';
            $f['prazo_operacional']=$prazo;$f['status_situacional']=$status;$fasesSituacionais[]=$f;
            if(!$faseSituacional&&$status!=='done'){$faseSituacional=$f;$faseSituacionalStatus=$status;}
        }
        if(!$faseSituacional&&$fasesSituacionais){$faseSituacional=end($fasesSituacionais);$faseSituacionalStatus='done';}
        $prazoDashboard=$faseSituacional?($cronogramaPorFase[(int)$faseSituacional['id']]??null):null;

        $dashboardRequisitosFase=$faseSituacional?array_values(array_filter($dashboardRequisitosBase,fn($r)=>(int)$r['fase_id']===(int)$faseSituacional['id'])):[];
        $dashboardFaseTotal=count($dashboardRequisitosFase);$dashboardFaseEnviados=$dashboardFaseAprovados=$dashboardFaseCorrecoes=$dashboardFaseAguardando=$dashboardFaseNaoEntregues=0;$dashboardSecretariasPendentes=[];
        foreach($dashboardRequisitosFase as $r){
            $d=$ultimosDocs[(int)$r['id']]??null;
            if(!$d)$dashboardFaseNaoEntregues++;
            else{$dashboardFaseEnviados++;if($d['status']==='APROVADO')$dashboardFaseAprovados++;elseif($d['status']==='CORRECAO')$dashboardFaseCorrecoes++;else$dashboardFaseAguardando++;}
            if($r['perfil_envio']!=='MUNICIPIO')continue;
            $status='';$rotulo='';
            if(!$d){$status='not-delivered';$rotulo='NÃO ENTREGUE';}
            elseif($d['status']==='CORRECAO'){$status='needs-correction';$rotulo='EM CORREÇÃO';}
            else continue;
            $key=(string)$r['secretaria_id'];
            if(!isset($dashboardSecretariasPendentes[$key]))$dashboardSecretariasPendentes[$key]=['id'=>(int)$r['secretaria_id'],'nome'=>$r['secretaria_nome'],'sigla'=>$r['secretaria_sigla'],'nao_entregues'=>0,'correcoes'=>0,'documentos'=>[]];
            if($status==='not-delivered')$dashboardSecretariasPendentes[$key]['nao_entregues']++;else$dashboardSecretariasPendentes[$key]['correcoes']++;
            $dashboardSecretariasPendentes[$key]['documentos'][]=['nome'=>$r['nome'],'departamento'=>$r['departamento_nome']??'','tipo'=>$r['tipo_nome'],'status_visual'=>$status,'rotulo'=>$rotulo,'observacao'=>$status==='needs-correction'?($d['observacao_validacao']??''):''];
        }
        $dashboardSecretariasPendentes=array_values($dashboardSecretariasPendentes);
        foreach($dashboardSecretariasPendentes as &$s){$s['total_pendencias']=$s['nao_entregues']+$s['correcoes'];$s['status_visual']=$s['nao_entregues']>0?'not-delivered':'needs-correction';}unset($s);
        usort($dashboardSecretariasPendentes,fn($a,$b)=>[$b['nao_entregues'],$b['correcoes'],$a['nome']]<=>[$a['nao_entregues'],$a['correcoes'],$b['nome']]);
        $dashboardTotalPendencias=array_sum(array_column($dashboardSecretariasPendentes,'total_pendencias'));
        $dashboardFaseProgresso=$dashboardFaseTotal?round(($dashboardFaseAprovados/$dashboardFaseTotal)*100):0;
        $dashboardFasesConcluidas=count(array_filter($fasesSituacionais,fn($f)=>($f['status_situacional']??'')==='done'));
        $dashboardQtdFases=count($fasesSituacionais);
        $dashboardResumoCard=['titulo'=>$this->scope==='stratelli'?'Resumo geral do processo':($this->scope==='secretaria'?'Resumo da secretaria':'Resumo municipal'),'progresso'=>$progresso.'%','pendencias'=>(string)$dashboardTotalPendencias,'analise'=>(string)$dashboardFaseAguardando,'fases'=>$dashboardFasesConcluidas.'/'.$dashboardQtdFases,'prazo'=>(string)($prazoDashboard['rotulo']??'—')];

        // Seleção da fase detalhada respeitando visibilidade e bloqueio sequencial.
        $atividade=null;$atividadePodeAcessar=false;
        if($requestedPhaseId){
            foreach($fasesVisiveis as $f){if((int)$f['id']===$requestedPhaseId){$atividade=$f;break;}}
            $atividadePodeAcessar=$atividade?(bool)($faseAcesso[(int)$atividade['id']]['pode_acessar']??false):false;
        }else{
            // Prioriza a primeira fase visível ainda não concluída e já liberada; depois a última fase liberada.
            foreach($fasesVisiveis as $f){$fid=(int)$f['id'];if(($faseAcesso[$fid]['pode_acessar']??false)&&($statusGlobalPorFase[$fid]??'pending')!=='done'){$atividade=$f;$atividadePodeAcessar=true;break;}}
            if(!$atividade){foreach(array_reverse($fasesVisiveis) as $f){$fid=(int)$f['id'];if($faseAcesso[$fid]['pode_acessar']??false){$atividade=$f;$atividadePodeAcessar=true;break;}}}
            if(!$atividade&&$fasesVisiveis)$atividade=$fasesVisiveis[0];
        }
        $faseAtualId=(int)($atividade['id']??0);
        $requisitosFase=($atividade&&$atividadePodeAcessar)?array_values(array_filter($requisitosTodos,function($r)use($atividade){
            if((int)$r['fase_id']!==(int)$atividade['id']||(int)$r['ativo']!==1)return false;
            if($this->scope==='stratelli')return true;
            if($this->scope==='municipio')return $r['perfil_envio']==='MUNICIPIO';
            return $r['perfil_envio']==='MUNICIPIO'&&$this->commonOwnsRequirement($r);
        })):[];
        $secretariaTemDocumentosFaseAtual=$this->scope!=='secretaria'||count($requisitosFase)>0;

        $workflowCounts=['total'=>count($requisitosFase),'enviados'=>0,'aguardando'=>0,'correcoes'=>0,'pendentes'=>0,'aprovados'=>0];
        foreach($requisitosFase as $r){$d=$ultimosDocs[(int)$r['id']]??null;if(!$d){$workflowCounts['pendentes']++;continue;}$workflowCounts['enviados']++;if($d['status']==='CORRECAO')$workflowCounts['correcoes']++;elseif($d['status']==='APROVADO')$workflowCounts['aprovados']++;else$workflowCounts['aguardando']++;}

        $gruposDocumentosFase=$this->groupDocuments($requisitosFase,$ultimosDocs);
        $prazoAtividade=$atividade?($cronogramaPorFase[(int)$atividade['id']]??null):null;
        [$workflowPrazoEntregaTexto,$workflowPrazoEntregaComplemento,$workflowPrazoEntregaClasse]=$this->secretaryDeadline($workflowCounts,$prazoAtividade);
        [$resumoEntregasFase,$summary,$secretariasResumoFase,$secretariasPendentesFase]=$this->deliverySummary($requisitosFase,$ultimosDocs);

        $statusAtual=$atividade?($statusGlobalPorFase[(int)$atividade['id']]??'pending'):'pending';
        $faseVisualClass=match($statusAtual){'done'=>'phase-complete','ready'=>'phase-ready','correction'=>'phase-correction',default=>'phase-pending'};
        $faseStatusLabel=match($statusAtual){'done'=>'ENCERRADA','ready'=>'PRONTA PARA ENCERRAMENTO','correction'=>'CORREÇÃO SOLICITADA','run'=>'EM ANDAMENTO',default=>'PENDENTE'};
        $closureService=new PhaseClosureService();
        $faseFechamento=$atividade?$closureService->current((int)$atividade['id']):null;
        $faseFormalmenteEncerrada=$faseFechamento&&strtoupper((string)($faseFechamento['status']??''))==='ENCERRADA';
        $faseElegibilidade=$atividade?$closureService->eligibility((int)$atividade['id']):['eligible'=>false,'total'=>0,'approved'=>0,'waiting'=>0,'correction'=>0,'missing'=>0];
        $faseElegivelEncerramento=!$faseFormalmenteEncerrada&&!empty($faseElegibilidade['eligible']);
        $faseHistoricoFormal=$atividade?$closureService->history((int)$atividade['id']):[];
        $faseSnapshot=null;if($faseFechamento&&!empty($faseFechamento['snapshot_documental'])){$decoded=json_decode((string)$faseFechamento['snapshot_documental'],true);if(is_array($decoded))$faseSnapshot=$decoded;}

        $historicoFase=$this->phaseHistory($atividade,$secretariaId);
        $logsPorPagina=6;$totalLogsFase=count($historicoFase);$totalPaginasLog=max(1,(int)ceil($totalLogsFase/$logsPorPagina));$paginaLog=max(1,min($totalPaginasLog,(int)($_GET['log_pagina']??1)));$inicioLog=($paginaLog-1)*$logsPorPagina;$historicoFasePagina=array_slice($historicoFase,$inicioLog,$logsPorPagina);

        try{(new NotificationService())->syncDeadlineState($faseSituacional,$prazoDashboard);}catch(\Throwable){}
        $notificacoes=[];$notificacaoContagemAtiva=0; // O layout usa agora notificações persistentes do NotificationService.

        $relatorioFases=$this->reportPhases($fasesVisiveis,$requisitosVisiveis,$ultimosDocs,$cronogramaPorFase);
        $relatorioSecretarias=$this->reportSecretaries($requisitosVisiveis,$ultimosDocs);
        $requisitosCatalogo=$this->scope==='municipio'?array_values(array_filter($requisitosVisiveis,fn($r)=>$faseAcesso[(int)$r['fase_id']]['pode_acessar']??false)):$requisitosVisiveis;
        $documentosPorFasePagina=$this->documentCatalog($requisitosCatalogo,$ultimosDocs);
        $documentosPagina=[];foreach($documentosPorFasePagina as $g)foreach($g['itens'] as $i)$documentosPagina[]=$i;

        return compact('fasesTodas','fasesVisiveis','fasesSituacionais','faseGlobalAtual','faseGlobalAtualStatus','faseAcesso','faseSituacional','faseSituacionalStatus','faseAtualId','atividade','atividadePodeAcessar','secretarias','departamentos','tiposDocumento','requisitosTodos','requisitosVisiveis','requisitosFase','ultimosDocs','totalDocs','totalEnviados','totalAprovados','totalCorrecoes','aguardando','progresso','dataInicioProcesso','cronogramaPorFase','diaAtualProjeto','maxDiaCronograma','percentualHojeGantt','prazoDashboard','dashboardRequisitosFase','dashboardFaseTotal','dashboardFaseEnviados','dashboardFaseAprovados','dashboardFaseCorrecoes','dashboardFaseAguardando','dashboardFaseNaoEntregues','dashboardSecretariasPendentes','dashboardTotalPendencias','dashboardFaseProgresso','dashboardFasesConcluidas','dashboardQtdFases','dashboardResumoCard','secretariaTemDocumentosFaseAtual','workflowCounts','gruposDocumentosFase','prazoAtividade','workflowPrazoEntregaTexto','workflowPrazoEntregaComplemento','workflowPrazoEntregaClasse','resumoEntregasFase','summary','secretariasResumoFase','secretariasPendentesFase','statusAtual','faseVisualClass','faseStatusLabel','faseFechamento','faseFormalmenteEncerrada','faseElegibilidade','faseElegivelEncerramento','faseHistoricoFormal','faseSnapshot','historicoFase','historicoFasePagina','logsPorPagina','totalLogsFase','totalPaginasLog','paginaLog','notificacoes','notificacaoContagemAtiva','parametrosInstancia','relatorioFases','relatorioSecretarias','documentosPorFasePagina','documentosPagina') + ['scope'=>$this->scope,'tenant'=>Tenant::current(),'user'=>$this->user];
    }

    public function historyData(): array
    {
        $state=$this->load();
        $mid=$this->municipioId;$secretariaId=(int)($this->user['secretaria_id']??0);$departamentoId=(int)($this->user['departamento_id']??0);
        $type=trim((string)($_GET['tipo']??''));$phase=max(0,(int)($_GET['fase']??0));
        $sortAllowed=['evento'=>'LOWER(COALESCE(h.evento,""))','fase'=>'COALESCE(f.ordem,999999)','documento'=>'LOWER(COALESCE(r.nome,""))','secretaria'=>'LOWER(COALESCE(s.nome,""))','responsavel'=>'LOWER(COALESCE(u.nome,""))','perfil'=>'LOWER(COALESCE(u.grupo,""))','data'=>'h.criado_em','origem'=>'LOWER(COALESCE(h.ip,""))','detalhamento'=>'LOWER(COALESCE(h.motivo,""))'];
        $requestedSort=(string)($_GET['ordem']??'data');
        $sort=array_key_exists($requestedSort,$sortAllowed)?$requestedSort:'data';
        $dir=strtolower((string)($_GET['direcao']??'desc'))==='asc'?'asc':'desc';
        $sql='SELECT h.*,r.nome documento,r.perfil_envio,r.secretaria_id,f.ordem fase_ordem,f.aba fase_aba,s.nome secretaria_nome,s.sigla secretaria_sigla,u.nome usuario_nome,u.email usuario_email,u.grupo usuario_grupo,u.administrador_plataforma
            FROM historico_documentos h
            LEFT JOIN requisitos_documentais r ON r.id=h.requisito_id AND r.municipio_id=h.municipio_id
            LEFT JOIN fases f ON f.id=h.fase_id AND f.municipio_id=h.municipio_id
            LEFT JOIN secretarias s ON s.id=r.secretaria_id AND s.municipio_id=h.municipio_id
            LEFT JOIN usuarios u ON u.id=h.usuario_id
            WHERE h.municipio_id=?';$params=[$mid];
        if($this->scope==='municipio')$sql.=' AND (r.perfil_envio="MUNICIPIO" OR r.id IS NULL)';
        if($this->scope==='secretaria'){$sql.=' AND r.perfil_envio="MUNICIPIO" AND r.secretaria_id=?';$params[]=$secretariaId;if($departamentoId){$sql.=' AND (r.departamento_id IS NULL OR r.departamento_id=?)';$params[]=$departamentoId;}}
        if($phase>0){$sql.=' AND h.fase_id=?';$params[]=$phase;}
        if($type!==''){$sql.=' AND LOWER(h.evento) LIKE ?';$params[]='%'.strtolower($type).'%';}
        $sql.=' ORDER BY '.$sortAllowed[$sort].' '.strtoupper($dir).', h.id DESC';
        $all=$this->all($sql,$params);
        $summary=['total'=>count($all),'envios'=>count(array_filter($all,fn($h)=>str_contains(strtolower((string)$h['evento']),'envi')||str_contains(strtolower((string)$h['evento']),'substitu'))),'aprovacoes'=>count(array_filter($all,fn($h)=>str_contains(strtolower((string)$h['evento']),'aprov'))),'correcoes'=>count(array_filter($all,fn($h)=>str_contains(strtolower((string)$h['evento']),'corre'))),'modelos'=>count(array_filter($all,fn($h)=>str_contains(strtolower((string)$h['evento']),'modelo')))];
        $perPage=10;$total=count($all);$pages=max(1,(int)ceil($total/$perPage));$page=max(1,min($pages,(int)($_GET['pagina']??1)));$start=($page-1)*$perPage;$items=array_slice($all,$start,$perPage);
        $visible=$pages<=7?range(1,$pages):array_values(array_unique(array_filter([1,$page-2,$page-1,$page,$page+1,$page+2,$pages],fn($p)=>$p>=1&&$p<=$pages)));sort($visible);
        return array_merge($state,compact('type','phase','sort','dir','summary','perPage','total','pages','page','start','items','visible'));
    }

    private function buildSchedule(array $phases,array $requirements,array $latest): array
    {
        $mid=$this->municipioId;
        $crono=$this->one('SELECT * FROM cronograma_processos WHERE municipio_id=?',[$mid]);
        if(!$crono){$this->pdo->prepare('INSERT INTO cronograma_processos(municipio_id,data_inicio,criado_em,atualizado_em) VALUES(?,CURDATE(),NOW(),NOW())')->execute([$mid]);$crono=$this->one('SELECT * FROM cronograma_processos WHERE municipio_id=?',[$mid]);}
        $start=(string)($crono['data_inicio']??date('Y-m-d'));$today=date('Y-m-d');
        $manual=[];foreach($this->all('SELECT * FROM cronograma_fases WHERE municipio_id=?',[$mid]) as $r)$manual[(int)$r['fase_id']]=$r;
        $schedule=[];$prevDeadline=null;$prevConclusion=null;$attentionDays=(new InstanceParameterService())->deadlineAlertDays($mid);
        foreach(array_values(array_filter($phases,fn($f)=>(int)$f['ativo']===1)) as $i=>$f){
            $duration=max(1,(int)$f['dia_fim']-(int)$f['dia_inicio']+1);$m=$manual[(int)$f['id']]??null;$closed=$m&&strtoupper((string)($m['status']??'ENCERRADA'))==='ENCERRADA';$real=$closed?($m['data_conclusao_real']??null):null;
            if($i===0){$phaseStart=$start;$blocked=false;}elseif($prevConclusion){$phaseStart=$this->addDays($prevConclusion,1);$blocked=false;}else{$phaseStart=$this->addDays((string)$prevDeadline,1);$blocked=true;}
            $end=$this->addDays($phaseStart,$duration-1);$daysRemaining=$this->days($today,$end);$deviation=$real?$this->days($end,$real):0;$attention=$attentionDays;
            if($real)$status=$deviation>0?'completed-late':'completed-on-time';elseif($blocked)$status='blocked';elseif($today<$phaseStart)$status='scheduled';elseif($daysRemaining<0)$status='overdue';elseif($daysRemaining<=$attention)$status='attention';else$status='on-track';
            $label=match($status){'completed-on-time'=>'ENCERRADA NO PRAZO','completed-late'=>'ENCERRADA COM ATRASO','overdue'=>'ATRASADA','attention'=>'ATENÇÃO AO PRAZO','on-track'=>'NO PRAZO','scheduled'=>'AGENDADA',default=>'AGUARDANDO FASE ANTERIOR'};
            $text=match($status){'completed-on-time'=>$deviation<0?abs($deviation).' dia(s) antes do limite':'Encerrada na data limite','completed-late'=>$deviation.' dia(s) de atraso','overdue'=>abs($daysRemaining).' dia(s) de atraso','attention'=>$daysRemaining===0?'Vence hoje':'Restam '.$daysRemaining.' dia(s)','on-track'=>'Restam '.$daysRemaining.' dia(s)','scheduled'=>'Início previsto em '.Format::date($phaseStart),default=>'A data definitiva depende da conclusão anterior'};
            if($today<$phaseStart)$percent=0;else{$consumed=$this->days($phaseStart,min($today,$end))+1;$percent=max(0,min(100,round(($consumed/$duration)*100)));}
            $schedule[(int)$f['id']]=['fase_id'=>(int)$f['id'],'duracao'=>$duration,'inicio'=>$phaseStart,'fim'=>$end,'conclusao_real'=>$real,'conclusao_origem'=>$real?'formal':'','bloqueada'=>$blocked,'status'=>$status,'rotulo'=>$label,'texto'=>$text,'dias_restantes'=>$daysRemaining,'dias_desvio'=>$deviation,'percentual_tempo'=>$percent,'provisorio'=>$blocked,'registro_manual'=>$m];
            $prevDeadline=$end;$prevConclusion=$real;
        }
        $day=max(1,$this->days($start,$today)+1);$max=90;foreach($phases as $f)$max=max($max,(int)$f['dia_fim']);$todayPct=max(0,min(100,(($day-1)/max(1,$max-1))*100));
        return [$start,$schedule,$day,$max,$todayPct];
    }

    private function phaseStatus(array $phase,array $requirements,array $latest): string
    {
        $docs=array_values(array_filter($requirements,fn($r)=>(int)$r['fase_id']===(int)$phase['id']&&(int)$r['ativo']===1&&(int)$r['obrigatorio']===1));if(!$docs)return'ready';$sent=$approved=$corrections=0;
        foreach($docs as $r){$d=$latest[(int)$r['id']]??null;if($d){$sent++;if($d['status']==='APROVADO')$approved++;elseif($d['status']==='CORRECAO')$corrections++;}}
        if($approved===count($docs))return'ready';if($corrections>0)return'correction';if($sent>0)return'run';return'pending';
    }

    private function groupDocuments(array $requirements,array $latest): array
    {
        $groups=[];$base=[];$norm=function($v){$x=(string)$v;$x=function_exists('mb_strtolower')?mb_strtolower($x,'UTF-8'):strtolower($x);return preg_replace('/\s+/u',' ',trim($x))?:'';};
        foreach($requirements as $r){$key=implode('|',[(int)$r['fase_id'],$norm($r['nome']),(int)$r['tipo_documento_id'],$r['perfil_envio'],(int)$r['obrigatorio']]);$base[$key][]=$r;}
        foreach($base as $items){usort($items,fn($a,$b)=>[(int)$a['ordem'],(int)$a['id']]<=>[(int)$b['ordem'],(int)$b['id']]);$ref=$items[0];$model=null;foreach($items as $r)if(!empty($r['modelo_id'])){$model=$r;break;}$deliveries=[];$counts=['approved'=>0,'waiting'=>0,'correction'=>0,'pending'=>0];
            foreach($items as $r){$d=$latest[(int)$r['id']]??null;[$label,$class]=$this->documentStatus($d);$counts[$class]++;$deliveries[]=['requisito'=>$r,'documento'=>$d,'rotulo'=>$label,'classe'=>$class];}
            $total=count($deliveries);if($counts['correction']>0){$label='CORREÇÃO PENDENTE';$class='correction';}elseif($counts['waiting']>0){$label='EM ANÁLISE';$class='waiting';}elseif($counts['approved']===$total){$label='TODAS APROVADAS';$class='approved';}else{$label=$counts['pending'].' ENTREGA(S) PENDENTE(S)';$class='pending';}
            $groups[]=['referencia'=>$ref,'modelo'=>$model,'requisitos'=>$items,'ids'=>implode(',',array_map(fn($x)=>(int)$x['id'],$items)),'entregas'=>$deliveries,'contagem'=>$counts,'rotulo_geral'=>$label,'classe_geral'=>$class,'ordem'=>(int)$ref['ordem']];
        }
        usort($groups,fn($a,$b)=>[$a['ordem'],(int)$a['referencia']['id']]<=>[$b['ordem'],(int)$b['referencia']['id']]);return$groups;
    }

    private function deliverySummary(array $requirements,array $latest): array
    {
        $items=[];$counts=['delivered'=>0,'awaiting_validation'=>0,'needs_correction'=>0,'not_delivered'=>0];$secs=[];
        foreach($requirements as $r){$d=$latest[(int)$r['id']]??null;if(!$d){$visual='not-delivered';$label='NÃO ENTREGUE';$icon='×';}elseif($d['status']==='CORRECAO'){$visual='needs-correction';$label='CORREÇÃO NECESSÁRIA';$icon='!';}elseif($d['status']==='APROVADO'){$visual='delivered';$label='ENTREGUE E APROVADO';$icon='✓';}else{$visual='awaiting-validation';$label='AGUARDANDO VALIDAÇÃO';$icon='…';}
            $countKey=str_replace('-','_',$visual);$counts[$countKey]++;$items[]=['requisito'=>$r,'documento'=>$d,'status_visual'=>$visual,'rotulo'=>$label,'icone'=>$icon];$key=(string)$r['secretaria_id'];if(!isset($secs[$key]))$secs[$key]=['id'=>(int)$r['secretaria_id'],'nome'=>$r['secretaria_nome'],'sigla'=>$r['secretaria_sigla'],'total'=>0,'delivered'=>0,'awaiting_validation'=>0,'needs_correction'=>0,'not_delivered'=>0,'pendencias'=>0,'documentos_pendentes'=>[],'documentos_correcao'=>[],'documentos_aguardando'=>[]];$secs[$key]['total']++;
            if($visual==='not-delivered'){$secs[$key]['not_delivered']++;$secs[$key]['pendencias']++;$secs[$key]['documentos_pendentes'][]=$r['nome'];}elseif($visual==='needs-correction'){$secs[$key]['needs_correction']++;$secs[$key]['pendencias']++;$secs[$key]['documentos_correcao'][]=$r['nome'];}elseif($visual==='awaiting-validation'){$secs[$key]['awaiting_validation']++;$secs[$key]['documentos_aguardando'][]=$r['nome'];}else$secs[$key]['delivered']++;
        }
        $pending=array_values(array_filter($secs,fn($s)=>$s['pendencias']>0));foreach($pending as &$s){$parts=[];if($s['not_delivered'])$parts[]=$s['not_delivered'].' não entregue(s)';if($s['needs_correction'])$parts[]=$s['needs_correction'].' em correção';$s['resumo_pendencia']=implode(' · ',$parts);$s['status_visual']=$s['not_delivered']?'not-delivered':'needs-correction';$s['rotulo']=$s['not_delivered']?'ENVIO PENDENTE':'CORREÇÃO PENDENTE';}unset($s);
        $total=count($items);$summary=$counts+['total'=>$total,'percent_delivered'=>$total?($counts['delivered']/$total)*100:0,'percent_waiting'=>$total?($counts['awaiting_validation']/$total)*100:0,'percent_correction'=>$total?($counts['needs_correction']/$total)*100:0,'percent_pending'=>$total?($counts['not_delivered']/$total)*100:0];return[$items,$summary,$secs,$pending];
    }

    private function secretaryDeadline(array $counts,?array $deadline): array
    {
        if($this->scope!=='secretaria')return['—','',(string)($deadline['status']??'blocked')];$text='—';$sub='';$class=(string)($deadline['status']??'blocked');$needs=$counts['pendentes']+$counts['correcoes'];
        if($needs>0){$days=(int)($deadline['dias_restantes']??0);$status=(string)($deadline['status']??'blocked');if(in_array($status,['on-track','attention'],true)){$text=$days===0?'Vence hoje':($days===1?'Resta 1 dia':'Restam '.$days.' dias');$sub=!empty($deadline['fim'])?'Entrega até '.Format::date($deadline['fim']):'';}elseif($status==='overdue'){$late=abs($days);$text='Prazo encerrado há '.$late.' dia(s)';$sub='Envio ou correção em atraso';}elseif($status==='scheduled'){$text='Prazo ainda não iniciado';$sub=!empty($deadline['inicio'])?'Início em '.Format::date($deadline['inicio']):'';}else{$text='Aguardando fase anterior';$sub='A data será liberada pelo andamento global';}}
        elseif($counts['aguardando']>0){$text='Entrega realizada';$sub='Aguardando validação da Stratelli';$class='waiting';}elseif($counts['total']>0){$text='Entregas concluídas';$sub='Documentos aprovados pela Stratelli';$class='completed-on-time';}return[$text,$sub,$class];
    }

    private function notifications(array $requirements,array $latest,array $phases,?array $current,?array $deadline): array
    {
        $by=[];foreach($phases as $f)$by[(int)$f['id']]=$f;$out=[];$currentOrder=(int)($current['ordem']??PHP_INT_MAX);
        $add=function($type,$icon,$title,$text,$phase,$date='',$priority=100,$action=true)use(&$out){$out[]=compact('type','icon','title','text','phase','date','priority','action');};
        foreach($requirements as $r){$fid=(int)$r['fase_id'];$f=$by[$fid]??null;if(!$f||(int)$f['ordem']>$currentOrder)continue;$d=$latest[(int)$r['id']]??null;$label=$r['nome'].' · '.$r['secretaria_nome'].' · Fase '.$f['ordem'].' · '.$f['aba'];if(!$d){if($current&&$fid===(int)$current['id']&&$r['perfil_envio']==='MUNICIPIO')$add('not-delivered','×',$this->scope==='stratelli'?'Entrega municipal pendente':'Documento ainda não enviado',$label,$fid,'',320,true);continue;}if($d['status']==='AGUARDANDO')$add('waiting','…',$this->scope==='stratelli'?'Documento aguardando validação':'Documento aguardando análise',$r['nome'].' · '.$r['secretaria_nome'].' · enviado em '.Format::dateTime($d['enviado_em']),$fid,$d['enviado_em'],$this->scope==='stratelli'?430:250,$this->scope==='stratelli');elseif($d['status']==='CORRECAO')$add('correction','!',$this->scope==='stratelli'?'Aguardando reenvio corrigido':'Correção solicitada pela Stratelli',$r['nome'].' · '.$r['secretaria_nome'].(!empty($d['observacao_validacao'])?' · '.$d['observacao_validacao']:''),$fid,$d['validado_em']?:$d['enviado_em'],410,true);}
        if($current&&$deadline){if(in_array($deadline['status'],['overdue','completed-late'],true))$add('not-delivered','⌛','Prazo da fase em atraso','Fase '.$current['ordem'].' · '.$deadline['texto'],(int)$current['id'],date('Y-m-d'),500,true);elseif($deadline['status']==='attention')$add('correction','⌛','Atenção ao prazo da fase','Fase '.$current['ordem'].' · '.$deadline['texto'],(int)$current['id'],date('Y-m-d'),450,true);}
        usort($out,fn($a,$b)=>[$b['priority'],strtotime($b['date']?:'1970-01-01')]<=>[$a['priority'],strtotime($a['date']?:'1970-01-01')]);return array_slice($out,0,12);
    }

    private function phaseHistory(?array $activity,int $secretariaId): array
    {
        if(!$activity)return[];$sql='SELECT h.*,r.nome documento,s.nome secretaria_nome,u.nome usuario_nome,u.email usuario_email,u.grupo usuario_grupo,u.administrador_plataforma FROM historico_documentos h LEFT JOIN requisitos_documentais r ON r.id=h.requisito_id AND r.municipio_id=h.municipio_id LEFT JOIN secretarias s ON s.id=r.secretaria_id AND s.municipio_id=h.municipio_id LEFT JOIN usuarios u ON u.id=h.usuario_id WHERE h.municipio_id=? AND h.fase_id=?';$p=[$this->municipioId,(int)$activity['id']];if($this->scope==='secretaria'){$sql.=' AND r.secretaria_id=? AND r.perfil_envio="MUNICIPIO"';$p[]=$secretariaId;$departmentId=(int)($this->user['departamento_id']??0);if($departmentId){$sql.=' AND (r.departamento_id IS NULL OR r.departamento_id=?)';$p[]=$departmentId;}}$sql.=' ORDER BY h.id DESC LIMIT 100';return$this->all($sql,$p);
    }

    private function documentCatalog(array $requirements,array $latest): array
    {
        $groups=[];foreach($requirements as $r){$d=$latest[(int)$r['id']]??null;[$label,$class]=$this->documentStatus($d);$item=['requisito'=>$r,'documento'=>$d,'rotulo'=>$label,'classe'=>$class,'tem_modelo'=>!empty($r['modelo_id'])];$fid=(int)$r['fase_id'];if(!isset($groups[$fid]))$groups[$fid]=['fase_id'=>$fid,'ordem'=>(int)$r['fase_ordem'],'aba'=>$r['fase_aba'],'titulo'=>$r['fase_titulo'],'itens'=>[]];$groups[$fid]['itens'][]=$item;}uasort($groups,fn($a,$b)=>[$a['ordem'],$a['fase_id']]<=>[$b['ordem'],$b['fase_id']]);return$groups;
    }

    private function reportPhases(array $phases,array $requirements,array $latest,array $schedule): array
    {
        $out=[];foreach($phases as $f){$req=array_values(array_filter($requirements,fn($r)=>(int)$r['fase_id']===(int)$f['id']&&(int)$r['ativo']===1));$a=$w=$c=$p=0;foreach($req as $r){$d=$latest[(int)$r['id']]??null;if(!$d)$p++;elseif($d['status']==='APROVADO')$a++;elseif($d['status']==='CORRECAO')$c++;else$w++;}$t=count($req);$out[]=['fase'=>$f,'total'=>$t,'aprovados'=>$a,'analise'=>$w,'correcao'=>$c,'pendentes'=>$p,'progresso'=>$t?round(($a/$t)*100):0,'prazo'=>$schedule[(int)$f['id']]??null];}return$out;
    }

    private function reportSecretaries(array $requirements,array $latest): array
    {
        $out=[];foreach($requirements as $r){$k=(int)$r['secretaria_id'];if(!isset($out[$k]))$out[$k]=['nome'=>$r['secretaria_nome'],'sigla'=>$r['secretaria_sigla'],'total'=>0,'aprovados'=>0,'analise'=>0,'correcao'=>0,'pendentes'=>0];$out[$k]['total']++;$d=$latest[(int)$r['id']]??null;if(!$d)$out[$k]['pendentes']++;elseif($d['status']==='APROVADO')$out[$k]['aprovados']++;elseif($d['status']==='CORRECAO')$out[$k]['correcao']++;else$out[$k]['analise']++;}foreach($out as &$s)$s['progresso']=$s['total']?round(($s['aprovados']/$s['total'])*100):0;unset($s);uasort($out,fn($a,$b)=>[$b['pendentes']+$b['correcao'],$a['nome']]<=>[$a['pendentes']+$a['correcao'],$b['nome']]);return$out;
    }

    private function commonOwnsRequirement(array $r): bool
    {
        $secretariaId=(int)($this->user['secretaria_id']??0);$departamentoId=(int)($this->user['departamento_id']??0);
        if(!$secretariaId||(int)($r['secretaria_id']??0)!==$secretariaId)return false;
        if(!$departamentoId)return true;
        $reqDepartment=(int)($r['departamento_id']??0);
        return $reqDepartment===0||$reqDepartment===$departamentoId;
    }

    private function documentStatus(?array $doc): array {if(!$doc)return['PENDENTE','pending'];return match($doc['status']){'APROVADO'=>['APROVADO','approved'],'CORRECAO'=>['CORREÇÃO','correction'],default=>['AGUARDANDO','waiting']};}
    private function addDays(string $date,int $days): string{return(new \DateTimeImmutable($date))->modify(($days>=0?'+':'').$days.' days')->format('Y-m-d');}
    private function days(string $a,string $b): int{return(int)(new \DateTimeImmutable($a))->diff(new \DateTimeImmutable($b))->format('%r%a');}
    private function all(string $sql,array $params=[]): array{$st=$this->pdo->prepare($sql);$st->execute($params);return$st->fetchAll(PDO::FETCH_ASSOC);}
    private function one(string $sql,array $params=[]): ?array{$st=$this->pdo->prepare($sql);$st->execute($params);$r=$st->fetch(PDO::FETCH_ASSOC);return$r?:null;}
}
