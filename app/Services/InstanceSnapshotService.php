<?php
namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Tenant;
use PDO;
use RuntimeException;
use ZipArchive;

final class InstanceSnapshotService
{
    private PDO $pdo; private int $mid; private array $tenant;
    private array $archiveTables=['municipios','parametros_instancia','secretarias','departamentos','usuarios','fases','fase_secretarias','tipos_documento','requisitos_documentais','modelos_documentos','documentos_enviados','historico_documentos','cronograma_processos','cronograma_fases','historico_fases','notificacoes','camadas_territoriais','objetos_territoriais','vinculos_territoriais','importacoes_estrutura','auditoria'];
    private array $restoreEntityTables=['secretarias','departamentos','fases','tipos_documento','requisitos_documentais','camadas_territoriais','objetos_territoriais'];

    public function __construct()
    {
        if(!Auth::isPlatformAdmin())throw new RuntimeException('Apenas a Stratelli pode criar ou restaurar pontos da instância.');
        $this->pdo=Database::connection();$this->mid=(int)Tenant::id();$this->tenant=Tenant::current()?:[];
        if($this->mid<1)throw new RuntimeException('Instância municipal não identificada.');
    }

    public function list(int $limit=20): array
    {
        $limit=max(1,min(50,$limit));
        $st=$this->pdo->prepare("SELECT p.*,u.nome usuario_nome FROM pontos_restauracao_instancia p LEFT JOIN usuarios u ON u.id=p.criado_por_usuario_id WHERE p.municipio_id=? AND p.status<>'REMOVIDO' ORDER BY p.id DESC LIMIT {$limit}");
        $st->execute([$this->mid]);$rows=$st->fetchAll();
        foreach($rows as&$r)$r['resumo']=json_decode((string)($r['resumo_json']??''),true)?:[];
        return$rows;
    }

