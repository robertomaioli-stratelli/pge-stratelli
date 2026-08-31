<?php
namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Tenant;
use PDO;
use RuntimeException;

final class StructureImportService
{
    private PDO $pdo; private int $mid;
    public function __construct(){if(!Auth::isPlatformAdmin())throw new RuntimeException('Apenas a Stratelli pode importar estruturas.');$this->pdo=Database::connection();$this->mid=(int)Tenant::id();}

    public function sourceMunicipalities(): array
    {
        $st=$this->pdo->prepare('SELECT id,nome,uf,status FROM municipios WHERE id<>? AND ativo=1 ORDER BY nome,uf');$st->execute([$this->mid]);return$st->fetchAll();
    }

    public function recentImports(int $limit=10): array
    {
        try{
            $limit=max(1,min(30,$limit));
            $st=$this->pdo->prepare("SELECT i.*,m.nome origem_nome,m.uf origem_uf,u.nome usuario_nome FROM importacoes_estrutura i LEFT JOIN municipios m ON m.id=i.origem_municipio_id LEFT JOIN usuarios u ON u.id=i.criado_por_usuario_id WHERE i.municipio_id=? ORDER BY i.id DESC LIMIT {$limit}");
            $st->execute([$this->mid]);$rows=$st->fetchAll();
            foreach($rows as&$r){$r['resumo']=json_decode((string)($r['resumo_json']??''),true)?:[];$r['itens']=json_decode((string)($r['itens_json']??''),true)?:[];$r['pode_desfazer']=$r['status']==='CONCLUIDA'&&empty($r['resumo']['updated_total']);}
            return$rows;
        }catch(\Throwable){return[];}
    }

    public function previewMunicipality(int $sourceId,array $selection,string $strategy='IGNORAR'): array
    {
        if($sourceId<1||$sourceId===$this->mid)throw new RuntimeException('Selecione um município de origem válido.');
        $strategy=$this->strategy($strategy);$selection=$this->selection($selection);
        if(!array_filter($selection))throw new RuntimeException('Selecione pelo menos Fases, Secretarias ou Departamentos.');
        $st=$this->pdo->prepare('SELECT id,nome,uf FROM municipios WHERE id=? AND ativo=1');$st->execute([$sourceId]);$source=$st->fetch();if(!$source)throw new RuntimeException('Município de origem não encontrado.');
        $data=['fases'=>[],'secretarias'=>[],'departamentos'=>[],'vinculos'=>[]];
        if($selection['fases']){$st=$this->pdo->prepare('SELECT ordem,codigo,aba,titulo,descricao,responsavel,dia_inicio,dia_fim,entregavel,criterio,exclusivo_stratelli,ativo FROM fases WHERE municipio_id=? ORDER BY ordem');$st->execute([$sourceId]);$data['fases']=$st->fetchAll();}
        if($selection['secretarias']){$st=$this->pdo->prepare('SELECT sigla,nome,ativo FROM secretarias WHERE municipio_id=? ORDER BY nome');$st->execute([$sourceId]);$data['secretarias']=$st->fetchAll();}
        if($selection['departamentos']){$st=$this->pdo->prepare('SELECT d.sigla,d.nome,d.ativo,s.sigla secretaria_sigla,s.nome secretaria_nome FROM departamentos d JOIN secretarias s ON s.id=d.secretaria_id AND s.municipio_id=d.municipio_id WHERE d.municipio_id=? ORDER BY s.nome,d.nome');$st->execute([$sourceId]);$data['departamentos']=$st->fetchAll();}
        if($selection['fases']||$selection['secretarias']){$st=$this->pdo->prepare('SELECT f.codigo fase_codigo,f.ordem fase_ordem,s.sigla secretaria_sigla,s.nome secretaria_nome FROM fase_secretarias fs JOIN fases f ON f.id=fs.fase_id AND f.municipio_id=fs.municipio_id JOIN secretarias s ON s.id=fs.secretaria_id AND s.municipio_id=fs.municipio_id WHERE fs.municipio_id=? ORDER BY f.ordem,s.nome');$st->execute([$sourceId]);$data['vinculos']=$st->fetchAll();}
        return$this->analyze([
            'source_type'=>'MUNICIPIO','source_id'=>$sourceId,'source_label'=>$source['nome'].' - '.$source['uf'],'file_name'=>null,'strategy'=>$strategy,'selection'=>$selection,'data'=>$data,
        ]);
    }

