<?php
namespace App\Services;

use PDO;
use RuntimeException;

/**
 * Bootstrap idempotente da estrutura necessária ao arquivamento da Etapa 1.
 *
 * A aplicação roda em XAMPP/Apache e pode não ter PHP-CLI funcional. Por isso,
 * a própria aplicação garante a existência da tabela e do diretório privado
 * quando uma funcionalidade da Etapa 1 for acessada.
 */
final class EtapaArchiveSchema
{
    private static bool $ready=false;

    public static function ensure(PDO $pdo): void
    {
        if(self::$ready)return;

        try{
            $pdo->exec("CREATE TABLE IF NOT EXISTS arquivamentos_etapa (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                municipio_id BIGINT UNSIGNED NOT NULL,
                etapa_numero TINYINT UNSIGNED NOT NULL DEFAULT 1,
                nome VARCHAR(180) NOT NULL,
                versao_pacote INT UNSIGNED NOT NULL DEFAULT 1,
                arquivo_path VARCHAR(500) NOT NULL,
                arquivo_nome VARCHAR(255) NOT NULL,
                checksum_sha256 CHAR(64) NOT NULL,
                tamanho BIGINT UNSIGNED NOT NULL DEFAULT 0,
                resumo_json LONGTEXT NULL,
                manifesto_json LONGTEXT NULL,
                arquivado_por_usuario_id BIGINT UNSIGNED NULL,
                arquivado_em DATETIME NOT NULL,
                criado_em DATETIME NOT NULL,
                UNIQUE KEY uq_arquivamento_etapa(municipio_id,etapa_numero),
                INDEX idx_arquivamento_municipio(municipio_id,etapa_numero,arquivado_em),
                CONSTRAINT fk_arquivamento_municipio FOREIGN KEY(municipio_id) REFERENCES municipios(id) ON DELETE RESTRICT,
                CONSTRAINT fk_arquivamento_usuario FOREIGN KEY(arquivado_por_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $root=dirname(__DIR__,2);
            $dir=$root.'/storage/archives';
            if(!is_dir($dir) && !mkdir($dir,0770,true) && !is_dir($dir)){
                throw new RuntimeException('Não foi possível criar o armazenamento privado dos pacotes de encerramento.');
            }
            self::$ready=true;
        }catch(\Throwable $e){
            throw new RuntimeException('Não foi possível preparar o arquivamento da Etapa 1 automaticamente: '.$e->getMessage(),0,$e);
        }
    }
}
