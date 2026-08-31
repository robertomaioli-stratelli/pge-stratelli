<?php
declare(strict_types=1);

use App\Core\Database;
use App\Core\Env;

$root=dirname(__DIR__);
require $root.'/app/Core/Env.php';
require $root.'/app/Core/Database.php';
Env::load($root.'/.env');
$pdo=Database::connection();

$pdo->exec("CREATE TABLE IF NOT EXISTS notificacoes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id BIGINT UNSIGNED NOT NULL,
    municipio_id BIGINT UNSIGNED NULL,
    tipo VARCHAR(60) NOT NULL,
    icone VARCHAR(30) NOT NULL DEFAULT '•',
    titulo VARCHAR(180) NOT NULL,
    mensagem TEXT NOT NULL,
    link VARCHAR(500) NOT NULL DEFAULT '',
    prioridade INT NOT NULL DEFAULT 100,
    chave_evento VARCHAR(190) NULL,
    lida_em DATETIME NULL,
    criado_em DATETIME NOT NULL,
    UNIQUE KEY uq_notificacao_usuario_evento(usuario_id,chave_evento),
    INDEX idx_notificacao_usuario_lida(usuario_id,lida_em,criado_em),
    INDEX idx_notificacao_municipio(municipio_id,criado_em),
    INDEX idx_notificacao_tipo(usuario_id,tipo,criado_em),
    CONSTRAINT fk_notificacao_usuario FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_notificacao_municipio FOREIGN KEY(municipio_id) REFERENCES municipios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

echo "Upgrade v4.15 concluído com sucesso.\n";
echo "Central de notificações persistentes criada.\n";
