<?php
namespace App\Services;

use App\Core\Database;
use App\Core\Format;
use PDO;

final class MacroDashboardService
{
    private PDO $pdo;
    private string $today;

    public function __construct()
    {
        $this->pdo = Database::connection();
        $this->today = date('Y-m-d');
    }

    public function load(): array
    {
        $municipios = $this->pdo->query('SELECT m.*,
            (SELECT COUNT(*) FROM usuarios u WHERE u.municipio_id=m.id AND u.ativo=1) usuarios_ativos,
            (SELECT COUNT(*) FROM usuarios u WHERE u.municipio_id=m.id AND u.grupo="GESTOR" AND u.ativo=1) gestores_ativos,
            (SELECT COUNT(*) FROM secretarias s WHERE s.municipio_id=m.id AND s.ativo=1) secretarias_ativas,
            (SELECT COUNT(*) FROM fases f WHERE f.municipio_id=m.id AND f.ativo=1) fases_ativas,
            (SELECT COUNT(*) FROM camadas_territoriais ct WHERE ct.municipio_id=m.id AND ct.ativo=1) camadas_territoriais_ativas,
            (SELECT COUNT(*) FROM objetos_territoriais ot WHERE ot.municipio_id=m.id AND ot.ativo=1) objetos_territoriais_ativos,
            (SELECT COUNT(DISTINCT vt.fase_id) FROM vinculos_territoriais vt WHERE vt.municipio_id=m.id AND vt.fase_id IS NOT NULL) fases_territorializadas
            FROM municipios m ORDER BY m.nome')->fetchAll();

        $clientes=[];
        foreach($municipios as $m){
            $clientes[]=$this->buildClient($m);
        }

        $summary=[
            'clientes'=>count($clientes),
            'ativos'=>count(array_filter($clientes,fn($c)=>(int)$c['ativo']===1)),
            'em_andamento'=>count(array_filter($clientes,fn($c)=>$c['categoria']==='andamento')),
            'no_prazo'=>count(array_filter($clientes,fn($c)=>$c['saude']==='normal')),
            'atencao'=>count(array_filter($clientes,fn($c)=>in_array($c['saude'],['attention','critical'],true))),
            'implantacao'=>count(array_filter($clientes,fn($c)=>$c['saude']==='implementation')),
            'aguardando_validacao'=>array_sum(array_column($clientes,'aguardando')),
            'correcoes'=>array_sum(array_column($clientes,'correcoes')),
            'secretarias_pendentes'=>array_sum(array_column($clientes,'secretarias_pendentes')),
            'fases_concluidas'=>array_sum(array_column($clientes,'fases_concluidas')),
            'fases_total'=>array_sum(array_column($clientes,'fases_total')),
            'camadas_territoriais'=>array_sum(array_column($clientes,'camadas_territoriais_ativas')),
            'objetos_territoriais'=>array_sum(array_column($clientes,'objetos_territoriais_ativos')),
            'fases_territorializadas'=>array_sum(array_column($clientes,'fases_territorializadas')),
        ];

        $attention=array_values(array_filter($clientes,fn($c)=>in_array($c['saude'],['attention','critical'],true)));
        usort($attention,fn($a,$b)=>($a['saude']==='critical'?0:1)<=>($b['saude']==='critical'?0:1));

        $recent=$this->pdo->query('SELECT h.id,h.evento,h.motivo,h.status,h.criado_em,m.nome municipio_nome,m.uf,m.slug,u.nome usuario_nome
            FROM historico_documentos h
            JOIN municipios m ON m.id=h.municipio_id
            LEFT JOIN usuarios u ON u.id=h.usuario_id
            ORDER BY h.criado_em DESC,h.id DESC LIMIT 12')->fetchAll();

        return compact('clientes','summary','attention','recent');
    }

    private function buildClient(array $m): array
    {
        $mid=(int)$m['id'];
        $phases=$this->all('SELECT * FROM fases WHERE municipio_id=? AND ativo=1 ORDER BY ordem,id',[$mid]);
        $requirements=$this->all('SELECT r.*,s.nome secretaria_nome,s.sigla secretaria_sigla
            FROM requisitos_documentais r
            JOIN secretarias s ON s.id=r.secretaria_id AND s.municipio_id=r.municipio_id
            WHERE r.municipio_id=? AND r.ativo=1 ORDER BY r.fase_id,r.ordem,r.id',[$mid]);
        $latest=[];
        foreach($this->all('SELECT d.* FROM documentos_enviados d
            JOIN (SELECT requisito_id,MAX(id) max_id FROM documentos_enviados WHERE municipio_id=? GROUP BY requisito_id) x ON x.max_id=d.id
            WHERE d.municipio_id=?',[$mid,$mid]) as $d){$latest[(int)$d['requisito_id']]=$d;}

        $total=count($requirements);$approved=$waiting=$corrections=$sent=0;$pendingSecretaries=[];
        foreach($requirements as $r){
            $d=$latest[(int)$r['id']]??null;
            if($d){
                $sent++;
                if($d['status']==='APROVADO')$approved++;
                elseif($d['status']==='CORRECAO'){$corrections++;$pendingSecretaries[(int)$r['secretaria_id']]=true;}
                else $waiting++;
            }else{$pendingSecretaries[(int)$r['secretaria_id']]=true;}
        }
        $pending=max(0,$total-$sent);
        $progress=$total?round(($approved/$total)*100):0;

        [$schedule,$completed,$current]=$this->buildSchedule($mid,$phases,$requirements,$latest);
        $currentDeadline=$current?$schedule[(int)$current['id']]??null:null;
        $faseProntaEncerramento=false;if($current){$mandatory=array_values(array_filter($requirements,fn($r)=>(int)$r['fase_id']===(int)$current['id']&&(int)$r['obrigatorio']===1));$faseProntaEncerramento=count($mandatory)===0; if($mandatory){$faseProntaEncerramento=true;foreach($mandatory as $r){$d=$latest[(int)$r['id']]??null;if(!$d||$d['status']!=='APROVADO'){$faseProntaEncerramento=false;break;}}}}

        $last=$this->one('SELECT criado_em,evento FROM historico_documentos WHERE municipio_id=? ORDER BY criado_em DESC,id DESC LIMIT 1',[$mid]);
        if(!$last)$last=$this->one('SELECT criado_em,"Cadastro da instância" evento FROM municipios WHERE id=?',[$mid]);

        $statusDb=(string)($m['status']??'IMPLANTACAO');
        $health='normal';$healthLabel='Normal';$category='andamento';
        if($statusDb==='NEGOCIACAO'){$health='implementation';$healthLabel='Negociação';$category='implantacao';}
        elseif($statusDb==='APRESENTACAO'){$health='implementation';$healthLabel='Apresentação';$category='implantacao';}
        elseif($statusDb==='IMPLANTACAO'||!$phases){$health='implementation';$healthLabel='Implantação';$category='implantacao';}
        elseif($statusDb==='DESATIVADO'){$health='critical';$healthLabel='Desativado';$category='atencao';}
        elseif($completed===count($phases)&&count($phases)>0){$health='completed';$healthLabel='Concluído';$category='concluido';}
        elseif(($currentDeadline['status']??'')==='overdue'){$health='critical';$healthLabel='Crítico';}
        elseif(in_array(($currentDeadline['status']??''),['attention'],true)||$corrections>0){$health='attention';$healthLabel='Atenção';}

        if($statusDb==='SUSPENSO'){$health='critical';$healthLabel='Suspenso';$category='atencao';}
        elseif(in_array($health,['attention','critical'],true))$category='atencao';

        if($faseProntaEncerramento&&$current)$nextAction='Encerrar formalmente a Fase '.(int)$current['ordem'];
        elseif($waiting>0)$nextAction='Validar '.$waiting.' documento'.($waiting===1?' recebido':'s recebidos');
        elseif($corrections>0)$nextAction='Acompanhar '.$corrections.' correção'.($corrections===1?' pendente':'ões pendentes');
        elseif($pending>0)$nextAction='Acompanhar '.$pending.' documento'.($pending===1?' pendente':'s pendentes');
        elseif($health==='implementation')$nextAction='Concluir configuração inicial do cliente';
        else $nextAction='Nenhuma ação imediata';

        $deadlineText=$currentDeadline['texto']??($health==='implementation'?'Processo ainda não iniciado':'—');
        $deadlineLabel=$currentDeadline['rotulo']??($health==='implementation'?'AGUARDANDO IMPLANTAÇÃO':'—');

        return $m+[
            'documentos_total'=>$total,
            'aprovados'=>$approved,
            'aguardando'=>$waiting,
            'correcoes'=>$corrections,
            'pendentes'=>$pending,
            'progresso'=>$progress,
            'secretarias_pendentes'=>count($pendingSecretaries),
            'fases_total'=>count($phases),
            'fases_concluidas'=>$completed,
            'fase_atual'=>$current,
            'fase_pronta_encerramento'=>$faseProntaEncerramento,
            'prazo_atual'=>$currentDeadline,
            'prazo_rotulo'=>$deadlineLabel,
            'prazo_texto'=>$deadlineText,
            'saude'=>$health,
            'saude_rotulo'=>$healthLabel,
            'categoria'=>$category,
            'proxima_acao'=>$nextAction,
            'ultima_movimentacao'=>$last['criado_em']??null,
            'ultima_movimentacao_evento'=>$last['evento']??'—',
        ];
    }

    private function buildSchedule(int $mid,array $phases,array $requirements,array $latest): array
    {
        if(!$phases)return [[],0,null];
        $crono=$this->one('SELECT * FROM cronograma_processos WHERE municipio_id=?',[$mid]);
        $start=(string)($crono['data_inicio']??date('Y-m-d'));
        $manual=[];foreach($this->all('SELECT * FROM cronograma_fases WHERE municipio_id=?',[$mid]) as $r)$manual[(int)$r['fase_id']]=$r;
        $schedule=[];$prevDeadline=null;$prevConclusion=null;$completed=0;$current=null;
        foreach($phases as $i=>$f){
            $duration=max(1,(int)$f['dia_fim']-(int)$f['dia_inicio']+1);
            $m=$manual[(int)$f['id']]??null;
            $closed=$m&&strtoupper((string)($m['status']??'ENCERRADA'))==='ENCERRADA';$real=$closed?($m['data_conclusao_real']??null):null;
            if($i===0){$phaseStart=$start;$blocked=false;}
            elseif($prevConclusion){$phaseStart=$this->addDays($prevConclusion,1);$blocked=false;}
            else{$phaseStart=$this->addDays((string)$prevDeadline,1);$blocked=true;}
            $end=$this->addDays($phaseStart,$duration-1);
            $remaining=$this->days($this->today,$end);$deviation=$real?$this->days($end,$real):0;$attention=(new InstanceParameterService())->deadlineAlertDays($mid);
            if($real){$status=$deviation>0?'completed-late':'completed-on-time';$completed++;}
            elseif($blocked)$status='blocked';
            elseif($this->today<$phaseStart)$status='scheduled';
            elseif($remaining<0)$status='overdue';
            elseif($remaining<=$attention)$status='attention';
            else$status='on-track';
            $label=match($status){'completed-on-time'=>'ENCERRADA NO PRAZO','completed-late'=>'ENCERRADA COM ATRASO','overdue'=>'ATRASADA','attention'=>'ATENÇÃO AO PRAZO','on-track'=>'NO PRAZO','scheduled'=>'AGENDADA',default=>'AGUARDANDO FASE ANTERIOR'};
            $text=match($status){'completed-on-time'=>$deviation<0?abs($deviation).' dia(s) antes do limite':'Encerrada na data limite','completed-late'=>$deviation.' dia(s) de atraso','overdue'=>abs($remaining).' dia(s) de atraso','attention'=>$remaining===0?'Vence hoje':'Restam '.$remaining.' dia(s)','on-track'=>'Restam '.$remaining.' dia(s)','scheduled'=>'Início previsto em '.Format::date($phaseStart),default=>'Aguardando o encerramento formal da fase anterior'};
            $schedule[(int)$f['id']]=['inicio'=>$phaseStart,'fim'=>$end,'conclusao_real'=>$real,'status'=>$status,'rotulo'=>$label,'texto'=>$text];
            if(!$real&&$current===null)$current=$f;
            $prevDeadline=$end;$prevConclusion=$real;
        }
        return [$schedule,$completed,$current];
    }

    private function addDays(string $date,int $days): string{return (new \DateTimeImmutable($date))->modify(($days>=0?'+':'').$days.' days')->format('Y-m-d');}
    private function days(string $a,string $b): int{return (int)(new \DateTimeImmutable($a))->diff(new \DateTimeImmutable($b))->format('%r%a');}
    private function all(string $sql,array $params=[]): array{$st=$this->pdo->prepare($sql);$st->execute($params);return $st->fetchAll();}
    private function one(string $sql,array $params=[]): ?array{$st=$this->pdo->prepare($sql);$st->execute($params);$r=$st->fetch();return$r?:null;}
}