    public function previewWorkbook(string $path,string $originalName,string $strategy='IGNORAR'): array
    {
        if(strtolower(pathinfo($originalName,PATHINFO_EXTENSION))!=='xlsx')throw new RuntimeException('Envie a planilha oficial no formato .xlsx.');
        if(!is_file($path)||filesize($path)>5*1024*1024)throw new RuntimeException('A planilha deve ter no máximo 5 MB.');
        $sheets=(new XlsxStructureReader())->read($path);
        foreach(['FASES','SECRETARIAS','DEPARTAMENTOS'] as$required)if(!isset($sheets[$required]))throw new RuntimeException('A planilha não possui a aba obrigatória '.$required.'. Baixe novamente o modelo oficial.');
        $data=[
            'fases'=>$this->parseRows($sheets['FASES']??[],'fases'),
            'secretarias'=>$this->parseRows($sheets['SECRETARIAS']??[],'secretarias'),
            'departamentos'=>$this->parseRows($sheets['DEPARTAMENTOS']??[],'departamentos'),
            'vinculos'=>$this->parseRows($sheets['VINCULOS_FASE_SECRETARIA']??[],'vinculos'),
        ];
        $selection=['fases'=>!empty($data['fases']),'secretarias'=>!empty($data['secretarias']),'departamentos'=>!empty($data['departamentos'])];
        return$this->analyze(['source_type'=>'PLANILHA','source_id'=>null,'source_label'=>'Planilha Excel','file_name'=>substr(basename($originalName),0,255),'strategy'=>$this->strategy($strategy),'selection'=>$selection,'data'=>$data]);
    }

