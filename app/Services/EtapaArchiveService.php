<?php
namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Tenant;
use PDO;
use RuntimeException;
use ZipArchive;

final class EtapaArchiveService
{
    private PDO $pdo;
    private int $mid;
    private array $tenant;
    private array $tables=[
        'municipios','parametros_instancia','secretarias','departamentos','usuarios','fases','fase_secretarias',
        'tipos_documento','requisitos_documentais','modelos_documentos','documentos_enviados','historico_documentos',
        'cronograma_processos','cronograma_fases','historico_fases','notificacoes','camadas_territoriais',
        'objetos_territoriais','vinculos_territoriais','importacoes_estrutura','auditoria'
    ];

    public function __construct()
    {
        $this->pdo=Database::connection();
        EtapaArchiveSchema::ensure($this->pdo);
        $this->mid=(int)Tenant::id();
        $this->tenant=Tenant::current()?:[];
        if($this->mid<1)throw new RuntimeException('Instância municipal não identificada.');
    }

    public function status(): array
    {
        $row=$this->one('SELECT * FROM arquivamentos_etapa WHERE municipio_id=? AND etapa_numero=1 LIMIT 1',[$this->mid]);
        $active=$this->all('SELECT f.id,f.ordem,f.aba,f.titulo,COALESCE(c.status,"PENDENTE") status,c.data_conclusao_real,c.encerrado_em FROM fases f LEFT JOIN cronograma_fases c ON c.fase_id=f.id AND c.municipio_id=f.municipio_id WHERE f.municipio_id=? AND f.ativo=1 ORDER BY f.ordem,f.id',[$this->mid]);
        $total=count($active);$closed=0;$pending=[];
        foreach($active as $f){if(strtoupper((string)$f['status'])==='ENCERRADA')$closed++;else$pending[]=$f;}
        return [
            'arquivada'=>(bool)$row,
            'registro'=>$row?:null,
            'total_fases'=>$total,
            'fases_encerradas'=>$closed,
            'todas_fases_encerradas'=>$total>0&&$closed===$total,
            'pendencias_fases'=>$pending,
            'pode_arquivar'=>!$row&&$total>0&&$closed===$total,
        ];
    }

    public function assertOpen(): void
    {
        $st=$this->pdo->prepare('SELECT id FROM arquivamentos_etapa WHERE municipio_id=? AND etapa_numero=1 LIMIT 1');
        $st->execute([$this->mid]);
        if($st->fetchColumn())throw new RuntimeException('A Etapa 1 está encerrada e arquivada. Esta instância está em modo somente leitura para preservar o pacote de encerramento e sua cadeia de custódia.');
    }

