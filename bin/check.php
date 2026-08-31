<?php
declare(strict_types=1);
use App\Core\Env;use App\Core\Database;
$root=dirname(__DIR__);require$root.'/app/Core/Env.php';Env::load($root.'/.env');spl_autoload_register(function($c)use($root){$p='App\\';if(str_starts_with($c,$p)){ $f=$root.'/app/'.str_replace('\\','/',substr($c,strlen($p))).'.php';if(is_file($f))require$f;}});
$pdo=Database::connection();$errors=[];
$checks=[
    'Administrador Stratelli'=>"SELECT COUNT(*) FROM usuarios WHERE administrador_plataforma=1 AND grupo='ADMINISTRADOR' AND ativo=1",
    'Usuários municipais sem município'=>"SELECT COUNT(*) FROM usuarios WHERE administrador_plataforma=0 AND municipio_id IS NULL",
    'Usuários comuns sem secretaria'=>"SELECT COUNT(*) FROM usuarios WHERE grupo='USUARIO' AND administrador_plataforma=0 AND secretaria_id IS NULL",
    'Usuários com departamento incompatível'=>"SELECT COUNT(*) FROM usuarios u LEFT JOIN departamentos d ON d.id=u.departamento_id AND d.municipio_id=u.municipio_id AND d.secretaria_id=u.secretaria_id WHERE u.departamento_id IS NOT NULL AND d.id IS NULL",
];
foreach($checks as $label=>$sql){$n=(int)$pdo->query($sql)->fetchColumn();if($label==='Administrador Stratelli'){echo"[".($n>0?'OK':'ERRO')."] {$label}: {$n}\n";if($n<1)$errors[]=$label;}else{echo"[".($n===0?'OK':'ERRO')."] {$label}: {$n}\n";if($n>0)$errors[]=$label;}}
foreach($pdo->query("SELECT m.id,m.nome,(SELECT COUNT(*) FROM usuarios u WHERE u.municipio_id=m.id AND u.grupo='GESTOR' AND u.ativo=1) gestores FROM municipios m WHERE m.ativo=1") as $m){$ok=(int)$m['gestores']>=1;echo'['.($ok?'OK':'ERRO')."] {$m['nome']} - gestores ativos: {$m['gestores']}\n";if(!$ok)$errors[]='Gestor '.$m['nome'];}

$requiredTables=['camadas_territoriais','objetos_territoriais','vinculos_territoriais','notificacoes','historico_fases','tentativas_login','password_reset_tokens','parametros_instancia','importacoes_estrutura','pontos_restauracao_instancia','arquivamentos_etapa'];
$db=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
foreach($requiredTables as $table){$st=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');$st->execute([$db,$table]);$ok=(int)$st->fetchColumn()===1;echo'['.($ok?'OK':'ERRO')."] Estrutura {$table}: ".($ok?'disponível':'ausente')."\n";if(!$ok)$errors[]='Tabela '.$table;}

$requiredColumns=[
    'documentos_enviados'=>['documento_anterior_id','mime_type','checksum_sha256','observacao_envio'],
    'modelos_documentos'=>['modelo_anterior_id','versao','mime_type','checksum_sha256'],
    'historico_documentos'=>['documento_id','mime_type','checksum_sha256','versao','ip','user_agent'],
    'usuarios'=>['auth_version','senha_alterada_em'],
    'auditoria'=>['usuario_nome','usuario_email','categoria','severidade','sucesso','metodo','rota','session_hash','contexto_json'],
    'cronograma_fases'=>['status','snapshot_documental','snapshot_sha256','encerrado_em','reaberto_por_usuario_id','reaberto_em','motivo_reabertura'],
    'parametros_instancia'=>['estilo_decoracao_cabecalho'],
];
foreach($requiredColumns as $table=>$cols){foreach($cols as $col){$st=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?');$st->execute([$db,$table,$col]);$ok=(int)$st->fetchColumn()===1;echo'['.($ok?'OK':'ERRO')."] {$table}.{$col}: ".($ok?'disponível':'ausente')."\n";if(!$ok)$errors[]=$table.'.'.$col;}}
foreach([['documentos_enviados','uq_documento_versao'],['modelos_documentos','uq_modelo_versao']] as [$table,$idx]){$st=$pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=?');$st->execute([$db,$table,$idx]);$ok=(int)$st->fetchColumn()>0;echo'['.($ok?'OK':'ERRO')."] Índice {$idx}: ".($ok?'ativo':'ausente')."\n";if(!$ok)$errors[]='Índice '.$idx;}
foreach(['trg_documentos_enviados_no_delete','trg_historico_documentos_no_delete','trg_historico_fases_no_delete','trg_historico_fases_no_update','trg_auditoria_no_delete','trg_auditoria_no_update'] as $trigger){$st=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=? AND TRIGGER_NAME=?');$st->execute([$db,$trigger]);$ok=(int)$st->fetchColumn()===1;echo'['.($ok?'OK':'ERRO')."] Proteção {$trigger}: ".($ok?'ativa':'ausente')."\n";if(!$ok)$errors[]='Trigger '.$trigger;}
try{$n=(int)$pdo->query("SELECT COUNT(*) FROM documentos_enviados WHERE COALESCE(checksum_sha256,'')='' OR COALESCE(mime_type,'')=''")->fetchColumn();echo'['.($n===0?'OK':'AVISO')."] Documentos sem metadados completos de integridade: {$n}\n";}catch(Throwable $e){}


try{$n=(int)$pdo->query('SELECT COUNT(*) FROM cronograma_fases c LEFT JOIN historico_fases h ON h.cronograma_fase_id=c.id AND h.evento="ENCERRAMENTO" WHERE c.status="ENCERRADA" AND h.id IS NULL')->fetchColumn();echo'['.($n===0?'OK':'AVISO')."] Fases encerradas sem evento histórico formal: {$n}\n";}catch(Throwable $e){}
try{$n=(int)$pdo->query('SELECT COUNT(*) FROM cronograma_fases WHERE status="ENCERRADA" AND COALESCE(snapshot_sha256,"")=""')->fetchColumn();echo'['.($n===0?'OK':'AVISO')."] Encerramentos legados sem snapshot SHA-256: {$n}\n";}catch(Throwable $e){}

if(!in_array('objetos_territoriais',$errors,true)){
    try{$n=(int)$pdo->query('SELECT COUNT(*) FROM objetos_territoriais o LEFT JOIN camadas_territoriais c ON c.id=o.camada_id AND c.municipio_id=o.municipio_id WHERE c.id IS NULL')->fetchColumn();echo'['.($n===0?'OK':'ERRO')."] Objetos territoriais sem camada válida: {$n}\n";if($n)$errors[]='Objetos territoriais órfãos';}catch(Throwable $e){}
}
exit($errors?1:0);
