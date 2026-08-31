<?php
declare(strict_types=1);

use App\Core\Database;
use App\Core\Env;

$root=dirname(__DIR__);
require $root.'/app/Core/Env.php';
require $root.'/app/Core/Database.php';
Env::load($root.'/.env');
$pdo=Database::connection();
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

function columnExists(PDO $pdo,string $table,string $column): bool {
    $st=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $st->execute([$table,$column]);return (int)$st->fetchColumn()>0;
}
function indexExists(PDO $pdo,string $table,string $index): bool {
    $st=$pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
    $st->execute([$table,$index]);return (int)$st->fetchColumn()>0;
}
function fkRule(PDO $pdo,string $table,string $constraint): ?string {
    $st=$pdo->prepare('SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=?');
    $st->execute([$table,$constraint]);$v=$st->fetchColumn();return $v===false?null:(string)$v;
}
function addColumn(PDO $pdo,string $table,string $column,string $definition): void {
    if(!columnExists($pdo,$table,$column))$pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
}
function fileMeta(string $root,string $relative): array {
    $base=realpath($root);$file=realpath($root.'/'.ltrim($relative,'/'));
    if(!$base||!$file||strpos($file,$base.DIRECTORY_SEPARATOR)!==0||!is_file($file))return ['mime'=>'','hash'=>'','size'=>0];
    $mime='application/octet-stream';
    if(class_exists('finfo')){$f=new finfo(FILEINFO_MIME_TYPE);$m=$f->file($file);if(is_string($m)&&$m!=='')$mime=substr($m,0,160);}
    elseif(function_exists('mime_content_type'))$mime=mime_content_type($file)?:$mime;
    return ['mime'=>$mime,'hash'=>hash_file('sha256',$file)?:'','size'=>(int)filesize($file)];
}

// Documentos enviados
addColumn($pdo,'documentos_enviados','documento_anterior_id','BIGINT UNSIGNED NULL AFTER requisito_id');
addColumn($pdo,'documentos_enviados','mime_type','VARCHAR(160) NULL AFTER tamanho');
addColumn($pdo,'documentos_enviados','checksum_sha256','CHAR(64) NULL AFTER mime_type');
addColumn($pdo,'documentos_enviados','observacao_envio','TEXT NULL AFTER checksum_sha256');

// Modelos
addColumn($pdo,'modelos_documentos','modelo_anterior_id','BIGINT UNSIGNED NULL AFTER requisito_id');
addColumn($pdo,'modelos_documentos','versao','INT NOT NULL DEFAULT 1 AFTER modelo_anterior_id');
addColumn($pdo,'modelos_documentos','mime_type','VARCHAR(160) NULL AFTER tamanho');
addColumn($pdo,'modelos_documentos','checksum_sha256','CHAR(64) NULL AFTER mime_type');

// Histórico documental
addColumn($pdo,'historico_documentos','documento_id','BIGINT UNSIGNED NULL AFTER requisito_id');
addColumn($pdo,'historico_documentos','mime_type',"VARCHAR(160) NOT NULL DEFAULT '' AFTER tamanho");
addColumn($pdo,'historico_documentos','checksum_sha256',"CHAR(64) NOT NULL DEFAULT '' AFTER mime_type");
addColumn($pdo,'historico_documentos','versao','INT NULL AFTER checksum_sha256');

$storage=$root.'/storage/uploads';

// Backfill de integridade e encadeamento dos documentos existentes.
$st=$pdo->query('SELECT id,municipio_id,requisito_id,versao,arquivo_salvo,tamanho,mime_type,checksum_sha256 FROM documentos_enviados ORDER BY municipio_id,requisito_id,versao,id');
$prev=[];$docVersion=[];
$up=$pdo->prepare('UPDATE documentos_enviados SET documento_anterior_id=?,versao=?,tamanho=?,mime_type=?,checksum_sha256=? WHERE id=?');
while($d=$st->fetch(PDO::FETCH_ASSOC)){
    $key=$d['municipio_id'].':'.$d['requisito_id'];$docVersion[$key]=($docVersion[$key]??0)+1;$meta=fileMeta($storage,(string)$d['arquivo_salvo']);
    $size=$meta['size']?:((int)$d['tamanho']);$mime=$meta['mime']?:((string)($d['mime_type']??''));$hash=$meta['hash']?:((string)($d['checksum_sha256']??''));
    $up->execute([$prev[$key]??null,$docVersion[$key],$size,$mime?:null,$hash?:null,$d['id']]);$prev[$key]=(int)$d['id'];
}

// Backfill das versões dos modelos.
$st=$pdo->query('SELECT id,municipio_id,requisito_id,arquivo_salvo,tamanho,mime_type,checksum_sha256 FROM modelos_documentos ORDER BY municipio_id,requisito_id,criado_em,id');
$prev=[];$version=[];
$up=$pdo->prepare('UPDATE modelos_documentos SET modelo_anterior_id=?,versao=?,tamanho=?,mime_type=?,checksum_sha256=? WHERE id=?');
while($m=$st->fetch(PDO::FETCH_ASSOC)){
    $key=$m['municipio_id'].':'.$m['requisito_id'];$version[$key]=($version[$key]??0)+1;$meta=fileMeta($storage,(string)$m['arquivo_salvo']);
    $up->execute([$prev[$key]??null,$version[$key],$meta['size']?:((int)$m['tamanho']),$meta['mime']?:($m['mime_type']?:null),$meta['hash']?:($m['checksum_sha256']?:null),$m['id']]);$prev[$key]=(int)$m['id'];
}

