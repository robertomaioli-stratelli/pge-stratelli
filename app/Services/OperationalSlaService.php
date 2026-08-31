<?php
namespace App\Services;

use App\Core\Database;
use App\Core\Tenant;
use DateTimeImmutable;
use PDO;

final class OperationalSlaService
{
    private PDO $pdo;
    private int $mid;

    public function __construct()
    {
        $this->pdo=Database::connection();
        $this->mid=(int)Tenant::id();
    }

    public function fromWorkflowState(array $state): array
    {
        $requirements=array_values(array_filter($state['requisitosVisiveis']??[],fn($r)=>(int)($r['ativo']??0)===1&&($r['perfil_envio']??'')==='MUNICIPIO'));
        $mandatory=array_values(array_filter($requirements,fn($r)=>(int)($r['obrigatorio']??0)===1));
        $schedule=$state['cronogramaPorFase']??[];
        $today=date('Y-m-d');
        $currentPhaseId=(int)($state['faseSituacional']['id']??0);

        $firstVersions=$this->firstVersions();
        $corrections=$this->correctionsByRequirement();

        $secretarias=[];
        foreach($requirements as $r){
            $sid=(int)$r['secretaria_id'];
            if(!isset($secretarias[$sid])){
                $secretarias[$sid]=[
                    'id'=>$sid,
                    'sigla'=>(string)($r['secretaria_sigla']??''),
                    'nome'=>(string)($r['secretaria_nome']??''),
                    'total_obrigacoes'=>0,
                    'avaliadas'=>0,
                    'no_prazo'=>0,
                    'fora_prazo'=>0,
                    'pendentes_nao_vencidas'=>0,
                    'pendentes_vencidas'=>0,
                    'tempo_envio_segundos'=>0,
                    'tempo_envio_amostras'=>0,
                    'correcoes'=>0,
                    'primeira_versao_decidida'=>0,
                    'primeira_versao_aprovada'=>0,
                    'fase_atual_total'=>0,
                    'fase_atual_enviadas'=>0,
                    'fase_atual_pendentes'=>0,
                ];
            }
            $first=$firstVersions[(int)$r['id']]??null;
            $secretarias[$sid]['correcoes']+=(int)($corrections[(int)$r['id']]??0);
            if($first&&in_array((string)$first['status'],['APROVADO','CORRECAO'],true)){
                $secretarias[$sid]['primeira_versao_decidida']++;
                if($first['status']==='APROVADO')$secretarias[$sid]['primeira_versao_aprovada']++;
            }
            if((int)$r['fase_id']===$currentPhaseId){
                $secretarias[$sid]['fase_atual_total']++;
                if($first)$secretarias[$sid]['fase_atual_enviadas']++;else$secretarias[$sid]['fase_atual_pendentes']++;
            }
        }

        foreach($mandatory as $r){
            $sid=(int)$r['secretaria_id'];
            $secretarias[$sid]['total_obrigacoes']++;
            $deadline=$schedule[(int)$r['fase_id']]??null;
            $first=$firstVersions[(int)$r['id']]??null;
            if(!$deadline)continue;

            $fixed=!($deadline['provisorio']??false);
            if(!$fixed)continue;

            $phaseStart=(string)($deadline['inicio']??'');
            $phaseEnd=(string)($deadline['fim']??'');
            if($phaseEnd==='')continue;

            if($first){
                $firstDate=substr((string)$first['enviado_em'],0,10);
                $secretarias[$sid]['avaliadas']++;
                if($firstDate<=$phaseEnd)$secretarias[$sid]['no_prazo']++;else$secretarias[$sid]['fora_prazo']++;
                if($phaseStart!==''){
                    $seconds=max(0,strtotime((string)$first['enviado_em'])-strtotime($phaseStart.' 00:00:00'));
                    $secretarias[$sid]['tempo_envio_segundos']+=$seconds;
                    $secretarias[$sid]['tempo_envio_amostras']++;
                }
            }elseif($phaseEnd<$today){
                $secretarias[$sid]['avaliadas']++;
                $secretarias[$sid]['fora_prazo']++;
                $secretarias[$sid]['pendentes_vencidas']++;
            }else{
                $secretarias[$sid]['pendentes_nao_vencidas']++;
            }
        }

        $rows=array_values($secretarias);
        foreach($rows as &$s){
            $s['sla_percentual']=$s['avaliadas']?round(($s['no_prazo']/$s['avaliadas'])*100,1):null;
            $s['tempo_medio_envio_segundos']=$s['tempo_envio_amostras']?(int)round($s['tempo_envio_segundos']/$s['tempo_envio_amostras']):null;
            $s['tempo_medio_envio']=$this->formatDuration($s['tempo_medio_envio_segundos']);
            $s['aprovacao_primeira_percentual']=$s['primeira_versao_decidida']?round(($s['primeira_versao_aprovada']/$s['primeira_versao_decidida'])*100,1):null;
            $s['sla_class']=$this->slaClass($s['sla_percentual']);
            $s['sla_label']=$this->slaLabel($s['sla_percentual']);
            $s['display_name']=trim(($s['sigla']!==''?$s['sigla'].' — ':'').$s['nome']);
        }
        unset($s);
        usort($rows,function($a,$b){
            $av=$a['sla_percentual'];$bv=$b['sla_percentual'];
            if($av===null&&$bv!==null)return 1;if($av!==null&&$bv===null)return -1;
            if($av!==$bv)return ($bv<=>$av);
            return strcmp($a['nome'],$b['nome']);
        });

        $evaluated=array_sum(array_column($rows,'avaliadas'));
        $onTime=array_sum(array_column($rows,'no_prazo'));
        $overall=$evaluated?round(($onTime/$evaluated)*100,1):null;
        $correctionsTotal=array_sum(array_column($rows,'correcoes'));
        $firstPassDecided=array_sum(array_column($rows,'primeira_versao_decidida'));
        $firstPassApproved=array_sum(array_column($rows,'primeira_versao_aprovada'));
        $firstPass=$firstPassDecided?round(($firstPassApproved/$firstPassDecided)*100,1):null;
        $sendSeconds=array_sum(array_column($rows,'tempo_envio_segundos'));
        $sendSamples=array_sum(array_column($rows,'tempo_envio_amostras'));
        $avgSeconds=$sendSamples?(int)round($sendSeconds/$sendSamples):null;

        $best=null;$attention=null;
        foreach($rows as $r){if($r['sla_percentual']!==null){$best??=$r;}}
        foreach(array_reverse($rows) as $r){if($r['sla_percentual']!==null){$attention=$r;break;}}

        return [
            'secretarias'=>$rows,
            'resumo'=>[
                'sla_percentual'=>$overall,
                'sla_class'=>$this->slaClass($overall),
                'sla_label'=>$this->slaLabel($overall),
                'avaliadas'=>$evaluated,
                'no_prazo'=>$onTime,
                'fora_prazo'=>max(0,$evaluated-$onTime),
                'tempo_medio_envio_segundos'=>$avgSeconds,
                'tempo_medio_envio'=>$this->formatDuration($avgSeconds),
                'correcoes'=>$correctionsTotal,
                'primeira_versao_decidida'=>$firstPassDecided,
                'primeira_versao_aprovada'=>$firstPassApproved,
                'aprovacao_primeira_percentual'=>$firstPass,
                'secretarias_avaliadas'=>count(array_filter($rows,fn($r)=>$r['sla_percentual']!==null)),
                'melhor'=>$best,
                'atencao'=>$attention,
            ],
            'fase_atual'=>$state['faseSituacional']??null,
            'metodologia'=>[
                'O SLA considera documentos obrigatórios de responsabilidade municipal.',
                'Uma entrega é considerada no prazo quando a primeira versão é enviada até a data limite operacional da respectiva fase.',
                'Documento obrigatório não enviado após o vencimento entra como entrega fora do prazo.',
                'Fases futuras com datas ainda provisórias não entram no cálculo até que o prazo operacional esteja consolidado.',
                'Tempo médio para enviar mede o intervalo entre o início operacional da fase e o primeiro envio do documento.',
                'Aprovação na primeira versão considera apenas primeiras versões que já receberam decisão da Stratelli.',
                'Quantidade de correções corresponde aos eventos formais de correção registrados no histórico documental.',
            ],
        ];
    }

