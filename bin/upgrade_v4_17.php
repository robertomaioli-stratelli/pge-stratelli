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

function v417ColumnExists(PDO $pdo,string $table,string $column): bool {
    $st=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $st->execute([$table,$column]);return (int)$st->fetchColumn()>0;
}
function v417IndexExists(PDO $pdo,string $table,string $index): bool {
    $st=$pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
    $st->execute([$table,$index]);return (int)$st->fetchColumn()>0;
}
function v417FkRule(PDO $pdo,string $table,string $constraint): ?string {
    $st=$pdo->prepare('SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=?');
    $st->execute([$table,$constraint]);$v=$st->fetchColumn();return $v===false?null:(string)$v;
}
function v417ConstraintExists(PDO $pdo,string $table,string $constraint): bool {
    $st=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=?');
    $st->execute([$table,$constraint]);return (int)$st->fetchColumn()>0;
}
function v417AddColumn(PDO $pdo,string $table,string $column,string $definition): void {
    if(!v417ColumnExists($pdo,$table,$column))$pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
}

v417AddColumn($pdo,'cronograma_fases','status',"ENUM('ENCERRADA','REABERTA') NOT NULL DEFAULT 'ENCERRADA' AFTER observacao");
v417AddColumn($pdo,'cronograma_fases','snapshot_documental','LONGTEXT NULL AFTER status');
v417AddColumn($pdo,'cronograma_fases','snapshot_sha256','CHAR(64) NULL AFTER snapshot_documental');
v417AddColumn($pdo,'cronograma_fases','encerrado_em','DATETIME NULL AFTER snapshot_sha256');
v417AddColumn($pdo,'cronograma_fases','reaberto_por_usuario_id','BIGINT UNSIGNED NULL AFTER encerrado_em');
v417AddColumn($pdo,'cronograma_fases','reaberto_em','DATETIME NULL AFTER reaberto_por_usuario_id');
v417AddColumn($pdo,'cronograma_fases','motivo_reabertura','TEXT NULL AFTER reaberto_em');

$pdo->exec("UPDATE cronograma_fases SET status='ENCERRADA' WHERE status IS NULL OR status=''");
$pdo->exec('UPDATE cronograma_fases SET encerrado_em=COALESCE(encerrado_em,atualizado_em,criado_em) WHERE status="ENCERRADA"');

if(!v417IndexExists($pdo,'cronograma_fases','idx_crono_fase_status'))$pdo->exec('ALTER TABLE cronograma_fases ADD INDEX idx_crono_fase_status(municipio_id,status,fase_id)');
if(!v417ConstraintExists($pdo,'cronograma_fases','fk_crono_reaberto_usuario'))$pdo->exec('ALTER TABLE cronograma_fases ADD CONSTRAINT fk_crono_reaberto_usuario FOREIGN KEY(reaberto_por_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL');

// Uma fase que possui histórico formal não pode desaparecer em cascata.
if(($rule=v417FkRule($pdo,'cronograma_fases','fk_crono_fase'))!==null && strtoupper($rule)!=='RESTRICT'){
    $pdo->exec('ALTER TABLE cronograma_fases DROP FOREIGN KEY fk_crono_fase');
}
if(v417FkRule($pdo,'cronograma_fases','fk_crono_fase')===null){
    $pdo->exec('ALTER TABLE cronograma_fases ADD CONSTRAINT fk_crono_fase FOREIGN KEY(fase_id,municipio_id) REFERENCES fases(id,municipio_id) ON DELETE RESTRICT');
}

$pdo->exec("CREATE TABLE IF NOT EXISTS historico_fases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    municipio_id BIGINT UNSIGNED NOT NULL,
    fase_id BIGINT UNSIGNED NOT NULL,
    cronograma_fase_id BIGINT UNSIGNED NULL,
    evento ENUM('ENCERRAMENTO','REABERTURA') NOT NULL,
    usuario_id BIGINT UNSIGNED NULL,
    data_referencia DATE NULL,
    observacao TEXT NULL,
    snapshot_documental LONGTEXT NULL,
    snapshot_sha256 CHAR(64) NULL,
    criado_em DATETIME NOT NULL,
    INDEX idx_hist_fase_tenant(municipio_id,fase_id,criado_em),
    CONSTRAINT fk_hist_fase_municipio FOREIGN KEY(municipio_id) REFERENCES municipios(id) ON DELETE RESTRICT,
    CONSTRAINT fk_hist_fase_fase FOREIGN KEY(fase_id,municipio_id) REFERENCES fases(id,municipio_id) ON DELETE RESTRICT,
    CONSTRAINT fk_hist_fase_crono FOREIGN KEY(cronograma_fase_id) REFERENCES cronograma_fases(id) ON DELETE RESTRICT,
    CONSTRAINT fk_hist_fase_usuario FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Conclusões antigas são preservadas como eventos legados. Não inventamos um snapshot retroativo.
$legacy=$pdo->query("SELECT c.* FROM cronograma_fases c
    WHERE c.status='ENCERRADA' AND NOT EXISTS(
        SELECT 1 FROM historico_fases h WHERE h.cronograma_fase_id=c.id AND h.evento='ENCERRAMENTO'
    ) ORDER BY c.id");
$ins=$pdo->prepare('INSERT INTO historico_fases(municipio_id,fase_id,cronograma_fase_id,evento,usuario_id,data_referencia,observacao,snapshot_documental,snapshot_sha256,criado_em) VALUES(?,?,?,"ENCERRAMENTO",?,?,?,?,?,?)');
while($c=$legacy->fetch(PDO::FETCH_ASSOC)){
    $obs=trim((string)($c['observacao']??''));
    $note='Registro de conclusão existente antes da v4.17; snapshot documental histórico não disponível.';
    $obs=$obs!==''?$obs."\n\n".$note:$note;
    $ins->execute([(int)$c['municipio_id'],(int)$c['fase_id'],(int)$c['id'],$c['concluido_por_usuario_id']?:null,$c['data_conclusao_real'],$obs,null,null,$c['encerrado_em']?:$c['atualizado_em']]);
}

$pdo->exec('DROP TRIGGER IF EXISTS trg_historico_fases_no_delete');
$pdo->exec("CREATE TRIGGER trg_historico_fases_no_delete BEFORE DELETE ON historico_fases FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Histórico de encerramento de fase: exclusão física não permitida.'");
$pdo->exec('DROP TRIGGER IF EXISTS trg_historico_fases_no_update');
$pdo->exec("CREATE TRIGGER trg_historico_fases_no_update BEFORE UPDATE ON historico_fases FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Histórico de encerramento de fase: alteração de evento auditado não permitida.'");

echo "Upgrade v4.17 concluído com sucesso.\n";
echo "Encerramento formal, snapshot documental e reabertura auditada ativados.\n";
echo "Conclusões antigas foram preservadas como registros legados, sem criação artificial de snapshot histórico.\n";