    public function execute(array $preview): array
    {
        (new EtapaArchiveService())->assertOpen();
        $preview=$this->analyze($preview);
        if(!empty($preview['errors']))throw new RuntimeException('A importação possui erros impeditivos. Corrija-os antes de confirmar.');
        $data=$preview['data'];$strategy=$preview['strategy'];$actions=['created'=>['fases'=>[],'secretarias'=>[],'departamentos'=>[],'vinculos'=>[]],'updated'=>[]];
        $this->pdo->beginTransaction();
        try{
            $phaseMap=$this->destinationPhaseMap();$secMap=$this->destinationSecretariaMap();$depMap=$this->destinationDepartmentMap();
            foreach($data['fases'] as$f){
                $existing=$this->matchPhase($f,$phaseMap);
                if($existing){if($strategy==='ATUALIZAR'){$old=$existing;$this->assertPhaseCanUpdate((int)$existing['id']);$this->pdo->prepare('UPDATE fases SET ordem=?,codigo=?,aba=?,titulo=?,descricao=?,responsavel=?,dia_inicio=?,dia_fim=?,entregavel=?,criterio=?,exclusivo_stratelli=?,ativo=?,atualizado_em=NOW() WHERE id=? AND municipio_id=?')->execute([(int)$f['ordem'],$f['codigo'],$f['aba'],$f['titulo'],$f['descricao'],$f['responsavel'],(int)$f['dia_inicio'],(int)$f['dia_fim'],$f['entregavel'],$f['criterio'],(int)$f['exclusivo_stratelli'],(int)$f['ativo'],(int)$existing['id'],$this->mid]);$actions['updated'][]=['type'=>'fase','id'=>(int)$existing['id'],'old'=>$old];}$phaseMap=$this->destinationPhaseMap();continue;}
                $this->pdo->prepare('INSERT INTO fases(municipio_id,ordem,codigo,aba,titulo,descricao,responsavel,dia_inicio,dia_fim,entregavel,criterio,exclusivo_stratelli,ativo,criado_em,atualizado_em) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())')->execute([$this->mid,(int)$f['ordem'],$f['codigo'],$f['aba'],$f['titulo'],$f['descricao'],$f['responsavel'],(int)$f['dia_inicio'],(int)$f['dia_fim'],$f['entregavel'],$f['criterio'],(int)$f['exclusivo_stratelli'],(int)$f['ativo']]);$actions['created']['fases'][]=(int)$this->pdo->lastInsertId();$phaseMap=$this->destinationPhaseMap();
            }
            foreach($data['secretarias'] as$s){
                $existing=$this->matchSecretaria($s,$secMap);
                if($existing){if($strategy==='ATUALIZAR'){$old=$existing;$this->pdo->prepare('UPDATE secretarias SET nome=?,sigla=?,ativo=?,atualizado_em=NOW() WHERE id=? AND municipio_id=?')->execute([$s['nome'],$s['sigla'],(int)$s['ativo'],(int)$existing['id'],$this->mid]);$actions['updated'][]=['type'=>'secretaria','id'=>(int)$existing['id'],'old'=>$old];}$secMap=$this->destinationSecretariaMap();continue;}
                $this->pdo->prepare('INSERT INTO secretarias(municipio_id,nome,sigla,ativo,criado_em,atualizado_em) VALUES(?,?,?,?,NOW(),NOW())')->execute([$this->mid,$s['nome'],$s['sigla'],(int)$s['ativo']]);$actions['created']['secretarias'][]=(int)$this->pdo->lastInsertId();$secMap=$this->destinationSecretariaMap();
            }
            foreach($data['departamentos'] as$d){
                $sec=$this->resolveSecretary($d,$secMap);if(!$sec)throw new RuntimeException('Secretaria não encontrada para o departamento '.$d['nome'].'.');
                $existing=$this->matchDepartment($d,(int)$sec['id'],$depMap);
                if($existing){if($strategy==='ATUALIZAR'){$old=$existing;$this->pdo->prepare('UPDATE departamentos SET secretaria_id=?,nome=?,sigla=?,ativo=?,atualizado_em=NOW() WHERE id=? AND municipio_id=?')->execute([(int)$sec['id'],$d['nome'],$d['sigla'],(int)$d['ativo'],(int)$existing['id'],$this->mid]);$actions['updated'][]=['type'=>'departamento','id'=>(int)$existing['id'],'old'=>$old];}$depMap=$this->destinationDepartmentMap();continue;}
                $this->pdo->prepare('INSERT INTO departamentos(municipio_id,secretaria_id,nome,sigla,ativo,criado_em,atualizado_em) VALUES(?,?,?,?,?,NOW(),NOW())')->execute([$this->mid,(int)$sec['id'],$d['nome'],$d['sigla'],(int)$d['ativo']]);$actions['created']['departamentos'][]=(int)$this->pdo->lastInsertId();$depMap=$this->destinationDepartmentMap();
            }
            $phaseMap=$this->destinationPhaseMap();$secMap=$this->destinationSecretariaMap();
            foreach($data['vinculos'] as$v){$phase=$this->resolvePhase($v,$phaseMap);$sec=$this->resolveSecretary($v,$secMap);if(!$phase||!$sec)continue;$check=$this->pdo->prepare('SELECT 1 FROM fase_secretarias WHERE municipio_id=? AND fase_id=? AND secretaria_id=?');$check->execute([$this->mid,$phase['id'],$sec['id']]);if($check->fetchColumn())continue;$this->pdo->prepare('INSERT INTO fase_secretarias(municipio_id,fase_id,secretaria_id) VALUES(?,?,?)')->execute([$this->mid,$phase['id'],$sec['id']]);$actions['created']['vinculos'][]=['fase_id'=>(int)$phase['id'],'secretaria_id'=>(int)$sec['id']];}
            $summary=$preview['summary'];$summary['created_total']=array_sum(array_map('count',$actions['created']));$summary['updated_total']=count($actions['updated']);
            $user=Auth::user();$this->pdo->prepare('INSERT INTO importacoes_estrutura(municipio_id,origem_municipio_id,origem_tipo,arquivo_nome,estrategia_conflito,resumo_json,itens_json,status,criado_por_usuario_id,criado_em) VALUES(?,?,?,?,?,?,?,"CONCLUIDA",?,NOW())')->execute([$this->mid,$preview['source_id']?:null,$preview['source_type'],$preview['file_name']?:null,$strategy,json_encode($summary,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),json_encode($actions,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(int)$user['id']]);
            $batchId=(int)$this->pdo->lastInsertId();$this->pdo->commit();
            Audit::log('ESTRUTURA_IMPORTADA','Importação estrutural #'.$batchId.' concluída: '.$summary['created_total'].' criação(ões), '.$summary['updated_total'].' atualização(ões).',$this->mid,['categoria'=>'ADMINISTRACAO','severidade'=>'ATENCAO','importacao_id'=>$batchId,'origem'=>$preview['source_type']]);
            return['id'=>$batchId,'summary'=>$summary];
        }catch(\Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }

    public function undo(int $id): void
    {
        (new EtapaArchiveService())->assertOpen();
        $st=$this->pdo->prepare('SELECT * FROM importacoes_estrutura WHERE id=? AND municipio_id=? AND status="CONCLUIDA"');$st->execute([$id,$this->mid]);$batch=$st->fetch();if(!$batch)throw new RuntimeException('Importação não encontrada ou já desfeita.');
        $summary=json_decode((string)$batch['resumo_json'],true)?:[];if(!empty($summary['updated_total']))throw new RuntimeException('Esta importação atualizou registros existentes e não pode ser desfeita automaticamente.');
        $items=json_decode((string)$batch['itens_json'],true)?:[];$c=$items['created']??[];
        $this->assertUndoSafe($c);
        $this->pdo->beginTransaction();try{
            foreach(array_reverse($c['vinculos']??[])as$v)$this->pdo->prepare('DELETE FROM fase_secretarias WHERE municipio_id=? AND fase_id=? AND secretaria_id=?')->execute([$this->mid,(int)$v['fase_id'],(int)$v['secretaria_id']]);
            foreach(array_reverse($c['departamentos']??[])as$did)$this->pdo->prepare('DELETE FROM departamentos WHERE id=? AND municipio_id=?')->execute([(int)$did,$this->mid]);
            foreach(array_reverse($c['secretarias']??[])as$sid)$this->pdo->prepare('DELETE FROM secretarias WHERE id=? AND municipio_id=?')->execute([(int)$sid,$this->mid]);
            foreach(array_reverse($c['fases']??[])as$fid)$this->pdo->prepare('DELETE FROM fases WHERE id=? AND municipio_id=?')->execute([(int)$fid,$this->mid]);
            $this->pdo->prepare('UPDATE importacoes_estrutura SET status="DESFEITA",desfeita_em=NOW() WHERE id=? AND municipio_id=?')->execute([$id,$this->mid]);$this->pdo->commit();
            Audit::log('IMPORTACAO_ESTRUTURA_DESFEITA','Importação estrutural #'.$id.' desfeita antes do uso dos registros.',$this->mid,['categoria'=>'ADMINISTRACAO','severidade'=>'ATENCAO','importacao_id'=>$id]);
        }catch(\Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }

    private function analyze(array $preview): array
    {
        $preview['strategy']=$this->strategy((string)($preview['strategy']??'IGNORAR'));$preview['data']=$this->normalizeData($preview['data']??[]);$errors=[];$warnings=[];$items=[];
        $phaseMap=$this->destinationPhaseMap();$secMap=$this->destinationSecretariaMap();$depMap=$this->destinationDepartmentMap();
        $combinedOrders=[];foreach($phaseMap['all'] as$f)$combinedOrders[(int)$f['ordem']]=true;
        $seenCodes=[];$seenOrders=[];foreach($preview['data']['fases'] as$i=>$f){$label='Fase '.$f['ordem'].' — '.$f['titulo'];$errs=[];if($f['aba']===''||$f['titulo']==='')$errs[]='Nome Curto e Título são obrigatórios.';if($f['dia_fim']<$f['dia_inicio'])$errs[]='Dia Final não pode ser menor que Dia Inicial.';if(isset($seenOrders[$f['ordem']]))$errs[]='Ordem duplicada na planilha/origem.';if(isset($seenCodes[$this->key($f['codigo'])]))$errs[]='Código duplicado na planilha/origem.';$seenOrders[$f['ordem']]=1;$seenCodes[$this->key($f['codigo'])]=1;$byCode=$phaseMap['code'][$this->key($f['codigo'])]??null;$byOrder=$phaseMap['order'][(int)$f['ordem']]??null;if($byCode&&$byOrder&&(int)$byCode['id']!==(int)$byOrder['id'])$errs[]='Código e ordem conflitam com fases diferentes já existentes no destino.';$existing=$this->matchPhase($f,$phaseMap);$status=$existing?($preview['strategy']==='ATUALIZAR'?'UPDATE':'SKIP'):'CREATE';if($existing&&$preview['strategy']==='ATUALIZAR'){try{$this->assertPhaseCanUpdate((int)$existing['id']);}catch(\Throwable$e){$errs[]=$e->getMessage();}}if($existing&&($existing['codigo']!==$f['codigo']&&(int)$existing['ordem']===(int)$f['ordem']))$errs[]='A ordem já pertence a outra fase no destino.';if($errs){$status='ERROR';foreach($errs as$e)$errors[]=$label.': '.$e;}$items[]=['type'=>'Fase','label'=>$label,'status'=>$status,'message'=>$this->statusMessage($status)];$combinedOrders[$f['ordem']]=true;}
        if($preview['data']['fases']&&$combinedOrders){$orders=array_keys($combinedOrders);sort($orders);$min=min($orders);$max=max($orders);for($n=$min;$n<=$max;$n++)if(!isset($combinedOrders[$n]))$errors[]='Sequência de fases incompleta: falta a ordem '.$n.'.';}
        $tempSecMap=$secMap;$seenSecSigla=[];$seenSecNome=[];
        foreach($preview['data']['secretarias'] as$s){
            $label=($s['sigla']?$s['sigla'].' — ':'').$s['nome'];$status='CREATE';$secErr=[];$resolvedSec=['id'=>0]+$s;
            $sigKey=$this->key($s['sigla']);$nameKey=$this->key($s['nome']);
            if($s['nome']==='')$secErr[]='Nome obrigatório.';
            if($sigKey!==''&&isset($seenSecSigla[$sigKey]))$secErr[]='Sigla duplicada na origem.';
            if($nameKey!==''&&isset($seenSecNome[$nameKey]))$secErr[]='Nome duplicado na origem.';
            if($sigKey!=='')$seenSecSigla[$sigKey]=1;if($nameKey!=='')$seenSecNome[$nameKey]=1;
            if(!$secErr){
                $bySigla=$sigKey!==''?($secMap['sigla'][$sigKey]??null):null;$byNome=$nameKey!==''?($secMap['nome'][$nameKey]??null):null;
                if($bySigla&&$byNome&&(int)$bySigla['id']!==(int)$byNome['id'])$secErr[]='Sigla e nome correspondem a Secretarias diferentes já existentes no destino.';
                else{$existing=$this->matchSecretaria($s,$secMap);if($existing){$resolvedSec=$existing;$status=$preview['strategy']==='ATUALIZAR'?'UPDATE':'SKIP';}}
            }
            if($secErr){$status='ERROR';foreach($secErr as$e)$errors[]=$label.': '.$e;}
            $items[]=['type'=>'Secretaria','label'=>$label,'status'=>$status,'message'=>$this->statusMessage($status)];
            if($sigKey!=='')$tempSecMap['sigla'][$sigKey]=$resolvedSec;if($nameKey!=='')$tempSecMap['nome'][$nameKey]=$resolvedSec;
        }
        $seenDept=[];
        foreach($preview['data']['departamentos'] as$d){
            $label=($d['sigla']?$d['sigla'].' — ':'').$d['nome'];$sec=$this->resolveSecretary($d,$tempSecMap);
            if(!$sec){$items[]=['type'=>'Departamento','label'=>$label,'status'=>'ERROR','message'=>'Secretaria de referência não encontrada.'];$errors[]=$label.': Secretaria '.($d['secretaria_sigla']?:$d['secretaria_nome']).' não encontrada.';continue;}
            if($d['nome']===''){$items[]=['type'=>'Departamento','label'=>$label,'status'=>'ERROR','message'=>'Nome obrigatório'];$errors[]='Departamento sem nome para a Secretaria '.($d['secretaria_sigla']?:$d['secretaria_nome']).'.';continue;}
            $dupKey=$this->key($d['secretaria_sigla']?:$d['secretaria_nome']).'|'.$this->key($d['sigla']?:$d['nome']);
            if(isset($seenDept[$dupKey])){$items[]=['type'=>'Departamento','label'=>$label,'status'=>'ERROR','message'=>'Duplicado na origem'];$errors[]=$label.': Departamento duplicado para a mesma Secretaria.';continue;}$seenDept[$dupKey]=1;
            $status='CREATE';
            if((int)($sec['id']??0)>0){
                $sid=(int)$sec['id'];$sigKey=$this->key($d['sigla']);$nameKey=$this->key($d['nome']);$bySigla=$sigKey!==''?($depMap[$sid]['sigla'][$sigKey]??null):null;$byNome=$nameKey!==''?($depMap[$sid]['nome'][$nameKey]??null):null;
                if($bySigla&&$byNome&&(int)$bySigla['id']!==(int)$byNome['id']){$status='ERROR';$errors[]=$label.': Sigla e nome correspondem a Departamentos diferentes já existentes nesta Secretaria.';}
                else{$existing=$this->matchDepartment($d,$sid,$depMap);if($existing)$status=$preview['strategy']==='ATUALIZAR'?'UPDATE':'SKIP';}
            }
            $items[]=['type'=>'Departamento','label'=>$label,'status'=>$status,'message'=>$this->statusMessage($status)];
        }
        $validLinks=0;foreach($preview['data']['vinculos'] as$v){$phase=$this->resolvePhase($v,$phaseMap,$preview['data']['fases']);$sec=$this->resolveSecretary($v,$tempSecMap);if(!$phase||!$sec){$warnings[]='Vínculo '.($v['fase_codigo']?:$v['fase_ordem']).' × '.($v['secretaria_sigla']?:$v['secretaria_nome']).' será ignorado porque uma das referências não foi localizada.';continue;}$validLinks++;}
        $counts=['CREATE'=>0,'UPDATE'=>0,'SKIP'=>0,'ERROR'=>0];foreach($items as$item)$counts[$item['status']]++;
        $preview['errors']=array_values(array_unique($errors));$preview['warnings']=array_values(array_unique($warnings));$preview['items']=$items;$preview['summary']=['fases'=>count($preview['data']['fases']),'secretarias'=>count($preview['data']['secretarias']),'departamentos'=>count($preview['data']['departamentos']),'vinculos'=>$validLinks,'criar'=>$counts['CREATE'],'atualizar'=>$counts['UPDATE'],'ignorar'=>$counts['SKIP'],'erros'=>$counts['ERROR'],'created_total'=>0,'updated_total'=>0];
        return$preview;
    }

    private function parseRows(array $rows,string $type): array
    {
        if(!$rows)return[];$headers=array_shift($rows);$map=[];foreach($headers as$i=>$h)$map[$this->headerKey((string)$h)]=$i;
        $schema=match($type){
            'fases'=>['ordem'=>'ordem','codigo'=>'codigo','nome curto'=>'aba','titulo'=>'titulo','descricao'=>'descricao','responsavel'=>'responsavel','dia inicial'=>'dia_inicio','dia final'=>'dia_fim','entregavel'=>'entregavel','criterio'=>'criterio','exclusiva stratelli'=>'exclusivo_stratelli','ativa'=>'ativo'],
            'secretarias'=>['sigla'=>'sigla','nome da secretaria'=>'nome','ativa'=>'ativo'],
            'departamentos'=>['sigla secretaria'=>'secretaria_sigla','sigla departamento'=>'sigla','nome do departamento'=>'nome','ativo'=>'ativo'],
            default=>['codigo fase'=>'fase_codigo','sigla secretaria'=>'secretaria_sigla'],
        };
        foreach(array_keys($schema)as$required)if(!array_key_exists($required,$map))throw new RuntimeException('Cabeçalho obrigatório ausente na aba '.strtoupper($type).': '.$required.'.');
        $out=[];foreach($rows as$row){$record=[];$nonEmpty=false;foreach($schema as$header=>$field){$v=trim((string)($row[$map[$header]]??''));$record[$field]=$v;if($v!=='')$nonEmpty=true;}if(!$nonEmpty)continue;if($type==='fases'&&$record['ordem']==='')throw new RuntimeException('A coluna Ordem é obrigatória em todas as fases preenchidas.');$out[]=$record;if(count($out)>1000)throw new RuntimeException('A planilha excede o limite de 1.000 registros por aba.');}return$out;
    }

    private function normalizeData(array $data): array
    {
        $out=['fases'=>[],'secretarias'=>[],'departamentos'=>[],'vinculos'=>[]];
        foreach((array)($data['fases']??[])as$f){$order=max(0,(int)($f['ordem']??0));$out['fases'][]=['ordem'=>$order,'codigo'=>trim((string)($f['codigo']??''))?:'FASE-'.str_pad((string)$order,2,'0',STR_PAD_LEFT),'aba'=>trim((string)($f['aba']??'')),'titulo'=>trim((string)($f['titulo']??'')),'descricao'=>trim((string)($f['descricao']??'')),'responsavel'=>trim((string)($f['responsavel']??'')),'dia_inicio'=>max(1,(int)($f['dia_inicio']??1)),'dia_fim'=>max(1,(int)($f['dia_fim']??1)),'entregavel'=>trim((string)($f['entregavel']??'')),'criterio'=>trim((string)($f['criterio']??'')),'exclusivo_stratelli'=>$this->boolValue($f['exclusivo_stratelli']??0),'ativo'=>$this->boolValue(trim((string)($f['ativo']??''))===''?1:$f['ativo'])];}
        foreach((array)($data['secretarias']??[])as$s)$out['secretarias'][]=['sigla'=>strtoupper(trim((string)($s['sigla']??''))),'nome'=>trim((string)($s['nome']??'')),'ativo'=>$this->boolValue(trim((string)($s['ativo']??''))===''?1:$s['ativo'])];
        foreach((array)($data['departamentos']??[])as$d)$out['departamentos'][]=['secretaria_sigla'=>strtoupper(trim((string)($d['secretaria_sigla']??''))),'secretaria_nome'=>trim((string)($d['secretaria_nome']??'')),'sigla'=>strtoupper(trim((string)($d['sigla']??''))),'nome'=>trim((string)($d['nome']??'')),'ativo'=>$this->boolValue(trim((string)($d['ativo']??''))===''?1:$d['ativo'])];
        foreach((array)($data['vinculos']??[])as$v)$out['vinculos'][]=['fase_codigo'=>trim((string)($v['fase_codigo']??'')),'fase_ordem'=>isset($v['fase_ordem'])?(int)$v['fase_ordem']:null,'secretaria_sigla'=>strtoupper(trim((string)($v['secretaria_sigla']??''))),'secretaria_nome'=>trim((string)($v['secretaria_nome']??''))];
        return$out;
    }

    private function destinationPhaseMap(): array{$st=$this->pdo->prepare('SELECT * FROM fases WHERE municipio_id=?');$st->execute([$this->mid]);$all=$st->fetchAll();$m=['code'=>[],'order'=>[],'all'=>$all];foreach($all as$f){$m['code'][$this->key($f['codigo'])]=$f;$m['order'][(int)$f['ordem']]=$f;}return$m;}
    private function destinationSecretariaMap(): array{$st=$this->pdo->prepare('SELECT * FROM secretarias WHERE municipio_id=?');$st->execute([$this->mid]);$all=$st->fetchAll();$m=['sigla'=>[],'nome'=>[],'all'=>$all];foreach($all as$s){if(trim($s['sigla'])!=='')$m['sigla'][$this->key($s['sigla'])]=$s;$m['nome'][$this->key($s['nome'])]=$s;}return$m;}
    private function destinationDepartmentMap(): array{$st=$this->pdo->prepare('SELECT * FROM departamentos WHERE municipio_id=?');$st->execute([$this->mid]);$all=$st->fetchAll();$m=[];foreach($all as$d){$sid=(int)$d['secretaria_id'];if(trim($d['sigla'])!=='')$m[$sid]['sigla'][$this->key($d['sigla'])]=$d;$m[$sid]['nome'][$this->key($d['nome'])]=$d;}return$m;}
    private function matchPhase(array$f,array$m):?array{return$m['code'][$this->key($f['codigo'])]??$m['order'][(int)$f['ordem']]??null;}
    private function matchSecretaria(array$s,array$m):?array{if($s['sigla']!==''&&isset($m['sigla'][$this->key($s['sigla'])]))return$m['sigla'][$this->key($s['sigla'])];return$m['nome'][$this->key($s['nome'])]??null;}
    private function matchDepartment(array$d,int$sid,array$m):?array{if($d['sigla']!==''&&isset($m[$sid]['sigla'][$this->key($d['sigla'])]))return$m[$sid]['sigla'][$this->key($d['sigla'])];return$m[$sid]['nome'][$this->key($d['nome'])]??null;}
    private function resolveSecretary(array$d,array$m):?array{if(!empty($d['secretaria_sigla'])&&isset($m['sigla'][$this->key($d['secretaria_sigla'])]))return$m['sigla'][$this->key($d['secretaria_sigla'])];if(!empty($d['secretaria_nome'])&&isset($m['nome'][$this->key($d['secretaria_nome'])]))return$m['nome'][$this->key($d['secretaria_nome'])];return null;}
    private function resolvePhase(array$v,array$m,array$incoming=[]):?array{if(!empty($v['fase_codigo'])&&isset($m['code'][$this->key($v['fase_codigo'])]))return$m['code'][$this->key($v['fase_codigo'])];if(isset($v['fase_ordem'])&&$v['fase_ordem']!==null&&isset($m['order'][(int)$v['fase_ordem']]))return$m['order'][(int)$v['fase_ordem']];foreach($incoming as$f)if((!empty($v['fase_codigo'])&&$this->key($f['codigo'])===$this->key($v['fase_codigo']))||(isset($v['fase_ordem'])&&$v['fase_ordem']!==null&&(int)$f['ordem']===(int)$v['fase_ordem']))return['id'=>0]+$f;return null;}
    private function assertPhaseCanUpdate(int$id):void{$st=$this->pdo->prepare('SELECT 1 FROM cronograma_fases WHERE municipio_id=? AND fase_id=? AND status="ENCERRADA" LIMIT 1');$st->execute([$this->mid,$id]);if($st->fetchColumn())throw new RuntimeException('Fase formalmente encerrada não pode ser atualizada pela importação.');}
    private function assertUndoSafe(array$c):void
    {
        foreach($c['departamentos']??[]as$id){$st=$this->pdo->prepare('SELECT (SELECT COUNT(*) FROM usuarios WHERE municipio_id=? AND departamento_id=?)+(SELECT COUNT(*) FROM requisitos_documentais WHERE municipio_id=? AND departamento_id=?)');$st->execute([$this->mid,$id,$this->mid,$id]);if((int)$st->fetchColumn()>0)throw new RuntimeException('Não é possível desfazer: um departamento importado já está em uso.');}
        $createdDeptIds=array_map('intval',$c['departamentos']??[]);$createdDeptSql=$createdDeptIds?implode(',',$createdDeptIds):'0';
        foreach($c['secretarias']??[]as$id){$st=$this->pdo->prepare('SELECT (SELECT COUNT(*) FROM usuarios WHERE municipio_id=? AND secretaria_id=?)+(SELECT COUNT(*) FROM requisitos_documentais WHERE municipio_id=? AND secretaria_id=?)+(SELECT COUNT(*) FROM departamentos WHERE municipio_id=? AND secretaria_id=? AND id NOT IN ('.$createdDeptSql.'))');$st->execute([$this->mid,$id,$this->mid,$id,$this->mid,$id]);if((int)$st->fetchColumn()>0)throw new RuntimeException('Não é possível desfazer: uma Secretaria importada já está em uso.');$links=$this->pdo->prepare('SELECT fase_id FROM fase_secretarias WHERE municipio_id=? AND secretaria_id=?');$links->execute([$this->mid,$id]);foreach($links->fetchAll(PDO::FETCH_COLUMN)as$fid){$known=false;foreach($c['vinculos']??[]as$v)if((int)$v['fase_id']===(int)$fid&&(int)$v['secretaria_id']===(int)$id){$known=true;break;}if(!$known)throw new RuntimeException('Não é possível desfazer: uma Secretaria importada recebeu novos vínculos de fase após a importação.');}}
        foreach($c['fases']??[]as$id){$st=$this->pdo->prepare('SELECT (SELECT COUNT(*) FROM requisitos_documentais WHERE municipio_id=? AND fase_id=?)+(SELECT COUNT(*) FROM cronograma_fases WHERE municipio_id=? AND fase_id=?)');$st->execute([$this->mid,$id,$this->mid,$id]);if((int)$st->fetchColumn()>0)throw new RuntimeException('Não é possível desfazer: uma fase importada já está em uso.');$links=$this->pdo->prepare('SELECT secretaria_id FROM fase_secretarias WHERE municipio_id=? AND fase_id=?');$links->execute([$this->mid,$id]);foreach($links->fetchAll(PDO::FETCH_COLUMN)as$sid){$known=false;foreach($c['vinculos']??[]as$v)if((int)$v['fase_id']===(int)$id&&(int)$v['secretaria_id']===(int)$sid){$known=true;break;}if(!$known)throw new RuntimeException('Não é possível desfazer: uma fase importada recebeu novos vínculos de Secretaria após a importação.');}}
    }
    private function selection(array$s):array{return['fases'=>!empty($s['fases']),'secretarias'=>!empty($s['secretarias']),'departamentos'=>!empty($s['departamentos'])];}
    private function strategy(string$s):string{return strtoupper($s)==='ATUALIZAR'?'ATUALIZAR':'IGNORAR';}
    private function statusMessage(string$s):string{return match($s){'CREATE'=>'Será criado','UPDATE'=>'Registro existente será atualizado','SKIP'=>'Já existe e será ignorado',default=>'Requer correção'};}
    private function boolValue(mixed$v):int{if(is_bool($v)||is_int($v))return(int)(bool)$v;$v=$this->key((string)$v);return in_array($v,['1','sim','s','yes','true','ativo','ativa'],true)?1:0;}
    private function key(string$v):string{$v=trim(function_exists('mb_strtolower')?mb_strtolower($v,'UTF-8'):strtolower($v));$ascii=function_exists('iconv')?@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$v):false;return preg_replace('/[^a-z0-9]+/','',strtolower($ascii!==false?$ascii:$v))?:'';}
    private function headerKey(string$v):string{$v=trim(function_exists('mb_strtolower')?mb_strtolower($v,'UTF-8'):strtolower($v));$ascii=function_exists('iconv')?@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$v):false;$v=strtolower($ascii!==false?$ascii:$v);return trim(preg_replace('/\s+/',' ',preg_replace('/[^a-z0-9]+/',' ',$v)));}
}