    public function exportCsv(array $data): never
    {
        $rows=$data['secretarias']??[];
        $filename='sla_secretarias_'.date('Ymd_His').'.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        echo "\xEF\xBB\xBF";
        $out=fopen('php://output','w');
        fputcsv($out,['Secretaria','SLA dentro do prazo (%)','Entregas avaliadas','No prazo','Fora do prazo','Tempo médio para enviar','Correções','Aprovação na primeira versão (%)','Pendências vencidas','Pendências não vencidas'],';');
        foreach($rows as $r){
            fputcsv($out,[
                $r['display_name'],
                $r['sla_percentual']===null?'Sem amostra':str_replace('.',',',(string)$r['sla_percentual']),
                $r['avaliadas'],$r['no_prazo'],$r['fora_prazo'],$r['tempo_medio_envio'],$r['correcoes'],
                $r['aprovacao_primeira_percentual']===null?'Sem decisão':str_replace('.',',',(string)$r['aprovacao_primeira_percentual']),
                $r['pendentes_vencidas'],$r['pendentes_nao_vencidas'],
            ],';');
        }
        fclose($out);
        exit;
    }

    private function firstVersions(): array
    {
        $st=$this->pdo->prepare('SELECT requisito_id,status,enviado_em,validado_em FROM documentos_enviados WHERE municipio_id=? AND versao=1');
        $st->execute([$this->mid]);$out=[];
        foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r)$out[(int)$r['requisito_id']]=$r;
        return $out;
    }

    private function correctionsByRequirement(): array
    {
        $st=$this->pdo->prepare('SELECT requisito_id,COUNT(*) qtd FROM historico_documentos WHERE municipio_id=? AND requisito_id IS NOT NULL AND evento="Correção solicitada" GROUP BY requisito_id');
        $st->execute([$this->mid]);$out=[];
        foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r)$out[(int)$r['requisito_id']]=(int)$r['qtd'];
        return $out;
    }

    private function formatDuration(?int $seconds): string
    {
        if($seconds===null)return 'Sem amostra';
        if($seconds<3600)return max(1,(int)round($seconds/60)).' min';
        if($seconds<86400){$hours=$seconds/3600;return number_format($hours,1,',','.').' h';}
        $days=$seconds/86400;return number_format($days,1,',','.').' dia(s)';
    }

    private function slaClass(?float $value): string
    {
        if($value===null)return 'neutral';
        if($value>=90)return 'excellent';
        if($value>=75)return 'good';
        if($value>=60)return 'attention';
        return 'critical';
    }

    private function slaLabel(?float $value): string
    {
        if($value===null)return 'SEM AMOSTRA';
        if($value>=90)return 'EXCELENTE';
        if($value>=75)return 'ADEQUADO';
        if($value>=60)return 'ATENÇÃO';
        return 'CRÍTICO';
    }
}