// Enriquece o histórico antigo com metadados da versão correspondente.
$hist=$pdo->query("SELECT id,arquivo_salvo,tipo_arquivo FROM historico_documentos WHERE arquivo_salvo<>'' ORDER BY id");
$updHist=$pdo->prepare('UPDATE historico_documentos SET documento_id=?,mime_type=?,checksum_sha256=?,versao=? WHERE id=?');
$findDoc=$pdo->prepare('SELECT id,mime_type,checksum_sha256,versao FROM documentos_enviados WHERE arquivo_salvo=? ORDER BY id DESC LIMIT 1');
$findModel=$pdo->prepare('SELECT mime_type,checksum_sha256,versao FROM modelos_documentos WHERE arquivo_salvo=? ORDER BY id DESC LIMIT 1');
while($h=$hist->fetch(PDO::FETCH_ASSOC)){
    $docId=null;$mime='';$hash='';$versao=null;
    $findDoc->execute([$h['arquivo_salvo']]);$d=$findDoc->fetch(PDO::FETCH_ASSOC);
    if($d){$docId=(int)$d['id'];$mime=(string)$d['mime_type'];$hash=(string)$d['checksum_sha256'];$versao=(int)$d['versao'];}
    else{$findModel->execute([$h['arquivo_salvo']]);$m=$findModel->fetch(PDO::FETCH_ASSOC);if($m){$mime=(string)$m['mime_type'];$hash=(string)$m['checksum_sha256'];$versao=(int)$m['versao'];}}
    $updHist->execute([$docId,$mime,$hash,$versao,$h['id']]);
}

if(!indexExists($pdo,'documentos_enviados','uq_documento_versao'))$pdo->exec('ALTER TABLE documentos_enviados ADD UNIQUE KEY uq_documento_versao(municipio_id,requisito_id,versao)');
if(!indexExists($pdo,'documentos_enviados','idx_documento_checksum'))$pdo->exec('ALTER TABLE documentos_enviados ADD INDEX idx_documento_checksum(municipio_id,checksum_sha256)');
if(!indexExists($pdo,'modelos_documentos','uq_modelo_versao'))$pdo->exec('ALTER TABLE modelos_documentos ADD UNIQUE KEY uq_modelo_versao(municipio_id,requisito_id,versao)');
if(!indexExists($pdo,'modelos_documentos','idx_modelo_checksum'))$pdo->exec('ALTER TABLE modelos_documentos ADD INDEX idx_modelo_checksum(municipio_id,checksum_sha256)');
if(!indexExists($pdo,'historico_documentos','idx_historico_documento'))$pdo->exec('ALTER TABLE historico_documentos ADD INDEX idx_historico_documento(documento_id)');

// Preservação: requisitos com documentos/modelos auditados deixam de aceitar deleção em cascata.
if(($rule=fkRule($pdo,'documentos_enviados','fk_doc_req'))!==null && strtoupper($rule)!=='RESTRICT'){$pdo->exec('ALTER TABLE documentos_enviados DROP FOREIGN KEY fk_doc_req');}
if(fkRule($pdo,'documentos_enviados','fk_doc_req')===null)$pdo->exec('ALTER TABLE documentos_enviados ADD CONSTRAINT fk_doc_req FOREIGN KEY(requisito_id,municipio_id) REFERENCES requisitos_documentais(id,municipio_id) ON DELETE RESTRICT');
if(fkRule($pdo,'documentos_enviados','fk_doc_anterior')===null)$pdo->exec('ALTER TABLE documentos_enviados ADD CONSTRAINT fk_doc_anterior FOREIGN KEY(documento_anterior_id) REFERENCES documentos_enviados(id) ON DELETE RESTRICT');

if(($rule=fkRule($pdo,'modelos_documentos','fk_modelo_req'))!==null && strtoupper($rule)!=='RESTRICT'){$pdo->exec('ALTER TABLE modelos_documentos DROP FOREIGN KEY fk_modelo_req');}
if(fkRule($pdo,'modelos_documentos','fk_modelo_req')===null)$pdo->exec('ALTER TABLE modelos_documentos ADD CONSTRAINT fk_modelo_req FOREIGN KEY(requisito_id,municipio_id) REFERENCES requisitos_documentais(id,municipio_id) ON DELETE RESTRICT');
if(fkRule($pdo,'modelos_documentos','fk_modelo_anterior')===null)$pdo->exec('ALTER TABLE modelos_documentos ADD CONSTRAINT fk_modelo_anterior FOREIGN KEY(modelo_anterior_id) REFERENCES modelos_documentos(id) ON DELETE RESTRICT');
if(fkRule($pdo,'historico_documentos','fk_hist_documento')===null)$pdo->exec('ALTER TABLE historico_documentos ADD CONSTRAINT fk_hist_documento FOREIGN KEY(documento_id) REFERENCES documentos_enviados(id) ON DELETE RESTRICT');

// Proteção adicional contra exclusão física silenciosa.
$pdo->exec('DROP TRIGGER IF EXISTS trg_documentos_enviados_no_delete');
$pdo->exec("CREATE TRIGGER trg_documentos_enviados_no_delete BEFORE DELETE ON documentos_enviados FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Documento auditado: exclusão física não permitida.'");
$pdo->exec('DROP TRIGGER IF EXISTS trg_historico_documentos_no_delete');
$pdo->exec("CREATE TRIGGER trg_historico_documentos_no_delete BEFORE DELETE ON historico_documentos FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Histórico documental auditado: exclusão física não permitida.'");

echo "Upgrade v4.16 concluído com sucesso.\n";
echo "Checksums SHA-256 e MIME dos arquivos existentes foram recalculados quando o arquivo físico estava disponível.\n";
echo "Histórico de versões encadeado e proteção contra exclusão física ativados.\n";
