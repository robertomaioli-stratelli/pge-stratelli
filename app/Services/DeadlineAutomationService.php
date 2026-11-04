<?php
namespace App\Services;

use App\Core\Database;
use App\Core\Audit;
use DateTimeImmutable;
use PDO;
use RuntimeException;

/**
 * Motor V32. Prazo e calculado somente quando a origem da contagem e comprovada.
 * Dias uteis = segunda a sexta; feriados nao estao disponiveis na versao V32.
 */
final class DeadlineAutomationService
{
    private PDO $db;
    public function __construct() { $this->db = Database::connection(); }

    public function rules(int $mid): array
    {
        $st=$this->db->prepare('SELECT * FROM pge_prazo_regras WHERE municipio_id=? ORDER BY nivel,item_id');
        $st->execute([$mid]);
        $out=[];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[$r['nivel'].':'.$r['item_id']]=$r;
        return $out;
    }

    public function results(int $mid): array
    {
        $st=$this->db->prepare('SELECT * FROM pge_prazo_resultados WHERE municipio_id=?');
        $st->execute([$mid]);
        $out=[];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[$r['nivel'].':'.$r['item_id']]=$r;
        return $out;
    }

    public function save(int $mid, string $level, int $itemId, string $origin, ?int $anchorId, ?string $date,
                         int $quantity, string $unit, string $direction, ?int $uid): void
    {
        if (!in_array($level,['FASE','ATO','ATIVIDADE'],true)) throw new RuntimeException('Nivel invalido.');
        if (!in_array($origin,['INICIO_FORMAL','DATA_FIXA','CONCLUSAO_ATO','CONCLUSAO_FASE'],true)) throw new RuntimeException('Tipo de marco invalido.');
        if (!in_array($unit,['DIAS_UTEIS','DIAS_CORRIDOS'],true)) throw new RuntimeException('Unidade de contagem invalida.');
        if (!in_array($direction,['APOS','ANTES'],true)) throw new RuntimeException('Direcao invalida.');
        if ($quantity<0 || $quantity>3650) throw new RuntimeException('Informe de 0 a 3650 dias.');
        $table=['FASE'=>'fases','ATO'=>'atividades_fase','ATIVIDADE'=>'pge_atividades_ato'][$level];
        $st=$this->db->prepare("SELECT COUNT(*) FROM $table WHERE id=? AND municipio_id=? AND ativo=1");
        $st->execute([$itemId,$mid]);
        if (!$st->fetchColumn()) throw new RuntimeException('Item nao encontrado neste municipio.');
        $date=$date!==null && trim($date)!==''?$date:null;
        if ($origin==='DATA_FIXA') {
            $dt=$date?DateTimeImmutable::createFromFormat('!Y-m-d',$date):false;
            if (!$dt || $dt->format('Y-m-d')!==$date) throw new RuntimeException('Informe data fixa valida.');
        } else $date=null;
        if ($origin==='CONCLUSAO_ATO' || $origin==='CONCLUSAO_FASE') {
            $target=$origin==='CONCLUSAO_ATO'?'atividades_fase':'fases';
            $st=$this->db->prepare("SELECT COUNT(*) FROM $target WHERE id=? AND municipio_id=? AND ativo=1");
            $st->execute([(int)$anchorId,$mid]);
            if (!$st->fetchColumn()) throw new RuntimeException('O marco dependente deve pertencer a este municipio.');
            if ($origin==='CONCLUSAO_ATO' && $level==='ATO' && $itemId===(int)$anchorId) throw new RuntimeException('Um Ato nao pode depender de si mesmo.');
            if ($origin==='CONCLUSAO_FASE' && $level==='FASE' && $itemId===(int)$anchorId) throw new RuntimeException('Uma Fase nao pode depender de si mesma.');
        } else $anchorId=null;
        $st=$this->db->prepare('INSERT INTO pge_prazo_regras(municipio_id,nivel,item_id,marco_tipo,marco_id,data_fixa,quantidade,unidade,direcao,usuario_id) VALUES(?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE marco_tipo=VALUES(marco_tipo),marco_id=VALUES(marco_id),data_fixa=VALUES(data_fixa),quantidade=VALUES(quantidade),unidade=VALUES(unidade),direcao=VALUES(direcao),usuario_id=VALUES(usuario_id),atualizado_em=NOW()');
        $st->execute([$mid,$level,$itemId,$origin,$anchorId,$date,$quantity,$unit,$direction,$uid]);
        $this->recalculate($mid);
        Audit::log('REGRA_PRAZO_ATUALIZADA', 'Regra de prazo '.$level.' #'.$itemId.' atualizada', $mid,
            ['categoria'=>'ADMINISTRACAO','nivel'=>$level,'item_id'=>$itemId,'marco_tipo'=>$origin,'quantidade'=>$quantity,'unidade'=>$unit]);
    }