    public function archive(): array
    {
        if(!Auth::isPlatformAdmin())throw new RuntimeException('Apenas a Stratelli pode encerrar e arquivar a Etapa 1.');
        $this->assertOpen();
        $status=$this->status();
        if(!$status['todas_fases_encerradas']){
            $labels=array_map(fn($f)=>'Fase '.(int)$f['ordem'].' — '.$f['aba'],$status['pendencias_fases']);
            throw new RuntimeException('A Etapa 1 ainda não pode ser arquivada. Fases pendentes: '.implode(', ',$labels).'.');
        }

        $snapshot=$this->capture();
        $this->validateClosureIntegrity($snapshot);
        $dir=$this->archiveDir();
        $slug=preg_replace('/[^a-z0-9_-]+/','-',strtolower((string)($this->tenant['slug']??'municipio')))?:'municipio';
        $filename='inpacta_'.$slug.'_etapa_1_encerramento_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.zip';
        $full=$dir.'/'.$filename;
        $this->writeZip($full,$snapshot);
        $hash=hash_file('sha256',$full)?:'';
        $size=(int)filesize($full);
        $summary=$snapshot['summary'];
        $user=Auth::user()?:[];
        $relative='municipio_'.$this->mid.'/'.$filename;

        $this->pdo->beginTransaction();
        try{
            $this->pdo->prepare('INSERT INTO arquivamentos_etapa(municipio_id,etapa_numero,nome,versao_pacote,arquivo_path,arquivo_nome,checksum_sha256,tamanho,resumo_json,manifesto_json,arquivado_por_usuario_id,arquivado_em,criado_em) VALUES(?,1,?,1,?,?,?,?,?,?,?,NOW(),NOW())')
                ->execute([$this->mid,'Etapa 1 — Encerramento e Arquivamento',$relative,$filename,$hash,$size,json_encode($summary,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),json_encode($snapshot['manifest'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(int)($user['id']??0)?:null]);
            $id=(int)$this->pdo->lastInsertId();
            $this->pdo->commit();
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();@unlink($full);throw$e;}

        Audit::log('ETAPA_1_ARQUIVADA','Etapa 1 encerrada e arquivada no pacote #'.$id.' · SHA-256 '.$hash,$this->mid,['categoria'=>'ADMINISTRACAO','severidade'=>'CRITICO','arquivamento_id'=>$id,'checksum'=>$hash]);
        return ['id'=>$id,'arquivo'=>$full,'checksum'=>$hash,'tamanho'=>$size,'summary'=>$summary];
    }

    public function download(): never
    {
        if(!Auth::isPlatformAdmin())throw new RuntimeException('Apenas a Stratelli pode exportar o pacote de encerramento.');
        $row=$this->one('SELECT * FROM arquivamentos_etapa WHERE municipio_id=? AND etapa_numero=1 LIMIT 1',[$this->mid]);
        if(!$row)throw new RuntimeException('A Etapa 1 ainda não foi arquivada.');
        $file=$this->resolveArchive((string)$row['arquivo_path']);
        if(!$file)throw new RuntimeException('O pacote de encerramento não está disponível no armazenamento.');
        $actual=hash_file('sha256',$file)?:'';
        if(!$actual||!hash_equals(strtolower((string)$row['checksum_sha256']),strtolower($actual)))throw new RuntimeException('Falha de integridade no pacote de encerramento: o SHA-256 armazenado não corresponde ao arquivo atual.');
        Audit::log('ETAPA_1_ARQUIVO_EXPORTADO','Pacote de encerramento da Etapa 1 exportado.',$this->mid,['categoria'=>'ADMINISTRACAO','severidade'=>'INFO','arquivamento_id'=>(int)$row['id']]);
        $name='INPACTA_'.$this->safeName((string)$this->tenant['nome']).'_Etapa_1_Encerramento.zip';
        header('Content-Type: application/zip');
        header('Content-Length: '.filesize($file));
        header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode($name));
        header('X-Content-Type-Options: nosniff');
        readfile($file);exit;
    }

    private function capture(): array
    {
        $tables=[];
        foreach($this->tables as $table){
            if($table==='municipios'){$st=$this->pdo->prepare('SELECT * FROM municipios WHERE id=?');$st->execute([$this->mid]);}
            elseif($table==='parametros_instancia'){$st=$this->pdo->prepare('SELECT * FROM parametros_instancia WHERE municipio_id=?');$st->execute([$this->mid]);}
            elseif($table==='fase_secretarias'){$st=$this->pdo->prepare('SELECT * FROM fase_secretarias WHERE municipio_id=? ORDER BY fase_id,secretaria_id');$st->execute([$this->mid]);}
            elseif($table==='cronograma_processos'){$st=$this->pdo->prepare('SELECT * FROM cronograma_processos WHERE municipio_id=?');$st->execute([$this->mid]);}
            elseif($table==='usuarios'){$st=$this->pdo->prepare('SELECT * FROM usuarios WHERE municipio_id=? ORDER BY id');$st->execute([$this->mid]);}
            elseif($table==='auditoria'){$st=$this->pdo->prepare('SELECT * FROM auditoria WHERE municipio_id=? ORDER BY id');$st->execute([$this->mid]);}
            else{$st=$this->pdo->prepare("SELECT * FROM {$table} WHERE municipio_id=? ORDER BY id");$st->execute([$this->mid]);}
            $tables[$table]=$st->fetchAll(PDO::FETCH_ASSOC);
        }
        // O pacote de encerramento registra responsáveis, mas não deve transportar credenciais.
        foreach($tables['usuarios']??[] as &$u){unset($u['senha_hash']);}
        unset($u);
        $files=$this->collectFiles($tables);
        $responsaveis=$this->responsaveis($tables);
        $aprovacoes=$this->aprovacoes($tables);
        $encerramentos=$this->encerramentos($tables);
        $historico=['documentos'=>$tables['historico_documentos']??[],'fases'=>$tables['historico_fases']??[],'auditoria'=>$tables['auditoria']??[]];
        $summary=[
            'fases'=>count($tables['fases']??[]),'fases_encerradas'=>count(array_filter($tables['cronograma_fases']??[],fn($r)=>($r['status']??'')==='ENCERRADA')),
            'secretarias'=>count($tables['secretarias']??[]),'departamentos'=>count($tables['departamentos']??[]),'usuarios'=>count($tables['usuarios']??[]),
            'requisitos_documentais'=>count($tables['requisitos_documentais']??[]),'modelos'=>count($tables['modelos_documentos']??[]),'documentos_versoes'=>count($tables['documentos_enviados']??[]),
            'aprovacoes'=>count(array_filter($tables['documentos_enviados']??[],fn($r)=>($r['status']??'')==='APROVADO')),'encerramentos'=>count(array_filter($tables['historico_fases']??[],fn($r)=>($r['evento']??'')==='ENCERRAMENTO')),
            'historico_documental'=>count($tables['historico_documentos']??[]),'historico_fases'=>count($tables['historico_fases']??[]),'auditoria'=>count($tables['auditoria']??[]),'arquivos'=>count($files)
        ];
        $data=['format'=>'INPACTA_ETAPA_ARCHIVE','format_version'=>1,'app_version'=>'4.20.14.1','created_at'=>date(DATE_ATOM),'etapa'=>['numero'=>1,'codigo'=>'ETAPA-01','nome'=>'Etapa 1','status'=>'ENCERRADA_E_ARQUIVADA'],'municipality'=>['id'=>$this->mid,'slug'=>$this->tenant['slug']??'','name'=>$this->tenant['nome']??'','uf'=>$this->tenant['uf']??''],'summary'=>$summary,'tables'=>$tables,'responsaveis'=>$responsaveis,'aprovacoes'=>$aprovacoes,'encerramentos'=>$encerramentos,'historico'=>$historico,'files'=>$files];
        $data['manifest']=['format'=>$data['format'],'format_version'=>1,'etapa'=>$data['etapa'],'municipality'=>$data['municipality'],'summary'=>$summary,'file_count'=>count($files),'files'=>$files];
        return $data;
    }


    private function validateClosureIntegrity(array $snapshot): void
    {
        foreach($snapshot['encerramentos'] as $e){
            if(trim((string)($e['snapshot_sha256']??''))==='')throw new RuntimeException('Existe uma fase encerrada sem SHA-256 do snapshot documental. Regularize o encerramento antes de arquivar a Etapa 1.');
        }
        foreach($snapshot['tables']['documentos_enviados']??[] as $d){
            if(trim((string)($d['checksum_sha256']??''))==='')throw new RuntimeException('A versão documental #'.(int)$d['id'].' não possui SHA-256. O arquivamento foi bloqueado para preservar a integridade do pacote.');
        }
        foreach($snapshot['tables']['modelos_documentos']??[] as $m){
            if(trim((string)($m['checksum_sha256']??''))==='')throw new RuntimeException('O modelo documental #'.(int)$m['id'].' não possui SHA-256. O arquivamento foi bloqueado para preservar a integridade do pacote.');
        }
    }

    private function writeZip(string $file,array $snapshot): void
    {
        if(!class_exists(ZipArchive::class))throw new RuntimeException('A extensão PHP ZIP precisa estar habilitada para gerar o pacote de encerramento.');
        $zip=new ZipArchive();
        if($zip->open($file,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Não foi possível criar o pacote de encerramento.');
        $json=json_encode($snapshot['tables'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);if($json===false){$zip->close();throw new RuntimeException('Não foi possível serializar os dados da instância.');}
        $manifest=json_encode($snapshot['manifest'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);if($manifest===false){$zip->close();throw new RuntimeException('Não foi possível gerar o manifesto do encerramento.');}
        foreach([
            'manifest.json'=>$manifest,
            'snapshot.json'=>$json,
            'responsaveis.json'=>json_encode($snapshot['responsaveis'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),
            'aprovacoes.json'=>json_encode($snapshot['aprovacoes'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),
            'encerramentos.json'=>json_encode($snapshot['encerramentos'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),
            'historico.json'=>json_encode($snapshot['historico'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),
            'README.txt'=>$this->readme($snapshot),
        ] as $name=>$content){if($content===false){$zip->close();throw new RuntimeException('Não foi possível gerar '.$name.'.');}$zip->addFromString($name,(string)$content);}
        foreach($snapshot['files'] as $entry){$source=$this->resolveUpload((string)$entry['path']);if(!$source){$zip->close();throw new RuntimeException('Arquivo vinculado não encontrado: '.$entry['path'].'. O arquivamento foi bloqueado para preservar a integridade do pacote.');}$zip->addFile($source,'files/'.ltrim((string)$entry['path'],'/'));}
        if($zip->close()!==true)throw new RuntimeException('Não foi possível finalizar o pacote de encerramento.');
    }

    private function collectFiles(array $tables): array
    {
        $paths=[];$m=$tables['municipios'][0]??[];
        if(!empty($m['brasao_path']))$paths[]=(string)$m['brasao_path'];
        foreach(['modelos_documentos','documentos_enviados','historico_documentos'] as $table){foreach($tables[$table]??[] as $r)if(!empty($r['arquivo_salvo']))$paths[]=(string)$r['arquivo_salvo'];}
        $paths=array_values(array_unique(array_filter($paths)));
        $out=[];
        foreach($paths as $path){$file=$this->resolveUpload($path);if(!$file)throw new RuntimeException('Arquivo vinculado não encontrado: '.$path.'. O arquivamento foi bloqueado.');$out[]=['path'=>$path,'sha256'=>hash_file('sha256',$file)?:'','size'=>(int)filesize($file),'nome'=>basename($path)];}
        return $out;
    }

    private function responsaveis(array $t): array
    {
        $users=[];foreach($t['usuarios']??[] as $u)$users[]=['id'=>(int)$u['id'],'nome'=>$u['nome']??'','email'=>$u['email']??'','grupo'=>$u['grupo']??'','secretaria_id'=>$u['secretaria_id']??null,'departamento_id'=>$u['departamento_id']??null,'ativo'=>(int)($u['ativo']??0)];
        return ['usuarios'=>$users,'fases'=>array_map(fn($f)=>['fase_id'=>(int)$f['id'],'responsavel'=>$f['responsavel']??''], $t['fases']??[])];
    }

    private function aprovacoes(array $t): array
    {
        $out=[];foreach($t['documentos_enviados']??[] as $d){if(($d['status']??'')==='APROVADO')$out[]=['documento_id'=>(int)$d['id'],'requisito_id'=>(int)$d['requisito_id'],'versao'=>(int)$d['versao'],'checksum_sha256'=>$d['checksum_sha256']??'','validado_por_usuario_id'=>$d['validado_por_usuario_id']??null,'validado_em'=>$d['validado_em']??null,'observacao'=>$d['observacao_validacao']??null];}
        return $out;
    }

    private function encerramentos(array $t): array
    {
        $out=[];foreach($t['cronograma_fases']??[] as $c){if(($c['status']??'')==='ENCERRADA')$out[]=['cronograma_fase_id'=>(int)$c['id'],'fase_id'=>(int)$c['fase_id'],'data_conclusao_real'=>$c['data_conclusao_real']??null,'encerrado_em'=>$c['encerrado_em']??null,'concluido_por_usuario_id'=>$c['concluido_por_usuario_id']??null,'observacao'=>$c['observacao']??null,'snapshot_sha256'=>$c['snapshot_sha256']??null];}
        return $out;
    }

    private function readme(array $s): string
    {
        return "INPACTA — PACOTE DE ENCERRAMENTO DA ETAPA 1\n\n".
            "Município: ".($s['municipality']['name']??'')." / ".($s['municipality']['uf']??'')."\n".
            "Etapa: 1 — Encerrada e arquivada\n".
            "Gerado em: ".($s['created_at']??'')."\n".
            "SHA-256 de cada arquivo vinculado: conforme manifest.json.\n\n".
            "Conteúdo: fases, documentos e todas as versões, hashes, responsáveis, aprovações, encerramentos, histórico documental, histórico de fases, auditoria e arquivos vinculados.\n\n".
            "Este pacote é um registro de encerramento e não deve ser alterado após sua emissão.\n";
    }

    private function one(string $sql,array $params=[]): ?array{$st=$this->pdo->prepare($sql);$st->execute($params);$r=$st->fetch(PDO::FETCH_ASSOC);return$r?:null;}
    private function all(string $sql,array $params=[]): array{$st=$this->pdo->prepare($sql);$st->execute($params);return$st->fetchAll(PDO::FETCH_ASSOC);}
    private function archiveDir(): string{$dir=dirname(__DIR__,2).'/storage/archives/municipio_'.$this->mid;if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir))throw new RuntimeException('Não foi possível criar a pasta segura dos pacotes de encerramento.');return$dir;}
    private function resolveArchive(string $relative): ?string{$root=realpath(dirname(__DIR__,2).'/storage/archives');$candidate=realpath(dirname(__DIR__,2).'/storage/archives/'.ltrim($relative,'/'));if(!$root||!$candidate||strpos($candidate,$root.DIRECTORY_SEPARATOR)!==0||!is_file($candidate))return null;return$candidate;}
    private function resolveUpload(string $relative): ?string{$root=realpath(dirname(__DIR__,2).'/storage/uploads');$candidate=realpath(dirname(__DIR__,2).'/storage/uploads/'.ltrim(str_replace('\\','/',$relative),'/'));if(!$root||!$candidate||strpos($candidate,$root.DIRECTORY_SEPARATOR)!==0||!is_file($candidate))return null;return$candidate;}
    private function safeName(string $s): string{return trim(preg_replace('/[^A-Za-z0-9_-]+/','_',iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s)?:$s),'_')?:'Municipio';}
}
