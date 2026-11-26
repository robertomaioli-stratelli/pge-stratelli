<?php
namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

/** Disparo seguro e controlado de notificacoes operacionais.
 * Depende das colunas de contato da atualizacao anterior.
 * Envios manuais: somente por solicitacao autenticada do controller.
 */
final class OperationalEmailService
{
    private PDO $db;
    public function __construct() { $this->db = Database::connection(); }

    public function etapaAtiva(int $mid): int
    {
        $q=$this->db->prepare('SELECT id FROM pge_etapas_execucao WHERE municipio_id=? AND habilitada=1 AND status<>"ARQUIVADO" ORDER BY id DESC LIMIT 1');
        $q->execute([$mid]); $id=(int)$q->fetchColumn();
        if(!$id) throw new RuntimeException('Nenhuma etapa ativa para o municipio.');
        return $id;
    }

    /** Busca apenas requisitos ativos da etapa ativa, sem alterar status. */
    public function pendencias(int $mid): array
    {
        $etapa=$this->etapaAtiva($mid);
        $q=$this->db->prepare('SELECT r.id requisito_id,r.nome documento,r.descricao orientacao,r.obrigatorio,r.perfil_envio,r.secretaria_id,r.departamento_id, '
            .'f.id fase_id,f.titulo fase,f.ordem ordem_fase,a.id ato_id,a.titulo ato,a.ordem ordem_ato, '
            .'atv.id atividade_id,atv.titulo atividade,atv.ordem ordem_atividade, '
            .'t.nome tipo_documento,t.extensoes extensoes_permitidas, '
            .'s.nome secretaria,s.cargo_responsavel cargo_secretaria,s.emails_responsaveis emails_secretaria, '
            .'d.nome departamento,d.cargo_responsavel cargo_departamento,d.emails_responsaveis emails_departamento, '
            .'COALESCE((SELECT de.status FROM documentos_enviados de WHERE de.municipio_id=r.municipio_id AND de.requisito_id=r.id ORDER BY de.versao DESC,de.id DESC LIMIT 1),"NAO_ENTREGUE") estado_documental, '
            .'(SELECT CONCAT_WS(" ",de.arquivo_original,DATE_FORMAT(de.enviado_em,"%d/%m/%Y")) FROM documentos_enviados de WHERE de.municipio_id=r.municipio_id AND de.requisito_id=r.id ORDER BY de.versao DESC,de.id DESC LIMIT 1) ultimo_arquivo, '
            .'(SELECT pr.fim_previsto FROM pge_prazo_resultados pr WHERE pr.municipio_id=r.municipio_id AND pr.nivel="ATIVIDADE" AND pr.item_id=atv.id AND pr.situacao="CALCULADO" LIMIT 1) prazo_atividade, '
            .'(SELECT pr.fim_previsto FROM pge_prazo_resultados pr WHERE pr.municipio_id=r.municipio_id AND pr.nivel="ATO" AND pr.item_id=a.id AND pr.situacao="CALCULADO" LIMIT 1) prazo_ato, '
            .'(SELECT pr.fim_previsto FROM pge_prazo_resultados pr WHERE pr.municipio_id=r.municipio_id AND pr.nivel="FASE" AND pr.item_id=f.id AND pr.situacao="CALCULADO" LIMIT 1) prazo_fase, '
            .'m.nome municipio,m.slug '
            .'FROM requisitos_documentais r '
            .'JOIN fases f ON f.id=r.fase_id AND f.municipio_id=r.municipio_id AND f.etapa_execucao_id=? AND f.ativo=1 '
            .'LEFT JOIN atividades_fase a ON a.id=r.atividade_id AND a.municipio_id=r.municipio_id '
            .'LEFT JOIN pge_atividades_ato atv ON atv.id=r.atividade_ato_id AND atv.municipio_id=r.municipio_id '
            .'LEFT JOIN tipos_documento t ON t.id=r.tipo_documento_id AND t.municipio_id=r.municipio_id '
            .'LEFT JOIN secretarias s ON s.id=r.secretaria_id AND s.municipio_id=r.municipio_id AND s.ativo=1 '
            .'LEFT JOIN departamentos d ON d.id=r.departamento_id AND d.municipio_id=r.municipio_id AND d.ativo=1 '
            .'JOIN municipios m ON m.id=r.municipio_id '
            .'WHERE r.municipio_id=? AND r.ativo=1 AND r.obrigatorio=1 ORDER BY f.ordem,a.ordem,atv.ordem,r.ordem,r.id');
        $q->execute([$etapa,$mid]); $out=[];
        $today=(new \DateTimeImmutable('today',new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if($r['estado_documental']==='APROVADO') continue;
            // Prazo do item mais especifico. Fase/ato sem prazo nao gera atraso.
            $due=$r['atividade_id'] ? ($r['prazo_atividade']?:null) : ($r['ato_id'] ? ($r['prazo_ato']?:null) : ($r['prazo_fase']?:null));
            $r['data_limite']=$due;
            $r['classificacao']=$due!==null && $due<$today?'ATRASADO':'PENDENTE';
            $r['destinatarios']=$this->emails(array_filter([(string)($r['emails_secretaria']??''),(string)($r['emails_departamento']??'')]));
            $out[]=$r;
        }
        return $out;
    }

    /** Chamado SOMENTE quando uma transicao de estado for confirmada no banco.
     * $eventKey deve ser o ID imutavel da operacao, evitando repeticao.
     * Integracao deve ser feita nos services que efetivam a transicao.
     */
    public function notificarTransicao(int $mid,string $nivel,int $itemId,string $evento,string $eventKey): array
    {
        if(!in_array($nivel,['FASE','ATO','ATIVIDADE'],true) || !in_array($evento,['INICIADO','CONCLUIDO'],true))
            throw new RuntimeException('Evento operacional invalido.');
        if(!preg_match('/^[a-zA-Z0-9:_-]{1,120}$/',$eventKey))throw new RuntimeException('Identificador de evento invalido.');
        $etapa=$this->etapaAtiva($mid);
        $query=match($nivel){
            'FASE'=>'SELECT id FROM fases WHERE id=? AND municipio_id=? AND etapa_execucao_id=? AND ativo=1',
            'ATO'=>'SELECT a.id FROM atividades_fase a JOIN fases f ON f.id=a.fase_id AND f.municipio_id=a.municipio_id WHERE a.id=? AND a.municipio_id=? AND f.etapa_execucao_id=? AND a.ativo=1',
            'ATIVIDADE'=>'SELECT c.id FROM pge_atividades_ato c JOIN atividades_fase a ON a.id=c.ato_legado_id JOIN fases f ON f.id=a.fase_id WHERE c.id=? AND c.municipio_id=? AND f.etapa_execucao_id=? AND c.ativo=1'};
        $st=$this->db->prepare($query);$st->execute([$itemId,$mid,$etapa]);
        if(!$st->fetchColumn())throw new RuntimeException('Item fora da etapa ativa.');
        // Recupera exigencias da area afetada, inclusive as ja aprovadas (para evento CONCLUIDO).
        $recipients=[];
        $rows=$this->db->prepare('SELECT r.id,r.nome,r.descricao,r.fase_id,r.atividade_id,r.atividade_ato_id,'
            .'m.nome municipio,m.slug,f.titulo fase,a.titulo ato,c.titulo atividade,'
            .'s.emails_responsaveis emails_secretaria,d.emails_responsaveis emails_departamento '
            .'FROM requisitos_documentais r JOIN fases f ON f.id=r.fase_id AND f.municipio_id=r.municipio_id '
            .'JOIN municipios m ON m.id=r.municipio_id '
            .'LEFT JOIN atividades_fase a ON a.id=r.atividade_id AND a.municipio_id=r.municipio_id '
            .'LEFT JOIN pge_atividades_ato c ON c.id=r.atividade_ato_id AND c.municipio_id=r.municipio_id '
            .'LEFT JOIN secretarias s ON s.id=r.secretaria_id AND s.municipio_id=r.municipio_id AND s.ativo=1 '
            .'LEFT JOIN departamentos d ON d.id=r.departamento_id AND d.municipio_id=r.municipio_id AND d.ativo=1 '
            .'WHERE r.municipio_id=? AND r.ativo=1 AND f.etapa_execucao_id=? AND ('
            .'(?="FASE" AND f.id=?) OR (?="ATO" AND a.id=?) OR (?="ATIVIDADE" AND c.id=?))');
        $rows->execute([$mid,$etapa,$nivel,$itemId,$nivel,$itemId,$nivel,$itemId]);
        foreach($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
            foreach($this->emails([(string)($row['emails_secretaria']??''),(string)($row['emails_departamento']??'')]) as $email)
                $recipients[$email]=$row;
        }
        $sent=0;$failed=0;$dup=0;
        foreach($recipients as $email=>$r){
            $title=($nivel==='FASE'?$r['fase']:($nivel==='ATO'?$r['ato']:$r['atividade']));
            $esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
            $url=rtrim((string)(getenv('APP_URL')?:''),'/').'/'.rawurlencode((string)$r['slug']).'/workflow/fase/'.(int)$r['fase_id'];
            $html='<h2>PGE Stratelli — '.$esc($nivel.' '.$evento).'</h2><p><strong>Município:</strong> '.$esc($r['municipio']).'</p><p><strong>Fase:</strong> '.$esc($r['fase']).'</p><p><strong>Item:</strong> '.$esc($title).'</p><p>Evento confirmado em '.date('d/m/Y H:i').'.</p><p><a href="'.$esc($url).'">Consultar Workflow</a></p>';
            $key=hash('sha256','TRANSICAO|'.$etapa.'|'.$nivel.'|'.$itemId.'|'.$evento.'|'.$eventKey.'|'.$email);
            $out=$this->sendReserved($mid,$etapa,null,$nivel,$itemId,$evento,$email,$key,'PGE Stratelli | '.$nivel.' '.$evento.' — '.$title,$html);
            if($out==='ENVIADO')$sent++;elseif($out==='DUPLICADO')$dup++;else $failed++;
        }
        return ['enviados'=>$sent,'falhas'=>$failed,'duplicados'=>$dup];
    }

    /** Executar em rotina agendada APOS homologacao. Apenas itens com prazo CALCULADO vencido. */
    public function notificarAtrasos(int $mid): array
    {
        $etapa=$this->etapaAtiva($mid);$sent=0;$skipped=0;$errors=0;
        foreach($this->pendencias($mid) as $r){
            if($r['classificacao']!=='ATRASADO'||!$r['data_limite'])continue;
            foreach($r['destinatarios'] as $email){
                // Uma notificacao por requisito, prazo vencido e destinatario.
                $key=hash('sha256','ATRASO|'.$etapa.'|'.$r['requisito_id'].'|'.$r['data_limite'].'|'.$email);
                $out=$this->sendReserved($mid,$etapa,(int)$r['requisito_id'],null,null,'ATRASO',$email,$key,
                    'PGE Stratelli | Prazo vencido — '.$r['documento'],$this->body($r,'ATRASO'));
                if($out==='ENVIADO')$sent++;elseif($out==='DUPLICADO')$skipped++;else $errors++;
            }
        }
        return ['enviados'=>$sent,'repetidos'=>$skipped,'falhas'=>$errors];
    }

    private function emails(array $lists): array
    {
        $out=[];
        foreach($lists as $v) foreach(preg_split('/[,;\s]+/u',$v,-1,PREG_SPLIT_NO_EMPTY)?:[] as $email) {
            $email=mb_strtolower(trim($email));
            if(filter_var($email,FILTER_VALIDATE_EMAIL)) $out[$email]=$email;
        }
        return array_values($out);
    }

    /** A UI lista tudo antes da confirmacao. Backend recalcula e valida IDs, nao confia no POST. */
    public function cobrar(int $mid,array $ids,string $filtro,string $mensagem,int $uid): array
    {
        if(!in_array($filtro,['PENDENTE','ATRASADO','TODOS'],true))throw new RuntimeException('Filtro invalido.');
        $ids=array_values(array_unique(array_map('intval',$ids)));
        if(!$ids||count($ids)>100)throw new RuntimeException('Selecione de 1 a 100 documentos.');
        $itens=[];foreach($this->pendencias($mid) as $row){
            if(in_array((int)$row['requisito_id'],$ids,true) && ($filtro==='TODOS'||$row['classificacao']===$filtro))$itens[]=$row;
        }
        if(count($itens)!==count($ids))throw new RuntimeException('Selecao alterada. Reabra a tela e confira os documentos.');
        $sent=0;$skipped=0;$errors=0;
        foreach($itens as $r){
            foreach($r['destinatarios'] as $email){
                // Janela de deduplicacao manual de 15 minutos, por documento e destinatario.
                $window=(int)floor(time()/900);
                $key=hash('sha256','MANUAL|'.$mid.'|'.$r['requisito_id'].'|'.$email.'|'.$window);
                $subject='PGE Stratelli | Cobrança de documento: '.$r['documento'];
                $body=$this->body($r,'COBRANCA_MANUAL',$mensagem);
                $result=$this->sendReserved($mid,$this->etapaAtiva($mid),(int)$r['requisito_id'],null,null,'COBRANCA_MANUAL',$email,$key,$subject,$body);
                if($result==='ENVIADO')$sent++;elseif($result==='DUPLICADO')$skipped++;else $errors++;
            }
        }
        return ['enviados'=>$sent,'repetidos'=>$skipped,'falhas'=>$errors,'sem_destinatario'=>count(array_filter($itens,fn($r)=>!$r['destinatarios']))];
    }

    private function body(array $r,string $event,string $note=''): string
    {
        $esc=static fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $url=rtrim((string)(getenv('APP_URL')?:''),'/').'/'.rawurlencode((string)$r['slug']).'/workflow/fase/'.(int)$r['fase_id'];
        $lines=['Município'=>$r['municipio'],'Fase'=>$r['fase'],'Ato'=>$r['ato']?:'—','Atividade'=>$r['atividade']?:'—',
        'Documento exigido'=>$r['documento'],'Tipo documental'=>$r['tipo_documento']?:'—','Formatos admitidos'=>$r['extensoes_permitidas']?:'—','Orientação'=>$r['orientacao']?:'—','Obrigatório'=>'Sim',
        'Situação documental'=>$r['estado_documental'],'Classificação'=>$r['classificacao'],
        'Data-limite'=>$r['data_limite']?:'Sem prazo definido', 'Secretaria'=>$r['secretaria']?:'Não vinculada',
        'Cargo responsável'=>$r['cargo_departamento']?:($r['cargo_secretaria']?:'Não informado'),
        'Departamento'=>$r['departamento']?:'—','Último arquivo'=>$r['ultimo_arquivo']?:'Não enviado'];
        $out='<h2>PGE Stratelli — '.($event==='COBRANCA_MANUAL'?'Solicitação de providências':$esc($event)).'</h2><p>Consulte o documento exigido e providencie a entrega ou correção.</p><table cellpadding="7" border="1" cellspacing="0">';
        foreach($lines as $k=>$v)$out.='<tr><th align="left">'.$esc($k).'</th><td>'.$esc($v).'</td></tr>';
        $out.='</table>';
        if(trim($note)!=='')$out.='<p><strong>Observação da Stratelli:</strong> '.nl2br($esc(mb_substr($note,0,1000))).'</p>';
        if(str_starts_with($url,'https://'))$out.='<p><a href="'.$esc($url).'">Abrir o Workflow</a></p>';
        return $out.'<p>Mensagem operacional. Não envie dados confidenciais em resposta a este e-mail.</p>';
    }

    private function sendReserved(int $mid,int $etapa,?int $rid,?string $level,?int $item,string $event,string $to,string $key,string $subject,string $html): string
    {
        $q=$this->db->prepare('INSERT IGNORE INTO pge_email_notificacoes(municipio_id,etapa_execucao_id,requisito_id,nivel,item_id,evento,destinatario,chave_evento) VALUES(?,?,?,?,?,?,?,?)');
        $q->execute([$mid,$etapa,$rid,$level,$item,$event,$to,$key]);
        if(!$q->rowCount())return 'DUPLICADO';
        $id=(int)$this->db->lastInsertId();
        try{
            (new MailService())->send($to,$subject,$html);
            $this->db->prepare('UPDATE pge_email_notificacoes SET status="ENVIADO",enviado_em=NOW() WHERE id=?')->execute([$id]);return 'ENVIADO';
        }catch(\Throwable $e){
            $this->db->prepare('UPDATE pge_email_notificacoes SET status="FALHOU",detalhe_erro=? WHERE id=?')->execute([mb_substr($e->getMessage(),0,255),$id]);return 'FALHOU';
        }
    }
}
