<?php
require dirname(__DIR__).'/app/Core/Env.php';
require dirname(__DIR__).'/app/Core/Database.php';

use App\Core\Env;
use App\Core\Database;

Env::load(dirname(__DIR__).'/.env');
$pdo=Database::connection();

$pdo->exec("ALTER TABLE municipios MODIFY status ENUM('NEGOCIACAO','APRESENTACAO','IMPLANTACAO','ATIVO','SUSPENSO','DESATIVADO') NOT NULL DEFAULT 'IMPLANTACAO'");
$pdo->exec("UPDATE municipios SET ativo=0 WHERE status='DESATIVADO'");
$pdo->exec("UPDATE municipios SET ativo=1 WHERE status<>'DESATIVADO'");

echo "Upgrade v4.13 concluído com sucesso.\n";
echo "Status disponíveis: NEGOCIACAO, APRESENTACAO, IMPLANTACAO, ATIVO, SUSPENSO e DESATIVADO.\n";
