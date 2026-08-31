<?php
declare(strict_types=1);
use App\Core\Database;use App\Core\Env;
$root=dirname(__DIR__);require $root.'/app/Core/Env.php';require $root.'/app/Core/Database.php';Env::load($root.'/.env');
$pdo=Database::connection();
function col(PDO $pdo,string $table,string $column): bool{$st=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$st->execute([$table,$column]);return (int)$st->fetchColumn()>0;}
function add(PDO $pdo,string $table,string $column,string $definition): void{if(!col($pdo,$table,$column))$pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");}
function idx(PDO $pdo,string $table,string $index): bool{$st=$pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');$st->execute([$table,$index]);return (int)$st->fetchColumn()>0;}
try{
 add($pdo,'usuarios','auth_version','INT NOT NULL DEFAULT 1 AFTER ativo');
 add($pdo,'usuarios','senha_alterada_em','DATETIME NULL AFTER ultimo_login_em');
 foreach([
 'usuario_nome'=>'VARCHAR(180) NOT NULL DEFAULT \'\' AFTER usuario_id','usuario_email'=>'VARCHAR(190) NOT NULL DEFAULT \'\' AFTER usuario_nome','categoria'=>'VARCHAR(40) NOT NULL DEFAULT \'OPERACIONAL\' AFTER evento','severidade'=>'VARCHAR(20) NOT NULL DEFAULT \'INFO\' AFTER categoria','sucesso'=>'TINYINT(1) NOT NULL DEFAULT 1 AFTER severidade','metodo'=>'VARCHAR(10) NOT NULL DEFAULT \'\' AFTER user_agent','rota'=>'VARCHAR(500) NOT NULL DEFAULT \'\' AFTER metodo','referer'=>'VARCHAR(500) NOT NULL DEFAULT \'\' AFTER rota','session_hash'=>'CHAR(64) NOT NULL DEFAULT \'\' AFTER referer','contexto_json'=>'LONGTEXT NULL AFTER session_hash'] as$c=>$d)add($pdo,'auditoria',$c,$d);
 if(!idx($pdo,'auditoria','idx_auditoria_categoria_data'))$pdo->exec('ALTER TABLE auditoria ADD INDEX idx_auditoria_categoria_data(categoria,severidade,criado_em)');
 if(!idx($pdo,'auditoria','idx_auditoria_usuario_data'))$pdo->exec('ALTER TABLE auditoria ADD INDEX idx_auditoria_usuario_data(usuario_id,criado_em)');
 add($pdo,'historico_documentos','ip','VARCHAR(64) NOT NULL DEFAULT \'\' AFTER status');add($pdo,'historico_documentos','user_agent','VARCHAR(500) NOT NULL DEFAULT \'\' AFTER ip');
 $pdo->exec("CREATE TABLE IF NOT EXISTS tentativas_login (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,usuario_id BIGINT UNSIGNED NULL,email_normalizado VARCHAR(190) NOT NULL DEFAULT '',ip VARCHAR(64) NOT NULL DEFAULT '',user_agent VARCHAR(500) NOT NULL DEFAULT '',sucesso TINYINT(1) NOT NULL DEFAULT 0,motivo VARCHAR(80) NOT NULL DEFAULT '',criado_em DATETIME NOT NULL,INDEX idx_login_email_data(email_normalizado,criado_em),INDEX idx_login_ip_data(ip,criado_em),CONSTRAINT fk_tentativa_usuario FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,usuario_id BIGINT UNSIGNED NOT NULL,token_hash CHAR(64) NOT NULL UNIQUE,expira_em DATETIME NOT NULL,usado_em DATETIME NULL,requested_ip VARCHAR(64) NOT NULL DEFAULT '',requested_user_agent VARCHAR(500) NOT NULL DEFAULT '',criado_em DATETIME NOT NULL,INDEX idx_reset_user(usuario_id,usado_em,expira_em),CONSTRAINT fk_reset_usuario FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 foreach(['trg_auditoria_no_delete','trg_auditoria_no_update'] as$t)$pdo->exec("DROP TRIGGER IF EXISTS $t");
 $pdo->exec("CREATE TRIGGER trg_auditoria_no_delete BEFORE DELETE ON auditoria FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Auditoria: exclusão física não permitida.'");
 $pdo->exec("CREATE TRIGGER trg_auditoria_no_update BEFORE UPDATE ON auditoria FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Auditoria: alteração de evento auditado não permitida.'");
 echo "INPACTA v4.18 - upgrade concluído.\n";
}catch(Throwable $e){fwrite(STDERR,"Erro: ".$e->getMessage()."\n");exit(1);}