    public function create(string $name='',string $origin='SALVO',bool $audit=true): array
    {
        $origin=in_array($origin,['SALVO','AUTOMATICO'],true)?$origin:'SALVO';
        $name=trim($name);if($name==='')$name=$origin==='AUTOMATICO'?'Ponto automático de segurança':'Ponto de restauração';
        $name=substr($name,0,180);
        $dir=$this->backupDir();$stamp=date('Ymd_His');$rand=bin2hex(random_bytes(4));$slug=preg_replace('/[^a-z0-9_-]+/','-',strtolower((string)($this->tenant['slug']??'municipio')))?:'municipio';
        $filename="inpacta_{$slug}_{$stamp}_{$rand}.zip";$full=$dir.'/'.$filename;
        $snapshot=$this->captureSnapshot($name,$origin);
        $this->writeZip($full,$snapshot);
        $checksum=hash_file('sha256',$full)?:'';$size=(int)filesize($full);$user=Auth::user();$summary=$this->summary($snapshot);
        $rel='municipio_'.$this->mid.'/'.$filename;
        $this->pdo->prepare('INSERT INTO pontos_restauracao_instancia(municipio_id,nome,origem,arquivo_path,arquivo_nome,checksum_sha256,tamanho,resumo_json,status,criado_por_usuario_id,criado_em) VALUES(?,?,?,?,?,?,?,?,"DISPONIVEL",?,NOW())')->execute([$this->mid,$name,$origin,$rel,$filename,$checksum,$size,json_encode($summary,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(int)($user['id']??0)?:null]);
        $id=(int)$this->pdo->lastInsertId();
        if($audit)Audit::log('PONTO_RESTAURACAO_CRIADO','Ponto de restauração #'.$id.' criado para '.$this->tenant['nome'], $this->mid,['categoria'=>'ADMINISTRACAO','severidade'=>'INFO','snapshot_id'=>$id,'origem'=>$origin,'checksum'=>$checksum]);
        return['id'=>$id,'name'=>$name,'file'=>$full,'summary'=>$summary];
    }

    public function importUploaded(string $tmp,string $original): int
    {
        if(!is_file($tmp))throw new RuntimeException('Selecione um pacote de backup válido.');
        if(strtolower(pathinfo($original,PATHINFO_EXTENSION))!=='zip')throw new RuntimeException('O pacote deve estar no formato .zip.');
        if(filesize($tmp)>250*1024*1024)throw new RuntimeException('O pacote de backup deve ter no máximo 250 MB.');
        $snapshot=$this->readSnapshotFromZip($tmp);
        $this->validateSnapshotIdentity($snapshot);
        $dir=$this->backupDir();$filename='importado_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.zip';$full=$dir.'/'.$filename;
        if(!copy($tmp,$full))throw new RuntimeException('Não foi possível armazenar o pacote importado.');
        $checksum=hash_file('sha256',$full)?:'';$size=(int)filesize($full);$summary=$this->summary($snapshot);$user=Auth::user();$rel='municipio_'.$this->mid.'/'.$filename;
        $name='Backup importado — '.substr(basename($original),0,120);
        $this->pdo->prepare('INSERT INTO pontos_restauracao_instancia(municipio_id,nome,origem,arquivo_path,arquivo_nome,checksum_sha256,tamanho,resumo_json,status,criado_por_usuario_id,criado_em) VALUES(?,?,"IMPORTADO",?,?,?,?,?,"DISPONIVEL",?,NOW())')->execute([$this->mid,$name,$rel,$filename,$checksum,$size,json_encode($summary,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(int)($user['id']??0)?:null]);
        $id=(int)$this->pdo->lastInsertId();
        Audit::log('PONTO_RESTAURACAO_IMPORTADO','Pacote de backup importado como ponto #'.$id,$this->mid,['categoria'=>'ADMINISTRACAO','severidade'=>'ATENCAO','snapshot_id'=>$id,'arquivo'=>$original]);
        return$id;
    }

    public function download(int $id): never
    {
        $row=$this->find($id);$file=$this->resolveBackup((string)$row['arquivo_path']);
        if(!$file||!is_file($file))throw new RuntimeException('O arquivo deste ponto de restauração não está disponível.');
        $actual=hash_file('sha256',$file)?:'';if(!$actual||!hash_equals(strtolower((string)$row['checksum_sha256']),strtolower($actual)))throw new RuntimeException('Falha de integridade no pacote de backup.');
        Audit::log('PONTO_RESTAURACAO_EXPORTADO','Ponto de restauração #'.$id.' exportado',$this->mid,['categoria'=>'ADMINISTRACAO','severidade'=>'INFO','snapshot_id'=>$id]);
        $name='INPACTA_'.$this->safeName((string)$this->tenant['nome']).'_backup_'.date('Ymd_His',strtotime((string)$row['criado_em'])).'.zip';
        header('Content-Type: application/zip');header('Content-Length: '.filesize($file));header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode($name));header('X-Content-Type-Options: nosniff');readfile($file);exit;
    }

    public function restore(int $id): array
    {
        (new EtapaArchiveService())->assertOpen();
        $row=$this->find($id);$file=$this->resolveBackup((string)$row['arquivo_path']);if(!$file)throw new RuntimeException('Pacote de restauração indisponível.');
        $actual=hash_file('sha256',$file)?:'';if(!$actual||!hash_equals(strtolower((string)$row['checksum_sha256']),strtolower($actual)))throw new RuntimeException('O pacote falhou na verificação SHA-256.');
        $snapshot=$this->readSnapshotFromZip($file);$this->validateSnapshotIdentity($snapshot);
        $safety=$this->create('Segurança automática antes de restaurar #'.$id,'AUTOMATICO',false);
        $this->restoreMissingFiles($file,$snapshot);
        $this->pdo->beginTransaction();
        try{$this->applySnapshot($snapshot);$this->pdo->prepare('UPDATE pontos_restauracao_instancia SET restaurado_em=NOW(),restaurado_por_usuario_id=?,restauracoes=restauracoes+1 WHERE id=? AND municipio_id=?')->execute([(int)(Auth::user()['id']??0)?:null,$id,$this->mid]);$this->pdo->commit();}
        catch(\Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
        Audit::log('PONTO_RESTAURACAO_APLICADO','Instância restaurada a partir do ponto #'.$id.'; ponto de segurança automático #'.$safety['id'].' criado antes da operação.',$this->mid,['categoria'=>'ADMINISTRACAO','severidade'=>'CRITICO','snapshot_id'=>$id,'safety_snapshot_id'=>$safety['id']]);
        return['snapshot_id'=>$id,'safety_id'=>$safety['id']];
    }

    public function remove(int $id): void
    {
        $row=$this->find($id);$file=$this->resolveBackup((string)$row['arquivo_path']);if($file&&is_file($file))@unlink($file);
        $this->pdo->prepare('UPDATE pontos_restauracao_instancia SET status="REMOVIDO" WHERE id=? AND municipio_id=?')->execute([$id,$this->mid]);
        Audit::log('PONTO_RESTAURACAO_REMOVIDO','Ponto de restauração #'.$id.' removido',$this->mid,['categoria'=>'ADMINISTRACAO','severidade'=>'ATENCAO','snapshot_id'=>$id]);
    }

    private function captureSnapshot(string$name,string$origin): array
    {
        $tables=[];
        foreach($this->archiveTables as$table){
            if($table==='municipios'){$st=$this->pdo->prepare('SELECT * FROM municipios WHERE id=?');$st->execute([$this->mid]);$rows=$st->fetchAll();}
            elseif($table==='usuarios'){$st=$this->pdo->prepare('SELECT * FROM usuarios WHERE municipio_id=?');$st->execute([$this->mid]);$rows=$st->fetchAll();}
            elseif($table==='auditoria'){$st=$this->pdo->prepare('SELECT * FROM auditoria WHERE municipio_id=? ORDER BY id');$st->execute([$this->mid]);$rows=$st->fetchAll();}
            else{$st=$this->pdo->prepare("SELECT * FROM {$table} WHERE municipio_id=?");$st->execute([$this->mid]);$rows=$st->fetchAll();}
            $tables[$table]=$rows;
        }
        return['format'=>'INPACTA_INSTANCE_SNAPSHOT','format_version'=>1,'app_version'=>'4.20.14','created_at'=>date(DATE_ATOM),'name'=>$name,'origin'=>$origin,'municipality'=>['id'=>$this->mid,'slug'=>$this->tenant['slug']??'','name'=>$this->tenant['nome']??'','uf'=>$this->tenant['uf']??''],'restore_policy'=>['mutable_state'=>'restored','audit_history'=>'preserved','document_history'=>'preserved','security_events'=>'preserved'],'tables'=>$tables];
    }

    private function writeZip(string$file,array$snapshot): void
    {
        if(!class_exists(ZipArchive::class))throw new RuntimeException('A extensão PHP ZIP precisa estar habilitada para criar backups da instância.');
        $zip=new ZipArchive();if($zip->open($file,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Não foi possível criar o pacote de backup.');
        $json=json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);if($json===false){$zip->close();throw new RuntimeException('Não foi possível serializar o estado da instância.');}
        $zip->addFromString('snapshot.json',$json);$zip->addFromString('LEIA-ME.txt',"Backup INPACTA da instância {$this->tenant['nome']} - {$this->tenant['uf']}\nCriado em: ".date('d/m/Y H:i:s')."\n\nEste pacote contém dados cadastrais, operacionais e cópias dos arquivos vinculados. A restauração automática preserva trilhas imutáveis de auditoria e histórico documental.\n");
        foreach($this->filePaths($snapshot) as$relative){$source=$this->uploadRoot().'/'.ltrim($relative,'/');if(is_file($source))$zip->addFile($source,'files/'.ltrim($relative,'/'));}
        $zip->close();
    }

    private function readSnapshotFromZip(string$file): array
    {
        if(!class_exists(ZipArchive::class))throw new RuntimeException('A extensão PHP ZIP precisa estar habilitada.');$zip=new ZipArchive();if($zip->open($file)!==true)throw new RuntimeException('Pacote ZIP inválido.');$json=$zip->getFromName('snapshot.json');$zip->close();if($json===false)throw new RuntimeException('Este arquivo não é um backup INPACTA válido.');$data=json_decode($json,true);if(!is_array($data))throw new RuntimeException('Manifesto do backup inválido.');return$data;
    }

    private function validateSnapshotIdentity(array$s): void
    {
        if(($s['format']??'')!=='INPACTA_INSTANCE_SNAPSHOT'||(int)($s['format_version']??0)!==1)throw new RuntimeException('Formato de backup não reconhecido.');
        if((int)($s['municipality']['id']??0)!==$this->mid||($s['municipality']['slug']??'')!==($this->tenant['slug']??''))throw new RuntimeException('Este pacote pertence a outro município e não pode ser restaurado nesta instância.');
    }

    private function applySnapshot(array$s): void
    {
        $t=$s['tables']??[];
        if(!empty($t['municipios'][0]))$this->updateMunicipio($t['municipios'][0]);
        if(!empty($t['parametros_instancia'][0]))$this->upsert('parametros_instancia',$t['parametros_instancia'][0],['municipio_id']);
        foreach($this->restoreEntityTables as$table){$rows=$t[$table]??[];foreach($rows as$r)$this->upsert($table,$r,['id']);$this->deactivateExtras($table,$rows);}
        $this->restoreUsers($t['usuarios']??[]);
        $this->restoreModelActivation($t['modelos_documentos']??[]);
        $this->pdo->prepare('DELETE FROM fase_secretarias WHERE municipio_id=?')->execute([$this->mid]);foreach($t['fase_secretarias']??[]as$r)$this->insertIgnore('fase_secretarias',$r);
        if(!empty($t['cronograma_processos'][0]))$this->upsert('cronograma_processos',$t['cronograma_processos'][0],['municipio_id']);else$this->pdo->prepare('DELETE FROM cronograma_processos WHERE municipio_id=?')->execute([$this->mid]);
        foreach($t['cronograma_fases']??[]as$r)$this->upsert('cronograma_fases',$r,['id']);$this->markExtraChronogramRowsReopened($t['cronograma_fases']??[]);
        $this->pdo->prepare('DELETE FROM vinculos_territoriais WHERE municipio_id=?')->execute([$this->mid]);foreach($t['vinculos_territoriais']??[]as$r)$this->insertIgnore('vinculos_territoriais',$r);
        // Modelos, documentos e históricos são deliberadamente preservados como trilha imutável.
    }

    private function restoreUsers(array $rows): void
    {
        $snapshotIds=[];
        foreach($rows as$row){
            $id=(int)($row['id']??0);if($id<1)continue;$snapshotIds[]=$id;
            $st=$this->pdo->prepare('SELECT senha_hash,auth_version,senha_alterada_em FROM usuarios WHERE id=? AND municipio_id=?');$st->execute([$id,$this->mid]);$current=$st->fetch();
            if($current){$row['senha_hash']=$current['senha_hash'];$row['auth_version']=$current['auth_version'];$row['senha_alterada_em']=$current['senha_alterada_em'];}
            $this->upsert('usuarios',$row,['id']);
        }
        if($snapshotIds){$ph=implode(',',array_fill(0,count($snapshotIds),'?'));$this->pdo->prepare("UPDATE usuarios SET ativo=0,auth_version=auth_version+1 WHERE municipio_id=? AND id NOT IN ({$ph})")->execute(array_merge([$this->mid],$snapshotIds));}
        else$this->pdo->prepare('UPDATE usuarios SET ativo=0,auth_version=auth_version+1 WHERE municipio_id=?')->execute([$this->mid]);
    }
    private function restoreModelActivation(array $rows): void
    {
        $this->pdo->prepare('UPDATE modelos_documentos SET ativo=0 WHERE municipio_id=?')->execute([$this->mid]);
        $active=array_values(array_filter(array_map(fn($r)=>(int)(!empty($r['ativo'])?($r['id']??0):0),$rows)));
        if($active){$ph=implode(',',array_fill(0,count($active),'?'));$this->pdo->prepare("UPDATE modelos_documentos SET ativo=1 WHERE municipio_id=? AND id IN ({$ph})")->execute(array_merge([$this->mid],$active));}
    }
    private function updateMunicipio(array$row): void
    {
        unset($row['id']);$cols=array_keys($row);$set=implode(',',array_map(fn($c)=>"`{$c}`=?",$cols));$vals=array_values($row);$vals[]=$this->mid;$this->pdo->prepare("UPDATE municipios SET {$set} WHERE id=?")->execute($vals);
    }
    private function upsert(string$table,array$row,array$keyCols): void
    {
        if(!$row)return;$cols=array_keys($row);$names=implode(',',array_map(fn($c)=>"`{$c}`",$cols));$ph=implode(',',array_fill(0,count($cols),'?'));$updates=[];foreach($cols as$c)if(!in_array($c,$keyCols,true))$updates[]="`{$c}`=VALUES(`{$c}`)";$sql="INSERT INTO `{$table}` ({$names}) VALUES ({$ph})".($updates?' ON DUPLICATE KEY UPDATE '.implode(',',$updates):'');$this->pdo->prepare($sql)->execute(array_values($row));
    }
    private function insertIgnore(string$table,array$row): void
    {if(!$row)return;$cols=array_keys($row);$names=implode(',',array_map(fn($c)=>"`{$c}`",$cols));$ph=implode(',',array_fill(0,count($cols),'?'));$this->pdo->prepare("INSERT IGNORE INTO `{$table}` ({$names}) VALUES ({$ph})")->execute(array_values($row));}
    private function deactivateExtras(string$table,array$rows): void
    {
        $ids=array_values(array_filter(array_map(fn($r)=>(int)($r['id']??0),$rows)));if(!$this->hasColumn($table,'ativo'))return;
        if($ids){$ph=implode(',',array_fill(0,count($ids),'?'));$this->pdo->prepare("UPDATE `{$table}` SET ativo=0 WHERE municipio_id=? AND id NOT IN ({$ph})")->execute(array_merge([$this->mid],$ids));}
        else$this->pdo->prepare("UPDATE `{$table}` SET ativo=0 WHERE municipio_id=?")->execute([$this->mid]);
    }
    private function markExtraChronogramRowsReopened(array$rows): void
    { $ids=array_values(array_filter(array_map(fn($r)=>(int)($r['id']??0),$rows)));if($ids){$ph=implode(',',array_fill(0,count($ids),'?'));$this->pdo->prepare("UPDATE cronograma_fases SET status='REABERTA',reaberto_em=COALESCE(reaberto_em,NOW()),motivo_reabertura=COALESCE(NULLIF(motivo_reabertura,''),'Reaberta por restauração de ponto da instância') WHERE municipio_id=? AND id NOT IN ({$ph})")->execute(array_merge([$this->mid],$ids));}else$this->pdo->prepare("UPDATE cronograma_fases SET status='REABERTA',reaberto_em=COALESCE(reaberto_em,NOW()),motivo_reabertura=COALESCE(NULLIF(motivo_reabertura,''),'Reaberta por restauração de ponto da instância') WHERE municipio_id=?")->execute([$this->mid]);}

    private function restoreMissingFiles(string$zipFile,array$snapshot): void
    {
        $zip=new ZipArchive();if($zip->open($zipFile)!==true)throw new RuntimeException('Não foi possível abrir os arquivos do backup.');$root=$this->uploadRoot();foreach($this->filePaths($snapshot)as$relative){$relative=ltrim(str_replace('\\','/',$relative),'/');if($relative===''||str_contains($relative,'..'))continue;$dest=$root.'/'.$relative;if(is_file($dest))continue;$entry='files/'.$relative;if($zip->locateName($entry)===false)continue;$dir=dirname($dest);if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir)){$zip->close();throw new RuntimeException('Não foi possível recriar a pasta de arquivos do backup.');}$content=$zip->getFromName($entry);if($content===false||file_put_contents($dest,$content)===false){$zip->close();throw new RuntimeException('Não foi possível restaurar um arquivo do pacote.');}}$zip->close();
    }
    private function filePaths(array$s): array
    {
        $paths=[];$m=$s['tables']['municipios'][0]??[];if(!empty($m['brasao_path']))$paths[]=$m['brasao_path'];foreach(['modelos_documentos','documentos_enviados','historico_documentos']as$table)foreach($s['tables'][$table]??[]as$r)if(!empty($r['arquivo_salvo']))$paths[]=$r['arquivo_salvo'];return array_values(array_unique(array_filter(array_map('strval',$paths))));
    }
    private function summary(array$s): array
    {
        $t=$s['tables']??[];return['fases'=>count($t['fases']??[]),'secretarias'=>count($t['secretarias']??[]),'departamentos'=>count($t['departamentos']??[]),'usuarios'=>count($t['usuarios']??[]),'requisitos'=>count($t['requisitos_documentais']??[]),'documentos'=>count($t['documentos_enviados']??[]),'modelos'=>count($t['modelos_documentos']??[]),'objetos_territoriais'=>count($t['objetos_territoriais']??[]),'arquivos'=>count($this->filePaths($s))];
    }
    private function find(int$id): array{$st=$this->pdo->prepare('SELECT * FROM pontos_restauracao_instancia WHERE id=? AND municipio_id=? AND status<>"REMOVIDO"');$st->execute([$id,$this->mid]);$r=$st->fetch();if(!$r)throw new RuntimeException('Ponto de restauração não encontrado.');return$r;}
    private function hasColumn(string$table,string$column): bool{try{$st=$this->pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");$st->execute([$column]);return(bool)$st->fetch();}catch(\Throwable){return false;}}
    private function backupDir(): string{$dir=dirname(__DIR__,2).'/storage/backups/municipio_'.$this->mid;if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir))throw new RuntimeException('Não foi possível criar a pasta segura de backups.');return$dir;}
    private function resolveBackup(string$relative): ?string{$root=realpath(dirname(__DIR__,2).'/storage/backups');$candidate=realpath(dirname(__DIR__,2).'/storage/backups/'.ltrim($relative,'/'));if(!$root||!$candidate||strpos($candidate,$root.DIRECTORY_SEPARATOR)!==0||!is_file($candidate))return null;return$candidate;}
    private function uploadRoot(): string{return dirname(__DIR__,2).'/storage/uploads';}
    private function safeName(string$s): string{return trim(preg_replace('/[^A-Za-z0-9_-]+/','_',iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s)?:$s),'_')?:'Municipio';}
}