    public function recalculate(int $mid): array
    {
        $formal=(new FormalStartService())->get($mid);
        $formalDate=$formal['data_inicio']??null;
        $rules=$this->rules($mid);
        $out=[];
        $query=$this->db->prepare('SELECT id,concluida_em FROM atividades_fase WHERE municipio_id=? AND ativo=1');
        $query->execute([$mid]);
        $atos=[];
        foreach($query->fetchAll(PDO::FETCH_ASSOC) as $row)$atos[(int)$row['id']]=$row['concluida_em'];
        $query=$this->db->prepare('SELECT fase_id,data_conclusao_real FROM cronograma_fases WHERE municipio_id=? AND status="ENCERRADA"');
        $query->execute([$mid]);
        $fases=[];
        foreach($query->fetchAll(PDO::FETCH_ASSOC) as $row)$fases[(int)$row['fase_id']]=$row['data_conclusao_real'];
        $insert=$this->db->prepare('INSERT INTO pge_prazo_resultados(municipio_id,nivel,item_id,inicio_previsto,fim_previsto,situacao,fundamento_calculo,calculado_em) VALUES(?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE inicio_previsto=VALUES(inicio_previsto),fim_previsto=VALUES(fim_previsto),situacao=VALUES(situacao),fundamento_calculo=VALUES(fundamento_calculo),calculado_em=NOW()');
        foreach($rules as $key=>$rule){
            $state='AGUARDANDO_INICIO';$start=null;$end=null;$note='Documento formal ainda nao registrado';
            if ($formalDate) {
                $state='AGUARDANDO_MARCO';$note='Aguardando evento de referencia';
                $origin=$rule['marco_tipo'];$anchor=null;
                if($origin==='INICIO_FORMAL')$anchor=$formalDate;
                elseif($origin==='DATA_FIXA')$anchor=$rule['data_fixa'];
                elseif($origin==='CONCLUSAO_ATO')$anchor=$atos[(int)$rule['marco_id']]??null;
                elseif($origin==='CONCLUSAO_FASE')$anchor=$fases[(int)$rule['marco_id']]??null;
                if($anchor){
                    try {
                        $date=new DateTimeImmutable(substr((string)$anchor,0,10));
                        $amount=(int)$rule['quantidade'];
                        $sign=$rule['direcao']==='ANTES'?-1:1;
                        $due=self::addDays($date,$amount,$rule['unidade']==='DIAS_UTEIS',$sign);
                        $start=($sign===1?$date:$due)->format('Y-m-d');
                        $end=($sign===1?$due:$date)->format('Y-m-d');
                        $state='CALCULADO';$note=$origin.' · '.$amount.' '.$rule['unidade'];
                    } catch(\Throwable $e) {$state='REGRA_INCOMPLETA';$note='Data do marco invalida';}
                }
            }
            $out[$key]=['inicio_previsto'=>$start,'fim_previsto'=>$end,'situacao'=>$state,'fundamento_calculo'=>$note];
            $insert->execute([$mid,$rule['nivel'],$rule['item_id'],$start,$end,$state,$note]);
        }
        return $out;
    }

    public static function addDays(DateTimeImmutable $start,int $amount,bool $business,int $direction=1): DateTimeImmutable
    {
        if($amount<0 || $amount>3650)throw new RuntimeException('Quantidade invalida.');
        $cursor=$start;
        for($count=0;$count<$amount;){
            $cursor=$cursor->modify(($direction<0?'-1':'+1').' day');
            if(!$business||(int)$cursor->format('N')<=5)$count++;
        }
        return $cursor;
    }
}
