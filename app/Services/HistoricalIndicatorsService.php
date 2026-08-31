<?php
namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Tenant;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class HistoricalIndicatorsService
{
    private PDO $pdo;
    private array $user;

    public function __construct()
    {
        $this->pdo=Database::connection();
        $this->user=Auth::user()??[];
    }

    public function tenant(array $query=[]): array
    {
        $mid=(int)Tenant::id();
        if(!$mid) throw new RuntimeException('Município não resolvido.');
        $months=$this->months($query);
        $periodStart=$this->periodStart($months);
        $scope=Auth::isPlatformAdmin()?'stratelli':(($this->user['grupo']??'')==='USUARIO'?'secretaria':'municipio');
        $visiblePhaseIds=$scope==='secretaria'?$this->visiblePhaseIdsForCommonUser($mid):null;

        $phase=$this->phasePerformance($mid,$periodStart,$visiblePhaseIds);
        $docs=$this->documentMetrics($mid,$periodStart,$scope==='secretaria');
        $monthly=$this->monthlyEvolution($mid,$months,$scope==='secretaria');
        $secretarias=$this->correctionsBySecretaria($mid,$periodStart,$scope==='secretaria');
        $tenant=Tenant::current();

        return [
            'months'=>$months,
            'periodStart'=>$periodStart,
            'scope'=>$scope,
            'tenant'=>$tenant,
            'phase'=>$phase,
            'docs'=>$docs,
            'monthly'=>$monthly,
            'secretariasCorrecoes'=>$secretarias,
            'methodology'=>$this->methodology($scope),
        ];
    }

    public function macro(array $query=[]): array
    {
        if(!Auth::isPlatformAdmin()) throw new RuntimeException('Acesso restrito à Stratelli.');
        $months=$this->months($query);
        $periodStart=$this->periodStart($months);
        $municipios=$this->pdo->query('SELECT id,nome,uf,slug,status,ativo FROM municipios ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);
        $clients=[];$allPhaseRows=[];$totValidationSeconds=0;$totValidations=0;$firstPassApproved=0;$firstPassTotal=0;$corrections=0;
        $etapasConcluidas=0;$etapasNoPrazo=0;$closedPhaseCount=0;$closedPhaseDays=0;$closedPhaseDelaySum=0;$closedPhaseOnTime=0;
        foreach($municipios as $m){
            $mid=(int)$m['id'];
            $phase=$this->phasePerformance($mid,$periodStart,null);
            $docs=$this->documentMetricsForMunicipality($mid,$periodStart);
            foreach($phase['periodRows'] as $row){$row['municipio_nome']=$m['nome'];$row['municipio_uf']=$m['uf'];$allPhaseRows[]=$row;}
            $closedPhaseCount+=$phase['closedCount'];$closedPhaseDays+=$phase['closedDaysSum'];$closedPhaseDelaySum+=$phase['delaySum'];$closedPhaseOnTime+=$phase['onTimeCount'];
            $totValidationSeconds+=$docs['validationSecondsSum'];$totValidations+=$docs['validationCount'];$firstPassApproved+=$docs['firstPassApproved'];$firstPassTotal+=$docs['firstPassTotal'];$corrections+=$docs['corrections'];
            $completedAt=$phase['etapa']['completedAt']??null;
            if($completedAt&&$completedAt.' 23:59:59'>=$periodStart){
                if($phase['etapa']['status']==='done_on_time'){$etapasConcluidas++;$etapasNoPrazo++;}
                elseif($phase['etapa']['status']==='done_late'){$etapasConcluidas++;}
            }
            $clients[]=[
                'municipio'=>$m,
                'phase'=>$phase,
                'docs'=>$docs,
            ];
        }
        $phaseRanking=$this->aggregatePhaseRanking($allPhaseRows);
        $secretarias=$this->macroCorrectionsBySecretaria($periodStart);
        $monthly=$this->monthlyEvolutionMacro($months);
        usort($clients,function($a,$b){
            $priority=['done_late'=>0,'in_progress'=>1,'done_on_time'=>2,'not_started'=>3];
            $pa=$priority[$a['phase']['etapa']['status']]??9;$pb=$priority[$b['phase']['etapa']['status']]??9;
            if($pa!==$pb)return $pa<=>$pb;
            return strcasecmp($a['municipio']['nome'],$b['municipio']['nome']);
        });
        return [
            'months'=>$months,
            'periodStart'=>$periodStart,
            'municipios'=>$municipios,
            'clients'=>$clients,
            'phaseRanking'=>$phaseRanking,
            'secretariasCorrecoes'=>$secretarias,
            'monthly'=>$monthly,
            'summary'=>[
                'municipios'=>count($municipios),
                'closedPhaseCount'=>$closedPhaseCount,
                'avgPhaseDays'=>$closedPhaseCount?round($closedPhaseDays/$closedPhaseCount,1):null,
                'avgDelayDays'=>$closedPhaseCount?round($closedPhaseDelaySum/$closedPhaseCount,1):null,
                'phaseOnTimeRate'=>$closedPhaseCount?round($closedPhaseOnTime/$closedPhaseCount*100,1):null,
                'avgValidationSeconds'=>$totValidations?(int)round($totValidationSeconds/$totValidations):null,
                'validationCount'=>$totValidations,
                'firstPassRate'=>$firstPassTotal?round($firstPassApproved/$firstPassTotal*100,1):null,
                'firstPassApproved'=>$firstPassApproved,
                'firstPassTotal'=>$firstPassTotal,
                'corrections'=>$corrections,
                'etapasConcluidas'=>$etapasConcluidas,
                'etapasNoPrazo'=>$etapasNoPrazo,
                'etapasNoPrazoRate'=>$etapasConcluidas?round($etapasNoPrazo/$etapasConcluidas*100,1):null,
            ],
            'methodology'=>$this->methodology('macro'),
        ];
    }

    public function exportTenant(array $query=[]): never
    {
        $data=$this->tenant($query);$tenant=$data['tenant'];
        Audit::log('EXPORTACAO_INDICADORES_HISTORICOS','Exportação CSV de indicadores históricos do município',(int)$tenant['id'],['categoria'=>'ACESSO_DADOS','severidade'=>'INFO']);
        header('Content-Type:text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="indicadores-historicos-'.$tenant['slug'].'-'.date('Ymd-His').'.csv"');
        echo "\xEF\xBB\xBF";$out=fopen('php://output','w');
        fputcsv($out,['INDICADORES EXECUTIVOS HISTÓRICOS',$tenant['nome'].' - '.$tenant['uf'],'Período',$data['months'].' meses'],';');
        fputcsv($out,[],';');
        fputcsv($out,['Indicador','Valor'],';');
        fputcsv($out,['Tempo médio das fases encerradas',$data['phase']['avgDays']!==null?$data['phase']['avgDays'].' dias':'Sem dados'],';');
        fputcsv($out,['Atraso médio das fases encerradas',$data['phase']['avgDelay']!==null?$data['phase']['avgDelay'].' dias':'Sem dados'],';');
        fputcsv($out,['Fases encerradas no prazo',$data['phase']['onTimeRate']!==null?$data['phase']['onTimeRate'].'%':'Sem dados'],';');
        fputcsv($out,['Tempo médio de validação Stratelli',$this->durationLabel($data['docs']['avgValidationSeconds'])],';');
        fputcsv($out,['Aprovação na primeira entrega',$data['docs']['firstPassRate']!==null?$data['docs']['firstPassRate'].'%':'Sem dados'],';');
        fputcsv($out,['Correções solicitadas',$data['docs']['corrections']],';');
        fputcsv($out,[],';');
        fputcsv($out,['Fase','Início operacional','Prazo limite','Encerramento','Duração planejada','Duração real','Atraso','Situação'],';');
        foreach($data['phase']['rows'] as $r){fputcsv($out,[$r['label'],$r['start'],$r['deadline'],$r['closure'],$r['plannedDays'],$r['actualDays'],$r['delay'],$r['statusLabel']],';');}
        fputcsv($out,[],';');fputcsv($out,['Secretaria','Correções','Documentos distintos'],';');
        foreach($data['secretariasCorrecoes'] as $r){fputcsv($out,[$r['label'],$r['correcoes'],$r['documentos']],';');}
        fputcsv($out,[],';');fputcsv($out,['Mês','Envios','Aprovações','Correções','Fases encerradas'],';');
        foreach($data['monthly'] as $r){fputcsv($out,[$r['label'],$r['uploads'],$r['approvals'],$r['corrections'],$r['closures']],';');}
        fclose($out);exit;
    }

    public function exportMacro(array $query=[]): never
    {
        $data=$this->macro($query);
        Audit::log('EXPORTACAO_INDICADORES_HISTORICOS_MACRO','Exportação CSV de indicadores históricos da carteira',null,['categoria'=>'ACESSO_DADOS','severidade'=>'ATENCAO']);
        header('Content-Type:text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="indicadores-historicos-macro-'.date('Ymd-His').'.csv"');echo "\xEF\xBB\xBF";$out=fopen('php://output','w');
        fputcsv($out,['INDICADORES EXECUTIVOS HISTÓRICOS - CARTEIRA STRATELLI','Período',$data['months'].' meses'],';');fputcsv($out,[],';');
        fputcsv($out,['Município','Etapa atual','Fases encerradas','Fases no prazo','Validação média','Aprovação 1ª entrega','Correções'],';');
        foreach($data['clients'] as $c){fputcsv($out,[$c['municipio']['nome'].' - '.$c['municipio']['uf'],$c['phase']['etapa']['label'],$c['phase']['closedCount'],$c['phase']['onTimeRate']!==null?$c['phase']['onTimeRate'].'%':'—',$this->durationLabel($c['docs']['avgValidationSeconds']),$c['docs']['firstPassRate']!==null?$c['docs']['firstPassRate'].'%':'—',$c['docs']['corrections']],';');}
        fputcsv($out,[],';');fputcsv($out,['Fase','Amostra','Tempo médio','Atraso médio','Taxa no prazo'],';');
        foreach($data['phaseRanking'] as $r){fputcsv($out,[$r['label'],$r['count'],$r['avgDays'].' dias',$r['avgDelay'].' dias',$r['onTimeRate'].'%'],';');}
        fclose($out);exit;
    }

    public function durationLabel(?int $seconds): string
    {
        if($seconds===null)return 'Sem dados';
        if($seconds<60)return $seconds.' s';
        $minutes=(int)round($seconds/60);
        if($minutes<60)return $minutes.' min';
        $hours=intdiv($minutes,60);$mins=$minutes%60;
        if($hours<24)return $hours.'h'.($mins?' '.$mins.'min':'');
        $days=intdiv($hours,24);$rest=$hours%24;
        return $days.'d'.($rest?' '.$rest.'h':'');
    }

    private function phasePerformance(int $mid,string $periodStart,?array $visiblePhaseIds): array
    {
        $stageName=(new InstanceParameterService())->stageName($mid);
        $st=$this->pdo->prepare('SELECT data_inicio FROM cronograma_processos WHERE municipio_id=?');$st->execute([$mid]);$processStart=$st->fetchColumn()?:null;
        $st=$this->pdo->prepare('SELECT id,ordem,aba,titulo,dia_inicio,dia_fim FROM fases WHERE municipio_id=? AND ativo=1 ORDER BY ordem,id');$st->execute([$mid]);$phases=$st->fetchAll(PDO::FETCH_ASSOC);
        $st=$this->pdo->prepare('SELECT * FROM cronograma_fases WHERE municipio_id=?');$st->execute([$mid]);$closures=[];foreach($st->fetchAll(PDO::FETCH_ASSOC) as $c)$closures[(int)$c['fase_id']]=$c;
        $rows=[];$prevClosure=null;$allClosed=!empty($phases);$lastRow=null;
        foreach($phases as $idx=>$f){
            $start=$idx===0?$processStart:($prevClosure?$this->plusDays($prevClosure,1):null);
            $planned=max(1,(int)$f['dia_fim']-(int)$f['dia_inicio']+1);
            $deadline=$start?$this->plusDays($start,$planned-1):null;
            $c=$closures[(int)$f['id']]??null;$closed=$c&&($c['status']??'')==='ENCERRADA';$closure=$closed?$c['data_conclusao_real']:null;
            $actual=($start&&$closure)?$this->daysInclusive($start,$closure):null;
            $delay=($deadline&&$closure)?$this->daysDifference($deadline,$closure):null;
            if(!$closed)$allClosed=false;if($closed)$prevClosure=$closure;else $prevClosure=null;
            $row=['fase_id'=>(int)$f['id'],'ordem'=>(int)$f['ordem'],'aba'=>$f['aba'],'titulo'=>$f['titulo'],'label'=>'Fase '.$f['ordem'].' — '.$f['aba'],'start'=>$start,'deadline'=>$deadline,'closure'=>$closure,'plannedDays'=>$planned,'actualDays'=>$actual,'delay'=>$delay,'closed'=>$closed,'onTime'=>$closed&&$delay!==null&&$delay<=0,'statusLabel'=>$closed?($delay!==null&&$delay<=0?'ENCERRADA NO PRAZO':'ENCERRADA COM ATRASO'):'NÃO ENCERRADA'];
            $rows[]=$row;$lastRow=$row;
        }
        $displayRows=$visiblePhaseIds===null?$rows:array_values(array_filter($rows,fn($r)=>in_array($r['fase_id'],$visiblePhaseIds,true)));
        $periodRows=array_values(array_filter($displayRows,fn($r)=>$r['closed']&&$r['closure']&&$r['closure'].' 23:59:59'>=$periodStart));
        $closedCount=count($periodRows);$closedDaysSum=0;$delaySum=0;$onTime=0;foreach($periodRows as $r){$closedDaysSum+=(int)($r['actualDays']??0);$delaySum+=max(0,(int)($r['delay']??0));if($r['onTime'])$onTime++;}
        $delayed=array_values(array_filter($periodRows,fn($r)=>(int)($r['delay']??0)>0));usort($delayed,fn($a,$b)=>($b['delay']<=>$a['delay'])?:($b['ordem']<=>$a['ordem']));
        $etapa=['status'=>'not_started','label'=>'Não iniciada','delay'=>null,'completedAt'=>null];
        if($processStart){$etapa=['status'=>'in_progress','label'=>'Em andamento','delay'=>null,'completedAt'=>null];if($allClosed&&$lastRow&&$lastRow['closure']){$etapa=$lastRow['delay']!==null&&$lastRow['delay']<=0?['status'=>'done_on_time','label'=>$stageName.' concluída no prazo','delay'=>$lastRow['delay'],'completedAt'=>$lastRow['closure']]:['status'=>'done_late','label'=>$stageName.' concluída com atraso','delay'=>$lastRow['delay'],'completedAt'=>$lastRow['closure']];}}
        return [
            'processStart'=>$processStart,'rows'=>$displayRows,'allRows'=>$rows,'periodRows'=>$periodRows,'closedCount'=>$closedCount,'closedDaysSum'=>$closedDaysSum,'delaySum'=>$delaySum,'onTimeCount'=>$onTime,
            'stageName'=>$stageName,'avgDays'=>$closedCount?round($closedDaysSum/$closedCount,1):null,'avgDelay'=>$closedCount?round($delaySum/$closedCount,1):null,'onTimeRate'=>$closedCount?round($onTime/$closedCount*100,1):null,'mostDelayed'=>$delayed[0]??null,'delayedRanking'=>$delayed,'etapa'=>$etapa,
        ];
    }

    private function documentMetrics(int $mid,string $periodStart,bool $common): array
    {
        [$scopeSql,$scopeParams]=$this->documentScope($common,'r');
        return $this->documentMetricsQuery($mid,$periodStart,$scopeSql,$scopeParams);
    }

    private function documentMetricsForMunicipality(int $mid,string $periodStart): array
    {
        return $this->documentMetricsQuery($mid,$periodStart,'',[]);
    }

    private function documentMetricsQuery(int $mid,string $periodStart,string $scopeSql,array $scopeParams): array
    {
        $params=array_merge([$mid,$periodStart],$scopeParams);
        $sql='SELECT COUNT(*) qtd,COALESCE(SUM(TIMESTAMPDIFF(SECOND,d.enviado_em,d.validado_em)),0) segundos FROM documentos_enviados d JOIN requisitos_documentais r ON r.id=d.requisito_id AND r.municipio_id=d.municipio_id WHERE d.municipio_id=? AND d.validado_em IS NOT NULL AND d.validado_em>=? AND r.perfil_envio="MUNICIPIO"'.$scopeSql;
        $st=$this->pdo->prepare($sql);$st->execute($params);$v=$st->fetch(PDO::FETCH_ASSOC)?:[];$validationCount=(int)($v['qtd']??0);$validationSecondsSum=(int)($v['segundos']??0);
        $params=array_merge([$mid,$periodStart],$scopeParams);
        $sql='SELECT COUNT(*) total,COALESCE(SUM(d.status="APROVADO"),0) aprovados FROM documentos_enviados d JOIN requisitos_documentais r ON r.id=d.requisito_id AND r.municipio_id=d.municipio_id WHERE d.municipio_id=? AND d.versao=1 AND d.validado_em IS NOT NULL AND d.validado_em>=? AND r.perfil_envio="MUNICIPIO"'.$scopeSql;
        $st=$this->pdo->prepare($sql);$st->execute($params);$f=$st->fetch(PDO::FETCH_ASSOC)?:[];$firstPassTotal=(int)($f['total']??0);$firstPassApproved=(int)($f['aprovados']??0);
        $params=array_merge([$mid,$periodStart],$scopeParams);
        $sql='SELECT COUNT(*) FROM historico_documentos h JOIN requisitos_documentais r ON r.id=h.requisito_id AND r.municipio_id=h.municipio_id WHERE h.municipio_id=? AND h.criado_em>=? AND h.evento="Correção solicitada" AND r.perfil_envio="MUNICIPIO"'.$scopeSql;
        $st=$this->pdo->prepare($sql);$st->execute($params);$corrections=(int)$st->fetchColumn();
        return [
            'validationCount'=>$validationCount,'validationSecondsSum'=>$validationSecondsSum,'avgValidationSeconds'=>$validationCount?(int)round($validationSecondsSum/$validationCount):null,
            'firstPassTotal'=>$firstPassTotal,'firstPassApproved'=>$firstPassApproved,'firstPassRate'=>$firstPassTotal?round($firstPassApproved/$firstPassTotal*100,1):null,'corrections'=>$corrections,
        ];
    }

    private function correctionsBySecretaria(int $mid,string $periodStart,bool $common): array
    {
        [$scopeSql,$scopeParams]=$this->documentScope($common,'r');
        $params=array_merge([$mid,$periodStart],$scopeParams);
        $sql='SELECT s.id,s.sigla,s.nome,COUNT(*) correcoes,COUNT(DISTINCT h.requisito_id) documentos FROM historico_documentos h JOIN requisitos_documentais r ON r.id=h.requisito_id AND r.municipio_id=h.municipio_id JOIN secretarias s ON s.id=r.secretaria_id AND s.municipio_id=r.municipio_id WHERE h.municipio_id=? AND h.criado_em>=? AND h.evento="Correção solicitada" AND r.perfil_envio="MUNICIPIO"'.$scopeSql.' GROUP BY s.id,s.sigla,s.nome ORDER BY correcoes DESC,s.nome LIMIT 12';
        $st=$this->pdo->prepare($sql);$st->execute($params);$rows=$st->fetchAll(PDO::FETCH_ASSOC);foreach($rows as &$r){$r['correcoes']=(int)$r['correcoes'];$r['documentos']=(int)$r['documentos'];$r['label']=trim(($r['sigla']?$r['sigla'].' — ':'').$r['nome']);}unset($r);return$rows;
    }

    private function macroCorrectionsBySecretaria(string $periodStart): array
    {
        $st=$this->pdo->prepare('SELECT m.nome municipio_nome,m.uf,s.sigla,s.nome,COUNT(*) correcoes,COUNT(DISTINCT h.requisito_id) documentos FROM historico_documentos h JOIN requisitos_documentais r ON r.id=h.requisito_id AND r.municipio_id=h.municipio_id JOIN secretarias s ON s.id=r.secretaria_id AND s.municipio_id=r.municipio_id JOIN municipios m ON m.id=h.municipio_id WHERE h.criado_em>=? AND h.evento="Correção solicitada" AND r.perfil_envio="MUNICIPIO" GROUP BY m.id,m.nome,m.uf,s.id,s.sigla,s.nome ORDER BY correcoes DESC,m.nome,s.nome LIMIT 15');$st->execute([$periodStart]);$rows=$st->fetchAll(PDO::FETCH_ASSOC);foreach($rows as &$r){$r['correcoes']=(int)$r['correcoes'];$r['documentos']=(int)$r['documentos'];$r['label']=$r['municipio_nome'].' · '.trim(($r['sigla']?$r['sigla'].' — ':'').$r['nome']);}unset($r);return$rows;
    }

    private function monthlyEvolution(int $mid,int $months,bool $common): array
    {
        $monthsMap=$this->monthMap($months);[$scopeSql,$scopeParams]=$this->documentScope($common,'r');
        $start=array_key_first($monthsMap).'-01 00:00:00';
        $params=array_merge([$mid,$start],$scopeParams);
        $sql='SELECT DATE_FORMAT(d.enviado_em,"%Y-%m") ym,COUNT(*) qtd FROM documentos_enviados d JOIN requisitos_documentais r ON r.id=d.requisito_id AND r.municipio_id=d.municipio_id WHERE d.municipio_id=? AND d.enviado_em>=? AND r.perfil_envio="MUNICIPIO"'.$scopeSql.' GROUP BY ym';$st=$this->pdo->prepare($sql);$st->execute($params);foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){if(isset($monthsMap[$r['ym']]))$monthsMap[$r['ym']]['uploads']=(int)$r['qtd'];}
        $params=array_merge([$mid,$start],$scopeParams);$sql='SELECT DATE_FORMAT(d.validado_em,"%Y-%m") ym,SUM(d.status="APROVADO") approvals,SUM(d.status="CORRECAO") corrections FROM documentos_enviados d JOIN requisitos_documentais r ON r.id=d.requisito_id AND r.municipio_id=d.municipio_id WHERE d.municipio_id=? AND d.validado_em IS NOT NULL AND d.validado_em>=? AND r.perfil_envio="MUNICIPIO"'.$scopeSql.' GROUP BY ym';$st=$this->pdo->prepare($sql);$st->execute($params);foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){if(isset($monthsMap[$r['ym']])){$monthsMap[$r['ym']]['approvals']=(int)$r['approvals'];$monthsMap[$r['ym']]['corrections']=(int)$r['corrections'];}}
        if(!$common){$st=$this->pdo->prepare('SELECT DATE_FORMAT(criado_em,"%Y-%m") ym,COUNT(*) qtd FROM historico_fases WHERE municipio_id=? AND evento="ENCERRAMENTO" AND criado_em>=? GROUP BY ym');$st->execute([$mid,$start]);foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){if(isset($monthsMap[$r['ym']]))$monthsMap[$r['ym']]['closures']=(int)$r['qtd'];}}
        return array_values($monthsMap);
    }

    private function monthlyEvolutionMacro(int $months): array
    {
        $monthsMap=$this->monthMap($months);$start=array_key_first($monthsMap).'-01 00:00:00';
        $st=$this->pdo->prepare('SELECT DATE_FORMAT(d.enviado_em,"%Y-%m") ym,COUNT(*) qtd FROM documentos_enviados d JOIN requisitos_documentais r ON r.id=d.requisito_id AND r.municipio_id=d.municipio_id WHERE d.enviado_em>=? AND r.perfil_envio="MUNICIPIO" GROUP BY ym');$st->execute([$start]);foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){if(isset($monthsMap[$r['ym']]))$monthsMap[$r['ym']]['uploads']=(int)$r['qtd'];}
        $st=$this->pdo->prepare('SELECT DATE_FORMAT(d.validado_em,"%Y-%m") ym,SUM(d.status="APROVADO") approvals,SUM(d.status="CORRECAO") corrections FROM documentos_enviados d JOIN requisitos_documentais r ON r.id=d.requisito_id AND r.municipio_id=d.municipio_id WHERE d.validado_em IS NOT NULL AND d.validado_em>=? AND r.perfil_envio="MUNICIPIO" GROUP BY ym');$st->execute([$start]);foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){if(isset($monthsMap[$r['ym']])){$monthsMap[$r['ym']]['approvals']=(int)$r['approvals'];$monthsMap[$r['ym']]['corrections']=(int)$r['corrections'];}}
        $st=$this->pdo->prepare('SELECT DATE_FORMAT(criado_em,"%Y-%m") ym,COUNT(*) qtd FROM historico_fases WHERE evento="ENCERRAMENTO" AND criado_em>=? GROUP BY ym');$st->execute([$start]);foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){if(isset($monthsMap[$r['ym']]))$monthsMap[$r['ym']]['closures']=(int)$r['qtd'];}
        return array_values($monthsMap);
    }

    private function aggregatePhaseRanking(array $rows): array
    {
        $groups=[];foreach($rows as$r){$key=$r['ordem'].'|'.$r['aba'];if(!isset($groups[$key]))$groups[$key]=['ordem'=>$r['ordem'],'aba'=>$r['aba'],'label'=>'Fase '.$r['ordem'].' — '.$r['aba'],'count'=>0,'days'=>0,'delay'=>0,'onTime'=>0,'late'=>0];$g=&$groups[$key];$g['count']++;$g['days']+=(int)($r['actualDays']??0);$g['delay']+=max(0,(int)($r['delay']??0));if($r['onTime'])$g['onTime']++;if((int)($r['delay']??0)>0)$g['late']++;unset($g);}foreach($groups as &$g){$g['avgDays']=round($g['days']/$g['count'],1);$g['avgDelay']=round($g['delay']/$g['count'],1);$g['onTimeRate']=round($g['onTime']/$g['count']*100,1);}unset($g);$out=array_values($groups);usort($out,fn($a,$b)=>($b['avgDelay']<=>$a['avgDelay'])?:($b['late']<=>$a['late'])?:($a['ordem']<=>$b['ordem']));return$out;
    }

    private function documentScope(bool $common,string $alias): array
    {
        if(!$common)return['',[]];$sid=(int)($this->user['secretaria_id']??0);$did=(int)($this->user['departamento_id']??0);if(!$sid)return[' AND 1=0',[]];$sql=' AND '.$alias.'.secretaria_id=?';$params=[$sid];if($did){$sql.=' AND ('.$alias.'.departamento_id IS NULL OR '.$alias.'.departamento_id=?)';$params[]=$did;}return[$sql,$params];
    }

    private function visiblePhaseIdsForCommonUser(int $mid): array
    {
        $sid=(int)($this->user['secretaria_id']??0);$did=(int)($this->user['departamento_id']??0);if(!$sid)return[];$sql='SELECT DISTINCT fase_id FROM requisitos_documentais WHERE municipio_id=? AND ativo=1 AND perfil_envio="MUNICIPIO" AND secretaria_id=?';$params=[$mid,$sid];if($did){$sql.=' AND (departamento_id IS NULL OR departamento_id=?)';$params[]=$did;}$st=$this->pdo->prepare($sql);$st->execute($params);return array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN));
    }

    private function monthMap(int $months): array
    {
        $map=[];$base=new DateTimeImmutable('first day of this month');for($i=$months-1;$i>=0;$i--){$d=$base->modify('-'.$i.' months');$key=$d->format('Y-m');$map[$key]=['key'=>$key,'label'=>$this->monthLabel($d),'uploads'=>0,'approvals'=>0,'corrections'=>0,'closures'=>0];}return$map;
    }

    private function monthLabel(DateTimeImmutable $d): string
    {
        $names=[1=>'Jan',2=>'Fev',3=>'Mar',4=>'Abr',5=>'Mai',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Set',10=>'Out',11=>'Nov',12=>'Dez'];return $names[(int)$d->format('n')].'/'.$d->format('y');
    }

    private function months(array $q): int
    {
        $m=(int)($q['periodo']??12);return in_array($m,[6,12,24],true)?$m:12;
    }

    private function periodStart(int $months): string
    {
        return (new DateTimeImmutable('first day of this month 00:00:00'))->modify('-'.($months-1).' months')->format('Y-m-d H:i:s');
    }

    private function plusDays(string $date,int $days): string{return (new DateTimeImmutable($date))->modify(($days>=0?'+':'').$days.' days')->format('Y-m-d');}
    private function daysInclusive(string $start,string $end): int{return max(1,(int)(new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->format('%r%a')+1);}
    private function daysDifference(string $from,string $to): int{return (int)(new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->format('%r%a');}

    private function methodology(string $scope): array
    {
        return [
            'A leitura histórica considera a etapa atual configurada em cada instância; ainda não representa múltiplos processos simultâneos.',
            'Tempo de fase é calculado entre o início operacional recalculado e o encerramento formal atualmente vigente.',
            'Atraso compara o encerramento formal com a data limite operacional recalculada da fase.',
            'Tempo de validação mede o intervalo entre o envio de cada versão municipal e a decisão da Stratelli.',
            'Aprovação na primeira entrega considera somente primeiras versões que já receberam decisão de aprovação ou correção.',
            $scope==='secretaria'?'Os indicadores documentais estão restritos à secretaria/departamento do usuário autenticado.':'Correções e validações usam somente documentos de responsabilidade municipal, evitando distorção por documentos internos da Stratelli.',
        ];
    }
}
