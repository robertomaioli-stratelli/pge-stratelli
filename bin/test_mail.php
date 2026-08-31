<?php
declare(strict_types=1);
use App\Core\Env;use App\Services\MailService;
$root=dirname(__DIR__);require$root.'/app/Core/Env.php';Env::load($root.'/.env');spl_autoload_register(function(string $class)use($root){$p='App\\';if(!str_starts_with($class,$p))return;$file=$root.'/app/'.str_replace('\\','/',substr($class,strlen($p))).'.php';if(is_file($file))require$file;});
$to=trim((string)($argv[1]??''));if(!filter_var($to,FILTER_VALIDATE_EMAIL)){fwrite(STDERR,"Uso: php bin/test_mail.php destinatario@dominio.com\n");exit(2);}try{(new MailService())->send($to,'Teste SMTP | INPACTA By Stratelli','<h2>SMTP configurado com sucesso</h2><p>Este é um teste de envio do <strong>INPACTA By Stratelli</strong>.</p>','SMTP configurado com sucesso. Este é um teste do INPACTA By Stratelli.');echo"[OK] E-mail de teste enviado para {$to}.\n";exit(0);}catch(Throwable$e){fwrite(STDERR,"[ERRO] ".$e->getMessage()."\n");exit(1);}
